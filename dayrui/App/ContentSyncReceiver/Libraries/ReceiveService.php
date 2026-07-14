<?php namespace Phpcmf\Library\Contentsyncreceiver;

class ReceiveService extends \Phpcmf\Table
{
    protected $moduleDir = 'xinwenguanli';
    protected $inited = false;
    protected $captureJson = false;
    protected $capturedJson = [];
    protected $captureExceptionFlag = '__content_sync_receiver_json__';

    /**
     * 执行接收流程
     */
    public function receive($payload, $config) {

        $payload = is_array($payload) ? $payload : [];
        $config = is_array($config) ? $config : [];

        $sourceContentId = trim((string)($payload['content_id'] ?? ''));
        if (!$sourceContentId) {
            return [
                'code' => 0,
                'msg' => 'content_id is required',
            ];
        }

        $title = trim((string)($payload['title'] ?? ''));
        if (!$title) {
            return [
                'code' => 0,
                'msg' => 'title is required',
            ];
        }

        $content = trim((string)($payload['content'] ?? ''));
        if (!$content) {
            return [
                'code' => 0,
                'msg' => 'content is required',
            ];
        }

        $sourceSite = trim((string)($config['source_site'] ?? ''));
        if (!$sourceSite) {
            $sourceSite = 'zhengzhou';
        }

        $logModel = \Phpcmf\Service::M('ReceiveLog', APP_DIR);
        $exists = $logModel->get_by_source($sourceSite, $sourceContentId);
        if ($exists && (int)$exists['local_content_id'] > 0) {
            return [
                'code' => 1,
                'msg' => 'success',
                'local_id' => (int)$exists['local_content_id'],
            ];
        }

        $logId = $exists ? (int)$exists['id'] : 0;
        if (!$logId) {
            $rt = $logModel->create($sourceSite, $sourceContentId, $title);
            if (!$rt['code']) {
                $exists = $logModel->get_by_source($sourceSite, $sourceContentId);
                if ($exists && (int)$exists['local_content_id'] > 0) {
                    return [
                        'code' => 1,
                        'msg' => 'success',
                        'local_id' => (int)$exists['local_content_id'],
                    ];
                }
                return [
                    'code' => 0,
                    'msg' => $rt['msg'] ?: 'create receive log failed',
                ];
            }
            $logId = (int)$rt['code'];
        }

        try {
            $this->init_module();
            $targetCatId = $this->get_target_catid((string)($payload['catid'] ?? ''), $config);
            if (!$targetCatId) {
                throw new \RuntimeException('target catid is not configured');
            }
            if (!isset($this->module['category'][$targetCatId])) {
                throw new \RuntimeException('target catid not exists: '.$targetCatId);
            }

            $member = $this->get_post_member($config);
            $postData = $this->build_post_data($payload, $targetCatId, $member);

            $rt = $this->run_post($postData);
            if (!(int)$rt['code']) {
                throw new \RuntimeException((string)$rt['msg']);
            }

            $localId = (int)($rt['data']['local_id'] ?? 0);
            if (!$localId) {
                throw new \RuntimeException('local id is empty');
            }

            $logModel->mark_success($logId, $localId, $title);

            return [
                'code' => 1,
                'msg' => 'success',
                'local_id' => $localId,
            ];
        } catch (\Throwable $e) {
            $message = $e->getMessage() ?: 'save content failed';
            $logId && $logModel->mark_failed($logId, $message, $title);
            return [
                'code' => 0,
                'msg' => $message,
            ];
        }
    }

    /**
     * 初始化新闻模块发布环境
     */
    protected function init_module() {
        if ($this->inited) {
            return;
        }

        $this->_module_init($this->moduleDir);

        $this->is_data = 1;
        $this->is_module_index = 1;
        $this->is_category_data_field = $this->module['category_data_field'] ? 1 : 0;
        $this->where_list_sql = $this->content_model->get_admin_list_where();
        $this->_init([
            'table' => dr_module_table_prefix($this->moduleDir),
            'field' => $this->module['field'],
            'sys_field' => ['inputtime', 'updatetime', 'inputip', 'displayorder', 'hits', 'uid', 'catid', 'status'],
            'date_field' => $this->module['setting']['search_time'] ? $this->module['setting']['search_time'] : 'updatetime',
            'show_field' => 'title',
            'where_list' => $this->where_list_sql,
            'order_by' => dr_safe_replace($this->module['setting']['order']),
            'list_field' => $this->module['setting']['list_field'],
            'search_first_field' => $this->module['setting']['search_first_field'] ? $this->module['setting']['search_first_field'] : 'title',
        ]);
        $this->content_model->init($this->init);
        $this->is_post_code = 0;
        $this->inited = true;
    }

