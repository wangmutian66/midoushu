<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\applet_app\controller;
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

/**
     * 使用 $this->name 获取配置
     * @access public     
     * @param  string $name 配置名称
     * @return multitype    配置值
     */
    public function __get($name) {
        return $this->config[$name];
    }
    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        $user_id = I('user_id/d', 0);
        $this->user_id = $user_id;
        $this->assign('user', $user); //存储用户信息

        $this->user_id = '2366';

        $is_bind_account = tpCache('basic.is_bind_account');
       

        $order_status_coment = array(
            'WAITPAY'      => '待付款', //订单查询状态 待支付
            'WAITSEND'     => '待发货', //订单查询状态 待发货
            'WAITRECEIVE'  => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );

        // $this->user_id = $this->request->param('user_id');
        // $this->token = $this->request->param('token');
        // $isUsers = M('users')->where(['user_id'=>$this->user_id , 'token'=>$this->token])->find();
        // if (ACTION_NAME != 'forget_pwd' && ACTION_NAME != 'find_pwd' && ACTION_NAME != 'set_pwd' && ACTION_NAME != 'yanzhengma' && ACTION_NAME != 'login' && ACTION_NAME != 'reg') {
        //     if(!$isUsers){
        //         exit(formt('',201,'用户不存在'));
        //     }
        // }
        
    }

    /*
     * 用户中心首页
     */
    public function index()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $listData = $user_info['result'];
        $listData['head_pic'] = $user_info['result']['head_pic'];
        $listData['rebate_money'] =  floor($user_info['result']['rebate_money'] * 100) / 100;
        $listData['rebate_money_all'] = floor($user_info['result']['rebate_money_all'] * 100) / 100;
        $listData['user_money'] = floor($user_info['result']['user_money'] * 100) / 100;
        $messageLogic       = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();
        $listData['user_message_count'] = $user_message_count;
        unset($listData['password']);
        unset($listData['paypwd']);
        exit(formt($listData));
    }
    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $listData['user_id'] = $user_info['result']['user_id'];
        $listData['head_pic'] = $user_info['result']['head_pic'];
        $listData['nickname'] = $user_info['result']['nickname'];
        $listData['real_name'] = $user_info['result']['real_name'];
        switch ($user_info['result']['sex'])
            {
            case 0:
                $listData['sex'] = '保密';
                break;
            case 1:
                $listData['sex'] = '男';
                break;
            default:
                $listData['sex'] = '女';
            }
        $listData['mobile'] = $user_info['result']['mobile'];
        exit(formt($listData,200,'操作成功'));
    }
    /*
        * 个人信息修改
        */
    public function setuserinfo()
    {
        $userLogic = new UsersLogic();
        if (IS_POST) {
            I('post.nickname') ? $post['nickname']   = I('post.nickname') : false; //昵称
            I('post.real_name')? $post['real_name']  = I('post.real_name') : false; //真实姓名
            I('post.head_pic') ? $post['head_pic']   = I('post.head_pic') : false; //头像地址
            I('post.sex')      ? $post['sex']        = I('post.sex') : false;  // 性别
            I('post.mobile')   ? $post['mobile']     = I('post.mobile') : false; //手机

            $email  = I('post.email');
            $mobile = I('post.mobile');
            $code   = I('post.mobile_code', '');
            $scene  = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && exit(formt('',201,'邮箱已被使用'));
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && exit(formt('',201,'手机已被使用'));
                if (!$code)
                    exit(formt('',201,'请输入验证码'));
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                    exit(formt('',201,$check_code['msg']));
            }
            if (!$userLogic->update_info($this->user_id, $post)){
                exit(formt('',201,'保存失败'));
            }else{
                exit(formt('',200,'保存成功'));
            }
        }
    }



   
    /*
     * 账户资金
     */
    public function account()
    {
        $user_id = I('user_id/d');
        $user = M('users')->where('user_id='.$user_id)->find();
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($user_id, I('get.type'));
        $account_log = $data['result'];
        $account['user']=$user;
        $account['account_log']=$account_log;
        exit(formt($res['result']));
    }

    public function account_list()
    {
        $user_id=$this->user_id;
    	$type = I('type','all');
        $p = I('p/d',1);
        $page_last = 14;
        if($type == 'all'){
            $count = M('account_log')->where("user_money!=0 and user_id=" . $user_id)->count();
            
            $account_log = M('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("user_money!=0 and user_id=" . $user_id)
                ->order('log_id desc')->page("{$p},{$page_last}")->select();
        }else{
            $where = $type=='plus' ? " and user_money>0 " : " and user_money<0 ";
            $count = M('account_log')->where("user_id=" . $user_id.$where)->count();
            
            $account_log = Db::name('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("user_id=" . $user_id.$where)
                ->order('log_id desc')->page("{$p},{$page_last}")->select();
        }
 
        $Page = new Page($count,$page_last);
        $show = $Page->show(); 
        $account_list['list']=$account_log;
        $account_list['totalPages']=$Page->totalPages;
        exit(formt($account_list));
    
    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
          exit(formt($detail));
    }
    
  

 

    /**
     * 登录
     */
    public function login()
    {
        
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));

        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        $msg= $res['msg'];
        if ($res['status'] == 1) {
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
            
            exit(formt($res['result'],'200',$msg));
        }else{
            exit(formt('',201,$msg));
        }

    }

 

    /**
     *  注册
     */
    public function reg()
    {

        if (I('post.username')) {
            $logic = new UsersLogic();
            //验证码检验
            $username  = I('post.username', '');
            $password  = I('post.password', '');
            $password2 = I('post.password2', '');
            $paypwd = I('post.paypwd', '');
            $paypwd2 = I('post.paypwd2', '');
            $is_bind_account = tpCache('basic.is_bind_account');
            $invite = I('invite');
            if(!empty($invite)){
                $invite = get_user_info($invite,2);//根据手机号查找邀请人
            }else{
                $invite = array();
            }
            $data = $logic->reg($username, $password, $password2, $paypwd, $paypwd2,0,$invite);
            if ($data['status'] != 1) {
                exit(formt('',201,$data['msg']));
            }
            //获取公众号openid,并保持到session的user中
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            if ($data['status'] == 1) {
                exit(formt($data['result'],200,$data['msg']));
            }
           
        }

        
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
                //绑定成功, 重新关联上下级
                $map['first_leader'] = cookie('first_leader');  //推荐人id
                // 如果找到他老爸还要找他爷爷他祖父等
                if($map['first_leader']){
                    $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
                    if($first_leader){
                        $map['second_leader'] = $first_leader['first_leader'];
                        $map['third_leader'] = $first_leader['second_leader'];
                    }
                    //他上线分销的下线人数要加1
                    M('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
                }else
                {
                    $map['first_leader'] = 0;
                }
                $ruser = $res['result'];
                M('Users')->where('user_id' , $ruser['user_id'])->save($map);
                
                $res['url'] = urldecode(I('post.referurl'));
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
                return $this->success("绑定成功", U('Mobile/User/index'));
            }else{
                return $this->error("绑定失败,失败原因:".$res['msg']);
            }
        }else{
            return $this->fetch();
        }
    }
   

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $user_id = I('get.user_id/d');
        $address_lists = get_user_address_list($user_id);
        foreach ($address_lists as $key => $value) {
            $address_lists[$key]=$value;
            $address_lists[$key]['province_name']=wechat_get_region_list($value['province']);
            $address_lists[$key]['city_name']=wechat_get_region_list($value['city']);
            $address_lists[$key]['district_name']=wechat_get_region_list($value['district']);
        }
        $address_list['address_lists']=$address_lists;
        exit(formt($address_list,200,'成功'));
    }

    /*
     * 添加地址
     */
    public function add_address()
    {

        if (I('consignee')) {
            $post_data['consignee']=I('consignee');
            $post_data['mobile']=I('mobile');
            $post_data['province']=I('province');
            $post_data['city']=I('city');
            $post_data['district']=I('district');
            $post_data['address']=I('address');
            $post_data['is_default']=I('is_default');
            $user_id = I('user_id/d', 0);
            $data      = $this->add_addressc($user_id, 0, $post_data);
            $addlist['addressid']=$data['result'];
            if ($data['status']=='1') {
                exit(formt($data['result'],200,'添加成功'));
            }else{
                exit(formt('',201,$data['msg']));
            }
        }
       
        
    }
   

    
    /*
     * 用户地址列表
     */
    public function edit_address_list()
    {
        $user_id = I('get.user_id/d');
        $address_id = I('get.address_id/d');
        $address_lists = $lists = M('user_address')->where(array('user_id'=>$user_id,' address_id'=>$address_id))->find();
       
        $address_lists['provinces']=$address_lists['province'];
        $address_lists['citys']=$address_lists['city'];
        $address_lists['districts']=$address_lists['district'];
        $address_lists['province']=wechat_get_region_list($address_lists['province']);
        $address_lists['city']=wechat_get_region_list($address_lists['city']);
        $address_lists['district']=wechat_get_region_list($address_lists['district']);
        
        $address_list['address_lists']=$address_lists;
        exit(formt($address_list,200,'成功'));
    }
    /*
     * 地址编辑
     */
    public function edit_address()
    {   
        $user_id = I('user_id');
        $id = I('address_id');
        if ($id) {
      
            $post_data['consignee']  = I('consignee');
            $post_data['province']   = I('province');
            $post_data['city']       = I('city');
            $post_data['district']   = I('district');
            $post_data['address']    = I('address');
            $post_data['mobile']     = I('mobile');
            $post_data['is_default'] = I('is_default');
            $data         = $this->add_addressc($user_id, $id, $post_data);
             if ($data['status']=='1') {
                exit(formt($data['result'],200,$data['msg']));
            }else{
                exit(formt('',201,$data['msg']));
            }
            
        }
        
    }
  

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('address_id');
        $user_id = I('user_id');
        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row){
            exit(formt('',201,'操作失败'));
        }else{
            exit(formt('',200,'操作成功'));
        }
    }



    
    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile(){
        if (I('user_id/d', 0)) {
            $user_id = I('user_id/d', 0);
            $user = M('users')->where('user_id', $user_id)->find();
            if (I('mobile')) {
                $mobile = I('mobile');
                $smscode = I('smscode');
                $smscodes = M('sms_log')->where('mobile='.$mobile.' and status=1 and scene=6')->order('add_time desc')->find();
                if ($smscodes['code'] == $smscode) {
                    
                } else {
                    return formt('',201,'验证码不正确');
                }
                $c = Db::name('users')->where(['mobile' => $mobile, 'user_id' => ['=', $user_id]])->count();
                 if ($c) {
                 exit(formt('',201,'手机已被使用'));
                 }
                 //检查是否第三方登录用户
             
                if(I('validate') == '1'){
                    $res = Db::name('users')->where(['user_id' => $user_id])->update(['mobile'=>$mobile]);
                    if($res){
                        return formt('',200,'修改成功');
                    }
                    return formt('',201,'修改失败');
                }
            }
            return formt($user,200,'ok');
        }
    }

    




    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $user_id = I('user_id');
        $userLogic = new UsersLogic();
        $data = $userLogic->wechat_get_goods_collect($user_id);
        $page= object_to_array($data['page']);
        $data['pages']['totalPages']=$page['totalPages'];
        unset($data['page']);
        unset($data['show']);
        return formt($data);
      
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id');
        $user_id = I('user_id');
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            return formt('',200,'取消收藏成功');
        } else {
            return formt('',201,'取消收藏失败');
        }
    }

   

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $user_id = I('user_id/d', 0);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
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
        if (I('user_id/d', 0)) {
            $logic = new UsersLogic();
            $user_id = I('user_id/d', 0);
            $data = $logic->get_info($user_id);

            $user = $data['result'];
            if ($user['mobile'] == '' && $user['email'] == '')
                exit(formt('',201,'请先绑定手机或邮箱'));
            $userLogic = new UsersLogic();
            $data = $userLogic->password($user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1){
                exit(formt('',201,$data['msg']));
            }else{
                return formt('',200,$data['msg']);
            }
        }
    }

    function forget_pwd()
    {
        
        $username = I('username');
        if (!empty($username)) {
            $user = M('users')->where('mobile', $username)->find();
            if ($user) {
                return formt('',200,'ok');
            } else {
                return formt('',201,'用户名不存在，请检查');
            }
        }
    }

    function find_pwd()
    {
        $mobile = I('mobile');
        $smscode = I('smscode');
        if (!empty($mobile)) {
            $smscodes = M('sms_log')->where('mobile='.$mobile.' and status=1 and scene=6')->order('id desc')->find();
            $user = M('users')->where('mobile', $mobile)->find();
             if ($smscodes['code'] == $smscode) {
                return formt('',200,'ok');
            } else {
                return formt('',201,'验证码不正确');
            }
            if ($user) {
                return formt('',200,'ok');
            } else {
                return formt('',201,'请先验证用户名');
            }
        }
    }


    public function set_pwd()
    {
        $mobile = I('mobile');
        if ($mobile) {
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                return formt('',201,'两次密码不一致');
            }
                $user = M('users')->where("mobile", $mobile)->find();
                M('users')->where("user_id", $user['user_id'])->save(array('password' => encrypt($password)));
                return formt($user);
            
        }
    }
 
  
    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    


    // 充值     
    public  function recharge_pay(){

        $Ad =  M('rechargecofig');
        $list = $Ad->order('orderby')->where('is_show = 1')->select();
        $userid = I('user_id/d'); //用户id
        $users = db('users')->where(["user_id"=>$userid])->find();
        $recharge['list']=$list;
        $recharge['user_money']=tk_money_format($users['user_money']);
        exit(formt($recharge));
    }
    
    public function recharge_list(){
      
        $user_id = I('user_id/d', 0);
        $recharge_log_where['user_id'] = ['eq',$user_id];
        $p = I('p/d',1);
        $page_last = 15;
        $count = M('recharge')->where($recharge_log_where)->count();
        $withdrawals_log = M('recharge')->where($recharge_log_where)
            ->order('order_id desc')
            ->page("{$p},{$page_last}")
            ->field("*,from_unixtime(ctime,'%Y-%m-%d') AS cdata")
            ->select();
        $Page = new Page($count,$page_last);
        $page= object_to_array($Page);
        $rechargeList['withdrawals_log']=$withdrawals_log;
        $rechargeList['pages']['totalPages']=$page['totalPages'];
        exit(formt($rechargeList));
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {
        $user_id = I('user_id/d', 0);
  
        $userinfo = M('users')->where('user_id ='.$this->user_id)->find();
        
        if (I('money')) {
       
            $data = I('post.');
            $data['user_id'] = $user_id;
            $data['create_time'] = time();
            $distribut_min  = tpCache('basic.min');            // 最少提现额度
            $service_fee    = tpCache('basic.service_fee');    // 会员提现手续费
            $data['taxfee'] = $data['money']*$service_fee/100; // 手续费
            $total = $data['money']+$data['taxfee'];           // 总
            
            if ($data['money'] < $distribut_min) {
                exit(formt('',201,'每次最少提现额度' . $distribut_min));
            }

            if($total > $userinfo['user_money'])
            {
                //$this->ajaxReturn(['status'=>0,'msg'=>"你最多可提现{$this->user['user_money']}账户余额."]);
                exit(formt('',201,'抱歉，您的余额不足'));
            }
          
            if(encrypt($data['paypwd']) != $userinfo['paypwd']){
                exit(formt('',201,'支付密码错误'));
                $this->ajaxReturn(['status'=>0,'msg'=>'支付密码错误']);
                exit;
            }
          
            if(M('withdrawals')->add($data)){
                accountLog($user_id, (-1 * $total), 0, 0, '会员提现申请');
                $up_data['frozen_money'] = $userinfo['frozen_money']+$total;
                M('users')->where('user_id ='.$user_id)->update($up_data);
                exit(formt('',200,'已提交申请'));
            }else{
                 exit(formt('',201,'联系客服'));
            }
        }
        $withdr['user_money']=tk_money_format($userinfo['user_money']);
        $withdr['service_fee']=tpCache('basic.service_fee');
        $withdr['distribut_min']=tpCache('basic.min');
       
        $withdrawals = M('withdrawals')->where('user_id ='.$user_id)->select();
      
		$res = array(); //想要的结果
		foreach ($withdrawals as $k => $v) {
		   $res[$v['bank_name']][] = $v;
		}
        $withdr['res']=$res;
        exit(formt($withdr));
        
    }

    
     
    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $p = I('p/d',1);
        $user_id = I('user_id/d', '0');
        $withdrawals_where['user_id'] = $user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $withdlist['list']=$list;
        $page= object_to_array($page);

        $withdlist['pages']['totalPages']=$page['totalPages'];
        exit(formt($withdlist));
        
    }

    


    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        // $type = I('type');
         $user_id = I('user_id');
        // $user_logic = new UsersLogic();
        $message_model = new MessageLogic();
        // if ($type === '0') {
        //     //系统消息
        //     $user_sys_message = $message_model->getUserMessageNotice();
        // } else if ($type == 1) {
        //     //活动消息：后续开发
        //     $user_sys_message = array();
        // } else {
        //     //全部消息：后续完善
        //     $user_sys_message = $message_model->getUserMessageNotice();
        // }
        $user_sys_message = $message_model->wechatgetUserMessageNotice($user_id);
        exit(formt($user_sys_message));

    }

    /**
     * ajax用户消息通知请求
     */
    public function set_message_notice()
    {
        $rec_id = I('rec_id');
        $status = I('status');
        $user_id = I('user_id');
        if (!empty($user_id)) {
            $data['status'] = $status;
            $set_where['user_id'] = $user_id;
            $set_where['rec_id'] = $rec_id;
            // $set_where['category'] = '0';
            // if($msg_id){
            //     $set_where['message_id'] = $msg_id;
            // }
            $updat_meg_res = Db::name('user_message')->where($set_where)->update($data);
            if ($updat_meg_res) {
                exit(formt());
            }else{
                exit(formt('',201,'操作失败'));
            }
        }
        exit(formt('',201,'操作失败'));
        
    }


  

    function yanzhengma(){
        $this->send_scene = C('SEND_SCENE');

        $type   = 'mobile';
        $scene  = '6';    //发送短信验证码使用场景
        $mobile = I('mobile');
       
      
            $res = checkEnableSendSms($scene);
            if($res['status'] != 1){
                // ajaxReturn($res);
                exit(formt('',201,$res));
            }
            //判断是否存在验证码
            $data = M('sms_log')->where(array('mobile'=>$mobile,'session_id'=>$session_id, 'status'=>1))->order('id DESC')->find();

            //获取时间配置
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 120;
            //120秒以内不可重复发送
            if($data && (time() - $data['add_time']) < $sms_time_out){
                exit(formt('',201,$sms_time_out.'秒内不允许重复发送'));
            }
            //随机一个验证码
            $code = rand(100000, 999999); 
            $params['code'] =$code;
            //发送短信
            $resp = sendSms($scene , $mobile , $params, $session_id);
            // M('sms_log')->save(array('mobile' => $mobile, 'code' => $code, 'add_time' => time(), 'session_id' => $session_id, 'status' => 0, 'scene' => $scene, 'msg' => $resp['msg']));
            if($resp['status'] == 1){
                //发送成功, 修改发送状态位成功
                M('sms_log')->where(array('mobile'=>$mobile,'code'=>$code , 'status' => 0))->save(array('status' => 1));

                exit(formt('',200,'发送成功,请注意查收'));
            }else{
                exit(formt('',201,'发送失败'.$resp['msg']));
            }
            
   }
    

    /**
     * 设置支付密码
     * @return mixed
     */
    public function paypwdone()
    {
        //检查是否第三方登录用户
        $mobile = I('mobile');
        $smscode = I('smscode');
        $smscodes = M('sms_log')->where('mobile='.$mobile.' and status=1 and scene=6')->order('add_time desc')->find();
        if ($smscodes['code'] == $smscode) {
            return formt('',200,'ok');
        } else {
            return formt('',201,'验证码不正确');
        }
        
       
        
    }
    /**
     * 设置支付密码
     * @return mixed
     */
    public function paypwd()
    {
        //检查是否第三方登录用户
        $user_id = I('user_id/d', 0);
        $user = M('users')->where('user_id', $user_id)->find();
        if ($user['mobile'] == '')
            return formt('',201,'请先绑定手机号');
       
        if (I('new_password')) {
            $new_password = trim(I('new_password'));
            if(strlen($new_password) != 6 ){
                return formt('',201,'支付密码必须6位！');
            }
            $confirm_password = trim(I('confirm_password'));
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($user_id, $new_password, $confirm_password);
            if ($data['status']=='-1') {
                return formt('',201,$data['msg']);
            }else{
                return formt('',200,$data['msg']);
            }
        }
    }

     
     /**
     * 修改支付密码
     * @return mixed
     */
    public function paypwded()
    {
        //检查是否第三方登录用户
        $user_id = I('user_id/d', 0);
        $user = M('users')->where('user_id', $user_id)->find();
    
        
        if ($user['mobile'] == '')
            return formt('',201,'请先绑定手机号');
        
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            if(strlen($new_password) != 6 ){
                return formt('',201,'支付密码必须6位！');
            }
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
                return formt('',201,'原密码验证错误！');
            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($user_id, $new_password, $confirm_password);
            if ($data['status']=='1') {
                return formt('',201,$data['msg']);
            }else{
                return formt($order_sn,200,'设置成功');
            }
        }
    
    }

   

    /**
     *  点赞
     * @author lx
     * @time  17-4-20
     * 拷多商家Order控制器
     */
    public function ajaxZan()
    {
        $comment_id = I('post.comment_id/d');
       
        $user_id = I('user_id/d', 0);
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

    /*提现到现金列表*/
        function toCashlist(){
           $toCashlist = M('users')->where('user_id',$this->user_id)->field('rebate_money_all,rebate_money,dj_rebate')->find();
           $toCashlist['rebate_money_all'] = tk_money_format($toCashlist['rebate_money_all']);
           $toCashlist['rebate_money'] = tk_money_format($toCashlist['rebate_money']);
           $toCashlist['dj_rebate'] = tk_money_format($toCashlist['dj_rebate']);
           return formt($toCashlist);
        }
      
   /*提现到现金余额*/
    function toCash(){
        if($money_cash = I('post.money_cash',0)){
            $user = M('users')->where('user_id',$this->user_id)->find();
            if($money_cash <= 0){
                return formt('',201,'提现金额不能为0');
            }elseif($user['rebate_money'] < $money_cash){
                return formt('',201,'用户返利余额不足！');
            }else{
                if(empty($user['paypwd'])){
                    return formt('',201,'请设置支付密码');
                }
                if($psw = I('post.psw/s')){
                    $user_id = I('user_id/d', 0);
                    $where['paypwd']    =   ['eq',encrypt($psw)];
                    $where['user_id']   =   ['eq',$user_id];
                    if(db('users')->where($where)->find()){

                        $midou_rate = tpCache('shoppingred.midou_rate');

                        $total_ratio = explode('|',tpCache('proportion.red_envelope')); // 提现到余额 现金 余额 比
                        $money       = $money_cash*$total_ratio[0];
                        $midou       = $money_cash*$total_ratio[1]/$midou_rate;

                        $save_data['user_id']     = $user_id;
                        $save_data['create_time'] = NOW_TIME;
                        $save_data['money']       = $money;
                        $save_data['midou']       = $midou;
                        $save_data['total']       = $money_cash;
                        $save_data['status']      = 0;

                        $user_data['dj_rebate']    = ['exp',"dj_rebate + {$money_cash}"];
                        $user_data['rebate_money'] = ['exp',"rebate_money - {$money_cash}"];
                        db('users')->where("user_id = {$user_id}")->update($user_data);
                        if(db('tocash')->add($save_data)){
                            $res['status']  =   1;
                        }else{
                            return formt('',201,'系统繁忙，请稍后再试！');
                        }
                    }else{
                        return formt('',201,'支付密码不正确！');
                    }
                }else{
                    return formt('',201,'请输入支付密码！');
                }
            }
            
        }else{
            return formt('',201,'请输入提现金额！');
        }
        return formt('',200,'提现成功');

    }
    ///不明确  s
    function toCashLog(){
        $p = I('p/d',1);
        $user_id = I('user_id/d', '0');
        $where['user_id']   =   ['eq',$user_id];
        $count = M('tocash')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('tocash')->alias('cash')->where($where)->order("id desc")
                                    // ->field('check_withdrawal(status),*')
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    ->select();
        foreach ($list as $key => $value) {
            $list[$key]['status']=check_withdrawal($value['status']);
        }
        $toCashLog['list']=$list;
        $page= object_to_array($page);
        $toCashLog['page']['totalPages']=$page['totalPages'];
        return formt($toCashLog);
        
        
    }
    /////// e 
    /*我的返利*/
    function already_rebate(){
        $p = I('p/d',1);
        $user_id = I('user_id/d', '0');
        $where['order.user_id']   =   ['eq',$user_id];
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
        $page_last = 10;
        $list = M('order')->alias('order')->where($where)
                                    ->order("order.add_time asc")
                                    ->field("order.add_time,is_forward,order.order_amount,order.shipping_price,order.already_rebate,order.order_id,order_old.id old_id,order_old.order_amount old_amount,order_old.shipping_price old_shpping,order_old.already_rebate old_rebate,total_rebate")
                                    ->join('order_old_rebate order_old','order.order_id = order_old.order_id','left')
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    // ->page("{$p},{$page_last}")
                                    ->select();
        foreach ($list as $key => $value) {
            $list[$key]['tx_rebate'] = $value['total_rebate'] - $value['old_rebate'];
            $list[$key]['rebate_price']   =   tk_money_format(bcsub($value['order_amount'],$value['shipping_price'],4));
            if($value['already_rebate'] != 0){
                $list[$key]['progress_bar']   =   intval($value['already_rebate'] / $list[$key]['rebate_price'] * 100);
            }else{
                $list[$key]['progress_bar']   =   0;
            }
        }
        $already['list']=$list;
        $page= object_to_array($page);
        $already['page']['totalPages']=$page['totalPages'];
        return formt($already);

    }


 /*
    我的红包
    作者：TK
    2018年5月28日16:01:24
    */

    function red_envelope(){
        $type = I('type','all');
        $user_id = I('user_id/d', 0);
        if($type == 'plus'){
            $where['money'] =   ['gt',0];
        }elseif($type == 'minus'){
            $where['money'] =   ['lt',0];
        }
        $p = I('p/d',1);
        $page_last = 10;

        $where['red.user_id']   =   ['eq',$user_id];
        $count = M('red_envelope')->alias('red')->where($where)->count();
        $page = new Page($count,$page_last);
        $page->rollPage = 2;
        $page = $page->show();
        $list = M('red_envelope')->alias('red')->where($where)->field('red.*,order.order_sn')
                                    ->join('order order','order.order_id = red.order_id','left')
                                    ->page("{$p},{$page_last}")
                                    ->order("id desc")
                                    ->select();
        $redenvelope['list']=$list;
        $page= object_to_array($page);
        $redenvelope['page']['totalPages']=$page['totalPages'];
        return formt($redenvelope);
    }


   

    function wx_login(){
        $APPID = '';//自己配置
        $AppSecret = '';//自己配置
        $code = input('code');
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $APPID . "&secret=" . $AppSecret . "&js_code=" . $code . "&grant_type=authorization_code";
        $arr = $this->vget($url); // 一个使用curl实现的get方法请求
        $arr = json_decode($arr, true);
        $openid = $arr['openid'];
        $session_key = $arr['session_key'];
        // 数据签名校验
        $signature = input('signature');
        $rawData = Request::instance()->post('rawData');
        $signature2 = sha1($rawData . $session_key);
        if ($signature != $signature2) {
        return json(['code' => 500, 'msg' => '数据签名验证失败！']);
        }
        Vendor("PHP.wxBizDataCrypt"); //加载解密文件，在官方有下载
        $encryptedData = input('encryptedData');
        $iv = input('iv');
        $pc = new \WXBizDataCrypt($APPID, $session_key);
        $errCode = $pc->decryptData($encryptedData, $iv, $data); //其中$data包含用户的所有数据
        $data = json_decode($data);
        if ($errCode == 0) {
        dump($data);
        die;//打印解密所得的用户信息
        } else {
        echo $errCode;//打印失败信息
        }
    }
    public function vget($url){
        $info=curl_init();
        curl_setopt($info,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($info,CURLOPT_HEADER,0);
        curl_setopt($info,CURLOPT_NOBODY,0);
        curl_setopt($info,CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($info,CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($info,CURLOPT_URL,$url);
        $output= curl_exec($info);
        curl_close($info);
        return $output;
    }

    /*线下消费记录*/
    function pay_paid(){
        $t = I('get.t/d','2');
        $where['a.user_id']   =   ['eq',$this->user_id];
        $where['pay_status']    =   ['eq',1];
        $p = I('p/d',1);
        $page_last = 10;
        if($t == 1){
            $count = db('staff_paid')->alias('a')->where($where)->count();
            $list = M('staff_paid')->alias('a')->where($where)
                                     ->order("id desc") #,tg.real_name tg_name
                                    ->field("a.*,staff.real_name staff_name,store.cname store_name,company.cname company_name,from_unixtime(a.create_time,'%Y-%m-%d %H:%i:%s') AS create_data")
                                    ->join('staff staff',"staff.id = a.staff_id",'left')
                                    ->join('company store',"store.cid = staff.store_id",'left')
                                    ->join('company company',"company.cid = staff.company_id",'left')
                                    ->page("{$p},{$page_last}")
                                    ->select();
        }else{
            $count = db('staff_mypays')->alias('a')->where($where)->count();
            $list = M('staff_mypays')->alias('a')->where($where)
                                     ->order("id desc") #,tg.real_name tg_name
                                    ->field("a.*,staff.real_name staff_name,store.cname store_name,company.cname company_name,from_unixtime(a.create_time,'%Y-%m-%d %H:%i:%s') AS create_data")
                                    ->join('staff staff',"staff.id = a.staff_id",'left')
                                    ->join('company store',"store.cid = staff.store_id",'left')
                                    ->join('company company',"company.cid = staff.company_id",'left')
                                    ->page("{$p},{$page_last}")
                                    ->select();
        }
        $Page = new Page($count,$page_last);
        $page= object_to_array($Page);
        $pay_paid['list']=$list;
        $pay_paid['pages']['totalPages']=$page['totalPages'];
        return formt($pay_paid);
       
    }

    /**
     * 地址添加/编辑
     * @param $user_id 用户id
     * @param $user_id 地址id(编辑时需传入)
     * @return array
     */
    public function add_addressc($user_id,$address_id=0,$data){
        $post = $data;
        if($address_id == 0)
        {
            $c = M('UserAddress')->where("user_id", $user_id)->count();
            if($c >= 20)
                return array('status'=>-1,'msg'=>'最多只能添加20个收货地址','result'=>'');
        }

        //检查手机格式
        if($post['consignee'] == '')
            return array('status'=>-1,'msg'=>'收货人不能为空','result'=>'');
        if(!$post['province'] || !$post['city'] || !$post['district'])
            return array('status'=>-1,'msg'=>'所在地区不能为空','result'=>'');
        if(!$post['address'])
            return array('status'=>-1,'msg'=>'地址不能为空','result'=>'');
        if(!check_mobile($post['mobile']))
            return array('status'=>-1,'msg'=>'手机号码格式有误','result'=>'');                
        
        //编辑模式
        if($address_id > 0){
            $address = M('user_address')->where(array('address_id'=>$address_id,'user_id'=> $user_id))->find();
            if($post['is_default'] == 1 && $address['is_default'] != 1)
                M('user_address')->where(array('user_id'=>$user_id))->save(array('is_default'=>0));
            $row = M('user_address')->where(array('address_id'=>$address_id,'user_id'=> $user_id))->save($post);
            if($row !== false){
                return array('status'=>1,'msg'=>'编辑成功','result'=>$address_id);
            }else{
                return array('status'=>-1,'msg'=>'操作完成','result'=>$address_id);
            }
        }

        //添加模式
        $post['user_id'] = $user_id;
        
        // 如果目前只有一个收货地址则改为默认收货地址
        $c = M('user_address')->where("user_id", $post['user_id'])->count();
        if($c == 0)  $post['is_default'] = 1;
        
        $address_id = M('user_address')->add($post);
        //如果设为默认地址
        $insert_id = M('user_address')->getLastInsID();
        $map['user_id'] = $user_id;
        $map['address_id'] = array('neq',$insert_id);
        if($post['is_default'] == 1)
            M('user_address')->where($map)->save(array('is_default'=>0));
        if(!$address_id)
            return array('status'=>-1,'msg'=>'添加失败','result'=>'');
        
        return array('status'=>1,'msg'=>'添加成功','result'=>$address_id);
    }
}
