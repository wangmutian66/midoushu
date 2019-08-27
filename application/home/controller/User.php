<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * 2015-11-21
 */
namespace app\home\controller; 
use app\common\logic\MessageLogic;
use app\common\logic\OrderLogic;
use app\common\logic\UsersLogic;
use app\common\logic\CartLogic;
use app\common\logic\CommentLogic;
use think\Page;
use think\Verify;
use think\Db;
class User extends Base{

	public $user_id = 0;
	public $user = array();
	
    public function _initialize() {      
        parent::_initialize();
        if(session('?user'))
        {
            if (time() - session('session_login_time') > session('session_user_time')){
                setcookie('uname','',time()-3600,'/');
                setcookie('cn','',time()-3600,'/');
                setcookie('cnreturn','',time()-3600,'/');
                setcookie('cnred','',time()-3600,'/');
                setcookie('user_id','',time()-3600,'/');
                setcookie('PHPSESSID','',time()-3600,'/');
                session_unset();
                session_destroy();
                $this->redirect('Home/User/login');
                exit;
            }

        	$user = $user_old = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            $user['pre_last_login'] = $user_old['pre_last_login']; // 上次登录时间
            session('user',$user);  //覆盖session 中的 user               
        	$this->user = $user;
        	$this->user_id = $user['user_id'];
        	$this->assign('user',$user); //存储用户信息
        	$this->assign('user_id',$this->user_id);
            //获取用户信息的数量
            $messageLogic = new MessageLogic();
            $user_message_count = $messageLogic->getUserMessageCount();
            $this->assign('user_message_count', $user_message_count);
        }else{
        	$nologin = array(
        			'login','pop_login','do_login','logout','verify','set_pwd','finished',
        			'verifyHandle','reg','send_sms_reg_code','identity','check_validate_code',
                'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code','bind_account','bind_guide','bind_reg',
        	);
        	if(!in_array(ACTION_NAME,$nologin)){
                $this->redirect('Home/User/login');
        		exit;
        	}
        }
        //用户中心面包屑导航
        $navigate_user = navigate_user();
        $this->assign('navigate_user',$navigate_user);        
    }

