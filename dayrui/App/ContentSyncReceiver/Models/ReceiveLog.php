<?php namespace Phpcmf\Model\ContentSyncReceiver;

class ReceiveLog extends \Phpcmf\Model
{
    protected $table = 'content_sync_receive_log';

    public function get_by_source($sourceSite, $sourceContentId) {
        return \Phpcmf\Service::M()->table($this->table)
            ->where('source_site', (string)$sourceSite)
            ->where('source_content_id', (string)$sourceContentId)
            ->getRow();
    }

    public function create($sourceSite, $sourceContentId, $title) {
        return \Phpcmf\Service::M()->table($this->table)->insert([
            'source_site' => (string)$sourceSite,
            'source_content_id' => (string)$sourceContentId,
            'local_content_id' => 0,
            'title' => dr_strcut((string)$title, 250),
            'status' => 0,
            'error_message' => '',
            'create_time' => SYS_TIME,
        ]);
    }

    public function mark_success($id, $localId, $title = '') {
        return \Phpcmf\Service::M()->table($this->table)->update((int)$id, [
            'local_content_id' => (int)$localId,
            'title' => dr_strcut((string)$title, 250),
            'status' => 1,
            'error_message' => '',
        ]);
    }

    public function mark_failed($id, $message, $title = '') {
        return \Phpcmf\Service::M()->table($this->table)->update((int)$id, [
            'title' => dr_strcut((string)$title, 250),
            'status' => -1,
            'error_message' => dr_strcut((string)$message, 490),
        ]);
    }
}
