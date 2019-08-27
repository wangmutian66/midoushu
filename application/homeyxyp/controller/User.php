<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * 2015-11-21
 */
namespace app\homeyxyp\controller; 
use app\common\logic\MessageLogic;
use app\common\logic\YxypOrderLogic;
use app\common\logic\YxypUsersLogic;
use app\common\logic\YxypCartLogic;
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
        $logic = new YxypUsersLogic();
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
        $order_list = M('order_yxyp')->order($order_str)->where($where)->bind($bind)->limit(3)->select();
        //获取订单商品
        $model = new YxypUsersLogic();
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
     *商品收藏
     */
    public function goods_collect(){
        $userLogic = new YxypUsersLogic();
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
        $row = M('goods_yxyp_collect')->where(array('collect_id'=>$id,'user_id'=>$this->user_id))->delete();
        if(!$row)
            $this->error("删除失败");
        $this->success('删除成功');
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