    protected function get_target_catid($sourceCatId, $config) {
        $sourceCatId = trim((string)$sourceCatId);
        $defaultCatid = (int)($config['default_catid'] ?? 0);
        $mapping = $this->parse_catid_mapping((string)($config['catid_mapping'] ?? ''));

        if ($sourceCatId !== '' && isset($mapping[$sourceCatId])) {
            return (int)$mapping[$sourceCatId];
        }

        if ($defaultCatid) {
            return $defaultCatid;
        }

        if ($sourceCatId !== '' && is_numeric($sourceCatId)) {
            return (int)$sourceCatId;
        }

        return 0;
    }

    protected function parse_catid_mapping($text) {
        $map = [];
        $text = trim((string)$text);
        if (!$text) {
            return $map;
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($source, $target) = explode('=', $line, 2);
            } elseif (strpos($line, ':') !== false) {
                list($source, $target) = explode(':', $line, 2);
            } else {
                continue;
            }
            $source = trim($source);
            $target = (int)trim($target);
            if ($source !== '' && $target > 0) {
                $map[$source] = $target;
            }
        }

        return $map;
    }

    protected function get_post_member($config) {
        $uid = (int)($config['sync_uid'] ?? 1);
        if ($uid < 1) {
            $uid = 1;
        }

        $member = \Phpcmf\Service::M('Member')->get_member($uid);
        if (!$member) {
            $member = \Phpcmf\Service::M('Member')->get_member(1);
        }
        if (!$member) {
            $member = [
                'id' => 1,
                'username' => 'admin',
                'name' => 'admin',
            ];
        }

        return $member;
    }

    protected function build_post_data($payload, $catid, $member) {
        $inputtime = $this->format_time($payload['inputtime'] ?? '');
        $thumb = $this->format_thumb($payload['thumb'] ?? '', $member);

        $data = [
            'catid' => (int)$catid,
            'uid' => (string)(($member['username'] ?? '') ?: 'admin'),
            'author' => (string)(($member['name'] ?? '') ?: (($member['username'] ?? '') ?: 'admin')),
            'title' => trim((string)$payload['title']),
            'content' => html_entity_decode(
                (string)$payload['content'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ),
            'keywords' => trim((string)($payload['seo_keywords'] ?? '')),
            'description' => trim((string)($payload['seo_description'] ?? '')),
            'inputtime' => $inputtime,
            'updatetime' => $inputtime,
            'inputip' => \Phpcmf\Service::L('input')->ip_info(),
            'displayorder' => 0,
            'hits' => 1,
            'status' => 9,
        ];

        if ($thumb) {
            $data['thumb'] = $thumb;
        }

        return $data;
    }

    protected function format_time($value) {
        if (is_numeric($value)) {
            $time = (int)$value;
        } else {
            $time = strtotime((string)$value);
        }
        if (!$time) {
            $time = SYS_TIME;
        }
        return $time;
    }

    protected function format_thumb($thumb, $member) {
        $thumb = trim((string)$thumb);
        if (!$thumb) {
            return 0;
        }

        if (is_numeric($thumb)) {
            return (int)$thumb;
        }

        if (!preg_match('/^\w+\:\/\//', $thumb)) {
            return 0;
        }

        try {
            $upload = \Phpcmf\Service::L('Upload')->down_file([
                'url' => $thumb,
                'timeout' => 8,
            ]);
            if (!$upload || empty($upload['code']) || empty($upload['data'])) {
                return 0;
            }

            if (!isset($upload['data']['remote'])) {
                $upload['data']['remote'] = 0;
            }

            \Phpcmf\Service::M('Attachment')->member = $member;
            $save = \Phpcmf\Service::M('Attachment')->save_data($upload['data']);
            if ($save && (int)$save['code'] > 0) {
                return (int)$save['code'];
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return 0;
    }

    protected function run_post($postData) {
        $oldPost = $_POST;
        $this->captureJson = true;
        $this->capturedJson = [];

        $_POST = [];
        $_POST['catid'] = (int)$postData['catid'];
        $_POST['data'] = $postData;

        try {
            $this->_Post(0, [], true, true);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== $this->captureExceptionFlag) {
                $_POST = $oldPost;
                $this->captureJson = false;
                throw $e;
            }
        } finally {
            $_POST = $oldPost;
            $this->captureJson = false;
        }

        if (!$this->capturedJson) {
            return dr_return_data(0, 'save failed');
        }

        return $this->capturedJson;
    }

    /**
     * 重写 _Post 完成后的返回数据，输出本地内容id
     */
    protected function _Call_Post($data) {
        $id = (int)($data[1]['id'] ?? ($data[0]['id'] ?? 0));
        if (!$id) {
            return dr_return_data(0, 'save failed');
        }
        return dr_return_data(1, 'success', [
            'local_id' => $id,
        ]);
    }

    /**
     * 捕获 _Post 内部的 _json 输出，避免 exit 中断流程
     */
    public function _json($code, $msg, $data = [], $return = false, $extend = []) {
        if ($this->captureJson) {
            $this->capturedJson = dr_return_data((int)$code, (string)$msg, $data, $extend);
            throw new \RuntimeException($this->captureExceptionFlag);
        }
        parent::_json($code, $msg, $data, $return, $extend);
    }
}