    /*
     * 用户中心首页
     */
    public function index(){
        $user_old = session('user');
        $logic = new UsersLogic();
        $user  = $logic->get_info($this->user_id);
        $user  = $user['result'];
        $user['pre_last_login'] = $user_old['pre_last_login']; // 上次登录时间
        $user['user_money']     = $user['user_money']; 
        $level = M('user_level')->select();
        $level = convert_arr_key($level,'level_id');

        $commentLogic = new CommentLogic;
        $com_num      = $commentLogic->getWaitCommentNum($this->user_id); //待评论数
        $this->assign('com_num',$com_num);

        $where = ' user_id=:user_id and order_prom_type < 5 ';
        $order_str = "order_id DESC";
        $bind['user_id'] = $this->user_id;
        $order_list = M('order')->order($order_str)->where($where)->bind($bind)->limit(3)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach($order_list as $k=>$v)
        {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
            if($order_list[$k]['order_prom_type'] == 4){
                $pre_sell_item =  M('goods_activity')->where(array('act_id'=>$order_list[$k]['order_prom_id']))->find();
                $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
                $order_list[$k]['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
                $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                $order_list[$k]['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            }else{
                $order_list[$k]['pre_sell_is_finished'] = -1;//没有参与预售的订单
            }
        }

        $this->assign('level',$level);
        $this->assign('user',$user);
        $this->assign('order_list',$order_list);
        return $this->fetch();
    }

    /*
     * 用户中心首页
     */
    public function index2(){
        
        $user_old = session('user');
        $logic = new UsersLogic();
        $user  = $logic->get_info($this->user_id);
        $user  = $user['result'];
        $user['pre_last_login'] = $user_old['pre_last_login']; // 上次登录时间
        $user['user_money']     = $user['user_money']; 
        $level = M('user_level')->select();
        $level = convert_arr_key($level,'level_id');

        $commentLogic = new CommentLogic;
        $com_num      = $commentLogic->getWaitCommentNum($this->user_id); //待评论数
        $this->assign('com_num',$com_num);

        $where = ' user_id=:user_id and order_prom_type < 5 ';
        $order_str = "order_id DESC";
        $bind['user_id'] = $this->user_id;
        $order_list = M('order')->order($order_str)->where($where)->bind($bind)->limit(3)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach($order_list as $k=>$v)
        {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
            if($order_list[$k]['order_prom_type'] == 4){
                $pre_sell_item =  M('goods_activity')->where(array('act_id'=>$order_list[$k]['order_prom_id']))->find();
                $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
                $order_list[$k]['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
                $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                $order_list[$k]['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            }else{
                $order_list[$k]['pre_sell_is_finished'] = -1;//没有参与预售的订单
            }
        }

        $this->assign('level',$level);
        $this->assign('user',$user);
        $this->assign('order_list',$order_list);
        return $this->fetch('index2');
    }


    public function logout(){
    	setcookie('uname','',time()-3600,'/');
    	setcookie('cn','',time()-3600,'/');
        setcookie('cnreturn','',time()-3600,'/');
        setcookie('cnred','',time()-3600,'/');
    	setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        session_unset();
        session_destroy();
        //$this->success("退出成功",U('Home/Index/index'));
        $this->redirect('Home/Index/index');
        exit;
    }

    /*
     * 账户资金
     */
    public function account(){
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id,I('get.type'));
        $account_log = $data['result'];

        $this->assign('user',$user);
        $this->assign('account_log',$account_log);
        $this->assign('page',$data['show']);
        $this->assign('active','account');
        return $this->fetch();
    }
    /*
     * 优惠券列表
     */
    public function coupon(){
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id,I('type'));
     //   dump($data);
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
        $this->assign('coupon_list',$coupon_list);
        $this->assign('page',$data['show']);
        $this->assign('active','coupon');
        return $this->fetch();
    }
    /**
     *  登录
     */
    public function login(){
        if($this->user_id > 0){
            $this->redirect('Home/User/index');
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Home/User/index");
        $this->assign('referurl',$referurl);
        return $this->fetch();
    }

    public function pop_login(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/index');
    	}
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Home/User/index");
        $this->assign('referurl',$referurl);
    	return $this->fetch();
    }
    
    public function do_login(){
        $username    = trim(I('post.username'));
        $password    = trim(I('post.password'));
    	$verify_code = I('post.verify_code');
        $autologin   = I('post.autologin') ? I('post.autologin') : 0;
     
        $verify = new Verify();
        if (!$verify->check($verify_code,'user_login'))
        {
            $res = array('status'=>0,'msg'=>'验证码错误');
            exit(json_encode($res));
        }
    	         
    	$logic = new UsersLogic();
    	$res   = $logic->login($username,$password);

    	if($res['status'] == 1){
    		$res['url'] =  urldecode(I('post.referurl'));
    		session('user',$res['result']);
    		
    		$nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];

            if($autologin){
                setcookie('user_id',$res['result']['user_id'],time()+7*24*3600,'/');
                setcookie('is_distribut',$res['result']['is_distribut'],time()+7*24*3600,'/');
                setcookie('uname',urlencode($nickname),time()+7*24*3600,'/');
                setcookie('cn',0,time()-3600,'/'); 
                setcookie('cnreturn',0,time()-3600,'/');
                setcookie('cnred',0,time()-3600,'/');             
            } else {
                setcookie('user_id',$res['result']['user_id'],null,'/');
                setcookie('is_distribut',$res['result']['is_distribut'],null,'/');
                setcookie('uname',urlencode($nickname),null,'/');
                setcookie('cn',0,time()-3600,'/'); 
                setcookie('cnreturn',0,time()-3600,'/');
                setcookie('cnred',0,time()-3600,'/'); 
            }

            $user_data['last_login'] = $res['result']['last_login'];
            $user_data['last_ip']    = GetIP();
            M('users')->where("user_id = ".$res['result']['user_id'])->save($user_data);

            session('session_login_time', $user_data['last_login']); //记录登陆时间
            if($autologin) session('session_user_time', 7*24*3600); 
            else session('session_user_time', 24*3600);  

    		$cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']); //登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
    	}
    	exit(json_encode($res));
    }
    /**
     *  注册
     */
    public function reg(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/index');
        }
        $reg_sms_enable  = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
        if(IS_POST){
            $logic = new UsersLogic();
            // 验证码检验
            // $this->verifyHandle('user_reg');
            $username  = I('post.username','');
            $password  = I('post.password','');
            $password2 = I('post.password2','');
            $code  = I('post.code','');
            $scene = I('post.scene', 1);
            $paypwd  = I('post.paypwd', '');
            $paypwd2 = I('post.paypwd2', '');
            $session_id = session_id();
            if(check_mobile($username)){
                if($reg_sms_enable){   //是否开启注册验证码机制
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn(array('status'=>-1,'msg'=>$check_code['msg'],'result'=>''));
                    }
                }else{
                    if(!$this->verifyHandle('user_reg')){
                        $this->ajaxReturn(array('status'=>-1,'msg'=>'图像验证码错误','result'=>''));
                    };
                }
            }
            if(check_email($username)){
                if($reg_smtp_enable){  //是否开启注册邮箱验证码机制
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn(array('status'=>-1,'msg'=>$check_code['msg'],'result'=>''));
                    }
                }else{
                    if(!$this->verifyHandle('user_reg')){
                        $this->ajaxReturn(array('status'=>-1,'msg'=>'图像验证码错误','result'=>''));
                    };
                }
            }
            $invite = I('invite');
            if(!empty($invite)){
            	$invite = get_user_info($invite,2);//根据手机号查找邀请人
            }
            $data = $logic->reg($username,$password,$password2,$paypwd,$paypwd2,0,$invite);
            if($data['status'] != 1){
                $this->ajaxReturn($data);
            }
            //session('user',$data['result']);
    		//setcookie('user_id',$data['result']['user_id'],null,'/');
    		//setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
            //$nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
            //setcookie('uname',$nickname,null,'/');
            //$cartLogic = new CartLogic();
            //$cartLogic->setUserId($data['result']['user_id']);
            //$cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('regis_sms_enable',tpCache('sms.regis_sms_enable')); // 注册启用短信：
        $this->assign('regis_smtp_enable',tpCache('smtp.regis_smtp_enable')); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    /*
     * 用户地址列表
     */
    public function address_list(){
        $address_lists = get_user_address_list($this->user_id);
        $region_list = get_region_list();
        $this->assign('region_list',$region_list);
        $this->assign('lists',$address_lists);
        $this->assign('active','address_list');

        //获取省份
        $p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
        $this->assign('province',$p);
        return $this->fetch();
    }
    /*
     * 添加地址
     */
    public function add_address(){
        header("Content-type:text/html;charset=utf-8");
        if(IS_POST){
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id,0,I('post.'));
            if($data['status'] != 1)
                exit('<script>alert("'.$data['msg'].'");history.go(-1);</script>');
            $call_back = $_REQUEST['call_back'];
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功 回调closeWindow方法 并返回新增的id
        }
        $p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
        $this->assign('province',$p);
        return $this->fetch('edit_address');
    }

    public function add_address2(){
        header("Content-type:text/html;charset=utf-8");
        if(IS_POST){
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id,0,I('post.'));
            if($data['status'] != 1) $this->error('操作失败');
            $this->success("操作成功");
        }
    }

    /*
     * 地址编辑
     */
    public function edit_address(){
        header("Content-type:text/html;charset=utf-8");
        $id = I('get.id/d');
        $address = M('user_address')->where(array('address_id'=>$id,'user_id'=> $this->user_id))->find();
        if(IS_POST){
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id,$id,I('post.'));
            if($data['status'] != 1)
                exit('<script>alert("'.$data['msg'].'");history.go(-1);</script>');

            $call_back = $_REQUEST['call_back'];
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功 回调closeWindow方法 并返回新增的id
        }
        //获取省份
        $p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
        $c = M('region')->where(array('parent_id'=>$address['province'],'level'=> 2))->select();
        $d = M('region')->where(array('parent_id'=>$address['city'],'level'=> 3))->select();
        if($address['twon']){
        	$e = M('region')->where(array('parent_id'=>$address['district'],'level'=>4))->select();
        	$this->assign('twon',$e);
        }

        $this->assign('province',$p);
        $this->assign('city',$c);
        $this->assign('district',$d);
        $this->assign('address',$address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default(){
        $id = I('get.id/d');
        M('user_address')->where(array('user_id'=>$this->user_id))->save(array('is_default'=>0));
        $row = M('user_address')->where(array('user_id'=>$this->user_id,'address_id'=>$id))->save(array('is_default'=>1));
        if(!$row)
            $this->error('操作失败');
        $this->success("操作成功");
    }
    
    /*
     * 地址删除
     */
    public function del_address(){
        $id = I('get.id/d');
        
        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id'=>$this->user_id,'address_id'=>$id))->delete();                
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if($address['is_default'] == 1)
        {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default'=>1));
        }        
        if(!$row)
            $this->error('操作失败',U('User/address_list'));
        else
            $this->success("操作成功",U('User/address_list'));
    }


    public function save_pickup()
    {
        $post = I('post.');
        if (empty($post['consignee'])) {
            return array('status' => -1, 'msg' => '收货人不能为空', 'result' => '');
        }
        if (!$post['province'] || !$post['city'] || !$post['district']) {
            return array('status' => -1, 'msg' => '所在地区不能为空', 'result' => '');
        }
        if(!check_mobile($post['mobile'])){
            return array('status'=>-1,'msg'=>'手机号码格式有误','result'=>'');
        }
        if(!$post['pickup_id']){
            return array('status'=>-1,'msg'=>'请选择自提点','result'=>'');
        }

        $user_logic = new UsersLogic();
        $res = $user_logic->add_pick_up($this->user_id, $post);
        if($res['status'] != 1){
            exit('<script>alert("'.$res['msg'].'");history.go(-1);</script>');
        }
        $call_back = $_REQUEST['call_back'];
        echo "<script>parent.{$call_back}({$post['province']},{$post['city']},{$post['district']});</script>";
        exit(); // 成功 回调closeWindow方法 并返回新增的id
    }

    /*
     * 个人信息
     */
    public function info(){
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if(IS_POST){
            I('post.nickname')  ? $post['nickname']  = I('post.nickname')            : false;             //昵称
            I('post.real_name') ? $post['real_name'] = I('post.real_name')           : false;             //真实姓名
            I('post.qq')        ? $post['qq']        = I('post.qq')                  : false;             //QQ号码
            I('post.head_pic')  ? $post['head_pic']  = I('post.head_pic')            : false;             //头像地址
            I('post.sex')       ? $post['sex']       = I('post.sex')                 : $post['sex'] = 0;  // 性别
            I('post.birthday')  ? $post['birthday']  = strtotime(I('post.birthday')) : false;             // 生日
            I('post.province')  ? $post['province']  = I('post.province')            : false;             //省份
            I('post.city')      ? $post['city']      = I('post.city')                : false;             // 城市
            I('post.district')  ? $post['district']  = I('post.district')            : false;             //地区
            if(!$userLogic->update_info($this->user_id,$post))
                $this->error("保存失败");
            setcookie('uname',urlencode($post['nickname']),null,'/');
            $this->success("操作成功");
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取订单城市
        $city =  M('region')->where(array('parent_id'=>$user_info['province'],'level'=>2))->select();
        //获取订单地区
        $area =  M('region')->where(array('parent_id'=>$user_info['city'],'level'=>3))->select();

        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);
        $this->assign('user',$user_info);
        $this->assign('sex',C('SEX'));
        $this->assign('active','info');
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate(){
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step',1);
        if(IS_POST){
            $email = I('post.email');
            $old_email = I('post.old_email',''); //旧邮箱
            $code = I('post.code');
            $info = session('validate_code');
            if(!$info)
                $this->error('非法操作');
            if($info['time']<time()){
            	session('validate_code',null);
            	$this->error('验证超时，请重新验证');
            }
            //检查原邮箱是否正确
            if($user_info['email_validated'] == 1 && $old_email != $user_info['email'])
                $this->error('原邮箱匹配错误');
            //验证邮箱和验证码
            if($info['sender'] == $email && $info['code'] == $code){
                session('validate_code',null);
                if(!$userLogic->update_email_mobile($email,$this->user_id))
                    $this->error('邮箱已存在');
                $this->success('绑定成功',U('Home/User/index'));
                exit;
            }
            $this->error('邮箱验证码不匹配');
        }
        $this->assign('user_info',$user_info);
        $this->assign('step',$step);
        return $this->fetch();
    }


    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); //获取用户信息
        $user_info = $user_info['result'];
        $config = tpCache('sms');
        $sms_time_out = $config['sms_time_out'];
        $step = I('get.step', 1);
        if (IS_POST) {
            $mobile = I('post.mobile');
            $old_mobile = I('post.old_mobile');
            $code = I('post.code');
            $scene = I('post.scene', 6);
            $session_id = I('unique_id', session_id());

            $logic = new UsersLogic();
            $res = $logic->check_validate_code($code, $mobile, 'phone', $session_id, $scene);

            if (!$res && $res['status'] != 1) $this->error($res['msg']);

            //检查原手机是否正确
            if ($user_info['mobile_validated'] == 1 && $old_mobile != $user_info['mobile'])
                $this->error('原手机号码错误');
            //验证手机和验证码

            if ($res['status'] == 1) {
                //验证有效期
                if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                    $this->error('手机已存在');
                $this->success('绑定成功', U('Home/User/index'));
                exit;
            } else {
                $this->error($res['msg']);
            }

        }
        $this->assign('time', $sms_time_out);
        $this->assign('step', $step);
        $this->assign('user_info', $user_info);
        return $this->fetch();
    }

    /*
     *商品收藏
     */
    public function goods_collect(){
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page',$data['show']);// 赋值分页输出
        $this->assign('lists',$data['result']);
        $this->assign('active','goods_collect');
        return $this->fetch();
    }

    /*
     * 删除一个收藏商品
     */
    public function del_goods_collect(){
        $id = I('get.id/d');
        if(!$id)
            $this->error("缺少ID参数");
        $row = M('goods_collect')->where(array('collect_id'=>$id,'user_id'=>$this->user_id))->delete();
        if(!$row)
            $this->error("删除失败");
        $this->success('删除成功');
    }

    /*
     * 密码修改
     */
    public function password(){
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if($user['mobile'] == '')
            $this->error('请先绑定手机',U('Home/User/info'));
        return $this->fetch();
    }

    public function password2(){
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];

        $check = session('validate_code');
        if (empty($check)) {
            $this->error('验证码还未验证通过', U('Home/User/password'));
        }

        if(IS_POST){
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id,I('post.old_password'),I('post.new_password'),I('post.confirm_password')); // 获取用户信息
            if($data['status'] == -1)
                $this->error($data['msg']);
            $this->success($data['msg'], 'User/password3');
            exit;
        }
        return $this->fetch();
    }

    public function password3(){
        return $this->fetch();
    }

    //忘记密码
    public function forget_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('Home/User/index'));
        }
        return $this->fetch();
    }
    
    public function set_pwd(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/Index');
    	}
    	$check = session('validate_code');
      
    	$logic = new UsersLogic();
    	if(empty($check)){
            $this->redirect('Home/User/forget_pwd');
    	}elseif($check['is_check']==0){
    		$this->error('验证码还未验证通过',U('Home/User/forget_pwd'));
    	}    	
    	if(IS_POST){
    		$password = I('post.password');
    		$password2 = I('post.confirm_password');
    		if($password2 != $password){
    			$this->error('两次密码不一致',U('Home/User/forget_pwd'));
    		}
    		if($check['is_check']==1){
                $user = M('users')->where("mobile|email", '=', $check['sender'])->find();
    			M('users')->where("user_id", $user['user_id'])->save(array('password'=>encrypt($password)));
    			session('validate_code',null);
                $this->redirect('Home/User/finished');
    		}else{
    			$this->error('验证码还未验证通过',U('Home/User/forget_pwd'));
    		}
    	}
    	return $this->fetch();
    }
    
    public function finished(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/index');
    	}
    	return $this->fetch();
    }   
    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        $data = I('post.');
        $userLogic = new \app\common\logic\UsersLogic();
        $user['mobile'] = $data['mobile'];
        $user['password'] = encrypt($data['password']);
        
        $verify = new Verify();
        if (!$verify->check($data['verify_code'], 'user_reg')) {
            $this->error("绑定失败,图像验证码有误");
        }
        //halt($userLogic);
        $res = $userLogic->oauth_bind_new($user); 
        if ($res['status'] == 1) {
            $res['url'] = urldecode(I('post.referurl'));
            $res['result']['nickname'] = empty($res['result']['nickname']) ? $res['result']['mobile'] : $res['result']['nickname'];
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            setcookie('uname', urlencode($res['result']['nickname']), null, '/');
            setcookie('head_pic', urlencode($res['result']['head_pic']), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            setcookie('cnred', 0, time() - 3600, '/');
            setcookie('cnreturn', 0, time() - 3600, '/');
            session('user', $res['result']);
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
            return $this->success("绑定成功", U('Home/User/index'));
        }else{
            return $this->error("绑定失败,失败原因:".$res['msg']);
        }
    }
    
    public function bind_guide(){
        
        $data = session('third_oauth');
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);
        
        return $this->fetch();
    }
    
    /**
     * 先注册再绑定账号
     * @return \think\mixed
     */
    public function bind_reg()
    {  
        if(IS_POST){ 
            $reg_sms_enable = tpCache('sms.regis_sms_enable');
            $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
            $thirdUser = session('third_oauth'); 
            $logic = new UsersLogic();
            //验证码检验
            $nickname = I('post.nickname', '');
            $username = I('post.mobile', ''); 
            $password = I('post.password', '');
            $password2 = I('post.pwdRepeat', '');
            $code = I('post.sms_code', '');
            $scene = I('post.scene', 1);
            $verify_code = I('post.verify_code', 1);
            $thirdUser && $head_pic = $thirdUser['head_pic'];
            $session_id = session_id();
            if(check_mobile($username)){
                if($reg_sms_enable){   //是否开启注册验证码机制
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->error($check_code['msg']);
                    }
                } 
            }
            $data = $logic->reg($username, $password, $password2,0,'',$nickname,$head_pic);
            if ($data['status'] != 1) {
                $this->error($data['msg']);
            }
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
            setcookie('uname', $nickname, null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
            
            //用户注册成功后, 绑定第三方账号
            $result = $logic->oauth_bind_new($data['result']);
            if($result['status']== -1){
                $this->error($result['msg']);
            }
            
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Home/User/index");
            if (isMobile()){
                $this->success($result['msg'], U('Home/index/index'));
            }else{
                $this->success($result['msg'], U('Home/index/index'));
            }
        } 
        return $this->fetch("bind_guide");
    }
    
    public function bind_auth()
    {
 
        $list = M('plugin')->cache(true)->where(array('type' => 'login', 'status' => 1))->select();
        if ($list) {
            foreach ($list as $val) {
                $val['is_bind'] = 0;
                
                $thridUser = M('OauthUsers')->where(array('user_id'=>$this->user['user_id'] , 'oauth'=>$val['code']))->find();
                 if ($thridUser) {
                    $val['is_bind'] = 1;
                }
                $val['bind_url'] = U('LoginApi/login', array('oauth' => $val['code']));
                $val['bind_remove'] = U('User/bind_remove', array('oauth' => $val['code']));;
                $val['config_value'] = unserialize($val['config_value']);
                $lists[] = $val;
            }
        }
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function bind_remove()
    {
        $oauth = I('oauth'); 
        $row = M('OauthUsers')->where(array('user_id' => $this->user_id , 'oauth'=>$oauth))->delete();
        if ($row) {
            $this->success('解除绑定成功', U('Home/User/bind_auth'));
        } else {
            $this->error('解除绑定失败', U('Home/User/bind_auth'));
        }
        
    }
    public function check_captcha(){
    	$verify = new Verify();
    	$type = I('post.type','user_login');
    	if (!$verify->check(I('post.verify_code'), $type)) {
    		exit(json_encode(0));
    	}else{
    		exit(json_encode(1));
    	}
    }
    
    public function check_username(){
    	$username = I('post.username');
    	if(!empty($username)){
    		$count = M('users')->where("email", $username)->whereOr('mobile', $username)->count();
    		exit(json_encode(intval($count)));
    	}else{
    		exit(json_encode(0));
    	}  	
    }

    public function identity()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('Home/User/Index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('userinfo', $user);
        return $this->fetch();
    }
      
    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        $result = $verify->check(I('post.verify_code'), $id ? $id : 'user_login');
        if (!$result) {
            return false;
        }else{
            return true;
        }
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'codeSet'  =>  '0123456789',
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => false,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
		exit();
    }

    /**
     * 安全设置
     */
    public function safety_settings()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];        
      #  dump($user_info);die;
        $this->assign('user',$user_info);
        return $this->fetch();
    }
    
    /**
     * 申请提现记录
     */
    public function withdrawals(){

        $user_id  = $this->user_id;
        $userinfo = M('users')->where('user_id ='.$user_id)->find();

    	if(IS_POST)
    	{
            if(!$this->verifyHandle('withdrawals')){
                $this->ajaxReturn(['status'=>0,'msg'=>'图像验证码错误']);
            };
    		$data = I('post.');
    		$data['user_id'] = $this->user_id;    		    		
    		$data['create_time'] = time();                
                $distribut_min = tpCache('basic.min'); // 最少提现额度
                $service_fee   = tpCache('basic.service_fee'); // 会员提现手续费
                $data['taxfee'] = $data['money']*$service_fee/100; // 手续费
                $total = $data['money']+$data['taxfee']; // 总
                if($data['money'] < $distribut_min)
                {
                    $this->ajaxReturn(['status'=>0,'msg'=>'每次最少提现额度'.$distribut_min]);
                        exit;
                }
                if( $total > $this->user['user_money'])
                {
                    //$this->ajaxReturn(['status'=>0,'msg'=>"你最多可提现{$this->user['user_money']}账户余额."]);
                    $this->ajaxReturn(['status'=>0,'msg'=>"抱歉，您的余额不足"]);
                    exit;
                }
            if(encrypt($data['paypwd']) != $this->user['paypwd']){
                $this->ajaxReturn(['status'=>0,'msg'=>"支付密码错误"]);
            }

    		if(M('withdrawals')->add($data)){
                accountLog($this->user_id, (-1 * $total), 0, 0, '会员提现申请');
                $up_data['frozen_money'] = $userinfo['frozen_money']+$total;
                M('users')->where('user_id ='.$this->user_id)->update($up_data);
                $this->ajaxReturn(['status'=>1,'msg'=>"已提交申请",'url'=>U('User/recharge',['type'=>2])]);
                exit;
    		}else{
                $this->ajaxReturn(['status'=>1,'msg'=>'提交失败,联系客服!']);
                exit;
    		}
    	}

        $Userlogic = new UsersLogic();
        $result=$Userlogic->get_withdrawals_log($this->user_id);  //提现记录
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        $this->assign('service_fee',tpCache('basic.service_fee'));
        $this->assign('distribut_min',tpCache('basic.min'));
         $withdrawals = M('withdrawals')->where('user_id ='.$user_id)->select();
		$res = array(); //想要的结果
		foreach ($withdrawals as $k => $v) {
		   $res[$v['bank_name']][] = $v;
		}
        $this->assign('withdrawals',$res);
        return $this->fetch();
    }
    public  function withdrawalsajax(){
    	$user_id  = $this->user_id;
        $b = I('post.b');
        $withdrawals1 = M('withdrawals')->where('user_id ='.$user_id)->select();
        if($b=="支付宝，农业银行，工商银行等..."){
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
    // 账户余额
    public  function recharge(){
       $type = I('type');
       $Userlogic = new UsersLogic();
       if($type == 1){
           $result=$Userlogic->get_account_log($this->user_id);  //用户资金变动记录
       }else if($type == 2){
           $result=$Userlogic->get_withdrawals_log($this->user_id);  //提现记录
        #   dump($result);die;
       }else{
           $result=$Userlogic->get_recharge_log($this->user_id);  //充值记录
       }
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
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

        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,2)")->select();
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
        $this->assign('bankCodeList',$bankCodeList);

        return $this->fetch();
    }


    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        return $this->fetch('user/message_notice');
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
        if ($type == 0) {
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
        return $this->fetch('user/ajax_message_notice');
    }

    /**
     * ajax用户消息通知请求
     */
    public function set_message_notice()
    {
        $type   = I('type');
        $msg_id = I('msg_id');
        $status = I('status');
        $user_logic = new UsersLogic();
        $res = $user_logic->setMessageForRead($type,$msg_id,$status);
        $this->ajaxReturn($res);
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd_index()
    {
        return $this->fetch();
    }
    public function paypwd()
    {
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if(strrchr($_SERVER['HTTP_REFERER'],'/') =='/cart2.html'){  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl',U('Mobile/Cart/cart2'));
        }
        if ($user['mobile'] == '' || $user['mobile_validated'] == 0)
            $this->error('请先绑定手机', U('User/userinfo',['action'=>'mobile']));
        $step = I('step', 1);
      
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('Home/User/paypwd'));
            }
        }
        if (IS_POST && $step == 3) {
            $userLogic = new UsersLogic();
            $data = I('post.');
            $data = $userLogic->paypwd($this->user_id, I('new_password'), I('confirm_password'));
            if ($data['status'] == -1)
                $this->error($data['msg']);
            //$this->success($data['msg']);
            $this->redirect(U('Home/User/paypwd', array('step' => 3)));
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }
    //未设置支付密码提示时绑定支付密码
     public function paypwded()
    {
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        $order_sn = I('order_sn');
        // dump($order_sn);die();
        if(strrchr($_SERVER['HTTP_REFERER'],'/') =='/cart2.html'){  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl',U('Mobile/Cart/cart2'));
        }
        if ($user['mobile'] == '' || $user['mobile_validated'] == 0)
            $this->error('请先绑定手机', U('User/userinfo',['action'=>'mobile']));
        $step = I('step', 1);
      
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('Home/User/paypwded'));
            }
        }
        
        if (IS_POST && $step == 3) {

            $userLogic = new UsersLogic();
            $data = I('post.');
            $data = $userLogic->paypwd($this->user_id, I('new_password'), I('confirm_password'));
            if ($data['status'] == -1){
                $this->error($data['msg']);
            }
            $this->redirect(U('Home/User/paypwded', array('step' => 3,'order_sn'=>$order_sn)));
            //$this->success($data['msg']);
            exit;
        }
        $this->assign('step', $step);
        $this->assign('order_sn', $order_sn);
        return $this->fetch();
    }

    //米商城未设置支付密码提示时绑定支付密码
     public function paypwdedred()
    {
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        $order_sn = I('order_sn');
        // dump($order_sn);die();
        if(strrchr($_SERVER['HTTP_REFERER'],'/') =='/cart2.html'){  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl',U('Mobilered/Cart/cart2'));
        }
        if ($user['mobile'] == '' || $user['mobile_validated'] == 0)
            $this->error('请先绑定手机', U('User/userinfo',['action'=>'mobile']));
        $step = I('step', 1);
      
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('Home/User/paypwdedred'));
            }
        }
        
        if (IS_POST && $step == 3) {

            $userLogic = new UsersLogic();
            $data = I('post.');
            $data = $userLogic->paypwd($this->user_id, I('new_password'), I('confirm_password'));
            if ($data['status'] == -1){
                $this->error($data['msg']);
            }
            $this->redirect(U('Home/User/paypwdedred', array('step' => 3,'order_sn'=>$order_sn)));
            //$this->success($data['msg']);
            exit;
        }
        $this->assign('step', $step);
        $this->assign('order_sn', $order_sn);
        return $this->fetch();
    }


    //申请成为供货商
    public function apply_suppliers(){
        $suppliers = get_suppliers_info_uid($this->user_id);
        if(!$suppliers){
            $suppliers_type = I('get.suppliers_type') ? I('get.suppliers_type') : 1;
            //  获取省份
            $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
            //  获取订单城市
            $city = M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();
            if (IS_POST){
                $data = I('post.');
                $buchong_img = serialize(I('buchong/a')); // 补充资质
                $data['buchong']  = $buchong_img;
                $data['add_time'] = time();
                $res = M('suppliers')->add($data);
                if($res){
                    $this->success('添加成功',U('User/apply_suppliers'));exit;
                }else{
                    $this->error('添加失败,'.$res['msg']);
                }
            }

            $url1 = U('Home/User/apply_suppliers',array('suppliers_type'=>1));
            $url2 = U('Home/User/apply_suppliers',array('suppliers_type'=>2));

            $levelList = M('suppliers_level')->order('level_id')->select();
            $this->assign('levelList', $levelList);
            $this->assign('province',$province);
            $this->assign('city',$city);
            $this->assign('suppliers_type', $suppliers_type);
            $this->assign('url1',$url1);
            $this->assign('url2',$url2);
            return $this->fetch();
        }else{
            $this->assign('is_check', $suppliers['is_check']);
            $this->assign('supplier_remark', $suppliers['supplier_remark']);
            $this->assign('status', $suppliers['status']);
            $this->assign('suppliers_password', $suppliers['suppliers_password']);
            $this->assign('suppliers_type', $suppliers['suppliers_type']);
            return $this->fetch('suppliers');
        }
    }

    public function detail_suppliers(){
        $user_id  = $this->user_id;
        $supplier = D('suppliers')->where(array('user_id'=>$user_id))->find();
        if(!$supplier)
            exit($this->error('信息有误，请联系管理员'));

        $supplier['buchongImages'] = unserialize($supplier['buchong']); // 补充资质

        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取城市
        $city =  M('region')->where(array('parent_id'=>$supplier['province_id'],'level'=>2))->select();
        $suppliers_type = I('get.suppliers_type') ? I('get.suppliers_type') : 1;
        
        if(IS_POST){
            //  供货商信息编辑
            if(!empty($_POST['suppliers_phone']))
            {   $suppliers_phone = trim($_POST['suppliers_phone']);
                $c = M('suppliers')->where("user_id != $user_id and suppliers_phone = '$suppliers_phone'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }            
            $buchong_img = serialize(I('buchong/a')); // 补充资质
            $_POST['buchong']  = $buchong_img;
            
            $row = M('suppliers')->where(array('user_id'=>$user_id))->save($_POST);
            if($row){
                $this->success('修改成功',U('User/apply_suppliers'));exit;
            }

            $this->error('未作内容修改或修改失败');
        }

        $url1 = U('Home/User/detail_suppliers',array('suppliers_type'=>1));
        $url2 = U('Home/User/detail_suppliers',array('suppliers_type'=>2));

        $levelList = M('suppliers_level')->order('level_id')->select();
        $this->assign('levelList', $levelList);
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('supplier',$supplier);
        $this->assign('suppliers_type', $suppliers_type);
        $this->assign('url1',$url1);
        $this->assign('url2',$url2);
        return $this->fetch('apply_suppliers');
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

            $logic = new UsersLogic();
            $data = $logic->get_info($this->user_id);
            $user = $data['result'];

            if($_POST['suppliers_password'] == $user['password']){
                exit($this->error('不可与会员登录密码一样'));
            }

            $row = M('suppliers')->where(array('user_id'=>$user_id))->save($_POST);
            if($row){
                $this->success('设置成功',U('User/apply_suppliers'),3);
            }
            $this->error('设置失败，请联系管理员'); 
        }

        $this->assign('supplier',$supplier);
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
     * 删除足迹
     * @author lxl
     * @time  17-4-20
     * 拷多商家User控制器
     */
    public function del_visit_log(){
        $visit_id = I('visit_id/d' , 0);
        $row = M('goods_visit')->where(['visit_id'=>$visit_id])->delete();
        if($row>0){
            $this->ajaxReturn(['status'=>1 , 'msg'=> '删除成功']);
        }else{
            $this->ajaxReturn(['status'=>-1 , 'msg'=> '删除失败']);
        }
    }

    /**
     * 我的足迹
     * @author lxl
     * @time  17-4-20
     * 拷多商家User控制器
     * */
    public function visit_log()
    {
        $cat_id = I('cat_id', 0);
        $map['user_id'] = $this->user_id;
        if ($cat_id > 0) $map['a.cat_id'] = $cat_id;
        $count = M('goods_visit a')->where($map)->count();
        $Page = new Page($count, 20);
        $visit_list = M('goods_visit a')->field("a.*,g.goods_name,g.shop_price")
            ->join('__GOODS__ g', 'a.goods_id = g.goods_id', 'LEFT')
            ->where($map)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('a.visittime desc')
            ->select();
        $visit_log = $cates = array();
        $visit_total = 0;
        if ($visit_list) {
            $now = time();
            $endLastweek = mktime(23, 59, 59, date('m'), date('d') - date('w') + 7 - 7, date('Y'));
            $weekarray = array("日", "一", "二", "三", "四", "五", "六");
            foreach ($visit_list as $k => $val) {
                if ($now - $val['visittime'] < 3600 * 24 * 7) {
                    if (date('Y-m-d') == date('Y-m-d', $val['visittime'])) {
                        $val['date'] = '今天';
                    } else {
                        if ($val['visittime'] < $endLastweek) {
                            $val['date'] = "上周" . $weekarray[date("w", $val['visittime'])];
                        } else {
                            $val['date'] = "周" . $weekarray[date("w", $val['visittime'])];
                        }
                    }
                } else {
                    $val['date'] = '更早以前';
                }
                $cat_ids[] = $val['cat_id'];
                $visit_log[$val['date']][] = $val;
            }
            $cateArr = M('goods_category')->where(array('id' => array('in', array_unique($cat_ids))))->getField('id,name');
            $cates = M('goods_visit a')->field('cat_id,COUNT(cat_id) as csum')->where($map)->group('cat_id')->select();
            foreach ($cates as $k => $v) {
                if (isset($cateArr[$v['cat_id']])) $cates[$k]['name'] = $cateArr[$v['cat_id']];
                $visit_total += $v['csum'];
            }
        }
        $this->assign('visit_total', $visit_total);
        $this->assign('catids', $cates);
        $this->assign('page', $Page->show());
        $this->assign('visit_log', $visit_log); //浏览记录
        #  dump( $visit_log);die;
        return $this->fetch();
    }


    /*
    我的红包
    作者：TK
    2018年5月28日16:01:24
    */

    function red_envelope(){
        $where['red.user_id']   =   ['eq',$this->user_id];
        $count = M('red_envelope')->alias('red')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('red_envelope')->alias('red')->where($where)->field('red.*,order.order_sn')
                                ->join('order order','order.order_id = red.order_id','left')
                                ->limit($page->firstRow . ',' . $page->listRows)
                                ->order("red.id desc")
                                ->select();
       /* if($list){
            foreach ($list as $key => $value) {
                $ids[]  =   $value['order_id'];
            }
            $order_goods = M('order_goods')->where('order_id','in',$ids)->select_key('order_id');
            foreach ($list as $key => $value) {
                $list[$key]['goods']  =   $order_goods[$value['order_id']];
            }
        }*/
        $this->assign('list',$list);
        $this->assign('page', $page);
        return $this->fetch();
    }

    /*我的返利
    作者：TK
    2018年5月29日14:54:53
    */
    function already_rebate(){
        $where['order.user_id']   =   ['eq',$this->user_id];
        $where['order.order_status']   =   ['in','2,4'];
        $where['is_allreturn']  =['eq',1];
        $rebate_status = I('rebate_status/d',1);
        if($rebate_status == 1){
            $where['(order.order_amount - order.shipping_price)']  =   ['exp'," > order.already_rebate"];
        }else{
            $where['(order.order_amount - order.shipping_price)']  =   ['exp'," <= order.already_rebate"];
        }

        $count = M('order')->alias('order')->where($where)->count();
        $page = new Page($count, 10);
        
        $list = M('order')->alias('order')->where($where)->order("add_time asc")
                                    ->field("order.add_time,is_forward,order.order_amount,order.shipping_price,order.already_rebate,order.order_id,order_old.id old_id,order_old.order_amount old_amount,order_old.shipping_price old_shpping,order_old.already_rebate old_rebate,total_rebate")
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    ->join('order_old_rebate order_old','order.order_id = order_old.order_id','left')
                                    ->select();
        foreach ($list as $key => $value) {
            $list[$key]['tx_rebate'] = $value['total_rebate'] - $value['old_rebate'];
            $list[$key]['rebate_price']   =   bcsub($value['order_amount'],$value['shipping_price'],4);
            if($value['already_rebate'] != 0){
                $list[$key]['progress_bar']   =   intval($value['already_rebate'] / $list[$key]['rebate_price'] * 100);
            }else{
                $list[$key]['progress_bar']   =   0;
            }
        }
        #  dump($list );
        $this->assign('list',$list);
        $this->assign('page', $page);
        return $this->fetch();   
    }

    /*线下消费记录*/
    function pay_paid(){
        $t = I('get.t/d',1);
        $where['a.user_id']  = ['eq',$this->user_id];
        $where['pay_status'] = ['eq',1];
        if($t == 1){
            $count = db('staff_paid')->alias('a')->where($where)->count();
            $page = new Page($count, 10);
            $list = M('staff_paid')->alias('a')->where($where)
                                     ->order("id desc")
                                    ->field("a.*,staff.real_name staff_name,store.cname store_name,company.cname company_name")
                                    ->join('staff staff',"staff.id = a.staff_id",'left')
                                    ->join('company store',"store.cid = staff.store_id",'left')
                                    ->join('company company',"company.cid = staff.company_id",'left')
                                    ->join('users u','u.user_id = a.user_id','left')
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    ->select();
        # dump($list);die;
        }else{
            $count = db('staff_mypays')->alias('a')->where($where)->count();
            $page = new Page($count, 10);
            $list = M('staff_mypays')->alias('a')->where($where)
                                     ->order("id desc")
                                    ->field("a.*,staff.real_name staff_name,store.cname store_name,company.cname company_name")
                                    ->join('staff staff',"staff.id = a.staff_id",'left')
                                    ->join('company store',"store.cid = staff.store_id",'left')
                                    ->join('company company',"company.cid = staff.company_id",'left')
                                    ->join('users u','u.user_id = a.user_id','left','left')
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    ->select();
        }
        
        $this->assign('list',$list);
        $this->assign('page', $page);
        return $this->fetch();   
    }

 /*退换货不知道怎么回事 会跳转到这里来*/
    function return_goods_info(){
        $url = U("/Home/Order/return_goods_info/",['id'=>I('id/d')]);
        header("Location:{$url}");
    }
    /*提现到现金余额*/
    function toCash(){
        $where['user_id']   =   ['eq',$this->user_id];
        $count = M('tocash')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('tocash')->alias('cash')->where($where)->order("id desc")
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    ->select();
        $this->assign('list',$list);
        $this->assign('page', $page);

       /* $order_count = M('newtocash')->where($where)->count();
        $order_page = new Page($order_count, 10);
        $orderlist = M('newtocash')->alias('cash')->where($where)->order("id desc")
                                    ->limit($order_page->firstRow . ',' . $order_page->listRows)
                                    ->select();
        $backdatelimit = db('backdatelimit')->order('sort asc')->cache(true)->select();
        foreach ($orderlist as $key => $value) {
            foreach ($backdatelimit as $k => $v) {
                if($v['start_date'] < $value['add_time'] && $v['end_date'] > $value['add_time']){
                    $orderlist[$key]['ready']    =  $value['money'] * $v['proportion'] / 100;
                    $orderlist[$key]['midou']    =  $value['money'] * $v['backmidou'] / 100;
                }
            }
        }
        $this->assign('orderlist',$orderlist);
        $this->assign('order_page', $order_page);
*/
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

    /*
    2.0版 提现到现金余额
    */
    function order_do_cash(){
        $order_id = I('get.order_id/d');
        $old_where['order_id'] = ['eq',$order_id];
        $old_where['user_id']   =   ['eq',$this->user_id];
        $old_order = db('order_old_rebate')->where($old_where)->find();
        if($old_order){
            //老系统的订单结算
            #如果有记录     如果这笔记录已经返完
            if($old_order['already_rebate'] == $old_order['total_rebate']){
                $res['status']  =   0;
                $res['info']    =   '该笔订单请在账户余额中提现！';
                $this->ajaxReturn($res);
            }else{
                //获取应返利金额
                $money_cash =   $old_order['total_rebate'] - $old_order['already_rebate'];
            }

        }
        //这是新系统的订单
        $where['already_rebate'] = ['exp',' = (order_amount - shipping_price)'];
        $where['user_id']   =   ['eq',$this->user_id];
        $where['order_id']  =   ['eq',$order_id];
        $r = M('order')->where($where)->where('is_forward = 0')->field('order_sn,order_amount,shipping_price,already_rebate,add_time')->find();
        if($r){
            M('order')->where($where)->setField('is_forward',1);
            $data['order_id']   =   $order_id;
            $data['order_sn']   =   $r['order_sn'];
            $data['user_id']   =   $this->user_id;
            $data['money']   =  $money_cash ? $money_cash : $r['already_rebate'];
            $data['status']   =   0;
            $data['admin_id']   =   0;
            $data['create_time']   =   NOW_TIME;
            $data['update_time']   =   NOW_TIME;
            $data['order_amount']   =   $r['order_amount'];
            $data['shipping_price']   =   $r['shipping_price'];
            $data['already_rebate']   =   $r['already_rebate'];
            $data['add_time']   =   $r['add_time'];
            $insert_r = db('newtocash')->insert($data);
            if($insert_r > 0){
                $res['status']  =   1;
                $res['info']    =   '您的提现申请已经提交，请等待管理人员审核';
            }else{
                $res['status']  =   0;
                $res['info']    =   '系统繁忙，请联系客服人员！';
            }
        }else{
            $res['status']  =   0;
            $res['info']    =   '您的该笔订单提现失败，请联系客服，并提供单号以供查询';
        }
        $this->ajaxReturn($res);
    }

    /*查询失败原因，并且修改改订单状态*/
    public function cash_remark(){
        $order_id = I('post.order_id/d');
        if($order_id){
            $where['order_id']  =   ['eq',$order_id];
            $where['user_id']   =   ['eq',$this->user_id];
            $order_result = db('newtocash')->where($where)->order('id desc')->getField('remark');
            db('order')->where($where)->setField('is_forward',0);
            $res['status']  =   1;
            $res['info']    =   $order_result ? $order_result : '请联系网站客服';
            $this->ajaxReturn($res);
        }
        
    }

}