<?php namespace Phpcmf\Library\Contentsyncreceiver;

/**
 * 媒体资源服务
 *
 * 负责：
 * - 媒体资源查询
 * - 媒体资源复用判断
 * - 媒体资源登记
 */
class MediaService
{
    protected $table = 'content_sync_media';

    /**
     * 根据目标站点和MD5查询可用媒体资源
     */
    public function getByMd5($targetSite, $md5) {
        $targetSite = trim((string)$targetSite);
        $md5 = strtolower(trim((string)$md5));
        if (!$targetSite || !$md5) {
            return null;
        }

        $row = \Phpcmf\Service::M()->table($this->table)
            ->select('id,target_site,source_site,md5,source_url,local_path,attachment_id,file_size,width,height,file_type,status,last_seen_time')
            ->where('target_site', $targetSite)
            ->where('md5', $md5)
            ->where('status', 1)
            ->getRow();

        return $row && is_array($row) ? $row : null;
    }

    /**
     * 检查媒体资源是否可复用（仅检查，不做修复）
     */
    public function checkAvailable($media) {
        if (!is_array($media) || !$media) {
            return [
                'available' => false,
                'reason' => 'media record is empty',
            ];
        }

        if ((int)($media['status'] ?? 0) !== 1) {
            return [
                'available' => false,
                'reason' => 'media status is invalid',
            ];
        }

        $localPath = trim((string)($media['local_path'] ?? ''));
        if (!$localPath) {
            return [
                'available' => false,
                'reason' => 'local_path is empty',
            ];
        }

        $realPath = $this->resolvePath($localPath);
        if (!$realPath || !is_file($realPath)) {
            return [
                'available' => false,
                'reason' => 'local file not exists',
            ];
        }

        $attachmentId = (int)($media['attachment_id'] ?? 0);
        $url = '';

        if ($attachmentId > 0) {
            $attachment = \Phpcmf\Service::M()->table('attachment_data')->get($attachmentId);
            if (!$attachment || empty($attachment['id'])) {
                return [
                    'available' => false,
                    'reason' => 'attachment is invalid',
                ];
            }

            if (function_exists('dr_get_file')) {
                $url = trim((string)dr_get_file($attachmentId));
            }
            if (!$url) {
                return [
                    'available' => false,
                    'reason' => 'attachment url is empty',
                ];
            }
        } else {
            $url = $this->normalizeUrl($localPath);
            if (!$url) {
                return [
                    'available' => false,
                    'reason' => 'local url is empty',
                ];
            }
        }

        return [
            'available' => true,
            'url' => $url,
            'attachment_id' => $attachmentId,
        ];
    }

    /**
     * 新增媒体资源记录
     */
    public function save($data) {
        if (!is_array($data) || !$data) {
            return false;
        }

        $saveData = [
            'target_site' => trim((string)($data['target_site'] ?? '')),
            'source_site' => trim((string)($data['source_site'] ?? '')),
            'md5' => strtolower(trim((string)($data['md5'] ?? ''))),
            'source_url' => trim((string)($data['source_url'] ?? '')),
            'local_path' => trim((string)($data['local_path'] ?? '')),
            'attachment_id' => (int)($data['attachment_id'] ?? 0),
            'file_size' => (int)($data['file_size'] ?? 0),
            'width' => (int)($data['width'] ?? 0),
            'height' => (int)($data['height'] ?? 0),
            'file_type' => trim((string)($data['file_type'] ?? '')),
            'status' => 1,
            'create_time' => SYS_TIME,
            'update_time' => SYS_TIME,
            'last_seen_time' => SYS_TIME,
        ];

        if (!$saveData['target_site'] || !$saveData['md5']) {
            return false;
        }

        try {
            $rt = \Phpcmf\Service::M()->table($this->table)->insert($saveData);
            $id = (int)($rt['code'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        } catch (\Throwable $e) {
            // V1.2.1 并发冲突回读
        }

        // V1.2.1 并发冲突回读：insert失败时按target_site+md5回读已有记录
        try {
            $media = $this->getByMd5($saveData['target_site'], $saveData['md5']);
            if ($media && is_array($media)) {
                $id = (int)($media['id'] ?? 0);
                return $id > 0 ? $id : false;
            }
        } catch (\Throwable $e) {
            // 回读异常时保持失败返回
        }

        return false;
    }

    /**
     * 更新媒体最后使用时间
     */
    public function touch($targetSite, $md5) {
        $targetSite = trim((string)$targetSite);
        $md5 = strtolower(trim((string)$md5));
        if (!$targetSite || !$md5) {
            return false;
        }

        $rt = \Phpcmf\Service::M()->table($this->table)
            ->where('target_site', $targetSite)
            ->where('md5', $md5)
            ->update(0, [
                'last_seen_time' => SYS_TIME,
                'update_time' => SYS_TIME,
            ]);

        return (bool)($rt['code'] ?? 0);
    }

    protected function resolvePath($path) {
        $path = str_replace('\\', '/', trim((string)$path));
        if (!$path) {
            return '';
        }

        if (preg_match('/^\w+\:\/\//', $path)) {
            return '';
        }

        if (preg_match('/^[a-zA-Z]\:[\/\\\\]/', $path) || strpos($path, '/') === 0) {
            return $path;
        }

        return rtrim(ROOTPATH, '/\\').'/'.ltrim($path, '/');
    }

    protected function normalizeUrl($localPath) {
        $localPath = trim((string)$localPath);
        if (!$localPath) {
            return '';
        }

        if (preg_match('/^\w+\:\/\//', $localPath)) {
            return $localPath;
        }

        return '/'.ltrim(str_replace('\\', '/', $localPath), '/');
    }
}
