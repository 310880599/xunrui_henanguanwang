<?php
//版本:1.2.1
define('in_skycaiji2cms', 1);//权限
include 'head.php';
class skycaiji2cms{
    public $charset;//编码
    
    public $pluginPath;//插件根目录
    public $pluginUrl;//插件根网址
    public $pluginConfig;//插件配置
    
    public $funcError;//报错函数
    public $funcFormPost;//表单提交函数
    public $funcApiPost;//接口提交函数
    public $funcGetPost;//获取$_POST参数函数
    
    public $openForm;//是否使用表单设置
    public $formHeadHtml;//表单头部html
    public $formFootHtml;//表单尾部html
    public $formUrl='';//form表单地址
    public $formRequired;//表单必填项
    public $formOptional;//表单选填项
    
    public $apiUrl;//接口地址
    public $bodyEndHtml;//结束html
    
    public $pluginLang;//语言包，不同编码
    
    public $static;//静态资源
    
    //初始化
    public function __construct($pluginPath,$pluginUrl,$charset='utf-8',$openForm=false){
        $this->pluginPath=rtrim($pluginPath,'/\\').DIRECTORY_SEPARATOR;
        $this->pluginUrl=rtrim($pluginUrl,'/\\').'/';
        $this->openForm=$openForm?true:false;
        
        $this->charset=strtolower($charset);
        
        $langFile=$this->pluginPath.'skycaiji2cms/lang.php';
        if(file_exists($langFile)){
            $this->pluginLang=include $langFile;
        }
        
        $this->static=array(
            'css'=>$this->pluginUrl.'skycaiji2cms/css.css'
        );
    }
    //表单是post提交
    public function formIsPost(){
        $sub=$this->doGetPost('formsub');
        if(!empty($sub)){
            return true;
        }else{
            return false;
        }
    }
    //表单提交
    public function formPost(){
        $data=array(
            'apikey'=>$this->doGetPost('apikey'),
            'author'=>$this->doGetPost('author'),
            'apitype'=>$this->doGetPost('apitype')
        );
        if(empty($data['author'])){
            $this->doError($this->pluginLang['empty_author']);
        }
        if(empty($data['apikey'])){
            $this->doError($this->pluginLang['empty_apikey']);
        }
        $this->doFormPost($data);
    }
    //表单html页面
    public function formView(){
        $this->pluginConfig=$this->setPluginConfig($this->pluginConfig);
        $scj2cms=$this;
        include $this->pluginPath.'skycaiji2cms/form.php';
    }

    //接口提交
    public function apiPost(){
        if(strtolower($_SERVER['REQUEST_METHOD'])!='post'){
            $this->returnJson(0,$this->pluginLang['error_request']);
        }
        $this->pluginConfig=$this->setPluginConfig($this->pluginConfig);
        if(empty($this->pluginConfig)){
            $this->returnJson(0,$this->pluginLang['empty_plugin_config']);
        }
        if(empty($this->pluginConfig['author'])){
            $this->returnJson(0,$this->pluginLang['empty_author']);
        }
        if(empty($this->pluginConfig['apikey'])){
            $this->returnJson(0,$this->pluginLang['empty_apikey']);
        }
        if(empty($this->pluginConfig['apitype'])){
            //普通
            if($_GET['apikey']!=md5($this->pluginConfig['apikey'])){
                $this->returnJson(0,$this->pluginLang['error_apikey']);
            }
        }elseif($this->pluginConfig['apitype']=='safe'){
            //安全
            $apiTime=$this->doGetPost('api_time');
            $apiTime=intval($apiTime);
            $apiSign=$this->doGetPost('api_sign');
            
            if($apiSign!=md5($apiTime.$this->pluginConfig['apikey'])){
                $this->returnJson(0,$this->pluginLang['error_api_sign']);
            }elseif(time()-$apiTime>600){
                $this->returnJson(0,$this->pluginLang['error_timeout'].date('Y-m-d H:i:s',$apiTime));
            }
        }else{
            $this->returnJson(0,$this->pluginLang['error_apitype']);
        }
        
        $this->doApiPost();
    }
    public function returnJson($id,$error='',$target='',$desc=''){
        //输出json数据
        ob_clean();
        $data=array(
            'id'=>$id,
            'target'=>$target,
            'desc'=>$desc,
            'error'=>$error,
        );
        if($data['error']){
            $data['error']=$this->pluginLang['error_return'].$data['error'];
        }
        header('content-type:application/json;charset=utf-8');
        $data=json_encode($data);
        exit($data);
    }
    //随机一行
    public function randLine($str){
        $list=array();
        if($str&&preg_match_all('/[^\r\n]+/',$str,$list)){
            $list=$list[0];
            $list=array_filter($list);
            $list=array_values($list);
        }else{
            $list=array();
        }
        $rand=array_rand($list,1);
        $rand=$list[$rand];
        return $rand;
    }
    
    public function curl($url,$headers=array(),$options=array(),$postData=null){
        $headers=is_array($headers)?$headers:array();
        $options=is_array($options)?$options:array();
        
        $options['timeout']=intval($options['timeout']);
        $options['timeout']=$options['timeout']>0?$options['timeout']:20;//连接超时
        
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_TIMEOUT, $options['timeout'] );
        curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT , 10 );//响应超时
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt ( $ch, CURLOPT_HEADER, 1 );
        if($options['nobody']){
            //不返回正文
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        if($options['useragent']){
            //浏览器标识
            curl_setopt($ch, CURLOPT_USERAGENT, $options['useragent']);
        }
        //忽略https模式
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
        //设置头信息
        if(!empty($headers)&&count($headers)>0){
            curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
        }
        
        if(isset($postData)&&$postData!==false){
            //不是null和false是post模式
            curl_setopt ( $ch, CURLOPT_POST, 1 );
            if(is_array($postData)){
                //必须转成url字符串才是网页表单类型application/x-www-form-urlencoded
                $postData=http_build_query($postData);
            }
            curl_setopt ( $ch, CURLOPT_POSTFIELDS, $postData );
        }
        
        $body = curl_exec ( $ch );
        
        $headerPos=strpos($body, "\r\n\r\n");//头信息定位索引
        if($headerPos!==false){
            $headerPos=intval($headerPos)+strlen("\r\n\r\n");//加上换行的长度等于头大小
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);//头大小
        $headerSize=intval($headerSize);
        if($headerSize<$headerPos){
            $headerSize=$headerPos;
        }
        
        $header = substr($body, 0, $headerSize);//头信息
        $body = substr($body, $headerSize);//body内容
        
        $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);//状态码
        $code=intval($code);
        curl_close ( $ch );
        
        $data=array('success'=>false,'header'=>$header);
        if($code>=200&&$code<300){
            $data['success']=true;
            $data['body']=$body;
        }
        return $data;
    }
    
    //运行错误
    protected function doError($msg){
        call_user_func($this->funcError, $msg);
    }
    //运行formPost
    protected function doFormPost($data){
        call_user_func($this->funcFormPost, $data);
    }
    //运行apiPost
    protected function doApiPost(){
        call_user_func($this->funcApiPost);
    }
    //获取post参数
    protected function doGetPost($key){
        if(empty($this->funcGetPost)){
            return isset($_POST[$key])?$_POST[$key]:'';
        }else{
            return call_user_func($this->funcGetPost,$key);
        }
    }
    //设置插件配置
    protected function setPluginConfig($pluginConfig){
        $pluginConfig=(array)$pluginConfig;
        if(empty($pluginConfig)){
            $pluginConfig=array();
        }
        return $pluginConfig;
    }
}