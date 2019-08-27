<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\companymobile\controller; 
use app\admin\logic\GoodsLogic;
use app\admin\logic\OrderLogic;
use app\admin\logic\RedOrderLogic;
use think\AjaxPage;
use think\Controller;
use think\Config;
use think\Page;
use think\Db;
class Rebate extends Base {

#	var $cid;
	/**
     * 析构函数
     */
    function _initialize() 
    {
    #	$this->cid = Session('company.cid');
        parent::_initialize();
    } 

    public function index(){
        $t = I('get.t/d',1);

        /*查询本公司下方所有实体店*/
        $store_list = TK_get_company_store($this->company_id);
        $this->assign('store_list',$store_list);
        $store_id = I('get.store_id');
        if($key_word = I('get.key_words/s')) $where['real_name'] = ['like',"%{$key_word}%"] ;
        if($t == 1){
            foreach ($store_list as $key => $value) {
                $ids[]  =   $value['cid'];
            }
            $where['parent_id']= ['in',$ids];
            if($store_id){
                $where['parent_id'] =   ['eq',$store_id];
            }
            #   如果是成员流水
            $count = M('member_commission')->alias('a')->join('company_member m','m.id = a.member_id')->where($where)->count();
            $pager = new Page($count,15);
            $list = M('member_commission')->alias('a')->where($where)
                        ->field('a.*,m.real_name')
                        ->order('a.id desc')
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->join('company_member m','m.id = a.member_id')
                        ->select();
         #   echo  M('member_commission')->getlastsql();die;
        }else{
            $where['company_id']= ['eq',$this->company_id];
            if($store_id){
                $where['store_id'] =   ['eq',$store_id];
            }
            $count = M('staff_commission')->alias('a')->where($where)->join('staff staff','staff.id = a.staff_id')->count();
            $pager = new Page($count,15);
            $list = M('staff_commission')->alias('a')->where($where)
                        ->field('a.*,staff.real_name')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }
        
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch('index');
    }

    public function member(){

        /*查询本公司下方所有实体店*/
        if($key_word = I('get.key_words/s')) $where['real_name'] = ['like',"%{$key_word}%"] ;

        $where['parent_id']= ['in',$this->company_id];
        #   如果是成员流水
        $count = M('member_commission')->alias('a')->join('company_member m','m.id = a.member_id')->where($where)->count();
        $pager = new Page($count,15);
        $list = M('member_commission')->alias('a')->where($where)
                    ->field('a.*,m.real_name')
                    ->order('a.id desc')
                    ->limit($pager->firstRow.','.$pager->listRows)
                    ->join('company_member m','m.id = a.member_id')
                    ->select();
        
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch('member');
    }

    function Order(){
        $t = I('get.t/d',1);
        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year"))));
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $condition = array();
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');
        
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;

        if($begin && $end){
            $condition['add_time'] = array('between',"$begin,$end");
        }
        $condition['order_prom_type'] = array('lt',5);
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;

        I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;
        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;

        /*查询本公司下方所有实体店*/
        $store_list = TK_get_company_store($this->company_id);
        $this->assign('store_list',$store_list);
        $store_id = I('get.store_id');
        $staff_where['company_id']    =   ['eq',$this->company_id];
        if($store_id){
            $staff_where['store_id'] =   ['eq',$store_id];
        }
        $staff_list = M('staff')->alias('staff')->field('id')->cache(true)->where($staff_where)->select();
        if($staff_list){
            foreach ($staff_list as $key => $value) {
                $ids[]  =   $value['id'];
            }
            
            $user_list = M('users')->alias('u')->field('user_id')->cache(true)->where('staff_id','in',$ids)->select();
            if($user_list){
                foreach ($user_list as $key => $value) {
                    $user_ids[] =   $value['user_id'];
                }
                $condition['user_id']   =   ['in',$user_ids];
                $condition['pay_status']    =   ['eq',1];
                if($t == 1){
                    $count = M('order')->alias('order')->where($condition)->count();
                    $pager = new Page($count,15);
                    $orderList = M('order')->alias('order')->where($condition)
                                ->field('order.*')
                                ->order('order.order_id desc')
                            #    ->join('staff staff','staff.id = a.staff_id')
                                ->limit($pager->firstRow.','.$pager->listRows)
                                ->select();
                }else{
                    $count = M('order_red')->alias('order')->where($condition)->count();
                    $pager = new Page($count,15);
                    $orderList = M('order_red')->alias('order')->where($condition)
                                ->field('order.*')
                                ->order('order.order_id desc')
                            #    ->join('staff staff','staff.id = a.staff_id')
                                ->limit($pager->firstRow.','.$pager->listRows)
                                ->select();
                }
                
            }
        }
        
        $this->assign('add_time_begin',date('Y-m-d',$begin));
        $this->assign('add_time_end',date('Y-m-d',$end));
        $order_status = C('ORDER_STATUS');
        $this->assign('order_status',$order_status);
        $this->assign('orderList',$orderList);
        $this->assign('pager',$pager);
        return $this->fetch('order');
    }



