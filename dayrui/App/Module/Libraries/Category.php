<?php namespace Phpcmf\Library\Module;
/**
 * {{www.xunruicms.com}}
 * {{迅睿内容管理框架系统}}
 * 本文件是框架系统文件，二次开发时不可以修改本文件，可以通过继承类方法来重写此文件
 **/

class Category {

    protected $ismain = 0;

    public function ismain($v) {
        $this->ismain = $v;
        return $this;
    }

    public function select($mid, $id = '', $str = '', $default = ' -- ', $onlysub = 0, $is_push = 0, $is_first = 0) {
		return \Phpcmf\Service::L('Tree')->ismain($this->ismain)->select_category($this->get_category($mid), $id, $str, $default, $onlysub, $is_push, $is_first);
    }

    // 获取全部栏目
    public function get_category($mid, $siteid = SITE_ID) {
        return \Phpcmf\Service::C()->get_cache('module-'.$siteid.'-'.$mid, 'category');
    }

    // 获取下级子栏目
    public function get_child($mid, $catid, $siteid = SITE_ID) {

        $cats = \Phpcmf\Service::C()->get_cache('module-'.$siteid.'-'.$mid, 'category');
        if (!$cats) {
            return [];
        }

        $rt = [];
        foreach ($cats as $c) {
            if ($c['pid'] == $catid) {
                $rt[] = $c['id'];
            }
        }

        return $rt;
    }

    // 通过目录找id
    public function get_catid($mid, $dir, $siteid = SITE_ID) {

        $cats = \Phpcmf\Service::C()->get_cache('module-'.$siteid.'-'.$mid, 'category_dir');
        if (!$cats) {
            return [];
        }

        return isset($cats[$dir]) ? $cats[$dir] : 0;
    }

    // 查询所属主栏目
    public function get_ismain_id($mid, $cat) {

        return $cat['id'];
    }

}