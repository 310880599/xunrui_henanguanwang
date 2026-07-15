<?php namespace Phpcmf\Model\Contentsyncreceiver;

class ReceiveLog extends \Phpcmf\Model
{
    public $table = 'content_sync_receive_log';

    public function get_by_source($sourceSite, $sourceContentId) {
        return \Phpcmf\Service::M()->table($this->table)
            ->where('source_site', (string)$sourceSite)
            ->where('source_content_id', (string)$sourceContentId)
            ->getRow();
    }

    public function create($sourceSite, $sourceContentId, $title) {
        return \Phpcmf\Service::M()->table($this->table)->insert(array_merge([
            'source_site' => (string)$sourceSite,
            'source_content_id' => (string)$sourceContentId,
            'local_content_id' => 0,
            'title' => dr_strcut((string)$title, 250),
            'status' => 0,
            'error_message' => '',
            'create_time' => SYS_TIME,
        ], $this->format_media_stats()));
    }

    public function mark_success($id, $localId, $title = '', $mediaStats = []) {
        return \Phpcmf\Service::M()->table($this->table)->update((int)$id, array_merge([
            'local_content_id' => (int)$localId,
            'title' => dr_strcut((string)$title, 250),
            'status' => 1,
            'error_message' => '',
        ], $this->format_media_stats($mediaStats)));
    }

    public function mark_failed($id, $message, $title = '', $mediaStats = []) {
        return \Phpcmf\Service::M()->table($this->table)->update((int)$id, array_merge([
            'title' => dr_strcut((string)$title, 250),
            'status' => -1,
            'error_message' => dr_strcut((string)$message, 490),
        ], $this->format_media_stats($mediaStats)));
    }

    protected function format_media_stats($mediaStats = []) {
        $defaults = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'error' => [],
            'time' => 0,
        ];

        if (!is_array($mediaStats)) {
            $mediaStats = [];
        }

        $mediaStats = array_merge($defaults, $mediaStats);

        $errors = $mediaStats['error'];
        if (is_string($errors)) {
            $decoded = dr_string2array($errors);
            if (is_array($decoded)) {
                $errors = $decoded;
            } elseif ($errors !== '') {
                $errors = [$errors];
            } else {
                $errors = [];
            }
        } elseif (!is_array($errors)) {
            $errors = [];
        }

        $errorJson = dr_array2string($errors);

        return [
            'media_total' => max(0, (int)$mediaStats['total']),
            'media_success' => max(0, (int)$mediaStats['success']),
            'media_failed' => max(0, (int)$mediaStats['failed']),
            'media_error' => dr_strcut((string)$errorJson, 490),
            'media_time' => max(0, (float)$mediaStats['time']),
        ];
    }
}
