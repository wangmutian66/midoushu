<?php
/**
 */
namespace app\mobilered\controller;
use app\common\logic\RedCartLogic;
use app\common\logic\UsersLogic;
use app\common\logic\RedUsersLogic;
use think\Controller;
use think\Session;

class MobileBase extends Controller {
    public $session_id;
    public $weixin_config;
    public $cateTrre = array();
    
    /*
     * 初始化操作
     */
    public function _initialize() {

        session('user'); //不用这个在忘记密码不能获取session('validate_code');

        // Session::start();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        $this->session_id = session_id(); // 当前的 session_id
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用
        // 判断当前用户是否手机                
        if(isMobile())
            cookie('is_mobile','1',3600); 
        else 
            cookie('is_mobile','0',3600);

        // 推广员
        if($recommend_id= I('get.recommend_id/d')){ // ？？？推荐人吗？
            $where['id'] = ['eq',$recommend_id];
        }
        if($invite_code = I('get.invite_code/s')){  // 邀请码
            $where['invite_code'] = ['eq',$invite_code];
        }

        if($recommend_id || $invite_code){
            $staff_info = db('staff')->where($where)->cache(true)->find(); // 获取推荐员工信息
            session('recommend_staff',$staff_info);
        }
        
        $wx_qr = M('wx_user')->cache(true)->value('qr'); //获取微信配置
        $this->assign('wx_qr',$wx_qr);
        //微信浏览器
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            $this->assign('is_weixin_browser',1);
            $user_temp = session('user');
            # dump($user_temp);die;
            if (isset($user_temp['user_id']) && $user_temp['user_id']) {
                $user = M('users')->where("user_id", $user_temp['user_id'])->find();
                if (!$user) {
                    $_SESSION['openid'] = 0;
                    session('user', null);
                }
                // 判断是否存在token
                if ( !$user['token'] ){
                    $_SESSION['openid'] = 0;
                    session('user', null);
                }
                // 判断是否存在 微信绑定用户信息
                $oauth_user = M('oauth_users')->where("user_id", $user['user_id'])->find();
                if (!$oauth_user) {
                    $_SESSION['openid'] = 0;
                    session('user', null);
                }
            }
            //dump($_SESSION['openid']);die;
            # if (empty($_SESSION['openid']) || ( ($_SESSION['staff_openid'] && $_SESSION['openid']) && empty($user_temp) )) {
            if ( empty($_SESSION['openid']) ) {
                $this->weixin_config = M('wx_user')->find(); //获取微信配置
                $this->assign('wechat_config', $this->weixin_config);

                if(is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1){
                    $wxuser = $this->GetOpenid(); //授权获取openid以及微信用户信息

                    session('subscribe', $wxuser['subscribe']);// 当前这个用户是否关注了微信公众号
                    setcookie('subscribe',$wxuser['subscribe']);
                    $logic = new UsersLogic();
                    
                    $is_bind_account = tpCache('basic.is_bind_account'); // 是否需要绑定账号

                    if($is_bind_account){ // 如果需要绑定账号

                        if($wxuser['unionid']){ // 获取微信公众号配置信息
                            $thirdUser = M('OauthUsers')->where(['unionid'=>$wxuser['unionid'], 'oauth'=>'weixin'])->find(); // 公众号 绑定用户 信息
                        }else{
                            $thirdUser = M('OauthUsers')->where(['openid'=>$wxuser['openid'], 'oauth'=>'weixin'])->find();  // 获取已绑定信息
                        }
                        if(empty($thirdUser)){ // 如果绑定信息有误
                            // 用户未关联账号, 跳到关联账号页
                            session('third_oauth',$wxuser);
                            $first_leader = I('first_leader');
                            return $this->redirect(U('Mobile/User/bind_guide',['first_leader'=>$first_leader]));

                        }else{
                            //微信自动登录
                            $data = $logic->thirdLogin_new($wxuser); 
                        }
                    }else{
                        $data = $logic->thirdLogin($wxuser);
                    }
                    if($data['status'] == 1){
                        //获取公众号openid,并保持到session的user中
                        $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
                        $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];
                        session('user',$data['result']);
                        setcookie('user_id',$data['result']['user_id'],null,'/');
                        setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
                        setcookie('uname',$data['result']['nickname'],null,'/');
                        // 登录后将购物车的商品的 user_id 改为当前登录的id
                        M('cart_red')->where("session_id" ,$this->session_id)->save(array('user_id'=>$data['result']['user_id']));
                        $cartLogic = new RedCartLogic();
                        $cartLogic->doUserLoginHandle($this->session_id,$data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
                    }
                }
            }
        }

        $vnum = tpCache('shop_info.vnum');
        $this->assign('vnum',$vnum);

        $user_mobile = session('user.mobile');
        if($user_mobile){
          $staff_info = M('staff')->field('invite_code,id')->where("phone = {$user_mobile}")->find();
        }

        $staff_id =  session('staff.id');
        if($staff_id){
          $staff_info = M('staff')->field('invite_code,id')->find($staff_id);
        }
        
        $this->assign('staff_info',$staff_info);
        //
      
