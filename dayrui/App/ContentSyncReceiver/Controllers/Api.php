<?php namespace Phpcmf\Controllers;

class Api extends \Phpcmf\App
{
    protected function output_json($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 后台配置
     */
    public function config() {

        if (!IS_ADMIN) {
            $this->_msg(0, dr_lang('无权限访问'));
        }

        $data = \Phpcmf\Service::M('app')->get_config(APP_DIR);

        if (IS_AJAX_POST) {
            $post = \Phpcmf\Service::L('input')->post('data');
            $post['api_key'] = trim((string)$post['api_key']);
            $post['source_site'] = trim((string)$post['source_site']);
            $post['default_catid'] = (int)$post['default_catid'];
            $post['sync_uid'] = (int)$post['sync_uid'];
            $post['catid_mapping'] = trim((string)$post['catid_mapping']);
            \Phpcmf\Service::M('app')->save_config(APP_DIR, $post);
            $this->_json(1, dr_lang('操作成功'));
        }

        $page = (int)\Phpcmf\Service::L('input')->get('page');
        $catSelect = \Phpcmf\Service::L('category', 'module')->select(
            'xinwenguanli',
            (int)($data['default_catid'] ?? 0),
            'name="data[default_catid]" class="form-control"',
            '--'
        );

        \Phpcmf\Service::V()->assign([
            'page' => $page,
            'data' => $data,
            'cat_select' => $catSelect,
            'form' => dr_form_hidden(['page' => $page]),
            'menu' => \Phpcmf\Service::M('auth')->_admin_menu(
                [
                    '新闻同步接收配置' => [APP_DIR.'/'.\Phpcmf\Service::L('Router')->class.'/config', 'fa fa-cog'],
                ]
            ),
        ]);
        \Phpcmf\Service::V()->display('config.html');
    }

    /**
     * 接收同步内容
     * POST: /index.php?s=contentsyncreceiver&c=api&m=receive
     */
    public function receive() {

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->output_json([
                'code' => 0,
                'msg' => 'method not allowed',
            ]);
        }

        $config = \Phpcmf\Service::M('app')->get_config(APP_DIR);
        $security = \Phpcmf\Service::L('Security', APP_DIR);
        $requestKey = $security->get_request_api_key();
        if (!$security->is_valid_api_key($requestKey, (string)($config['api_key'] ?? ''))) {
            $this->output_json([
                'code' => 0,
                'msg' => 'invalid api key',
            ]);
        }

        $payload = $this->get_payload();
        if (!is_array($payload)) {
            $this->output_json([
                'code' => 0,
                'msg' => 'invalid json body',
            ]);
        }

        $service = \Phpcmf\Service::L('ReceiveService', APP_DIR);
        $rt = $service->receive($payload, $config);
        if ((int)$rt['code']) {
            $this->output_json([
                'code' => 1,
                'msg' => 'success',
                'local_id' => (string)$rt['local_id'],
            ]);
        }

        $this->output_json([
            'code' => 0,
            'msg' => (string)($rt['msg'] ?: 'save failed'),
        ]);
    }

    protected function get_payload() {
        $raw = trim((string)file_get_contents('php://input'));
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
            return null;
        }

        $post = \Phpcmf\Service::L('input')->post();
        return is_array($post) ? $post : [];
    }
}
