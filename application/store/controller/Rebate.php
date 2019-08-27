<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\store\controller; 
use app\admin\logic\OrderLogic;
use app\admin\logic\RedOrderLogic;
use think\Controller;
use think\AjaxPage;
use think\Config;
use think\Page;
use think\Db;
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
       
        $store_id = $this->store_id;
        if($key_word = I('get.key_words/s')) $where['real_name'] = ['like',"%{$key_word}%"] ;
        if($t == 1){
            if($store_id){
                $where['parent_id'] =   ['eq',$store_id];
            }
            #   如果是成员流水
           /* $where ['parent_id']= ['eq',$this->store_id];
            */
            $count = M('member_commission')->alias('a')->join('company_member m','m.id = a.member_id')->where($where)->count();
            $pager = new Page($count,12);
            $list = M('member_commission')->alias('a')->where($where)
                        ->field('a.*,m.real_name')
                        ->order('a.id desc')
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->join('company_member m','m.id = a.member_id')
                        ->select();

        }else{
            if($store_id){
                $where['store_id'] =   ['eq',$store_id];
            }
            $count = M('staff_commission')->alias('a')->where($where)->join('staff staff','staff.id = a.staff_id')->count();
            $pager = new Page($count,12);
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


        $staff_where['store_id'] =   ['eq',$this->store_id];

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
        $begin = strtotime(I('get.add_time_begin/s'));
        $end = strtotime(I('get.add_time_end/s'));

        $condition = array();
        if($begin && $end){
            $condition['a.create_time'] = array('between',"$begin,$end");
            $this->assign('add_time_begin',date('Y-m-d',$begin));
            $this->assign('add_time_end',date('Y-m-d',$end));
        }
        $keywords = I('keywords','','trim');
        if($keywords){
            $condition['staff.real_name'] =   ['eq',$keywords];
        }
        $condition['staff.store_id'] =  ['eq',$this->store_id];
        
        if($pay_status = I('get.pay_status')){
            if($pay_status == 2){
                $condition['pay_status']    =   ['eq',0];
            }elseif($pay_status == 1){
                $condition['pay_status']    =   ['eq',1];
            }
        }

        if($t == 1){
            $count = M('staff_paid')->alias('a')
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->join('company store','store.cid = staff.store_id')
                        ->where($condition)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->count();
            $pager = new Page($count,15);
            $list = M('staff_paid')->alias('a')
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->join('company store','store.cid = staff.store_id')
                        ->where($condition)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }else{
            $count = M('staff_mypays')->alias('a')
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->join('company store','store.cid = staff.store_id')
                        ->where($condition)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->count();
            $pager = new Page($count,15);
            $list = M('staff_mypays')->alias('a')
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->join('company store','store.cid = staff.store_id')
                        ->where($condition)
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
            
        }
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
     * [线下流水订单]
     * @author 王牧田
     * @date 2018-11-23
     */
    public function OrderStore(){
        
        return $this->fetch();
    }

     /*
     *Ajax
     */
    public  function ajaxRepurchase(){

        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year")))); 
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $where = array();
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');

        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $where['consignee'] = trim($consignee) : false;

        if($begin && $end){
            $where['add_time'] = array('between',"$begin,$end");
        }
        $where['order_prom_type'] = array('lt',5);
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $where['order_sn'] = trim($order_sn) : false;

        I('order_status') != '' ? $where['order_status'] = I('order_status') : false;
        I('shipping_status') != '' ? $where['shipping_status'] = I('shipping_status') : false;

        $store_id = $this->store_id;
        $where['is_store'] = 1;
        $where['store_id'] = $store_id;
        $count = M('order_red')->alias('order')->where($where)->count();
        //dump($count);
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        $orderList = db('order_red')->where($where)->order("order_id desc")->limit($Page->firstRow,$Page->listRows)->select();
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        $this->assign('orderList',$orderList);
        // var_dump($orderList);
        return $this->fetch();
    }
        public function return_repurchase_fileput()
    {

        \think\Config::set('show_error_msg',true);
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 50 : $_REQUEST['size'];
        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year")))); 
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $where = array();
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');

        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $where['consignee'] = trim($consignee) : false;

        if($begin && $end){
            $where['add_time'] = array('between',"$begin,$end");
        }
        $where['order_prom_type'] = array('lt',5);
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $where['order_sn'] = trim($order_sn) : false;

        I('order_status') != '' ? $where['order_status'] = I('order_status') : false;
        I('shipping_status') != '' ? $where['shipping_status'] = I('shipping_status') : false;

        $store_id = $this->store_id;
        $where['is_store'] = 1;
        $where['store_id'] = $store_id;
        $orderList = db('order_red')->where($where)->order("order_id desc")->select();

        $sid = $this->store_id;
        $dir_url = "./public/store_xx_order/data_" . $sid . "/";
        if (!is_dir($dir_url)) {
            mkdir($dir_url, 0777, true);
        }
        $Page = new Page($count, $size);
        if ($Page->nowPage <= $Page->totalPages) {
            file_put_contents($dir_url . "/return_com_" . $Page->nowPage . ".txt", json_encode($orderList));
            return ceil($Page->nowPage / $Page->totalPages * 100);
        }

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

    public function export_repurchase()
    {
        $sid = $this->store_id;
        $dir_url = "./public/store_xx_order/data_" . $sid . "/";
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
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">收货人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">总金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">应付金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">运费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">成本总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">订单状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">支付方式</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">发货状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">配送方式</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">下单时间</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            $region = get_region_list();
            $n = 0;
            foreach($orderList as $k=>$val){
                $n++;
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['consignee'].":".$val['mobile'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['goods_price'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_amount'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['shipping_price'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['goods_price'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.tk_money_format($val['tk_cost_price']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$this->order_status[$val['order_status']].'</td>';
                if($val['pay_name'] == ""){
                    $val['pay_name'] = '其他方式';
                }
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['pay_name'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$this->shipping_status[$val['shipping_status']].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['shipping_name'].'</td>';
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
    //返利流水导出  开始  吴宇凡
    public function return_flowingwater_fileput()
    {

        \think\Config::set('show_error_msg',true);
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 50 : $_REQUEST['size'];
        $t = I('post.t/d',1);

        /*查询本公司下方所有实体店*/
       
        $store_id = $this->store_id;
        if($key_word = I('post.key_words/s')) $where['real_name'] = ['like',"%{$key_word}%"] ;
        if($t == 1){
            if($store_id){
                $where['parent_id'] =   ['eq',$store_id];
            }
            #   如果是成员流水
           /* $where ['parent_id']= ['eq',$this->store_id];
            */
            $list = M('member_commission')->alias('a')->where($where)
                        ->field('a.*,m.real_name')
                        ->order('a.id desc')
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->join('company_member m','m.id = a.member_id')
                        ->select();

        }else{
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

        $sid = $this->store_id;
        $dir_url = "./public/store_fl_order/data_" . $sid . "/";
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
        $sid = $this->store_id;
        $dir_url = "./public/store_fl_order/data_" . $sid . "/";
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
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">返利时间</td>';
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
        downloadExcel($strTable,'返利流水明细导出');
        $this->removeDir($dir_url);
        exit();
    }
    //返利流水导出  结束
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
            $condition['real_name'] =   ['eq',$keywords];
        }
        $condition['staff.store_id'] =  ['eq',$this->store_id];
        
        if($pay_status = I('post.pay_status')){
            if($pay_status == 2){
                $condition['pay_status']    =   ['eq',0];
            }elseif($pay_status == 1){
                $condition['pay_status']    =   ['eq',1];
            }
        }

        if($t == 1){
            $list = M('staff_paid')->alias('a')->where($condition)
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->join('company store','store.cid = staff.store_id')
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        }else{
            $list = M('staff_mypays')->alias('a')->where($condition)
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->join('company store','store.cid = staff.store_id')
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
            
        }

        $sid = $this->store_id;
        $dir_url = "./public/store_sweep_order/data_" . $sid . "/";
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
        $sid = $this->store_id;
        $dir_url = "./public/store_sweep_order/data_" . $sid . "/";
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
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">顾客微信名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">服务员工</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:80px;">支付状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">支付时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">支付方式</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">实体店结余</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">公司结余</td>';
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
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['nickname'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['staff_name'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['money'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.pay_status($val['pay_status']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.date("Y-m-d H:i",$val['pay_time']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['pay_name'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.tk_money_format($val['store_money']).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.tk_money_format($val['dby_money']).'</td>';
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