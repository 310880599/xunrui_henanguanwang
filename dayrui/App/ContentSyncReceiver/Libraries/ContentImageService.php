<?php namespace Phpcmf\Library\Contentsyncreceiver;

class ContentImageService
{
    protected $maxProcessCount = 30;
    protected $sourcePrefix = 'https://www.zzyugong.cn/';
    protected $localCopyHost = 'www.zzyugong.cn';
    protected $localCopySourceRoot = '/www/wwwroot/zhengzhoudaguanwang';
    protected $localCopyTargetRoot = '/www/wwwroot/xunruidaguanwang';
    protected $localDomains = [
        'www.hnyugong.com',
        'hnyugong.com',
    ];

    /**
     * 正文图片本地化处理
     */
    public function process($content, $member, &$mediaStats = null) {
        $startTime = microtime(true);
        $this->init_media_stats($mediaStats);
        $content = (string)$content;

        if (!$content || stripos($content, '<img') === false) {
            $mediaStats['time'] = $this->calculate_media_time($startTime);
            return $content;
        }

        try {
            $processedCount = 0;
            $cache = [];
            $processedUrlStats = [];

            return preg_replace_callback(
                '/<img\b[^>]*>/i',
                function ($imgTag) use ($member, &$processedCount, &$cache, &$processedUrlStats, &$mediaStats) {
                    $tag = $imgTag[0];
                    $src = $this->extract_src($tag);
                    if ($src === '') {
                        return $tag;
                    }

                    if (!$this->should_process_url($src)) {
                        return $tag;
                    }

                    if ($processedCount >= $this->maxProcessCount) {
                        return $tag;
                    }

                    $isNewUrl = !isset($processedUrlStats[$src]);
                    if ($isNewUrl) {
                        $processedUrlStats[$src] = true;
                        $mediaStats['total']++;
                    }

                    $localCopyFailed = false;
                    if ($this->is_local_copy_url($src)) {
                        $localCopyReason = '';
                        if (!isset($cache['local:'.$src])) {
                            $cache['local:'.$src] = $this->copy_local_image($src, $localCopyReason);
                            $cache['local_reason:'.$src] = $localCopyReason;
                        }
                        $localSrc = $cache['local:'.$src];
                        if ($localSrc) {
                            $processedCount++;
                            if ($isNewUrl) {
                                $mediaStats['success']++;
                            }
                            return $this->replace_src($tag, $localSrc);
                        }
                        $localCopyFailed = true;
                    }

                    $downloadReason = '';
                    if (!isset($cache[$src])) {
                        $cache[$src] = $this->download_and_save($src, $member, $downloadReason);
                        $cache['download_reason:'.$src] = $downloadReason;
                    }
                    $downloadResult = $cache[$src];
                    $newSrc = $this->extract_download_url($downloadResult);
                    if (!$newSrc) {
                        if ($isNewUrl) {
                            $reason = (string)($cache['download_reason:'.$src] ?? '');
                            if (!$reason) {
                                $reason = $this->extract_download_reason($downloadResult);
                            }
                            if (!$reason && $localCopyFailed) {
                                $reason = (string)($cache['local_reason:'.$src] ?? 'copy local image failed');
                            }
                            if (!$reason) {
                                $reason = 'download failed';
                            }
                            $this->record_media_failed($mediaStats, $src, $reason);
                        }
                        return $tag;
                    }

                    $processedCount++;
                    if ($isNewUrl) {
                        $mediaStats['success']++;
                    }
                    return $this->replace_src($tag, $newSrc);
                },
                $content
            );
        } catch (\Throwable $e) {
            if (is_array($mediaStats)) {
                $this->record_media_failed($mediaStats, '', $e->getMessage());
            }
            return $content;
        } finally {
            $mediaStats['time'] = $this->calculate_media_time($startTime);
        }
    }

