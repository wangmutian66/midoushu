<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\mobile\controller;
use app\common\logic\CartLogic;
use app\common\logic\MessageLogic;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\logic\CouponLogic;
use think\Page;
use think\Request;
use think\Verify;
use think\db;



class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished','setMobilestore',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express' , 'bind_guide', 'bind_account','reg1'
        );
        $is_bind_account = tpCache('basic.is_bind_account');
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            if($recommend_id= I('get.recommend_id/d')){
                $where['id']    =   ['eq',$recommend_id];
            }
            if($invite_code = I('get.invite_code/s')){
                $where['invite_code'] = ['eq',$invite_code];
            }
            if($recommend_id || $invite_code){
                $staff_info =   db('staff')->where($where)->cache(true)->find();
                session('recommend_staff',$staff_info);
            }
            #  dump($staff_info);die;
            // $this->assign('staff_info',db('staff')->where($where)->cache(true)->find());
            if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && $is_bind_account) {
                header("location:" . U('/Mobile/User/bind_guide'));  //微信浏览器, 调到绑定账号引导页面
            }/*elseif(strstr($_SERVER['HTTP_USER_AGENT'],'AlipayClient') && $is_bind_account){
                //header("location:" . U('/Mobile/User/bind_guide'));  //支付宝览器, 调到绑定账号引导页面
            }*/else{
                session('referurl',$_SERVER['REQUEST_URI']);
                header("location:" . U('/Mobile/User/login'));
            }
            exit;
        }

        $order_status_coment = array(
            'WAITPAY'      => '待付款', //订单查询状态 待支付
            'WAITSEND'     => '待发货', //订单查询状态 待发货
            'WAITRECEIVE'  => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);
    }

    /*
     * 用户中心首页
     */
    public function index()
    {
        $user_id = $this->user_id;
        $logic = new UsersLogic();
        $user  = $logic->get_info($user_id); //当前登录用户信息
        $comment_count = M('comment')->where("user_id", $user_id)->count();   // 我的评论数
        $level_name    = M('user_level')->where("level_id", $this->user['level'])->getField('level_name'); // 等级名称
        //获取用户信息的数量
        $messageLogic       = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();
        $this->assign('user_message_count', $user_message_count);
        $this->assign('level_name', $level_name);
        $this->assign('comment_count', $comment_count);
        $this->assign('user',$user['result']);
        return $this->fetch();
    }


    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('uname','',time()-3600,'/');
        setcookie('cn','',time()-3600,'/');
        setcookie('cnred','',time()-3600,'/');
        setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }

    public function tklogout()
    {
        session_unset();
        session_destroy();
        setcookie('uname','',time()-3600,'/');
        setcookie('cn','',time()-3600,'/');
        setcookie('cnred','',time()-3600,'/');
        setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        //$this->success("退出成功",U('Mobile/Index/index'));
    #    header("Location:" . U('Mobile/Index/index'));
        exit();
    }
    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    public function account_list()
    {
    	$type = I('type','all');
        $p = I('p/d',1);
        $page_last = 7;
        if($type == 'all'){
            $count = M('account_log')->where("user_money!=0 and user_id=" . $this->user_id)->count();
            
            $account_log = M('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("user_money!=0 and user_id=" . $this->user_id)
                ->order('log_id desc')->page("{$p},{$page_last}")->select();
        }else{
            $where = $type=='plus' ? " and user_money>0 " : " and user_money<0 ";
            $count = M('account_log')->where("user_id=" . $this->user_id.$where)->count();
            
            $account_log = Db::name('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("user_id=" . $this->user_id.$where)
                ->order('log_id desc')->page("{$p},{$page_last}")->select();
        }
 
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show(); 

        $this->assign('type', $type);
        $this->assign('account_log', $account_log);
        $this->assign('page', $show);
    	return $this->fetch();
    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
        $this->assign('detail',$detail);
        return $this->fetch();
    }
    
    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        foreach($data['result'] as $k =>$v){
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if($user_type==1){ //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id'=>$v['cid']])->getField('goods_id');
            }
            if($user_type==2){ //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id'=>$v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
            // header("Location: " . U('Mobile/User/index'));
        }
        // dump($_SERVER);die;
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        if(session('referurl')){
            $referurl = urlencode(session('referurl'));
        }
        $this->assign('referurl', $referurl);
        return $this->fetch();
    }

    /**
     * 登录
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        //验证码验证
        if (isset($_POST['verify_code'])) {
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }
        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = urldecode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            setcookie('cnred', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

        if($this->user_id > 0) {
            $this->redirect(U('Mobile/User/index'));
        }
        $reg_sms_enable  = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname  = I('post.nickname', '');
            $username  = I('post.username', '');
            $password  = I('post.password', '');
            $password2 = I('post.password2', '');
            $paypwd  = I('post.paypwd', '');
            $paypwd2 = I('post.paypwd2', '');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);
            
            $session_id = session_id();

            //是否开启注册验证码机制
            if(check_mobile($username)){
                if($reg_sms_enable){
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            //是否开启注册邮箱验证码机制
            if(check_email($username)){
                if($reg_smtp_enable){
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            
            /*没什么用，原分销用*/
            $invite = array();
            
            
            if($is_bind_account && session("third_oauth")){ //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic = $thirdUser['head_pic'];
                $nickname = $thirdUser['nickname'];
                $data = $logic->reg($username, $password, $password2,$paypwd,$paypwd2,0, $invite ,$nickname , $head_pic);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new UsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            }else{
                $data = $logic->reg($username, $password, $password2,$paypwd,$paypwd2,0,$invite);
            }
             
            
            if ($data['status'] != 1) $this->ajaxReturn($data);
            
            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];
            
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }

        $this->assign('regis_sms_enable',$reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable',$reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    public function bind_guide(){
        // dump($_SESSION);die;
        #  dump(session('recommend_staff'));
       /* $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        if(session('referurl')){
            $referurl = urlencode(session('referurl'));
        }
        $this->assign('referurl', $referurl);*/

        $data = session('third_oauth');
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);
        return $this->fetch();
    }
    
    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        if(IS_POST){  // 提交绑定信息
            // 提交信息
            $data = I('post.');
            $userLogic = new UsersLogic(); 
            $bind_user['mobile']   = $data['mobile']; // 账号
            $bind_user['password'] = encrypt($data['password']);  // 密码
            $res = $userLogic->oauth_bind_new($bind_user);        // 绑定新用户

            if ($res['status'] == 1) {

                // 如果找到他老爸还要找他爷爷他祖父等
                $map['first_leader'] = 0;

                $ruser = $res['result'];
                M('Users')->where('user_id' , $ruser['user_id'])->save($map);
                
                $res['result']['nickname'] = empty($res['result']['nickname']) ? $res['result']['mobile'] : $res['result']['nickname'];
                setcookie('user_id', $res['result']['user_id'], null, '/');
                setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
                setcookie('uname', urlencode($res['result']['nickname']), null, '/');
                setcookie('head_pic', urlencode($res['result']['head_pic']), null, '/');
                setcookie('cn', 0, time() - 3600, '/');
                //获取公众号openid,并保持到session的user中
                $oauth_users = M('OauthUsers')->where(['user_id'=>$res['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
                $oauth_users && $res['result']['open_id'] = $oauth_users['open_id'];
                session('user', $res['result']);
                $cartLogic = new CartLogic();
                $cartLogic->setUserId($res['result']['user_id']);
                $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
                $userlogic = new OrderLogic();    //登录后将超时未支付订单给取消掉
                $userlogic->setUserId($res['result']['user_id']);
                $userlogic->abolishOrder();
                $referurl = session('referurl');
                if($referurl){
                    $referurl = urldecode($referurl);
                    return $this->success("绑定成功",$referurl);
                }else{
                    return $this->success("绑定成功", U('Mobile/User/index'));
                }
                
            }else{
                return $this->error("绑定失败,失败原因:".$res['msg']);
            }
        }else{
            return $this->fetch();
        }
    }
    public function express()
    {
        $order_id = I('get.order_id/d', 195);
        $order_goods = M('order_goods')->where("order_id", $order_id)->select();
        $delivery = M('delivery_doc')->where("order_id", $order_id)->find();
        $this->assign('order_goods', $order_goods);
        $this->assign('delivery', $delivery);
        return $this->fetch();
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        if (IS_POST) {

            $source    = input('source');
            $post_data = input('post.');
            $logic     = new UsersLogic();
            $data      = $logic->add_address($this->user_id, 0, $post_data);
            $goods_id  = input('goods_id/d');
            $item_id   = input('item_id/d');
            $goods_num = input('goods_num/d');
            $order_id  = input('order_id/d');
            $action    = input('action');
            $is_allreturn = input('is_allreturn');

            if($is_allreturn == 1){
                if ($source == 'cart2') {
                    $data['url']=U('/Mobile/ReturnCart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                    $this->ajaxReturn($data);
                }
            } else {
      
                if ($data['status'] != 1){
                //    $this->error($data['msg']);
                    $this->ajaxReturn($data);
                } elseif ($source == 'cart2') {
                    $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                    $this->ajaxReturn($data);
                } elseif ($_POST['source'] == 'integral') {
                    $data['url']=U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                    $this->ajaxReturn($data);
                } elseif($source == 'pre_sell_cart'){
                    $data['url']=U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                    $this->ajaxReturn($data);
                } elseif($_POST['source'] == 'team'){
                    $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                    $this->ajaxReturn($data);
                }else{
                    $data['url']= U('/Mobile/User/address_list');      
                    $this->ajaxReturn($data);
                    //$this->success($data['msg'], U('/Mobile/User/address_list'));
                } 
            }
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        //return $this->fetch('edit_address');
        return $this->fetch();
    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $source       = input('source');
            $goods_id     = input('goods_id/d');
            $item_id      = input('item_id/d');
            $goods_num    = input('goods_num/d');
            $action       = input('action');
            $order_id     = input('order_id/d');
            $post_data    = input('post.');
            $logic        = new UsersLogic();
            $data         = $logic->add_address($this->user_id, $id, $post_data);

            $is_allreturn = input('is_allreturn');

            if($is_allreturn == 1){
                if ($source == 'cart2') {
                    $data['url']=U('/Mobile/ReturnCart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                    $this->ajaxReturn($data);
                }
            } else {

                if ($post_data['source'] == 'cart2') {
                    $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                    $this->ajaxReturn($data);
                } elseif ($_POST['source'] == 'integral') {
                    $data['url'] = U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                    $this->ajaxReturn($data);
                } elseif($source == 'pre_sell_cart'){
                    $data['url'] = U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                    $this->ajaxReturn($data);
                } elseif($_POST['source'] == 'team'){
                    $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                    $this->ajaxReturn($data);
                } else{
                    $data['url']= U('/Mobile/User/address_list');
                    $this->ajaxReturn($data);
                }
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }


    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
        	if ($_FILES['head_pic']['tmp_name']) {
        		$file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
        		$validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
        		$dir = 'public/upload/head_pic/';
        		if (!($_exists = file_exists($dir))){
        			$isMk = mkdir($dir);
        		}
        		$parentDir = date('Ymd');
        		$info = $file->validate($validate)->move($dir, true);
        		if($info){
        			$post['head_pic'] = '/'.$dir.$parentDir.'/'.$info->getFilename();
        		}else{
        			$this->error($file->getError());//上传错误提示错误信息
        		}
        	}
            I('post.nickname') ? $post['nickname']   = I('post.nickname') : false; //昵称
            I('post.real_name')? $post['real_name']  = I('post.real_name') : false; //真实姓名
            I('post.qq')       ? $post['qq']         = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic']   = I('post.head_pic') : false; //头像地址
            I('post.sex')      ? $post['sex']        = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday']   = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province']   = I('post.province') : false;  //省份
            I('post.city')     ? $post['city']       = I('post.city') : false;  // 城市
            I('post.district') ? $post['district']   = I('post.district') : false;  //地区
            I('post.email')    ? $post['email']      = I('post.email') : false; //邮箱
            I('post.mobile')   ? $post['mobile']     = I('post.mobile') : false; //手机

            $email  = I('post.email');
            $mobile = I('post.mobile');
            $code   = I('post.mobile_code', '');
            $scene  = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            setcookie('uname',urlencode($post['nickname']),null,'/');
            $this->success("操作成功",url('User/userinfo'));
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
        }
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile(){
        $userLogic = new UsersLogic();
        if (IS_POST) {
            $mobile = input('mobile');
            $mobile_code = input('mobile_code');
            $scene = input('post.scene', 6);
            $validate = I('validate',0);
            $status = I('status',0);
            $c = Db::name('users')->where(['mobile' => mobile, 'user_id' => ['<>', $this->user_id]])->count();
            $c && $this->error('手机已被使用');
            if($this->user['mobile']){
                if (!$mobile_code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($mobile_code, $mobile, 'phone', $this->session_id, $scene);
                if($check_code['status'] !=1){
                    $this->error($check_code['msg']);
                }
            }
            if($validate == 1 && $status == 0){
                $new_password = trim(I('new_password'));
                $confirm_password = trim(I('confirm_password'));
                if($new_password || $confirm_password){
                    if(strlen($new_password) < 6)
                        $this->error('密码不能低于6位字符');
                    if($new_password != $confirm_password)
                        $this->error('两次密码输入不一致');
                    $save_data['paypwd']    =   encrypt($new_password);
                }
                $save_data['mobile']    =   $mobile;
                $res = Db::name('users')->where(['user_id' => $this->user_id])->save($save_data);
                if($res){
                    session('user.mobile',$mobile);
                    $this->success('修改成功',U('User/userinfo'));
                }
                $this->error('修改失败');
            }
        }
        $this->assign('status',$status);
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobilestore(){
        $userLogic = new UsersLogic();
        if (IS_POST) {
            $mobile = input('mobile');
            $mobile_code = input('mobile_code');
            $order_sn = input('order_sn');
            $store_id = input('store_id/d');
            $scene = input('post.scene', 6);

            $c = Db::name('users')->where(['mobile' => mobile, 'user_id' => ['<>', $this->user_id]])->count();
            $c && $this->error('手机已被使用');
            if($this->user['paypwd']){
                if (!$mobile_code){
                    $this->error('请输入验证码');
                }
                $check_code = $userLogic->check_validate_code($mobile_code, $mobile, 'phone', $this->session_id, $scene);
                if($check_code['status'] !=1){
                    $this->error($check_code['msg']);
                }
            }
            if($this->user['mobile'] != $mobile){
                Db::name('users')->where(['user_id' => $this->user_id])->update(['mobile'=>$mobile]);
            }
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            if ($data['status']==1) {
                session('user.mobile',$mobile);
                $this->success('修改成功',U('/Mobilered/Cart/cart4',array('order_sn' =>$order_sn , 'store_id'=>$store_id)));
            }else{
                $this->error($data['msg']);
            }
        }
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    //申请成为供货商
    public function apply_suppliers(){
        $suppliers = get_suppliers_info_uid($this->user_id);
        if(!$suppliers){
            return $this->fetch();
        }else{
            $this->assign('is_check', $suppliers['is_check']);
            $this->assign('supplier_remark', $suppliers['supplier_remark']);
            $this->assign('status', $suppliers['status']);
            $this->assign('suppliers_password', $suppliers['suppliers_password']);
            $this->assign('suppliers_type', $suppliers['suppliers_type']);
            $this->assign('add_time', $suppliers['add_time']);
            return $this->fetch('suppliers');
        }
    }

    // 供货商 密码
    public function suppliers_password(){
        $user_id  = $this->user_id;
        $supplier = D('suppliers')->field('suppliers_name')->where(array('user_id'=>$user_id))->find();
        if(IS_POST){
            //  供货商信息编辑
            $suppliers_password  = I('post.suppliers_password');
            $suppliers_password2 = I('post.suppliers_password2');
            if($suppliers_password != '' && $suppliers_password != $suppliers_password2){
                exit($this->error('两次输入密码不同'));
            }
            if($suppliers_password == '' && $suppliers_password2 == ''){
                unset($_POST['suppliers_password']);
            }else{
                $_POST['suppliers_password'] = encrypt($_POST['suppliers_password']);
            } 

            $row = M('suppliers')->where(array('user_id'=>$user_id))->save($_POST);
            if($row){
                $this->success('设置成功',U('User/apply_suppliers'));exit;
            }
            $this->error('设置失败，请联系管理员'); 
        }

        $this->assign('supplier',$supplier);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) {      //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id/d');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if(!$this->verifyHandle('message')){
                $this->error('验证码错误', U('User/message_list'));
            };

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $showpage = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $showpage);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }

    
    public function points_list()
    {
    	$type = I('type','all');
    	$usersLogic = new UsersLogic;
    	$result = $usersLogic->points($this->user_id, $type);
    	$this->assign('type', $type);
    	$showpage = $result['page']->show();
    	$this->assign('account_log', $result['account_log']);
    	$this->assign('page', $showpage);
    	if ($_GET['is_ajax']) {
    		 return $this->fetch('ajax_points');
    	}
    	return $this->fetch();
    }
    
    
    /*
     * 密码修改
     */
    public function password()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->get_info($this->user_id);
            $user = $data['result'];
            if ($user['mobile'] == '' && $user['email'] == '')
                $this->ajaxReturn(['status'=>-1,'msg'=>'请先绑定手机或邮箱','url'=>U('/Mobile/User/index')]);
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1)
                $this->ajaxReturn(['status'=>-1,'msg'=>$data['msg']]);
            $this->ajaxReturn(['status'=>1,'msg'=>$data['msg'],'url'=>U('/Mobile/User/index')]);
            exit;
        }
        return $this->fetch();
    }

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                if(!$this->verifyHandle('forget')){
                    $this->error("验证码错误");
                };
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field));
                    header("Location: " . U('User/find_pwd'));
                    exit;
                } else {
                    $this->error("用户名不存在，请检查");
                }
            }
        }
        return $this->fetch();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        if (empty($check)) {
            header("Location:" . U('User/forget_pwd'));
        } elseif ($check['is_check'] == 0) {
            $this->error('验证码还未验证通过', U('User/forget_pwd'));
        }
        if (IS_POST) {
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                $this->error('两次密码不一致', U('User/forget_pwd'));
            }
            if ($check['is_check'] == 1) {
                $user = M('users')->where("mobile", $check['sender'])->whereOr('email', $check['sender'])->find();
                M('users')->where("user_id", $user['user_id'])->save(array('password' => encrypt($password)));
                session('validate_code', null);
                return $this->fetch('reset_pwd_sucess');
                exit;
            } else {
                $this->error('验证码还未验证通过', U('User/forget_pwd'));
            }
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }
 
    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            return false;
        }
        return true;
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'codeSet'  => '0123456789',
            'fontSize' => 30,
            'length' => 4,
            'imageH' =>  60,
            'imageW' =>  300,
            'fontttf' => '5.ttf',
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
		exit();
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    public function recharge()
    {
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }


    // 充值     
    public  function recharge_pay(){

        $Ad =  M('rechargecofig');
        $list = $Ad->order('orderby')->where('is_show = 1')->select();
        $this->assign('list',$list);// 赋值数据集

        if(IS_POST){
            $user = session('user');
            $data['user_id']  = $this->user_id;
            $data['nickname'] = $user['nickname'];
            $data['account']  = I('account');
            $data['order_sn'] = 'recharge'.get_rand_str(10,0,1);
            $data['ctime']    = time();
            $order_id = M('recharge')->add($data);
            if($order_id){
                $url = U('Payment/getPay',array('pay_radio'=>$_REQUEST['pay_radio'],'order_id'=>$order_id));
                $this->redirect($url);
            }else{
                $this->error('提交失败,参数有误!');
            }
        }

        /*$paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,2)")->select();
        $paymentList = convert_arr_key($paymentList, 'code');       
        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('bankCodeList',$bankCodeList);*/
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and scene = 1")->select();                //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');
        $this->assign('paymentList', $paymentList);
        return $this->fetch();
    }
    
    public function recharge_list(){
      
    //	$usersLogic = new UsersLogic;
        $recharge_log_where['user_id'] = ['eq',$this->user_id];
        $p = I('p/d',1);
        $page_last = 7;
        $count = M('recharge')->where($recharge_log_where)->count();
        $withdrawals_log = M('recharge')->where($recharge_log_where)
            ->order('order_id desc')
            ->page("{$p},{$page_last}")
            ->select();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $page = $Page->show(); 
        $this->assign('lists',$withdrawals_log);
        $this->assign('page', $page);
    	return $this->fetch();
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {
        C('TOKEN_ON', true);

        $user_id  = $this->user_id;
        $userinfo = M('users')->where('user_id ='.$user_id)->find();
        
        if (IS_POST) {
            if(!$this->verifyHandle('withdrawals')){
                $this->ajaxReturn(['status'=>0,'msg'=>'验证码错误']);
            };
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $distribut_min  = tpCache('basic.min');            // 最少提现额度
            $service_fee    = tpCache('basic.service_fee');    // 会员提现手续费
            $data['taxfee'] = $data['money']*$service_fee/100; // 手续费
            $total = $data['money']+$data['taxfee'];           // 总
            
            if ($data['money'] < $distribut_min) {
                $this->ajaxReturn(['status'=>0,'msg'=>'每次最少提现额度' . $distribut_min]);
                exit;
            }

            if($total > $this->user['user_money'])
            {
                //$this->ajaxReturn(['status'=>0,'msg'=>"你最多可提现{$this->user['user_money']}账户余额."]);
                $this->ajaxReturn(['status'=>0,'msg'=>"抱歉，您的余额不足"]);
                exit;
            }
          
            if(encrypt($data['paypwd']) != $this->user['paypwd']){
                $this->ajaxReturn(['status'=>0,'msg'=>'支付密码错误']);
                exit;
            }
          
            if(M('withdrawals')->add($data)){
                accountLog($this->user_id, (-1 * $total), 0, 0, '会员提现申请');
                $up_data['frozen_money'] = $userinfo['frozen_money']+$total;
                M('users')->where('user_id ='.$this->user_id)->update($up_data);
                $this->ajaxReturn(['status'=>1,'msg'=>"已提交申请",'url'=>U('User/withdrawals_list',['type'=>2])]);
                exit;
            }else{
                $this->ajaxReturn(['status'=>1,'msg'=>'提交失败,联系客服!']);
                exit;
            }
        }
        $this->assign('user_money', $this->user['user_money']);    //用户余额
        $this->assign('service_fee',tpCache('basic.service_fee'));
        $this->assign('distribut_min',tpCache('basic.min'));
        $withdrawals = M('withdrawals')->where('user_id ='.$user_id)->select();
         if ($withdrawals) {
            $withdstr="1";
        }else{
            $withdstr="0";
        }
		$res = array(); //想要的结果
		foreach ($withdrawals as $k => $v) {
		   $res[$v['bank_name']][] = $v;
		}
        $this->assign('withdstr',$withdstr);
        $this->assign('withdrawals',$res);
        return $this->fetch();
    }

    
     public  function withdrawalsajax(){
    	$user_id  = $this->user_id;
        $b = I('post.b');
        $withdrawals1 = M('withdrawals')->where('user_id ='.$user_id)->select();
        if($b=="如：工商银行/支付宝"){
        	$this->assign('withdrawalss',"请选择账号"); 
        }else{
        	$res = array();  
			foreach ($withdrawals1 as $k => $v) {
			  $res[$v['bank_name']][] = $v;
			}
			function array_unset_tt($arr,$key){                 //建立一个目标数组           
				$res = array();                    
				foreach ($arr as $value) {                          //查看有没有重复项     
					if(isset($res[$value[$key]])){                  
						unset($value[$key]);  //有：销毁             
					}else{                      
						$res[$value[$key]] = $value;           
				    }          
				}       
				return $res;    
			} 
			$ress = array_unset_tt($res[$b],$res[$b]['bank_card']);
	    	$this->assign('withdrawalss',$ress); 
        }
		
        return $this->fetch();
    }
    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
        }
        return $this->fetch();
    }

    /**
     * 我的关注
     * @author lxl
     * @time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        return $this->fetch();
    }

    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        $type = I('type');
        $user_logic = new UsersLogic();
        $message_model = new MessageLogic();
        if ($type === '0') {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
        } else if ($type == 1) {
            //活动消息：后续开发
            $user_sys_message = array();
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $this->assign('messages', $user_sys_message);
        return $this->fetch('ajax_message_notice');

    }

    /**
     * ajax用户消息通知请求
     */
    public function set_message_notice()
    {
        $type = I('type');
        $msg_id = I('msg_id');
        $user_logic = new UsersLogic();
        $res =$user_logic->setMessageForRead($type,$msg_id);
        $this->ajaxReturn($res);
    }


    public function set_message_notice_new(){
        $user_info = session('user');
        $status = I('status',1);
        $rec_id = I('rec_id');

        if (!empty($user_info['user_id'])) {
            $data['status'] = $status;
            //$data['status'] = 0;
            $set_where = [];
            //$set_where['rec_id'] = ['gt',$rec_id];
            $set_where['user_id'] = $user_info['user_id'];
            if($rec_id){
                $set_where['rec_id'] = $rec_id;
            }

            $updat_meg_res = Db::name('user_message')->where($set_where)->update($data);

            if ($updat_meg_res){
                $res =  ['status'=>1,'msg'=>'操作成功'];
                $this->ajaxReturn($res);
            }
        }
        $res =  ['status'=>-1,'msg'=>'操作失败'];

        $this->ajaxReturn($res);

    }




    /**
     * 设置消息通知
     */
    public function set_notice(){
        //暂无数据
        return $this->fetch();
    }

    /**
     * 浏览记录
     */
    public function visit_log()
    {
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $Page = new Page($count, 20);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        $this->assign('visit_list', $visit_list);
        if (I('get.is_ajax', 0)) {
            return $this->fetch('ajax_visit_log');
        }
        return $this->fetch();
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id','IN', $visit_ids)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if(strrchr($_SERVER['HTTP_REFERER'],'/') =='/cart2.html'){  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl',U('Mobile/Cart/cart2'));
        }
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号',U('User/userinfo',['action'=>'mobile']));
        $step = I('step', 1);
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('mobile/User/paypwd'));
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            if(strlen($new_password) != 6 ){
                $this->ajaxReturn(['status'=>-1,'msg'=>'支付密码必须6位！','result'=>'']);
            }
            $confirm_password = trim(I('confirm_password'));
            /*$oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
            }*/
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    

     
     /**
     * 未设置支付密码时提示绑定的方法
     * @return mixed
     */
    public function paypwded()
    {
        //检查是否第三方登录用户
        $order_sn = I('order_sn');
        $user = M('users')->where('user_id', $this->user_id)->find();
        if(strrchr($_SERVER['HTTP_REFERER'],'/') =='/cart2.html'){  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl',U('Mobile/Cart/cart2'));
        }
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号',U('User/userinfo',['action'=>'mobile']));
        $step = I('step', 1);
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('mobile/User/paypwded'));
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            if(strlen($new_password) != 6 ){
                $this->ajaxReturn(['status'=>-1,'msg'=>'支付密码必须6位！','result'=>'']);
            }
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $data['msg']="设置成功";
            $data['url']="/index.php/mobile/Cart/cart4/order_sn/".$order_sn.".html";
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        $this->assign('order_sn', $order_sn);
        return $this->fetch();
    }

    

     /**
     * 未设置支付密码时提示绑定的方法
     * @return mixed
     */
    public function paypwdedred()
    {
        //检查是否第三方登录用户
        $order_sn = I('order_sn');
        $store_id = I('store_id','');

        $user = M('users')->where('user_id', $this->user_id)->find();
        if(strrchr($_SERVER['HTTP_REFERER'],'/') =='/cart2.html'){  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl',U('Mobilered/Cart/cart2'));
        }
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号',U('User/userinfo',['action'=>'mobile']));
        $step = I('step', 1);
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('mobile/User/paypwdedred'));
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            if(strlen($new_password) != 6 ){
                $this->ajaxReturn(['status'=>-1,'msg'=>'支付密码必须6位！','result'=>'']);
            }
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $data['msg']="设置成功";
            $data['url']="/index.php/mobilered/Cart/cart4/order_sn/".$order_sn."/store_id/".$store_id.".html";
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        $this->assign('order_sn', $order_sn);
        return $this->fetch();
    }

    /**
     *  点赞
     * @author lxl
     * @time  17-4-20
     * 拷多商家Order控制器
     */
    public function ajaxZan()
    {
        $comment_id = I('post.comment_id/d');
        $user_id = $this->user_id;
        $comment_info = M('comment')->where(array('comment_id' => $comment_id))->find();  //获取点赞用户ID
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {  //判断用户有没点赞过
            $result['success'] = 0;
        } else {
            array_push($comment_user_id_array, $user_id);  //加入用户ID
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;  //点赞数量加1
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment')->where(array('comment_id' => $comment_id))->save($comment_data);
            $result['success'] = 1;
        }
        exit(json_encode($result));
    }


    /**
     * 会员签到积分奖励
     * 2017/9/28
     */
    public function sign() {
        $user_id = $this->user_id;
        $config = tpCache('sign');
        if (IS_AJAX) {
            $date = I('str'); //20170929
            //是否正确请求
            (date("Y-n-j", time()) != $date) && $this->ajaxReturn(['status' => -1, 'msg' => '请求错误！', 'result' => date("Y-n-j", time())]);

            $integral = $config['sign_integral'];
            $msg = "签到赠送" . $integral . "积分";
            //签到开关
            if ($config['sign_on_off'] > 0) {
                $map['lastsign'] = $date;
                $map['user_id'] = $user_id;
                $check = DB::name('user_sign')->where($map)->find();
                $check && $this->ajaxReturn(['status' => -1, 'msg' => '您今天已经签过啦！', 'result' => '']);
                if (!DB::name('user_sign')->where(['user_id' => $user_id])->find()) {
                    //第一次签到
                    $data = [];
                    $data['user_id'] = $user_id;
                    $data['signtotal'] = 1;
                    $data['lastsign'] = $date;
                    $data['cumtrapz'] = $config['sign_integral'];
                    $data['signtime'] = "$date";
                    $data['signcount'] = 1;
                    $data['thismonth'] = $config['sign_integral'];
                    if (M('user_sign')->add($data)) {
                        $status = ['status' => 1, 'msg' => '签到成功！', 'result' => $config['sign_integral']];
                    } else {
                        $status = ['status' => -1, 'msg' => '签到失败!', 'result' => ''];
                    }
                    $this->ajaxReturn($status);
                } else {
                    $update_data = array(
                        'signtotal' => ['exp', 'signtotal+' . 1], //累计签到天数            
                        'lastsign' => ['exp', "'$date'"], //最后签到时间    
                        'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_integral']], //累计签到获取积分
                        'signtime' => ['exp', "CONCAT_WS(',',signtime ,'$date')"], //历史签到记录
                        'signcount' => ['exp', 'signcount+' . 1], //连续签到天数
                        'thismonth' => ['exp', 'thismonth+' . $config['sign_integral']], //本月累计积分
                    );

                    $daya = Db::name('user_sign')->where('user_id', $user_id)->value('lastsign');    //上次签到时间
                    $dayb = date("Y-n-j", strtotime($date) - 86400);                                   //今天签到时间
                    //不是连续签
                    if ($daya != $dayb) {
                        $update_data['signcount'] = ['exp', 1];                                       //连续签到天数
                    }
                    $mb = date("m", strtotime($date));                                               //获取本次签到月份
                    //不是本月签到
                    if (intval($mb) != intval(date("m", strtotime($daya)))) {
                        $update_data['signcount'] = ['exp', 1];                                      //连续签到天数
                        $update_data['signtime'] = ['exp', "'$date'"];                                  //历史签到记录;
                        $update_data['thismonth'] = ['exp', $config['sign_integral']];              //本月累计积分
                    }

                    $update = Db::name('user_sign')->where(['user_id' => $user_id])->update($update_data);

                    (!$update) && $this->ajaxReturn(['status' => -1, 'msg' => '网络异常！', 'result' => '']);

                    $signcount = Db::name('user_sign')->where('user_id', $user_id)->value('signcount');
                    $integral = $config['sign_integral'];
                    //满足额外奖励                     
                    if (( $signcount >= $config['sign_signcount']) && ($config['sign_on_off'] > 0)) {
                        Db::name('user_sign')->where(['user_id' => $user_id])->update([
                            'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_award']],
                            'thismonth' => ['exp', 'thismonth+' . $config['sign_award']]
                        ]);
                        $integral = $config['sign_integral'] + $config['sign_award'];
                        $msg = "签到赠送" . $config['sign_integral'] . "积分，连续签到奖励" . $config['sign_award'] . "积分，共" . $integral . "积分";
                    }
                }
                if ($config['sign_integral'] > 0 && $config['sign_on_off'] > 0) {
                    accountLog($user_id, 0, $integral, $msg);
                    $status = ['status' => 1, 'msg' => '签到成功！', 'result' => $integral];
                } else {
                    $status = ['status' => -1, 'msg' => '签到失败!', 'result' => ''];
                }
                $this->ajaxReturn($status);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => '该功能未开启！', 'result' => '']);
            }
        }
        $map = [];
        $map['us.user_id'] = $user_id;
        $field = [
            'u.user_id as user_id',
            'u.nickname',
            'u.mobile',
            'us.*',
        ];
        $join = [
            ['users u', 'u.user_id=us.user_id', 'left']
        ];
        $info = Db::name('user_sign')->alias('us')->field($field)
                        ->join($join)->where($map)->find();

        ($info['lastsign'] != date("Y-n-j", time())) && $tab = "1";

        $signtime = explode(",", $info['signtime']);
        $str = "";
        //是否标识历史签到
        if (date("m", strtotime($info['lastsign'])) == date("m", time())) {
            foreach ($signtime as $val) {
                $str .= date("j", strtotime($val)) . ',';
            }
            $this->assign('info', $info);
            $this->assign('str', $str);
        }
      
        $this->assign('cumtrapz', $info['cumtrapz']);
        $this->assign("jifen", ($config['sign_signcount'] * $config['sign_integral']) + $config['sign_award']);
        $this->assign('config', $config);
        $this->assign('tab', $tab);

        return $this->fetch();
    }

	// 扫码选择
	function sweepCode(){
		if($recommend_id= I('get.recommend_id/d')){
			$where['id']	=	['eq',$recommend_id];
		}
		if($invite_code = I('get.invite_code/s')){
			$where['invite_code'] = ['eq',$invite_code];
		}
        //s7SxLod35Q
        $staff = db('staff staff')->where($where)
                    ->field('staff.*,store.cname store_name')
                    ->join('company store','store.cid = staff.store_id')
                    ->find();
		//判断是否是推广员,如果是推广员则跳转页面
        if($staff['type']=='1'){
            $this->redirect(U('User/tuiguangyuan',array('staff_id'=>$recommend_id)));
        }

        if(!$staff){
            $this->error('该二维码已失效',U('/Mobile'));
        }

        $this->assign('store_id',$staff["store_id"]);
        $this->assign('actions','dopays');
        $this->assign('staff_info',$staff);
        return $this->fetch();
	}

    function dosweepCode(){

        $vs = I('vs/d',1);
        $staff_id = I('staff_id/d');

        switch ($vs) {
            case 1:
                $this->assign('staff_info',db('staff')->cache("staff_{$staff_id}")->find($staff_id));
                $this->assign('actions','dopays');
                return $this->fetch('paid');
                break;
            case 2:
                $this->assign('staff_info',db('staff')->cache("staff_{$staff_id}")->find($staff_id));
                $this->assign('actions','dopaid');
                return $this->fetch('paid');
                break;
            case 3:
            case 4:
                if($vs ==  4){
                    $this->checkApplyStatus($vs,$staff_id);
                }
                $storeId = db('staff')->cache("staff_{$staff_id}")->find($staff_id);
                $storeId = $storeId['store_id'];
                $storeName = M('company')->field('cname')->where("cid={$storeId}")->find();
                $this->assign('storeName',$storeName['cname']);
                $this->assign('staff_id',$staff_id);
                $this->assign('vs',$vs);
                $this->assign('vstitle',$vs == 4 ? '申请成为创业推广员' : '申请成为实体店工作人员');
                return $this->fetch('apply_promoters');
                break;
        }

    }

    function checkApplyStatus($vs,$staff_id){
        $res = db('apply_promoters')->field('status,mobile')->where("user_id=".$this->user_id." and staff_id=".$staff_id)->order('create_time desc')->find();
        if($res){
            if($vs == 4){
                $staff = db('staff')->where(['phone'=>$res['mobile'],'parent_id'=>$staff_id])->find();
                if($staff || $res['status'] != 3){
                    $this->redirect(U('Mobile/Activity/tgy_sale',['staff_id'=>$staff_id]));
                }
            }else{
                $this->redirect(U('Mobile/Index/index'));
            }

        }

    }

    function http_request_curl($url, $rawData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawData);
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER,
            array(
                'Content-Type: text'
            )
        );
        $data = curl_exec($ch);
        curl_close($ch);
        return ($data);


    }