    function Sweep(){
        $t = I('get.t/d',1);
        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year"))));
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $condition = array();

        $keywords = I('keywords','','trim');
        if($keywords){
            $condition['staff.real_name'] =   ['like',"%{$keywords}%"] ;
        }
        // $store_list = TK_get_company_store($this->company_id);
        $this->assign('store_list',$store_list);
        $store_id = I('get.store_id');
        // $condition['company_id']    =   ['eq',$this->company_id];
        if($store_id){
            $condition['store_id'] =  ['eq',$store_id];
        }
        if($pay_status = I('get.pay_status')){
            if($pay_status == 2){
                $condition['pay_status']    =   ['eq',0];
            }elseif($pay_status == 1){
                $condition['pay_status']    =   ['eq',1];
            }
        }

        if($t == 1){
            $count = M('staff_paid')->alias('a')->where($condition)
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where('staff.company_id',$this->company_id)
                        ->count();
            $pager = new Page($count,15);
            $list = M('staff_paid')->alias('a')->where($condition)
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname,staff.store_id')
                        ->order('id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where('staff.company_id',$this->company_id)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }else{
            $count = M('staff_mypays')->alias('a')->where($condition)
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where('staff.company_id',$this->company_id)
                        ->count();
            $pager = new Page($count,15);
            $list = M('staff_mypays')->alias('a')->where($condition)
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname,staff.store_id')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where('staff.company_id',$this->company_id)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }
    
        $this->assign('add_time_begin',date('Y-m-d',$begin));
        $this->assign('add_time_end',date('Y-m-d',$end));
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch('sweep');
    }


    function view_order(){

        // 订单 支付 发货状态
        $this->assign('order_status',C('ORDER_STATUS'));
        $this->assign('pay_status',C('PAY_STATUS'));
        $this->assign('shipping_status',C('SHIPPING_STATUS'));
        $this->assign('refuse_status',C('REFUSE_STATUS'));

        $order_id = I('get.order_id/d');
        $orderLogic = new OrderLogic();
        $order      = $orderLogic->getOrderInfo($order_id);
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $button     = $orderLogic->getOrderButton($order);
        // 获取操作记录
        $action_log = M('order_action')->where(array('order_id'=>$order_id))->order('log_time desc')->select();
        $has_user   = false;
        $adminIds   = [];

        //拒绝发货记录
        $refuse_info = M('order_refuse')->where(array('order_id'=>$order_id,'refuse_status'=>1))->find();
        $this->assign('refuse',$refuse_info);

        $refuse_no_info = M('order_refuse')->where(array('order_id'=>$order_id,'refuse_status'=>3))->find();
        $this->assign('refuse_no',$refuse_no_info);

        //查找用户昵称
        foreach ($action_log as $k => $v){
            if ($v['action_user']) {
                $adminIds[$k] = $v['action_user'];
            } else {
                $has_user = true;
            }
        }

        if($order['user_id']) $has_user = true;
        if($adminIds && count($adminIds) > 0){
            $admins = M("admin")->where("admin_id in (".implode(",",$adminIds).")")->getField("admin_id , user_name", true);
        }
        if($has_user){
            $user = M("users")->field('user_id,nickname')->where('user_id',$order['user_id'])->find();
        }
        $this->assign('admins',$admins);  
        $this->assign('user', $user);
        $this->assign('order',$order);
        $this->assign('action_log',$action_log);
        $this->assign('orderGoods',$orderGoods);
        $split = count($orderGoods) >1 ? 1 : 0;
        foreach ($orderGoods as $val){
            if($val['goods_num']>1){
                $split = 1;
            }
        }
        $this->assign('split',$split);
        $this->assign('button',$button);
        return $this->fetch('view_order');
    }


    /*
    线下换购列表
    wucaoqun  2018/11/23
    */

    public function repurchase(){
        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year"))));
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $orderLogic = new RedOrderLogic();
        $timegap    = I('timegap');

        // 搜索条件
        $condition = array();



        // $consignee ? $condition['consignee'] = trim($consignee) : false;
        //2018-09-26 李鑫修改查询订单模糊查询
        //$consignee ? $condition['consignee'] = array('like',"%$consignee%") : false;
        //---修改结束

        if($begin && $end){
            $condition['add_time'] = array('between',"$begin,$end");
        }

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $condition['suppliers_id'] = array('gt', 0);
        } else if($sp && $sp == 2){
            $condition['suppliers_id'] = 0;
        }

        $condition['order_prom_type']      = array('lt',5);

        $condition['is_store']      = array('neq',0);//线下状态

        $order_sn = I('order_sn');
        // $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        //2018-09-26 李鑫修改查询订单模糊查询
        $order_sn ? $condition['order_sn'] = array('like',"%$order_sn%") : false;
        //---修改结束
        I('order_status')    != '' ? $condition['order_status'] = I('order_status') : false;
        I('pay_status1')     != '' ? $condition['pay_status']   = I('pay_status1')  : false;
        I('pay_status')      != '' ? $condition['pay_status']   = I('pay_status')   : false;
        I('pay_code')        != '' ? $condition['pay_code']     = I('pay_code')     : false;
        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;
        I('user_id1') ? $condition['user_id'] = trim(I('user_id1')) : false;
        I('user_id')      ? $condition['user_id']      = trim(I('user_id')) : false;
        I('suppliers_id') ? $condition['suppliers_id'] = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $condition['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09
        $sort_order = I('order_by','order_id DESC').' '.I('sort');
        $storeid= db('company')->where(["parent_id"=>$this->company_id])->column("cid");
        $condition['store_id'] = ["in",$storeid];
        $count = M('order_red')->where($condition)->count();


        $Page  = new Page($count,5);

        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);

        foreach ($orderList as $k => $val) {
            $val['back_midou'] = 0;
            $orderGoods = $orderLogic->getOrderGoods($val['order_id']);
            foreach ($orderGoods as $k2 => $val2) {
                if( $val2['is_z_back'] == 1) $midou_back_percent = tpCache('shoppingred.midou_back_percent');
                else $midou_back_percent = $val2['midou_back_percent'];
                $goods_price = $val2['midou_money']*$midou_back_percent/100;
                $md = $goods_price/tpCache('shoppingred.midou_rate');
                $val2['back_midou'] = num_float2($md);

                $val['back_midou'] += $val2['goods_num']*$val2['back_midou']; // 订单赠送米豆累计
            }
            $orderList[$k] = $val;
        }

        $this->assign('orderList',$orderList);

        //$this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        $this->assign('add_time_begin',date('Y-m-d',$begin));
        $this->assign('add_time_end',date('Y-m-d',$end));
        $this->assign('timegap',$begin.'-'.$end);
        $GoodsLogic = new GoodsLogic();
        $suppliersList = $GoodsLogic->getSuppliers();
        $this->assign('suppliersList', $suppliersList);
        return $this->fetch();
    }
    /*
    *Ajax
    */
    public  function ajaxRepurchase(){

        $orderLogic = new RedOrderLogic();
        $timegap    = I('timegap');
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
        }else{
            //@new 新后台UI参数
            $begin = strtotime(I('add_time_begin'));
            $end   = strtotime(I('add_time_end'));
        }

        // 搜索条件
        $condition = array();
        $keyType   = I("keytype");
        $keywords  = I('keywords','','trim');

        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        // $consignee ? $condition['consignee'] = trim($consignee) : false;
        //2018-09-26 李鑫修改查询订单模糊查询
        $consignee ? $condition['consignee'] = array('like',"%$consignee%") : false;
        //---修改结束

        if($begin && $end){
            $condition['add_time'] = array('between',"$begin,$end");
        }

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $condition['suppliers_id'] = array('gt', 0);
        } else if($sp && $sp == 2){
            $condition['suppliers_id'] = 0;
        }

