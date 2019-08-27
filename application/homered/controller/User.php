<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * 2015-11-21
 */
namespace app\homered\controller; 
use app\common\logic\MessageLogic;
use app\common\logic\RedOrderLogic;
use app\common\logic\RedUsersLogic;
use app\common\logic\RedCartLogic;
use app\common\logic\RedCommentLogic;
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
                session_unset();
                session_destroy();
                $this->redirect('Homered/User/login');
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
        $logic = new RedUsersLogic();
        $user  = $logic->get_info($this->user_id);
        $user  = $user['result'];
        $user['pre_last_login'] = $user_old['pre_last_login']; // 上次登录时间
        $user['user_money']     = num_float($user['user_money']); 
        $level = M('user_level')->select();
        $level = convert_arr_key($level,'level_id');

        $commentLogic = new RedCommentLogic;
        $com_num      = $commentLogic->getWaitCommentNum($this->user_id); //待评论数
        $this->assign('com_num',$com_num);

        $where     = 'user_id=:user_id and order_prom_type < 5 ';
        $order_str = "order_id DESC";
        $bind['user_id'] = $this->user_id;
        $order_list = M('order_red')->order($order_str)->where($where)->bind($bind)->limit(3)->select();
        //获取订单商品
        $model = new RedUsersLogic();
        foreach($order_list as $k=>$v)
        {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            // $order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
            if($order_list[$k]['order_prom_type'] == 4){
                $pre_sell_item = M('goods_red_activity')->where(array('act_id'=>$order_list[$k]['order_prom_id']))->find();
                $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
                $order_list[$k]['pre_sell_is_finished']     = $pre_sell_item['is_finished'];
                $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                $order_list[$k]['pre_sell_retainage_end']   = $pre_sell_item['retainage_end'];
            }else{
                $order_list[$k]['pre_sell_is_finished']     = -1; //没有参与预售的订单
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
        $logic = new RedUsersLogic();
        $user  = $logic->get_info($this->user_id);
        $user  = $user['result'];
        $user['pre_last_login'] = $user_old['pre_last_login']; // 上次登录时间
        $user['user_money']     = num_float($user['user_money']); 
        $level = M('user_level')->select();
        $level = convert_arr_key($level,'level_id');

        $commentLogic = new RedCommentLogic;
        $com_num      = $commentLogic->getWaitCommentNum($this->user_id); //待评论数
        $this->assign('com_num',$com_num);

        $where     = 'user_id=:user_id and order_prom_type < 5 ';
        $order_str = "order_id DESC";
        $bind['user_id'] = $this->user_id;
        $order_list = M('order_red')->order($order_str)->where($where)->bind($bind)->limit(3)->select();
        //获取订单商品
        $model = new RedUsersLogic();
        foreach($order_list as $k=>$v)
        {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            // $order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
            if($order_list[$k]['order_prom_type'] == 4){
                $pre_sell_item = M('goods_red_activity')->where(array('act_id'=>$order_list[$k]['order_prom_id']))->find();
                $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
                $order_list[$k]['pre_sell_is_finished']     = $pre_sell_item['is_finished'];
                $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                $order_list[$k]['pre_sell_retainage_end']   = $pre_sell_item['retainage_end'];
            }else{
                $order_list[$k]['pre_sell_is_finished']     = -1; //没有参与预售的订单
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
        $logic = new RedUsersLogic();
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
        $logic = new RedUsersLogic();
        $data = $logic->get_coupon($this->user_id,I('type'));
        // dump($data);
        foreach($data['result'] as $k =>$v){
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if($user_type==1){ //指定商品
                $data['result'][$k]['goods_id'] = M('goods_red_coupon')->field('goods_id')->where(['coupon_id'=>$v['cid']])->getField('goods_id');
            }
            if($user_type==2){ //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_red_coupon')->where(['coupon_id'=>$v['cid']])->getField('goods_category_id');
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
    	         
    	$logic = new RedUsersLogic();
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
            } else {
                setcookie('user_id',$res['result']['user_id'],null,'/');
                setcookie('is_distribut',$res['result']['is_distribut'],null,'/');
                setcookie('uname',urlencode($nickname),null,'/');
                setcookie('cn',0,time()-3600,'/'); 
            }

            $user_data['last_login'] = $res['result']['last_login'];
            $user_data['last_ip']    = GetIP();
            M('users')->where("user_id = ".$res['result']['user_id'])->save($user_data);

            session('session_login_time', $user_data['last_login']); //记录登陆时间
            if($autologin) session('session_user_time', 7*24*3600); 
            else session('session_user_time', 24*3600);  

    		$cartLogic = new RedCartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new RedOrderLogic();
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
            $logic = new RedUsersLogic();
            // 验证码检验
            // $this->verifyHandle('user_reg');
            $username  = I('post.username','');
            $password  = I('post.password','');
            $password2 = I('post.password2','');
            $code  = I('post.code','');
            $scene = I('post.scene', 1);
            $session_id = session_id();
            if(check_mobile($username)){
                if($reg_sms_enable){   //是否开启注册验证码机制
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->error($check_code['msg']);
                    }
                }else{
                    if(!$this->verifyHandle('user_reg')){
                        $this->error('图像验证码错误');
                    };
                }
            }
            if(check_email($username)){
                if($reg_smtp_enable){  //是否开启注册邮箱验证码机制
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if($check_code['status'] != 1){
                        $this->error($check_code['msg']);
                    }
                }else{
                    if(!$this->verifyHandle('user_reg')){
                        $this->error('图像验证码错误');
                    };
                }
            }
            $invite = I('invite');
            if(!empty($invite)){
            	$invite = get_user_info($invite,2);//根据手机号查找邀请人
            }
            $data = $logic->reg($username,$password,$password2,0,$invite);
            if($data['status'] != 1){
                $this->ajaxReturn($data);
            }
            session('user',$data['result']);
    		setcookie('user_id',$data['result']['user_id'],null,'/');
    		setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
            $nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
            setcookie('uname',$nickname,null,'/');
            $cartLogic = new RedCartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('regis_sms_enable',tpCache('sms.regis_sms_enable')); // 注册启用短信：
        $this->assign('regis_smtp_enable',tpCache('smtp.regis_smtp_enable')); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
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
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
		exit();
    }

    /**
    *商品收藏
     */
    public function goods_collect(){
        $userLogic = new RedUsersLogic();
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
        $row = M('goods_red_collect')->where(array('collect_id'=>$id,'user_id'=>$this->user_id))->delete();
        if(!$row)
            $this->error("删除失败");
        $this->success('删除成功');
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
        $comment_info = M('comment_red')->where(array('comment_id' => $comment_id))->find();  //获取点赞用户ID
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {  //判断用户有没点赞过
            $result['success'] = 0;
        } else {
            array_push($comment_user_id_array, $user_id);  //加入用户ID
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;  //点赞数量加1
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment_red')->where(array('comment_id' => $comment_id))->save($comment_data);
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
        $row = M('goods_red_visit')->where(['visit_id'=>$visit_id])->delete();
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
        $count = M('goods_red_visit a')->where($map)->count();
        $Page = new Page($count, 20);
        $visit_list = M('goods_red_visit a')->field("a.*,g.goods_name,g.shop_price")
            ->join('__GOODS_RED__ g', 'a.goods_id = g.goods_id', 'LEFT')
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
            $cateArr = M('goods_red_category')->where(array('id' => array('in', array_unique($cat_ids))))->getField('id,name');
            $cates = M('goods_red_visit a')->field('cat_id,COUNT(cat_id) as csum')->where($map)->group('cat_id')->select();
            foreach ($cates as $k => $v) {
                if (isset($cateArr[$v['cat_id']])) $cates[$k]['name'] = $cateArr[$v['cat_id']];
                $visit_total += $v['csum'];
            }
        }
        $this->assign('visit_total', $visit_total);
        $this->assign('catids', $cates);
        $this->assign('page', $Page->show());
        $this->assign('visit_log', $visit_log); //浏览记录
        return $this->fetch();
    }



    /*我的返利
    作者：TK
    2018年5月29日14:54:53
    */
    function already_rebate(){
        $where['user_id']   =   ['eq',$this->user_id];
        $where['order_status']   =   ['in','2,4'];
        $rebate_status = I('rebate_status',1);
        if($rebate_status == 1){
            $where['(midou_money - shipping_price)']  =   ['exp'," > already_rebate"];
        }else{
            $where['(midou_money - shipping_price)']  =   ['exp'," <= already_rebate"];
        }
        $count = M('order_red')->alias('order')->where($where)->count();
        $page = new Page($count, 10);
        
        $list = M('order_red')->alias('order')->where($where)->order("add_time asc")
                                    ->field("add_time,order_amount,midou_money,shipping_price,already_rebate,order_id")
                                    ->limit($page->firstRow . ',' . $page->listRows)
                                    ->select();

        foreach ($list as $key => $value) {
            $list[$key]['rebate_price']   =   bcsub($value['midou_money'],$value['shipping_price'],4);
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


}