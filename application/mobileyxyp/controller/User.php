<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\mobileyxyp\controller;
use app\common\logic\YxypCartLogic;
use app\common\logic\MessageLogic;
use app\common\logic\YxypUsersLogic;
use app\common\logic\YxypOrderLogic;
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
            if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && $is_bind_account){
                header("location:" . U('/Mobile/User/bind_guide'));  //微信浏览器, 调到绑定账号引导页面
            }else{
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
        $logic = new YxypUsersLogic();
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
        $logic = new YxypUsersLogic();
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
        $logic = new YxypUsersLogic();
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

   
   

    public function express()
    {
        $order_id = I('get.order_id/d', 195);
        $order_goods = M('order_yxyp_goods')->where("order_id", $order_id)->select();
        $delivery = M('delivery_doc')->where("order_id", $order_id)->find();
        $this->assign('order_goods', $order_goods);
        $this->assign('delivery', $delivery);
        return $this->fetch();
    }

  


   
  
    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new YxypUsersLogic();
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
        if (M('goods_yxyp_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
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
        $user_logic = new YxypUsersLogic();
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


    
	

   

    #支付成功页面
    function pay_status(){
        $paid_sn = I('paid_sn');
        $where['paid_sn']   =   ['eq',$paid_sn];
        $order = db('staff_mypays')->where($where)->find();
        $store_id = db('staff')->where(["id"=>$order['staff_id']])->value('store_id');

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
            $logic     = new YxypUsersLogic();
            $data      = $logic->add_address($this->user_id, 0, $post_data);
            $goods_id  = input('goods_id/d');
            $item_id   = input('item_id/d');
            $goods_num = input('goods_num/d');
            $order_id  = input('order_id/d');
            $action    = input('action');
            $is_allreturn = input('is_allreturn');

            if($is_allreturn == 1){
                if ($source == 'cart2') {
                    $data['url']=U('/Mobileyxyp/ReturnCart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                    $this->ajaxReturn($data);
                }
            } else {
      
                if ($data['status'] != 1){
                //    $this->error($data['msg']);
                    $this->ajaxReturn($data);
                } elseif ($source == 'cart2') {
                    $data['url']=U('/Mobileyxyp/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                    $this->ajaxReturn($data);
                } elseif ($_POST['source'] == 'integral') {
                    $data['url']=U('/Mobileyxyp/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                    $this->ajaxReturn($data);
                } elseif($source == 'pre_sell_cart'){
                    $data['url']=U('/Mobileyxyp/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                    $this->ajaxReturn($data);
                } elseif($_POST['source'] == 'team'){
                    $data['url']= U('/Mobileyxyp/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                    $this->ajaxReturn($data);
                }else{
                    $data['url']= U('/Mobileyxyp/User/address_list');      
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
            $logic        = new YxypUsersLogic();
            $data         = $logic->add_address($this->user_id, $id, $post_data);

            $is_allreturn = input('is_allreturn');

            if($is_allreturn == 1){
                if ($source == 'cart2') {
                    $data['url']=U('/Mobileyxyp/ReturnCart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                    $this->ajaxReturn($data);
                }
            } else {

                if ($post_data['source'] == 'cart2') {
                    $data['url']=U('/Mobileyxyp/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                    $this->ajaxReturn($data);
                } elseif ($_POST['source'] == 'integral') {
                    $data['url'] = U('/Mobileyxyp/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                    $this->ajaxReturn($data);
                } elseif($source == 'pre_sell_cart'){
                    $data['url'] = U('/Mobileyxyp/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                    $this->ajaxReturn($data);
                } elseif($_POST['source'] == 'team'){
                    $data['url']= U('/Mobileyxyp/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                    $this->ajaxReturn($data);
                } else{
                    $data['url']= U('/Mobileyxyp/User/address_list');
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
  
}