        $condition['order_prom_type']      = array('lt',5);

        $condition['is_store']      = array('neq',0);//线下状态

        $order_sn = ($keyType && $keyType  == 'order_sn') ? $keywords : I('order_sn') ;
        // $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        //2018-09-26 李鑫修改查询订单模糊查询
        $order_sn ? $condition['order_sn'] = array('like',"%$order_sn%") : false;
        //---修改结束
        I('order_status')    != '' ? $condition['order_status'] = I('order_status') : false;
        I('pay_status1')     != '' ? $condition['pay_status']   = I('pay_status1')  : false;
        I('pay_status')      != '' ? $condition['pay_status']   = I('pay_status')   : false;
        I('pay_code')        != '' ? $condition['pay_code']     = I('pay_code')     : false;
        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;
        I('user_id1') ? $condition['user_id'] = trim(I('user_id1')) : false;
        I('user_id')      ? $condition['user_id']      = trim(I('user_id')) : false;
        I('suppliers_id') ? $condition['suppliers_id'] = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $condition['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09
        $sort_order = I('order_by','DESC').' '.I('sort');

        $count = M('order_red')->where($condition)->count();


        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);

        foreach ($orderList as $k => $val) {
            $val['back_midou'] = 0;
            $orderGoods = $orderLogic->getOrderGoods($val['order_id']);
            foreach ($orderGoods as $k2 => $val2) {
                if( $val2['is_z_back'] == 1) $midou_back_percent = tpCache('shoppingred.midou_back_percent');
                else $midou_back_percent = $val2['midou_back_percent'];
                $goods_price = $val2['midou_money']*$midou_back_percent/100;
                $md = $goods_price/tpCache('shoppingred.midou_rate');
                $val2['back_midou'] = num_float2($md);

                $val['back_midou'] += $val2['goods_num']*$val2['back_midou']; // 订单赠送米豆累计
            }
            $orderList[$k] = $val;
        }

        $this->assign('orderList',$orderList);

        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

}