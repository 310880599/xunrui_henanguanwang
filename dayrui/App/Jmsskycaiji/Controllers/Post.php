<?php namespace Phpcmf\Controllers;


class Post extends \Phpcmf\Table
{
    public $scj2cms;
    
    public function __construct(...$params){
        parent::__construct(...$params);
        $this->_Extend_Init();
        require_once APPPATH.'Libraries/skycaiji2cms/skycaiji2cms.php';
        $this->scj2cms=new \skycaiji2cms(
            APPPATH.'Libraries',
            FC_NOW_HOST,
            'utf-8',
            false
        );
    }
    
    // 继承类初始化
    protected function _Extend_Init() {
        // 初始化模块
        $this->_module_init('xinwenguanli');
        // 支持附表存储
        $this->is_data = 1;
        // 是否支持模块索引表
        $this->is_module_index = 1;
        // 是否支持
        $this->is_category_data_field = $this->module['category_data_field'] ? 1 : 0;
        $this->where_list_sql = $this->content_model->get_admin_list_where();
        // 初始化数据表
        $this->_init([
            'table' => dr_module_table_prefix('xinwenguanli'),
            'field' => $this->module['field'],
            'sys_field' => ['inputtime', 'updatetime', 'inputip', 'displayorder', 'hits','uid','catid','status'],
            'date_field' => $this->module['setting']['search_time'] ? $this->module['setting']['search_time'] : 'updatetime',
            'show_field' => 'title',
            'where_list' => $this->where_list_sql,
            'order_by' => dr_safe_replace($this->module['setting']['order']),
            'list_field' => $this->module['setting']['list_field'],
            'search_first_field' => $this->module['setting']['search_first_field'] ? $this->module['setting']['search_first_field'] : 'title',
        ]);
        $this->content_model->init($this->init); // 初始化内容模型
        
        $this->is_post_code=0;//验证码
    }
    
    public function index() {
        ob_clean();//清除输出
        ob_start();//缓存输出
        register_shutdown_function(array($this,'_scjExitDo'));
        
        $configFile=WRITEPATH.'config/jmsskycaiji.php';//配置文件
        if(file_exists($configFile)){
            $pluginConfig=include $configFile;
            $pluginConfig=json_decode(base64_decode($pluginConfig),true);
        }
        $this->scj2cms->pluginConfig=is_array($pluginConfig)?$pluginConfig:array();
        $this->scj2cms->funcApiPost=array($this,'_scjFuncApiPost');
        $this->scj2cms->apiPost();
    }
    public function _scjFuncApiPost(){
        $scj2cms=$this->scj2cms;
        
        if(empty(\Phpcmf\Service::L('input')->post('title'))){
            $scj2cms->returnJson(0,'标题为空');
        }
        if(empty(\Phpcmf\Service::L('input')->post('content'))){
            $scj2cms->returnJson(0,'内容为空');
        }
        
        $author=$scj2cms->randLine($this->scj2cms->pluginConfig['author']);
        $member=null;
        $mmember=new \Phpcmf\Model\Member();
        if(is_numeric($author)){
            $member=\Phpcmf\Service::M('Member')->get_member($author);
        }else{
            $member=\Phpcmf\Service::M('Member')->get_member(0,$author);
        }
        if(empty($member)){
            $scj2cms->returnJson(0,'作者不存在：'.$author);
        }
        //分类
        $catid=\Phpcmf\Service::L('input')->post('catid');
        if($catid){
            $cat=null;
            $catTb=str_replace('_xinwenguanli', '_share_category', $this->init['table']);
            $mcat=\Phpcmf\Service::M()->table($catTb);
            if(is_numeric($catid)){
                $cat=$mcat->where('id',$catid)->getRow();
            }else{
                $cat=$mcat->where('name',$catid)->getRow();
            }
            if(empty($cat)){
                $scj2cms->returnJson(0,'分类不存在：'.$catid);
            }else{
                $catid=$cat['id'];
            }
        }else{
            $catid=0;
        }
        
        $_POST['catid']=$catid;
        
        //缩略图
        $thumb=\Phpcmf\Service::L('input')->post('thumb');
        if($thumb&&preg_match('/^\w+\:\/\//', $thumb)){
            try{
                //远程图片
                $thumbData=$scj2cms->curl($thumb);
                if($thumbData['success']){
                    if(preg_match('/content-type\s*\:\s*image\/(\w+)/i',$thumbData['header'],$mext)){
                        //是图片
                        $thumbConfig=array(
                            'file_ext'=>$mext[1],
                            'file_content'=>$thumbData['body']
                        );
                        $thumbData=\Phpcmf\Service::L('Upload')->down_file($thumbConfig);
                        if($thumbData&&$thumbData['code']){
                            // 附件归档
                            if(!isset($thumbData['data']['remote'])){
                                $thumbData['data']['remote']=0;
                            }
                            \Phpcmf\Service::M('Attachment')->member=$member;
                            $thumbData = \Phpcmf\Service::M('Attachment')->save_data($thumbData['data']);
                            if($thumbData&&$thumbData['code']>0){
                                $thumb=$thumbData['code'];//附件id
                            }
                        }
                    }
                }
            }catch (\Exception $ex){}
        }
        if(is_numeric($thumb)){
            $_POST['thumb']=$thumb;
        }else{
            //不是id
            unset($_POST['thumb']);
        }
        
        $data=$_POST;
        //默认参数
        $defParams=array('inputtime'=>date('Y-m-d H:i:s'),'updatetime'=>date('Y-m-d H:i:s'),'inputip'=>\Phpcmf\Service::L('input')->ip_info(),'author'=>$member['name'],'hits'=>1);
        foreach ($defParams as $k=>$v){
            if(!isset($data[$k])){
                $data[$k]=$v;
            }
        }
        
        $data['uid']=$member['username'];
        $data['status']=9;//通过
        $_POST['data']=$data;
        
        try {
            $result=$this->_Post(0,[],true,true);
        }catch(\Exception $ex){
            $scj2cms->returnJson(0,$ex->getMessage());
        }
    }
    public function _scjExitDo(){
        $html=ob_get_contents();//获取输出
        $html=trim($html);
        ob_clean();//清理页面
        
        if(!empty($html)){
            if(strpos($html, '{')===0){
                //是json
                $json=json_decode($html,true);
                if(isset($json['code'])){
                    //cms信息
                    if($json['code']==1){
                        //成功,获取id
                        $lastData=$this->content_model->table($this->init['table'])->order_by('id DESC')->limit(1)->getAll();
                        if(!empty($lastData)&&!empty($lastData[0])){
                            $id=$lastData[0]['id'];
                            $this->scj2cms->returnJson($id,'',FC_NOW_HOST.'index.php?c=show&id='.$id);
                        }
                    }
                    $this->scj2cms->returnJson(0,$json['msg']);
                }else{
                    exit($html);
                }
            }
        }
        $this->scj2cms->returnJson(0,'failed');
    }
}

