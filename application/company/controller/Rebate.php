<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\company\controller; 
use app\admin\logic\OrderLogic;
use app\admin\logic\RedOrderLogic;
use think\AjaxPage;
use think\Request;
use think\Controller;
use think\Config;
use think\Page;
use think\Db;
use think\Loader;
class Rebate extends Base {



    public  $order_status;
    public  $pay_status;
    public  $shipping_status;
    public  $refuse_status;

    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        C('TOKEN_ON',false); // 关闭表单令牌验证
        $this->order_status    = C('ORDER_STATUS');
        $this->pay_status      = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        $this->refuse_status   = C('REFUSE_STATUS');
        // 订单 支付 发货状态
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);
        $this->assign('refuse_status',$this->refuse_status);
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
            $condition['staff.real_name'] =   ['eq',$keywords];
        }
        // $store_list = TK_get_company_store($this->company_id);
        // $this->assign('store_list',$store_list);
        $store_id = I('get.store_id');
        // $condition['company_id']    =   ['eq',$this->company_id];
        if($begin && $end){
            $condition['a.create_time'] = array('between',"$begin,$end");
        }
        if($store_id){
            $condition['a.store_id'] =  ['eq',$store_id];
        }
        if($pay_status = I('get.pay_status')){
            if($pay_status == 2){
                $condition['pay_status']    =   ['eq',0];
            }elseif($pay_status == 1){
                $condition['pay_status']    =   ['eq',1];
            }
        }

        if($t == 1){
            $count = $list = M('staff_paid')->alias('a')
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname,staff.store_id')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where($condition)
                        ->where('staff.company_id',$this->company_id)
                        ->count();
            $pager = new Page($count,15);
            $list = M('staff_paid')->alias('a')
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname,staff.store_id')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where($condition)
                        ->where('staff.company_id',$this->company_id)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }else{
            $count = M('staff_mypays')->alias('a')
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname,staff.store_id')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where($condition)
                        ->where('staff.company_id',$this->company_id)
                        ->count();
            $pager = new Page($count,15);
            $list = M('staff_mypays')->alias('a')
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname,staff.store_id')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where($condition)
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



    /**
     * 重新制作米豆区线下订单显示
     * @author 王牧田
     * @date 2018-11-26
     * @return mixed
     */
    function view_order_red(){

        // 订单 支付 发货状态
        $this->assign('order_status',C('ORDER_STATUS'));
        $this->assign('pay_status',C('PAY_STATUS'));
        $this->assign('shipping_status',C('SHIPPING_STATUS'));
        $this->assign('refuse_status',C('REFUSE_STATUS'));

        $order_id = I('get.order_id/d');
        $orderLogic = new RedOrderLogic();
        $order      = $orderLogic->getOrderInfo($order_id);
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $button     = $orderLogic->getOrderButton($order);

        $cname = M('company')->where(["cid"=>$order["store_id"]])->value("cname");

        // 获取操作记录
        $action_log = M('order_red_action')->where(array('order_id'=>$order_id))->order('log_time desc')->select();
        $has_user   = false;
        $adminIds   = [];

        //拒绝发货记录
        $refuse_info = M('order_red_refuse')->where(array('order_id'=>$order_id,'refuse_status'=>1))->find();
        $this->assign('refuse',$refuse_info);

        $refuse_no_info = M('order_red_refuse')->where(array('order_id'=>$order_id,'refuse_status'=>3))->find();
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

        $this->assign('cname',$cname);
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

        $condition['is_store']      = 1;//线下状态

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
        //modify
        // $condition['is_red'] = ['eq',$this->is_red];

//        dump($this->company_id);
//        exit();


        $storeid= db('company')->where(["parent_id"=>$this->company_id])->column("cid");
        $condition['store_id'] = ["in",$storeid];
        $count = M('order_red')->where($condition)->count();
        // echo M('order_red')->where($condition)->getlastsql();
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
    //删除非空目录的解决方案
   public function removeDir($dirName)
   {
       if(! is_dir($dirName))
       {
           return false;
       }
       $handle = @opendir($dirName);
       while(($file = @readdir($handle)) !== false)
       {
           if($file != '.' && $file != '..')
           {
               $dir = $dirName . '/' . $file;
               is_dir($dir) ? removeDir($dir) : @unlink($dir);
           }
       }
       closedir($handle);

       return rmdir($dirName) ;
   }
   //线下换购订单导出功能  开始  吴宇凡
    public function return_repurchase_fileput()
    {

        \think\Config::set('show_error_msg',true);
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 50 : $_REQUEST['size'];
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

        $condition['is_store']      = 1;//线下状态

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
        $storeid= db('company')->where(["parent_id"=>$this->company_id])->column("cid");
        $condition['store_id'] = ["in",$storeid];
        $count = M('order_red')->where($condition)->count();
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

        $cid = $this->company_id;
        $dir_url = "./public/company_xx_order/data_" . $cid . "/";
        if (!is_dir($dir_url)) {
            mkdir($dir_url, 0777, true);
        }
        $Page = new Page($count, $size);
        if ($Page->nowPage <= $Page->totalPages) {
            file_put_contents($dir_url . "/return_com_" . $Page->nowPage . ".txt", json_encode($orderList));
            return ceil($Page->nowPage / $Page->totalPages * 100);
        }

    }

    public function export_repurchase()
    {
        $cid = $this->company_id;
        $dir_url = "./public/company_xx_order/data_" . $cid . "/";
        $files = scandir($dir_url);
        unset($files[0]);
        unset($files[1]);
        $filelenght = count($files);
        $orderList = [];
        for ($i = 1; $i <= $filelenght; $i++) {
            $data = file_get_contents($dir_url . "return_com_" . $i . ".txt");
            $row = json_decode($data, true);
            $orderList = array_merge($orderList, $row);
        }
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">实体店</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">赠送米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">订单状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">支付状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">发货状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">拒绝发货</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">下单时间</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            $region = get_region_list();
            $n = 0;
            foreach($orderList as $k=>$val){
                $n++;
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.shitis($val['store_id']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['goods_price'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['back_midou'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$this->order_status[$val['order_status']].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.pay_status($val['pay_status']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$this->shipping_status[$val['shipping_status']].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$this->refuse_status[$val['refuse_status']].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.date("Y-m-d H:i",$val['add_time']).'</td>';
                $strTable .= '</tr>';
            }
        }
         // die();
        $strTable .='</table>';
        downloadExcel($strTable,'线下换购订单导出');
        $this->removeDir($dir_url);
        exit();
    }
    //线下换购订单导出功能  结束
    //分红流水导出  开始  吴宇凡
    public function return_flowingwater_fileput()
    {

        \think\Config::set('show_error_msg',true);
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 50 : $_REQUEST['size'];
        $t = I('post.t/d',1);
        /*查询本公司下方所有实体店*/
        $store_list = TK_get_company_store($this->company_id);
        $store_id = I('post.store_id');
        if($key_word = I('post.key_words/s')) $where['real_name'] = ['like',"%{$key_word}%"] ;
        if($t == 1){
            foreach ($store_list as $key => $value) {
                $ids[]  =   $value['cid'];
            }
            $where['parent_id']= ['in',$ids];
            if($store_id){
                $where['parent_id'] =   ['eq',$store_id];
            }
            #   如果是成员流水
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
            $list = M('staff_commission')->alias('a')->where($where)
                        ->field('a.*,staff.real_name')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }

        $cid = $this->company_id;
        $dir_url = "./public/company_fl_order/data_" . $cid . "/";
        if (!is_dir($dir_url)) {
            mkdir($dir_url, 0777, true);
        }
        $Page = new Page($count, $size);
        if ($Page->nowPage <= $Page->totalPages) {
            file_put_contents($dir_url . "/return_com_" . $Page->nowPage . ".txt", json_encode($list));
            return ceil($Page->nowPage / $Page->totalPages * 100);
        }

    }
    public function export_flowingwater()
    {
        $cid = $this->company_id;
        $dir_url = "./public/company_fl_order/data_" . $cid . "/";
        $files = scandir($dir_url);
        unset($files[0]);
        unset($files[1]);
        $filelenght = count($files);
        $orderList = [];
        for ($i = 1; $i <= $filelenght; $i++) {
            $data = file_get_contents($dir_url . "return_com_" . $i . ".txt");
            $row = json_decode($data, true);
            $orderList = array_merge($orderList, $row);
        }
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">成员姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">流水金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">分红时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:500px;">说明</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            $region = get_region_list();
            $n = 0;
            foreach($orderList as $k=>$val){
                $n++;
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['real_name'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.tk_money_format($val['money']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.date("Y-m-d H:i",$val['create_time']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['info'].'</td>';
                $strTable .= '</tr>';
            }
        }
         // die();
        $strTable .='</table>';
        downloadExcel($strTable,'分红流水明细导出');
        $this->removeDir($dir_url);
        exit();
    }
    //分红流水导出  结束
    //线下消费流水导出 开始 吴宇凡
    public function return_sweep_fileput()
    {

        \think\Config::set('show_error_msg',true);
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 50 : $_REQUEST['size'];
        $t = I('post.t/d',1);
        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year"))));
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $condition = array();
        if($begin && $end){
            $condition['a.create_time'] = array('between',"$begin,$end");
        }
        $keywords = I('keywords','','trim');
        if($keywords){
            $condition['staff.real_name'] =   ['eq',$keywords];
        }
        $store_id = I('post.store_id');
        if($store_id){
            $condition['a.store_id'] =  ['eq',$store_id];
        }
        if($pay_status = I('post.pay_status')){
            if($pay_status == 2){
                $condition['pay_status']    =   ['eq',0];
            }elseif($pay_status == 1){
                $condition['pay_status']    =   ['eq',1];
            }
        }

        if($t == 1){
            $list = M('staff_paid')->alias('a')->where($condition)
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname,staff.store_id')
                        ->order('id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where('staff.company_id',$this->company_id)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }else{
            $list = M('staff_mypays')->alias('a')->where($condition)
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname,staff.store_id')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->where('staff.company_id',$this->company_id)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }

        $cid = $this->company_id;
        $dir_url = "./public/company_sweep_order/data_" . $cid . "/";
        if (!is_dir($dir_url)) {
            mkdir($dir_url, 0777, true);
        }
        $Page = new Page($count, $size);
        if ($Page->nowPage <= $Page->totalPages) {
            file_put_contents($dir_url . "/return_com_" . $Page->nowPage . ".txt", json_encode($list));
            return ceil($Page->nowPage / $Page->totalPages * 100);
        }

    }
    public function export_sweep()
    {
        $cid = $this->company_id;
        $dir_url = "./public/company_sweep_order/data_" . $cid . "/";
        $files = scandir($dir_url);
        unset($files[0]);
        unset($files[1]);
        $filelenght = count($files);
        $orderList = [];
        for ($i = 1; $i <= $filelenght; $i++) {
            $data = file_get_contents($dir_url . "return_com_" . $i . ".txt");
            $row = json_decode($data, true);
            $orderList = array_merge($orderList, $row);
        }
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">顾客信息</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">服务员工</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:300px;">实体店</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">付款金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">支付状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">支付时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">支付方式</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">下单时间</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            $region = get_region_list();
            $n = 0;
            foreach($orderList as $k=>$val){
                $n++;
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['paid_sn'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['user_id'].':'.substr_replace($val['mobile'],'****',3,4).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['staff_name'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.shitis($val['store_id']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['money'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.pay_status($val['pay_status']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.date("Y-m-d H:i",$val['pay_time']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['pay_name'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.date("Y-m-d H:i",$val['create_time']).'</td>';
                $strTable .= '</tr>';
            }
        }
         // die();
        $strTable .='</table>';
        downloadExcel($strTable,'线下流水明细导出');
        $this->removeDir($dir_url);
        exit();
    }
}