<?php namespace Phpcmf\Controllers\Admin;

use Phpcmf\Common;

class Home extends \Phpcmf\Common
{
    public $scj2cms;
    public $configFile;
    public function __construct(...$params){
        parent::__construct(...$params);
        require_once APPPATH.'Libraries/skycaiji2cms/skycaiji2cms.php';
        $this->scj2cms=new \skycaiji2cms(
            APPPATH.'Libraries',
            FC_NOW_HOST,
            'utf-8',
            true
        );
        $this->scj2cms->funcError=array($this,'_scjFuncPluginError');
        $this->scj2cms->funcGetPost=array($this,'_scjFuncGetPost');

        $this->configFile=WRITEPATH.'config/jmsskycaiji.php';//配置文件
    }

	public function index() {
	    $scj2cms=&$this->scj2cms;
	    $scj2cms->formUrl=FC_NOW_URL;
	    $scj2cms->apiUrl=FC_NOW_HOST.'index.php?s=jmsskycaiji&c=post';
	    $scj2cms->static['css']=FC_NOW_URL.'&m=css';
	    $scj2cms->formHeadHtml=dr_form_hidden();
	    if(IS_POST&&$scj2cms->formIsPost()){
	        //提交
	        $scj2cms->funcFormPost=array($this,'_scjFuncFormPost');
	        $scj2cms->formPost();
	        $this->_admin_msg(1, '设置成功',$scj2cms->formUrl,1);
	    }else{
	        $pluginConfig=null;
	        if(file_exists($this->configFile)){
	            $pluginConfig=include $this->configFile;
	            $pluginConfig=json_decode(base64_decode($pluginConfig),true);
	        }
	        $scj2cms->pluginConfig=is_array($pluginConfig)?$pluginConfig:array();
	        $scj2cms->formRequired=array(
	            'title'=>'标题',
	            'content'=>'内容'
	        );
	        $scj2cms->formOptional=array(
	            'catid'=>'栏目名称或id（默认空）',
	            'thumb'=>'缩略图（默认空，填入图片网址可自动下载为附件，或者填入附件ID）',
	            'keywords'=>'关键字（默认空，多个用,号分隔）',
	            'description'=>'描述（默认空）',
	            'inputtime'=>'录入时间（默认当前时间）',
	            'updatetime'=>'更新时间（默认当前时间）',
	            'inputip'=>'客户端ip（默认当前ip）',
	            'displayorder'=>'排列值（默认0）',
	            'hits'=>'浏览数（默认1）',
	            'author'=>'笔名（默认自动获取）',
	        );

	        ob_start();
	        $scj2cms->formView();
	        $html=ob_get_contents();
	        ob_clean();

	        \Phpcmf\Service::V()->assign([
	            'html' => $html,
				'menu' => \Phpcmf\Service::M('auth')->_admin_menu(
					[
						'蓝天采集发布接口设置' => ['jmsskycaiji/home/index', 'fa fa-cog'],
						//'help' => [''],
					]
				),
	        ]);
	        \Phpcmf\Service::V()->display('home.html');
	    }
    }
    //css文件
    public function css(){
        ob_clean();
        header('Content-type: text/css');
        $css=file_get_contents($this->scj2cms->pluginPath.'/skycaiji2cms/css.css');
        exit($css);
    }
    //表单提交
    public function _scjFuncFormPost($data){
        $data=base64_encode(json_encode($data));
        file_put_contents($this->configFile,'<?php return "'.$data.'";');
    }
    //错误提示
    public function _scjFuncPluginError($msg){
        $this->_admin_msg(0,$msg);
    }
    //获取post参数
    public function _scjFuncGetPost($name){
        return \Phpcmf\Service::L('input')->post($name);
    }

}