    protected function init_media_stats(&$mediaStats) {
        $mediaStats = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'error' => [],
            'time' => 0,
        ];
    }

    protected function record_media_failed(&$mediaStats, $url, $reason) {
        $mediaStats['failed']++;
        if (count($mediaStats['error']) < 10) {
            $mediaStats['error'][] = [
                'url' => (string)$url,
                'reason' => (string)$reason,
            ];
        }
    }

    protected function calculate_media_time($startTime) {
        $elapsed = intval((microtime(true) - (float)$startTime) * 1000);
        return $elapsed <= 0 ? 1 : $elapsed;
    }

    protected function extract_src($imgTag) {
        if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $imgTag, $match)) {
            return html_entity_decode(trim((string)$match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/\bsrc\s*=\s*([^\s>"\']+)/i', $imgTag, $match)) {
            return html_entity_decode(trim((string)$match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    protected function should_process_url($url) {
        $url = trim((string)$url);
        if (!$url) {
            return false;
        }

        if (stripos($url, $this->sourcePrefix) !== 0) {
            return false;
        }

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!$host) {
            return false;
        }

        if (in_array($host, $this->localDomains, true)) {
            return false;
        }

        $siteHost = strtolower((string)parse_url((string)SITE_URL, PHP_URL_HOST));
        if ($siteHost && $host === $siteHost) {
            return false;
        }

        return true;
    }

    protected function is_local_copy_url($url) {
        $host = strtolower((string)parse_url((string)$url, PHP_URL_HOST));
        return $host && $host === $this->localCopyHost;
    }

    public function copy_local_image($url, &$reason = '') {
        $reason = '';
        $url = trim((string)$url);

        if (!$url) {
            $reason = 'copy local image failed';
            return '';
        }

        $path = (string)parse_url($url, PHP_URL_PATH);
        if (!$path) {
            $reason = 'copy local image failed';
            return '';
        }

        $relativePath = ltrim(str_replace('\\', '/', $path), '/');
        if ($relativePath === '' || strpos($relativePath, '..') !== false) {
            $reason = 'copy local image failed';
            return '';
        }

        $sourceRoot = rtrim($this->localCopySourceRoot, '/');
        $targetRoot = rtrim($this->localCopyTargetRoot, '/');
        $sourceFile = $sourceRoot.'/'.$relativePath;
        $targetFile = $targetRoot.'/'.$relativePath;

        if (!is_file($sourceFile)) {
            $reason = 'copy local image failed';
            return '';
        }

        $targetDir = dirname($targetFile);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            $reason = 'copy local image failed';
            return '';
        }

        if (!@copy($sourceFile, $targetFile)) {
            $reason = 'copy local image failed';
            return '';
        }

        $newSrc = '/'.$relativePath;
        return $newSrc;
    }

    protected function download_and_save($url, $member, &$reason = '') {
        $reason = '';
        try {
            // V1.2 MD5媒体资源去重
            $upload = \Phpcmf\Service::L('Upload')->down_file([
                'url' => $url,
                'timeout' => 8,
            ]);
            if (!$upload || empty($upload['code']) || empty($upload['data'])) {
                $reason = 'download failed';
                return $this->build_download_result(false, '', false, 0, '', $reason);
            }

            $uploadData = (array)$upload['data'];
            $md5 = strtolower(trim((string)($uploadData['md5'] ?? '')));
            $targetSite = $this->get_target_site();
            $mediaService = null;
            try {
                $mediaService = \Phpcmf\Service::L('MediaService', APP_DIR);
            } catch (\Throwable $e) {
                $mediaService = null;
            }

            if ($mediaService && $targetSite && $md5) {
                try {
                    $media = $mediaService->getByMd5($targetSite, $md5);
                    if ($media) {
                        $available = $mediaService->checkAvailable($media);
                        if (!empty($available['available']) && !empty($available['url'])) {
                            try {
                                $mediaService->touch($targetSite, $md5);
                            } catch (\Throwable $e) {
                                // touch 失败不影响正文同步
                            }
                            // V1.2.1 临时文件清理（仅清理本次 down_file 产生的文件）
                            $this->cleanup_temp_download($uploadData);
                            return $this->build_download_result(
                                true,
                                (string)$available['url'],
                                true,
                                (int)($available['attachment_id'] ?? ($media['attachment_id'] ?? 0)),
                                $md5
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    // 媒体查询异常时降级走原有附件保存流程
                }
            }

            if (!isset($uploadData['remote'])) {
                $uploadData['remote'] = 0;
            }

            \Phpcmf\Service::M('Attachment')->member = $member;
            $save = \Phpcmf\Service::M('Attachment')->save_data($uploadData);
            if (!$save || (int)($save['code'] ?? 0) <= 0) {
                $reason = 'save attachment failed';
                return $this->build_download_result(false, '', false, 0, $md5, $reason);
            }

            $attachmentId = (int)$save['code'];
            if ($attachmentId <= 0) {
                $reason = 'save attachment failed';
                return $this->build_download_result(false, '', false, 0, $md5, $reason);
            }

            $newUrl = '';
            if (function_exists('dr_get_file')) {
                $newUrl = (string)dr_get_file($attachmentId);
            }
            if (!$newUrl) {
                $reason = 'save attachment failed';
                return $this->build_download_result(false, '', false, $attachmentId, $md5, $reason);
            }

            if ($mediaService && $targetSite && $md5) {
                try {
                    $mediaService->save([
                        'target_site' => $targetSite,
                        'source_site' => $this->get_source_site($url),
                        'md5' => $md5,
                        'source_url' => (string)$url,
                        'local_path' => (string)($uploadData['path'] ?? ($uploadData['file'] ?? '')),
                        'attachment_id' => $attachmentId,
                        'file_size' => (int)($uploadData['size'] ?? 0),
                        'width' => $this->extract_image_dimension($uploadData['info'] ?? [], 0),
                        'height' => $this->extract_image_dimension($uploadData['info'] ?? [], 1),
                        'file_type' => (string)($uploadData['ext'] ?? ''),
                    ]);
                } catch (\Throwable $e) {
                    // 媒体登记异常时不影响原有同步流程
                }
            }

            return $this->build_download_result(true, $newUrl, false, $attachmentId, $md5);
        } catch (\Throwable $e) {
            $reason = (string)$e->getMessage();
            return $this->build_download_result(false, '', false, 0, '', $reason);
        }
    }

    protected function build_download_result($success, $url = '', $dedup = false, $attachmentId = 0, $md5 = '', $reason = '') {
        if ($success) {
            return [
                'success' => true,
                'url' => (string)$url,
                'dedup' => (bool)$dedup,
                'attachment_id' => (int)$attachmentId,
                'md5' => strtolower(trim((string)$md5)),
            ];
        }

        return [
            'success' => false,
            'reason' => (string)$reason,
            'url' => '',
            'dedup' => false,
            'attachment_id' => 0,
            'md5' => strtolower(trim((string)$md5)),
        ];
    }

    protected function extract_download_url($result) {
        if (is_array($result)) {
            if (empty($result['success'])) {
                return '';
            }
            return trim((string)($result['url'] ?? ''));
        }

        return trim((string)$result);
    }

    protected function extract_download_reason($result) {
        if (!is_array($result)) {
            return '';
        }

        return trim((string)($result['reason'] ?? ''));
    }

    // V1.2.1 临时文件清理
    protected function cleanup_temp_download($uploadData) {
        try {
            $uploadData = (array)$uploadData;
            $path = trim((string)($uploadData['path'] ?? ''));
            if (!$path) {
                return;
            }

            if (preg_match('/^\w+\:\/\//', $path)) {
                return;
            }

            if (!(preg_match('/^[a-zA-Z]\:[\/\\\\]/', $path) || strpos($path, '/') === 0 || strpos($path, '\\') === 0)) {
                $path = rtrim((string)ROOTPATH, '/\\').'/'.ltrim($path, '/\\');
            }

            if (is_file($path)) {
                @unlink($path);
            }
        } catch (\Throwable $e) {
            // 清理失败不影响新闻同步
        }
    }

    protected function get_target_site() {
        $targetSite = trim((string)parse_url((string)SITE_URL, PHP_URL_HOST));
        if (!$targetSite) {
            $targetSite = trim((string)SITE_URL);
        }

        return $targetSite;
    }

    protected function get_source_site($url) {
        $sourceSite = trim((string)parse_url((string)$url, PHP_URL_HOST));
        if ($sourceSite) {
            return $sourceSite;
        }

        return trim((string)parse_url((string)$this->sourcePrefix, PHP_URL_HOST));
    }

    protected function extract_image_dimension($info, $index) {
        if (!is_array($info) || !isset($info[$index])) {
            return 0;
        }

        return max(0, (int)$info[$index]);
    }

    protected function replace_src($imgTag, $newSrc) {
        $newSrc = trim((string)$newSrc);
        if (!$newSrc) {
            return $imgTag;
        }

        $replacement = 'src="'.str_replace('"', '&quot;', $newSrc).'"';

        if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $imgTag)) {
            return preg_replace('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $replacement, $imgTag, 1);
        }

        if (preg_match('/\bsrc\s*=\s*([^\s>"\']+)/i', $imgTag)) {
            return preg_replace('/\bsrc\s*=\s*([^\s>"\']+)/i', $replacement, $imgTag, 1);
        }

        return $imgTag;
    }
}
