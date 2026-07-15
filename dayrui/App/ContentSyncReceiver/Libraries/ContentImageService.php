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
                        if (!isset($cache['local:'.$src])) {
                            $cache['local:'.$src] = $this->copy_local_image($src);
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

                    if (!isset($cache[$src])) {
                        $cache[$src] = $this->download_and_save($src, $member);
                    }
                    $newSrc = $cache[$src];
                    if (!$newSrc) {
                        if ($isNewUrl) {
                            $reason = $localCopyFailed
                                ? 'copy_local_image and download_and_save failed'
                                : 'download_and_save failed';
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
        return max(
            0,
            intval((microtime(true) - (float)$startTime) * 1000)
        );
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

    public function copy_local_image($url) {
        $url = trim((string)$url);

        if (!$url) {
            return '';
        }

        $path = (string)parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return '';
        }

        $relativePath = ltrim(str_replace('\\', '/', $path), '/');
        if ($relativePath === '' || strpos($relativePath, '..') !== false) {
            return '';
        }

        $sourceRoot = rtrim($this->localCopySourceRoot, '/');
        $targetRoot = rtrim($this->localCopyTargetRoot, '/');
        $sourceFile = $sourceRoot.'/'.$relativePath;
        $targetFile = $targetRoot.'/'.$relativePath;

        if (!is_file($sourceFile)) {
            return '';
        }

        $targetDir = dirname($targetFile);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return '';
        }

        if (!@copy($sourceFile, $targetFile)) {
            return '';
        }

        $newSrc = '/'.$relativePath;
        return $newSrc;
    }

    protected function download_and_save($url, $member) {
        try {
            $upload = \Phpcmf\Service::L('Upload')->down_file([
                'url' => $url,
                'timeout' => 8,
            ]);
            if (!$upload || empty($upload['code']) || empty($upload['data'])) {
                return '';
            }

            if (!isset($upload['data']['remote'])) {
                $upload['data']['remote'] = 0;
            }

            \Phpcmf\Service::M('Attachment')->member = $member;
            $save = \Phpcmf\Service::M('Attachment')->save_data($upload['data']);
            if (!$save || (int)($save['code'] ?? 0) <= 0) {
                return '';
            }

            $attachmentId = (int)$save['code'];
            if ($attachmentId <= 0) {
                return '';
            }

            if (function_exists('dr_get_file')) {
                return (string)dr_get_file($attachmentId);
            }

            return '';
        } catch (\Throwable $e) {
            return '';
        }
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
