<?php namespace Phpcmf\Controllers\Admin;

class Config extends \Phpcmf\App
{

    public function index() {

        $data = \Phpcmf\Service::M('app')->get_config(APP_DIR);

        if (IS_AJAX_POST) {

            $post = \Phpcmf\Service::L('input')->post('data');
            \Phpcmf\Service::M('app')->save_config(APP_DIR, $post);

            $this->_json(1, dr_lang('操作成功'));
        }

        $page = intval(\Phpcmf\Service::L('input')->get('page'));

        \Phpcmf\Service::V()->assign([
            'page' => $page,
            'data' => $data,
            'form' => dr_form_hidden(['page' => $page]),
            'menu' => \Phpcmf\Service::M('auth')->_admin_menu(
                [
                    '插件设置' => [APP_DIR.'/'.\Phpcmf\Service::L('Router')->class.'/index', 'fa fa-cog'],
                ]
            ),
        ]);
        \Phpcmf\Service::V()->display('config.html');
    }

    public function set_index() {

        $id = intval(\Phpcmf\Service::L('input')->get('id'));
        $page = intval(\Phpcmf\Service::L('input')->get('page'));

        if (!$page) {
            $this->_html_msg(1, '正在批量设置中...', dr_url('ueditor/config/set_index', ['page' =>1, 'id' => $id]));
        }

        if (!is_file(CMSPATH.'Field/Ueditor.php')) {
            $this->_html_msg(0, '没有找到Ueditor字段文件，检查是否遗漏文件');
        }

        if ($id) {
            \Phpcmf\Service::M()->db->table('field')->where('fieldtype', 'Editor')->update([
                'fieldtype' => 'Ueditor',
            ]);
            \Phpcmf\Service::M('cache')->update_cache();
            \Phpcmf\Service::M('cache')->update_data_cache();
            $this->_html_msg(1, '已将系统编辑器变更为百度编辑器');
        } else {
            \Phpcmf\Service::M()->db->table('field')->where('fieldtype', 'Ueditor')->update([
                'fieldtype' => 'Editor'
            ]);
            \Phpcmf\Service::M('cache')->update_cache();
            \Phpcmf\Service::M('cache')->update_data_cache();
            $this->_html_msg(1, '已将百度编辑器还原为系统编辑器');
        }

    }

}