//将XMl转化为数组
    function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring), true);
        return $val;
    }



    #支付成功页面
    function pay_status(){
        $paid_sn = I('paid_sn');
        $where['paid_sn']   =   ['eq',$paid_sn];
        $order = db('staff_mypays')->where($where)->find();
        $store_id = db('staff')->where(["id"=>$order['staff_id']])->value('store_id');
        if (($order['pay_code'] == 'weixin' || $order['pay_code'] == 'weixinH5') && $order['transaction_id'] != '' && $order['pay_status'] != '1') {
            $wxPay = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置

            $wxPayVal = unserialize($wxPay['config_value']);
            $data = array(
                'appid' => 'wx6ab3b8d3038ccd2a',
                'mch_id' => "1507332611",         //微信支付商户号
                'nonce_str' => md5(time().rand(1000,9999)),           //随机字符串
                'transaction_id' => $order['transaction_id'],        //用户登录时获取的code中含有的值
            );
            $key =  $wxPayVal["key"];

            ksort($data);
            $buff = "";
            foreach ($data as $k => $v) {
                if ($k != "sign" && $v != "" && !is_array($v)) {
                    $buff .= $k . "=" . $v . "&";
                }
            }
            $buff = trim($buff, "&");
            //签名步骤二：在string后加入KEY
            $string = $buff . "&key=" . $key;
            //签名步骤三：MD5加密

            $string = md5($string);
            //签名步骤四：所有字符转为大写
            $sign = strtoupper($string);
            $data['sign'] = $sign;

            ksort($data);
            //进行拼接数据
            $abc_xml = "<xml>";
            foreach ($data as $key => $val) {
                if (is_numeric($val)) {
                    $abc_xml .= "<" . $key . ">" . $val . "</" . $key . ">";
                } else {
                    $abc_xml .= "<" . $key . ">" . $val . "</" . $key . ">";
                }
            }
            $abc_xml .= "</xml>";
            $url = "https://api.mch.weixin.qq.com/pay/orderquery";
            $result = $this->http_request_curl($url, $abc_xml);
            $info = $this->xmlToArray($result);


            if ($info['return_code'] == 'SUCCESS' && $info['trade_state']=='SUCCESS') {
                $order['pay_status']='1';
            }


        }

        #线下在实体店扫码微信提醒实体店家
        if($order['pay_status'] == 1){
            $wechat = new \app\common\logic\WxLogic;
            $wechat->sendTemplateMsgOnStoreOrderPay($order);
        }

        #张洪凯 2018-10-25 推荐商品
        $elite_goods = M('elite_goods')->alias('e')
            ->field('g.goods_id,g.goods_name,g.market_price')
            ->where('e.type=2')
            ->where('g.is_check = 1')
            ->where('g.is_on_sale=1')
            ->join('goods_red g', 'g.goods_id=e.goods_id')
            ->order('e.addtime desc')
            ->limit(10)
            ->select();

        foreach ($elite_goods as $k => $val) {
            $rand_str = get_rand_str(6,1,1);
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $val['midou']       = $midouInfo['midou'];
            $val['midou_money'] = $midouInfo['midou_money'];
            $val['midou_index'] = $midouInfo['midou_index'];
            $val['rand_str']    = $rand_str;
            $elite_goods[$k] = $val;
        }

        $this->assign('elite_goods',$elite_goods);

        $this->assign('order',$order);
    //    $midou = bcdiv($order['money'],tpCache('shoppingred.midou_rate'),4);
        $midou = db('red_envelope')->where("order_sn = '{$paid_sn}'")->getField('money');
        $this->assign('midou',$midou);
        $this->assign('store_id',$store_id);

        return $this->fetch('pay_status');
    }

    #申请成为推广员
    function do_apply_promoters(){
        $staff_id = I('post.staff_id/d');
        $vs = I('post.vs/d');  //张洪凯 加2018-10-19
        $contact = I('post.contact/s');
        $mobile = I('post.mobile');
        $password = I('post.psw1');

        if($mobile && $contact && $staff_id && $password){

            #判断是否已在实体店申请注册
            $staff_info = db('staff')->cache("staff_{$staff_id}")->find($staff_id);
            $verification_where['store_id'] =   ['eq',$staff_info['store_id']];
            $verification_where['phone'] =   ['eq',$mobile];
            $verification = db('staff')->where($verification_where)->find();
            if($verification){
                $res['status'] = 2;
                $res['info']    =   '此手机号已在实体店注册';
                $this->ajaxReturn($res);
            }

            if($vs == 4){
                $apply_info = M('apply_promoters')->where("user_id=".$this->user_id." and status !=3")->order("create_time desc")->find();
                if(!empty($apply_info)){
                    $res['status'] = 6;
                    $res['staff_id'] = $apply_info['staff_id'];
                    $this->ajaxReturn($res);
                }
            }
            if($vs == 3){
                $apply_where['s.store_id'] = ['eq',$staff_info['store_id']];
                $apply_where['a.mobile'] =   ['eq',$mobile];
                $apply_info = M('apply_staff')
                    ->alias('a')
                    ->join('staff s','s.id=a.staff_id')
                    ->where($apply_where)
                    ->find();
                if($apply_info){
                    $res['status'] = 2;
                    $res['info']    =   '请不要重复申请！';
                    $this->ajaxReturn($res);
                }

            }


            $insert_data    =   ['mobile'=>$mobile,
                'contact'=>$contact,
                'user_id'=>$this->user_id,
                'staff_id'=>$staff_id,
                'psw'=>encrypt($password),
                'create_time'=>NOW_TIME];
            if($vs == 4){
                $r = db('apply_promoters')->insert($insert_data);
                if($r){
                    $rs['status'] = 1;
                }
            }else{


                $insert_data['status'] = 3;
                $insert_data['update_time'] = time();
                $r = db('apply_staff')->insert($insert_data);

                if($r){
                    #员工申请自动通过
                    $res = db('apply_staff a')
                        ->field("a.*,u.password,s.store_id,s.company_id")
                        ->where("status = 3 and u.user_id =".$this->user_id)
                        ->join("users u","u.user_id = a.user_id")
                        ->join("staff s","s.id = a.staff_id")
                        ->find();
                    $data['uname']  =   $res['contact'];
                    $data['tkpsw']  =   $res['psw'];
                    $data['phone']  =   $res['mobile'];
                    $data['create_time']  =   NOW_TIME;
                    $data['real_name']  =   $res['contact'];
                    $data['store_id']  =   $res['store_id'];
                    $data['company_id']  =   $res['company_id'];
                    $data['is_lock']  =   0;
                    $data['type']  =  0;
                    $data['parent_id']  =   $res['staff_id'];
                    $data['invite_code']    =  judge_invite_code(get_rand_str(10,0,1));
                    $staff_obj    = new \app\admin\logic\StaffLogic();
                    $rs           = $staff_obj->addStaff($data);
                    #员工申请自动通过
                }

            }

            if($rs['status'] == 1){
                $res['status']  =   1;
                $res['vs'] = $vs;    //张洪凯 加2018-10-19
            }else{
                $res['status']  =   0;
                $res['info']    =   '系统繁忙，请稍后再试！';
            }
        }else{
            $res['status']  =   0;
            $res['info']    =   '信息不完整，请填写重要信息';
        }
        $this->ajaxReturn($res);
    }

    function manage(){
        return $this->fetch();
    }
    // 二维码
    public function qrcode($id=0,$invite_code=null)
    {
        if($id == 0){
            return ;
        }
        #       http://192.168.1.118/mobile/User/sweepCode/recommend_id/1
    //    $domain = 'http://' . $_SERVER['HTTP_HOST'];
        $domain = "https://www.midoushu.com";
        $savePath = APP_PATH . "/../public/qrcode/{$id}/";
        $webPath = "/qrcode/{$id}/";
        if($invite_code){
            $qrData = $domain.'/mobile/User/sweepCode/invite_code/' . $invite_code;
        }else{
            $qrData = $domain.'/mobile/User/sweepCode/recommend_id/' . $id;
        }
        
        $qrLevel = 'H';
        $qrSize = '8';
        if($filename = createQRcode($savePath, $qrData, $qrLevel, $qrSize)){
            $pic = $webPath . $filename;
        }
        return $pic;
    }
    
    /*扫码付款支付*/
    function dopays(){
        $data['staff_id']   =   I('staff_id/d');
        $data['money']  =   I('post.money/f');
        if($data['money'] < 1){
            $msg['status']  =   0;
            $msg['info']    =   '支付金额不能小于1元';
            $this->ajaxReturn($msg);
        }
        if($data['staff_id'] && $data['money']){
            #冗余记录
            $tgy_id = db('users')->alias('users')
                    ->where('user_id',$this->user_id)
                    ->getField('staff_id');
            if(empty($tgy_id)){ 
                $tgy_id = 0;
            }
            $staff_info = db('staff')->cache("staff_{$data['staff_id']}")->where('id',$data['staff_id'])->find();
            
            $data['create_time']    =   NOW_TIME;
            $data['user_id']    =   $this->user_id;
            $data['pay_status'] =   0;
            $data['paid_sn']    =   get_paid_sn(2);
            $data['remark'] = I('remark/s','');
            $data['tgy_id'] =   $tgy_id;
            $data['store_id']   =   $staff_info['store_id'];
            $data['company_id']   =   $staff_info['company_id'];
            $r = db::name('staff_mypays')->insertGetId($data);
            if($r){
                $msg['status']  =   1;
                $msg['id']  =   $r;
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '系统繁忙，请稍后再试！';  
            }
        }else{
            $msg['status']  =   0;
            $msg['info']    =   '员工账号和金额不能为空';
        }
        $this->ajaxReturn($msg);
    }

    function mypays(){
        $id = I('get.id/d');
        $where['p.user_id']  =   ['eq',$this->user_id];
    // $where['pay_status']    =   ['eq',0];

        $r = M('staff_mypays')->alias('p')
                                ->field('p.*,u.mobile,store.cname store_name,store.is_payment store_alipay_status,store.siyao store_private_key,store.gongyao store_public_key,store.alipay_id store_app_id,company.cname company_name,company.is_payment company_alipay_status,company.siyao company_private_key,company.gongyao company_public_key,company.alipay_id company_app_id,staff.real_name staff_name,staff.store_id,staff.company_id')
                                ->join('users u',"u.user_id = p.user_id")
                                ->join('staff staff','staff.id = p.staff_id')
                                ->join('company store','store.cid = staff.store_id')
                                ->join('company company','company.cid = staff.company_id')
                                ->where($where)
                                ->find($id);
        //    print_r($r );die;
        if($r['pay_status']){
            $this->error('该订单已经支付',U('/Mobile/User/pay_paid'));
        }
        if($r){
            $transfer_log_data['is_alipay'] = 0;
            if($r['company_alipay_status'] == 1){
                $transfer_log_data['store_id']  =   $r['company_id'];
                $transfer_log_data['store_name']  =   $r['company_name'];
                $transfer_log_data['alipay_app_id']  =   $r['company_app_id'];
                $transfer_log_data['alipay_public']  =   $r['company_public_key'];
                $transfer_log_data['alipay_private']  =   $r['company_private_key'];
                $transfer_log_data['is_alipay'] = 1;
            }
            if($r['store_alipay_status'] == 1){
                $transfer_log_data['store_id']  =   $r['store_id'];
                $transfer_log_data['store_name']  =   $r['store_name'];
                $transfer_log_data['alipay_app_id']  =   $r['store_app_id'];
                $transfer_log_data['alipay_public']  =   $r['store_public_key'];
                $transfer_log_data['alipay_private']  =   $r['store_private_key'];
                $transfer_log_data['is_alipay'] = 1;
            }
            if($transfer_log_data['is_alipay'] == 1){
                if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $this->error('请使用外部浏览器支付该订单，公众号中无法支持该笔交易',U('/Mobile/User/sweepCode/',['recommend_id'=>$r['staff_id']]));
                    exit();
                }
                $transfer_log_data['staff_id']  =   $r['staff_id'];
                $transfer_log_data['staff_name']  = $r['staff_name'];
                $transfer_log_data['paid_sn']  =    $r['paid_sn'];
                $transfer_log_data['create_time']  =   NOW_TIME;
                $transfer_log_data['paid_id']  =   $id;
                if(!db('transfer_log')->where("paid_sn = '{$r['paid_sn']}'")->find()){
                    db('transfer_log')->insert($transfer_log_data);
                }
                $paymentList[0]['code'] =   'alipayMobile';
                $paymentList[0]['name'] =   '手机网站支付宝';
                $paymentList[0]['icon'] =   'logo.gif';
                $paymentList[0]['type']  =   'payment';
            }else{
                $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene = 1")->select();
                //微信浏览器
                if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
                }
            }
            $paymentList = convert_arr_key($paymentList, 'code');

            $this->assign('paymentList', $paymentList);
            $this->assign('item',$r);
            return $this->fetch();
        }else{
            $this->error('数据不存在或无权限支付');
        }
    }
	
	function dopaid(){
		$data['staff_id']	=	I('staff_id/d');
		$data['money']	=	I('post.money/f');
        if($data['money'] < 5){
            $msg['status']  =   0;
            $msg['info']    =   '代付金额不能小于5元';
            $this->ajaxReturn($msg);
        }
		if($data['staff_id'] && $data['money']){
			$data['create_time']	=	NOW_TIME;
			$data['user_id']	=	$this->user_id;
			$data['pay_status']	=	0;
            $data['paid_sn']    =   get_paid_sn();
            $data['remark'] = I('remark/s','');
			$r = db('staff_paid')->insert($data);
			if($r){
				$msg['status']	=	1;
			}else{
				$msg['status']	=	0;
				$msg['info']	=	'系统繁忙，请稍后再试！';	
			}
		}else{
			$msg['status']	=	0;
			$msg['info']	=	'员工账号和金额不能为空';
		}
		$this->ajaxReturn($msg);
	}


    /*提现到现金余额*/
    function toCash(){
        return $this->fetch(); 
    }

   /*提现到现金余额*/
    function doCash(){
        if($money_cash = I('post.money_cash',0)){
            if($money_cash <= 0){
                $res['status']  =   0;
                $res['info']    =   '提现金额不能为0';
            }elseif($this->user['rebate_money'] < $money_cash){
                $res['status']  =   0;
                $res['info']    =   '用户返利余额不足！';
            }else{
                if(empty($this->user['paypwd'])){
                    $res['status']  =   0;
                    $res['info']    =   '请设置支付密码';
                    $this->ajaxReturn($res);
                }
                if($psw = I('post.psw/s')){
                    $where['paypwd']    =   ['eq',encrypt($psw)];
                    $where['user_id']   =   ['eq',$this->user_id];
                    if(db('users')->where($where)->find()){

                        $midou_rate = tpCache('shoppingred.midou_rate');

                        $total_ratio = explode('|',tpCache('proportion.red_envelope')); // 提现到余额 现金 余额 比
                        $money       = $money_cash*$total_ratio[0];
                        $midou       = $money_cash*$total_ratio[1]/$midou_rate;

                        $save_data['user_id']     = $this->user_id;
                        $save_data['create_time'] = NOW_TIME;
                        $save_data['money']       = $money;
                        $save_data['midou']       = $midou;
                        $save_data['total']       = $money_cash;
                        $save_data['status']      = 0;

                        $user_data['dj_rebate']    = ['exp',"dj_rebate + {$money_cash}"];
                        $user_data['rebate_money'] = ['exp',"rebate_money - {$money_cash}"];
                        db('users')->where("user_id = {$this->user_id}")->update($user_data);
                        if(db('tocash')->add($save_data)){
                            $res['status']  =   1;
                        }else{
                            $res['status']  =   0;
                            $res['info']    =   '系统繁忙，请稍后再试！';
                        }
                    }else{
                        $res['status']  =   0;
                        $res['info']    =   '支付密码不正确！';
                    }
                }else{
                    $res['status']  =   0;
                    $res['info']    =   '请输入支付密码！';
                }
            }
            
        }else{
            $res['status']  =   0;
            $res['info']    =   '请输入提现金额！';
        }
        $this->ajaxReturn($res);

    }
    
    function toCashLog(){
        $where['user_id']   =   ['eq',$this->user_id];
        $count = M('tocash')->where($where)->count();
        $page = new Page($count, 4);
        $list = M('tocash')->alias('cash')->where($where)->order("id desc")
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    ->select();
        $this->assign('list',$list);
        $this->assign('page', $page);
        if(I('get.is_ajax')){
            return $this->fetch('ajax_toCashLog');
        }
        return $this->fetch(); 
    }
    /*我的返利*/
    function already_rebate(){
        $where['order.user_id']   =   ['eq',$this->user_id];
        $where['order.order_status']   =   ['in','2,4'];
        $where['order.is_allreturn']  =['eq',1];
        $rebate_status = I('rebate_status/d',1);
        if($rebate_status == 1){
            $where['(order.order_amount - order.shipping_price)']  =   ['exp'," > order.already_rebate"];
        }else{
            $where['(order.order_amount - order.shipping_price)']  =   ['exp'," <= order.already_rebate"];
        }
        $count = M('order')->alias('order')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('order')->alias('order')->where($where)
                                    ->order("order.add_time asc")
                                    ->field("order.add_time,is_forward,order.order_amount,order.shipping_price,order.already_rebate,order.order_id,order_old.id old_id,order_old.order_amount old_amount,order_old.shipping_price old_shpping,order_old.already_rebate old_rebate,total_rebate")
                                    ->join('order_old_rebate order_old','order.order_id = order_old.order_id','left')
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    ->select();
      #   echo M('order')->getlastsql();die;
      #  dump($list);die;
        foreach ($list as $key => $value) {
            $list[$key]['tx_rebate'] = $value['total_rebate'] - $value['old_rebate'];
            $list[$key]['rebate_price']   =   bcsub($value['order_amount'],$value['shipping_price'],4);
            if($value['already_rebate'] != 0){
                $list[$key]['progress_bar']   =   intval($value['already_rebate'] / $list[$key]['rebate_price'] * 100);
            }else{
                $list[$key]['progress_bar']   =   0;
            }
        }
        $this->assign('list',$list);
        $this->assign('page', $page);
        return $this->fetch(); 
    }


 /*
    我的红包
    作者：TK
    2018年5月28日16:01:24
    */

    function red_envelope(){
        $type = I('type','all');
        if($type == 'plus'){
            $where['money'] =   ['gt',0];
        }elseif($type == 'minus'){
            $where['money'] =   ['lt',0];
        }
        $p = I('p/d',1);
        $page_last = 10;

        $where['red.user_id']   =   ['eq',$this->user_id];
        $count = M('red_envelope')->alias('red')->where($where)->count();
        $page = new Page($count,$page_last);
        $page->rollPage = 2;
        $page = $page->show();
        $list = M('red_envelope')->alias('red')->where($where)->field('red.*,order.order_sn')
                                    ->join('order order','order.order_id = red.order_id','left')
                                    ->page("{$p},{$page_last}")
                                    ->order("id desc")
                                    ->select();
        $this->assign('list',$list);
        $this->assign('page', $page);
        return $this->fetch();
    }


    /*线下消费记录*/
    function pay_paid(){
        $t = I('get.t/d',999);
        $where['a.user_id']   =   ['eq',$this->user_id];
        $where['pay_status']    =   ['eq',1];
        $p = I('p/d',1);
        $page_last = 10;
        if($t == 1){
            $count = db('staff_paid')->alias('a')->where($where)->count();
            $list = M('staff_paid')->alias('a')->where($where)
                                     ->order("id desc") #,tg.real_name tg_name
                                    ->field("a.*,staff.real_name staff_name,store.cname store_name,company.cname company_name")
                                    ->join('staff staff',"staff.id = a.staff_id",'left')
                                    ->join('company store',"store.cid = staff.store_id",'left')
                                    ->join('company company',"company.cid = staff.company_id",'left')
              /*                      ->join('users u','u.user_id = a.user_id','left')
                                    ->join('staff tg','tg.id = u.staff_id')*/
                                    ->page("{$p},{$page_last}")
                                    ->select();
        }else{
            $count = db('staff_mypays')->alias('a')->where($where)->count();
            $list = M('staff_mypays')->alias('a')->where($where)
                                     ->order("id desc") #,tg.real_name tg_name
                                    ->field("a.*,staff.real_name staff_name,store.cname store_name,company.cname company_name")
                                    ->join('staff staff',"staff.id = a.staff_id",'left')
                                    ->join('company store',"store.cid = staff.store_id",'left')
                                    ->join('company company',"company.cid = staff.company_id",'left')
                                    ->page("{$p},{$page_last}")
                                    ->select();
        }
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $page = $Page->show(); 

        $this->assign('list',$list);
        $this->assign('page', $page);
        return $this->fetch();   
    }

    public function reg1(){
        return $this->fetch();
    }

    public function bind_store(){
        $cid = I('get.cid/d',0);
        $mobile = I('mobile','');
        $cname = M('company')->where("cid=".$cid)->value('cname');
        $this->assign('cid',$cid);
        $this->assign('cname',$cname);
        $this->assign('mobile',$mobile);
        return $this->fetch();
    }

    public function do_bind_store(){

        $mobile = I('mobile','');
        if($mobile == ''){
            return json(array('status'=>0,'info'=>'请输入手机号码'));
        }

        $bd = M('bind_store_user')->where("mobile='".$mobile."'")->find();
        if($bd){
            return json(array('status'=>0,'info'=>'该实体店已绑定！'));
        }

        $sr = M('company')->field('cid')->where("mobile='".$mobile."' and parent_id > 0")->find();
        if(!$sr){
            return json(array('status'=>0,'info'=>'手机号码不存在，只有实体店才可以绑定!'));
        }

        return json(array('status'=>1,'cid'=>$sr['cid'],'mobile'=>$mobile));

    }

    public function bind_store_ok(){
        $cid = I('cid/d',0);
        $mobile = I('mobile','');
        $openid = $this->GetOpenid();
        $addtime = time();

        $result = M('bind_store_user')->save(['cid'=>$cid,'mobile'=>$mobile,'openid'=>$openid,'addtime'=>$addtime]);
        if($result){
            return json(array('status'=>1,'info'=>'绑定成功！'));
        }else{
            return json(array('status'=>0,'info'=>'绑定失败！'));
        }
    }

    /*
    合并数据
    作者tk
    时间：2018年10月24日15:01:32
    查询出用户输入的用户名密码，然后与当前的用户合并，保留当前用户，删除查询的用户
    */
    public function Consolidated_data(){
        if(Request::instance()->isAjax()){
            $param = I('post.');
            $consolidated_sms = cache('consolidated_sms');
            if(!$consolidated_sms){
                $userLogic = new UsersLogic();
                $check_code = $userLogic->check_validate_code($param['mobile_code'], $param['mobile'], 'phone', session_id(), 6);
                if ($check_code['status'] != 1){
                    $res['status'] =   0;
                    $res['info']   =   $check_code['msg'];
                    $this->ajaxReturn($res);
                }
                cache('consolidated_sms',true,600);
            }
        
            $where['mobile']    =   ['eq',$param['mobile']];
            $where['user_id']   =   ['neq',$this->user_id];
            $cache_key = md5(serialize($where));
            $r = M('users')->where($where)->cache($cache_key,600)->find();
            $sql_array = include APP_PATH . "sql_array.php";
            
            if($r){
                for ($i=$param['n']; $i < count($sql_array); $i++){
                    $table_arr = array_slice($sql_array,$i,1);
                    $table_name = current(array_keys($table_arr));
                    $column_name = $table_arr[$table_name];
                    $pk = db::name($table_name)->getPk();       //有的时候获取的是数组
                    $pk = is_array($pk) ? current($pk) : $pk;
                    $result = db::name($table_name)->field("{$pk},{$column_name}")->where($column_name,$r['user_id'])->order($pk . " desc")->limit(10000)->select();
                    if($result){
                        foreach ($result as $key => $value) {
                            db::name($table_name)->where($pk,$value[$pk])->setField($column_name,$this->user_id);
                        }
                    }
                    if($i == (count($sql_array) - 1)){
                        db::name('oauth_users')->where('user_id',$r['user_id'])->delete();
                        floatval($r['user_money']) && $user_save_data['user_money']   =   ['exp',"user_money + {$r['user_money']}"];
                        floatval($r['frozen_money']) && $user_save_data['frozen_money']   =   ['exp',"frozen_money + {$r['frozen_money']}"];
                        floatval($r['rebate_money']) && $user_save_data['rebate_money']   =   ['exp',"rebate_money + {$r['rebate_money']}"];
                        floatval($r['rebate_money_all']) && $user_save_data['rebate_money_all']   =   ['exp',"user_money + {$r['rebate_money_all']}"];
                        floatval($r['midou']) && $user_save_data['midou']   =   ['exp',"midou + {$r['midou']}"];
                        floatval($r['midou_all']) && $user_save_data['midou_all']   =   ['exp',"midou_all + {$r['midou_all']}"];
                        floatval($r['total_amount']) && $user_save_data['total_amount']   =   ['exp',"total_amount + {$r['total_amount']}"];
                        intval($r['staff_id']) && $user_save_data['staff_id']   =   $r['staff_id'];
                        $user_save_data['mobile']   =   $r['mobile'];
                        $user_save_data['mobile_validated']   =   1;
                        if(!$this->user['password']){
                            $user_save_data['password'] =   $r['password'];
                        }
                        if(!$this->user['paypwd']){
                            $user_save_data['paypwd']   =   $r['paypwd'];
                        }
                        db::name('users')->where('user_id',$this->user_id)->save($user_save_data);               
                        db::name('users')->where('user_id',$r['user_id'])->delete();
                        session('user.mobile',$r['mobile']);
                        \Think\Cache::rm('consolidated_sms');
                    }
                    $i++;
                    $progress =  intval($i/count($sql_array) * 100) ;
                    $res = ['n' => $i,'progress'=>$progress];
                    $this->ajaxReturn($res);
                }
            }else{
                $res['status']  =   0;
                $res['info']    =   '您输入的账户不存在';
                $this->ajaxReturn($res);
            }
        }else{
            return $this->fetch('consolidated_data');  
        }
    }

    /*
    会员充值
    2018年11月18日19:24:19
    */
    function club_card(){
        $group_id = I('get.group_id/d');
        $card_number = I('get.card_number/s');
        if($group_id && $card_number){
            $where['group_id']  =   ['eq',$group_id];
            $where['encryption_code']   =   ['eq',$card_number];
            $result = db('club_card_qrcode_list')
                    ->field('card_list.*,card_group.status group_status,denomination')
                    ->alias('card_list')
                    ->where($where)
                    ->join('club_card_group card_group','card_group.id = card_list.group_id')
                    ->find();
           
            if($result){
                if($result['group_status'] != 0){
                    $res['status']  =   -1;
                    $res['info']    =   '该充值卡已经过期！';
                }else{
                    if($result['use_status'] == 0 && $result['user_id'] == 0){
                        #日志
                        $source =   '充值卡' . sprintf("%04d", $result['id']) . '米豆充值';
                        $log_data['create_time'] = NOW_TIME;
                        $log_data['source']      = $source;
                        $log_data['money']       = $result['denomination'];
                        $log_data['user_id']     = $this->user_id;         
                        $log_data['order_id']    = $result['id'];
                        $log_data['order_sn']   =   $result['encryption_code'];
                        $log_data['is_red'] =   0;
                        #锁住充值卡
                        $club_card_qrcode_list_data['user_id']  =   $this->user_id;
                        $club_card_qrcode_list_data['use_status']  =   1;
                        #会员表记录
                        $data['midou'] = ['exp',"midou + {$result['denomination']}"];
                        $data['midou_all'] = ['exp',"midou_all + {$result['denomination']}"];
                        Db::startTrans();
                        try{
                            #流程 先将卡片锁住 ,然后再充值
                            db('club_card_qrcode_list')->where('id',$result['id'])->update($club_card_qrcode_list_data);
                            db('club_card_group')->where('id',$group_id)->setInc('been_used',1);
                            db('red_envelope')->add($log_data);
                            db('users')->where("user_id = {$this->user_id}")->update($data);
                            Db::commit();
                            $res['status']  =   1;
                            $res['info']    =   '恭喜您，充值成功！';
                            $res['denomination']    =   $result['denomination'];
                        } catch (\Exception $e) {
                            // 回滚事务
                            Db::rollback();
                            $res['status']  =   -1;
                            $res['info']    =   '系统繁忙，请重试';
                        }
                    }else{
                        $res['status']  =   -1;
                        $res['info']    =   '充值卡已经被使用，充值失败';
                    }
                }
            }
        }else{
            $res['status']  =   -1;
            $res['info']    =   '二维码已失效,请联系系统管理员';
        }
        $this->assign('res',$res);
        return $this->fetch('recharge_status');  
    }
    /*充值状态页面*/
 /*   function recharge_status(){
        
    }*/
        //推广员页面
      function tuiguangyuan(){
          $staffif = I('staff_id');
          $this->assign('staffif',$staffif);
          return $this->fetch();
       }
}