        $this->public_assign();
    }
    
    /**
     * 保存公告变量到 smarty中 比如 导航 
     */   
    public function public_assign()
    {
        $first_login = session('first_login');
        $this->assign('first_login', $first_login);
        if (!$first_login && ACTION_NAME == 'login') {
            session('first_login', 1);
        }
        
       $tpshop_config = array();
       $tp_config = M('config')->cache(true,TPSHOP_CACHE_TIME)->select();       
       foreach($tp_config as $k => $v)
       {
       	  if($v['name'] == 'hot_keywords'){
       	  	 $tpshop_config['hot_keywords'] = explode('|', $v['value']);
       	  }       	  
          $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
       }                        
       
       $goods_category_tree = get_goods_red_category_tree();
       $this->cateTrre = $goods_category_tree;
       $this->assign('goods_category_tree', $goods_category_tree);                     
       $brand_list = M('brand_red')->cache(true,TPSHOP_CACHE_TIME)->field('id,cat_id,logo,is_hot')->where("cat_id>0")->select();              
       $this->assign('brand_list', $brand_list);
       $this->assign('tpshop_config', $tpshop_config);
       /** 修复首次进入微商城不显示用户昵称问题 **/
       $user_id = cookie('user_id');
       $uname = cookie('uname');
       if(empty($user_id) && ($users = session('user')) ){
           $user_id = $users['user_id'];
           $uname = $users['nickname'];
       }

       $this->assign('user_id',$user_id);
       $this->assign('uname',$uname);

       if(ACTION_NAME == 'goodsInfo'){
            $goods_id = I('id/d');
            $suppliers_id = db('goods_red')->where(['goods_id'=>$goods_id])->cache(true)->value('suppliers_id');

            $chat_group_id = db('suppliers')->where(['suppliers_id'=>$suppliers_id])->cache(true)->value('chat_group_id');   //查询默认分组
            if(!$chat_group_id){
                //供货商没有设置客服
                $chat_group_id = db('chat_group')->cache('default_chat_group_id')->where("is_default = 1")->getField('id');
            }
            $this->assign('chat_group_id',$chat_group_id);
            //查询该分组下面有没有在线的客服
            $user_list = db('users')->where("chat_group_id = {$chat_group_id} and is_line = 1")->field('user_id')->select(); 
            $this->assign('is_default_chat',(empty($user_list)?0:1));
        }else{
            $chat_group_id = db('chat_group')->cache('default_chat_group_id')->where('is_default = 1')->value('id');
            $user_list = db('users')->where("chat_group_id = {$chat_group_id} and is_line = 1")->field('user_id')->select();
            $this->assign('chat_group_id',$chat_group_id);
            $this->assign('is_default_chat',(empty($user_list)?0:1));
        }

        /*分享锁定*/
        $user_mobile = session('user.mobile');
        if($user_mobile){
            $staff_info = db('staff')
                            ->field('invite_code,id')
                            ->cache("mobile_base_mobile_{$user_mobile}")
                            ->where("phone = {$user_mobile}")
                            ->find();
        }
        if($staff_id = session('staff.id')){
            $staff_info = M('staff')
                            ->field('invite_code,id')
                            ->cache("mobile_base_id_{$staff_id}")
                            ->find($staff_id);
        }
        $this->assign('staff_info',$staff_info);
    }      

    // 网页授权登录获取 OpendId
    public function GetOpenid()
    {
        if($_SESSION['openid'])
            return $_SESSION['openid'];
        
        // 通过code获得openid
        if (!isset($_GET['code'])){
            // 触发微信返回code码
            // $baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            $baseUrl = urlencode($this->get_url());
            $url = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址
            Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
            exit();
        } else {
            // 上面获取到code后这里跳转回来
            $code = $_GET['code'];
            $data = $this->getOpenidFromMp($code); // 获取网页授权access_token和用户openid
            $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息
            // dump($data2);die;
            $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
            $data['sex'] = $data2['sex'];
            $data['head_pic'] = $data2['headimgurl']; 
            $data['subscribe'] = $data2['subscribe'];      
            $data['oauth_child'] = 'mp';
            $_SESSION['openid'] = $data['openid'];
            $data['oauth'] = 'weixin';
            if(isset($data2['unionid'])){
            	$data['unionid'] = $data2['unionid'];
            }
            return $data;
        }
    }

    /**
     * 获取当前的url 地址
     * @return type
     */
    private function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }    
    
    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
    	  //1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
    	  //2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code);       
        $ch = curl_init(); // 初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);         
        curl_close($ch);
        return $data;
    }
    
    
        /**
     *
     * 通过access_token openid 从工作平台获取UserInfo      
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    {         
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);            
        curl_close($ch);
        //获取用户是否关注了微信公众号， 再来判断是否提示用户 关注
        if(!isset($data['unionid'])){
        	$access_token2 = $this->get_access_token();//获取基础支持的access_token
        	$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token2&openid=$openid";
        	$subscribe_info = httpRequest($url,'GET');
        	$subscribe_info = json_decode($subscribe_info,true);
        	$data['subscribe'] = $subscribe_info['subscribe'];
        }                
        return $data;
    }
    
    
    public function get_access_token(){
        //判断是否过了缓存期
        $expire_time = $this->weixin_config['web_expires'];

        if($expire_time > time()){
           return $this->weixin_config['web_access_token'];
        }

        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->weixin_config[appid]}&secret={$this->weixin_config[appsecret]}";
        $return = httpRequest($url,'GET');
        $return = json_decode($return,1);
        $web_expires = time() + 7000; // 提前60秒过期
        M('wx_user')->where(array('id'=>$this->weixin_config['id']))->save(array('web_access_token'=>$return['access_token'],'web_expires'=>$web_expires));
        return $return['access_token'];
    }    

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        // $urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["secret"] = $this->weixin_config['appsecret'];
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址     
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token,$openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';        
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;                    
    }    
    
    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }
    public function ajaxReturn($data){
        exit(json_encode($data));
    }

}