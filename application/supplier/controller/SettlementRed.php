<?php
/**
 * tpshop
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\supplier\controller;
use app\supplier\logic\RedOrderLogic;
use think\AjaxPage;
use think\Page;
use think\Db;

class SettlementRed extends Base {
    public  $order_status;
    public  $pay_status;
    public  $shipping_status;
    public  $refuse_status;
    public  $js_status;
 //   var $is_red;
    /*
     * 初始化操作
     */
    public function _initialize() {
        $this->suppliers_id = Session('suppliers.suppliers_id');
        parent::_initialize();
        C('TOKEN_ON',false); // 关闭表单令牌验证
        $this->order_status    = C('ORDER_STATUS');
        $this->pay_status      = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        $this->refuse_status   = C('REFUSE_STATUS');
        $this->js_status       = C('JS_STATUS');
        // 订单 支付 发货状态
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);
        $this->assign('refuse_status',$this->refuse_status);
        $this->assign('js_status',$this->js_status);
    }

    /*
     *订单首页
     */
    public function index(){
    	$begin = date('Y-m-d',strtotime("-1 year"));//30天前
    	$end   = date('Y/m/d',strtotime('+1 days')); 	
    	$this->assign('timegap',$begin.'-'.$end);

        $js_time_start = tpCache('settlement.supplier_jstime_start');
        $js_time_end   = tpCache('settlement.supplier_jstime_end');
        $this->assign('js_time_start',$js_time_start);
        $this->assign('js_time_end',$js_time_end);
        return $this->fetch();
    }

    /*
     *Ajax首页
     */
    public function ajaxindex(){
        $orderLogic = new RedOrderLogic(); 

        $js_time_start = tpCache('settlement.supplier_jstime_start');
        $js_time_end   = tpCache('settlement.supplier_jstime_end');

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

        /*if($end > $js_time_end){
            $m_num        = date('Y-m');
            if($js_time_start > $js_time_end){
                $m_num = date('Y-m',strtotime("+1 month",strtotime(time())));
            } 
            $end_time_str = $m_num.'-'.$js_time_end;
            $end          = strtotime($end_time_str);
        }*/
        
        // 搜索条件
        $condition = array();
        $keyType   = I("keytype");
        $keywords  = I('keywords','','trim');
        
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;

        if($begin && $end){
        	$condition['confirm_time'] = array('between',"$begin,$end");
        } /*else {
            $m_num        = date('Y-m');
            if($js_time_start > $js_time_end){
                $m_num = date('Y-m',strtotime("+1 month",strtotime(time())));
            } 
            $end_time_str = $m_num.'-'.$js_time_end;
            $end          = strtotime($end_time_str);
            $condition['confirm_time'] = array('elt',$end); 
        }*/
        $condition['order_prom_type'] = array('lt',5);
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        
        I('user_id') ? $condition['user_id'] = trim(I('user_id')) : false;
        
        $condition['suppliers_id'] = $this->suppliers_id;   // 供货商ID liyi 2018.04.18
        $condition['js_status']    = 0; // 未结算
        $condition['order_status'] = array('in', '2,4'); // 已完成订单
        $condition['pay_status']   = ['>',1]; // 已支付
//        $condition['is_store'] = "0"; // 正式上线前只显示线上订单
        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('order_red')->where($condition)->count();
 /*echo M('order_red')->getlastsql();
        die;*/
        $Page  = new AjaxPage($count,20);
        $show  = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }


    public function js_status(){
        //搜索条件
        $consignee    = I('consignee');
        $order_sn     = I('order_sn');
        $timegap      = I('timegap');
        $order_status = I('order_status');
        $order_ids    = I('order_ids');
        $where        = array();//搜索条件

        $where['suppliers_id'] = $this->suppliers_id; // 供货商ID
        $where['js_status']    = 0; // 未结算
        $where['order_status'] = array('in', '2,4');  // 已完成订单
        $where['pay_status']   = 1; // 已支付

        if($consignee){
            $where['consignee'] = ['like','%'.$consignee.'%'];
        }
        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        if($order_status){
            $where['order_status'] = $order_status;
        }
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }
        if($order_ids){
            $where['order_id'] = ['in', $order_ids];
        }

        $orderList = Db::name('order_red')->field("*,FROM_UNIXTIME(add_time,'%Y-%m-%d') as create_time")->where($where)->order('order_id')->select();
        if($orderList){
            foreach ($orderList as $k => $val) {
                $data['order_id']       = $val['order_id'];
                $data['order_sn']       = $val['order_sn'];
                $data['suppliers_id']   = $this->suppliers_id;
                $data['order_amount']   = $val['tk_cost_price'];
                $data['shipping_price'] = $val['shipping_price'];
                $data['status']         = 1;
                $data['add_time']       = time();         

                $res = M('order_red_settlement')->add($data);
                if($res){
                    $data2['js_status'] = 1;
                    M('order_red')->where('order_id ='.$val['order_id'])->save($data2);
                } else {
                    $this->error('申请失败！请联系管理员！');
                }
            }
            $this->success('申请成功！请耐心等待处理！');
        } else {
            $this->error('暂无可结算订单！');
        }
    }

    public function settlement(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end   = date('Y/m/d',strtotime('+1 days'));    
        $this->assign('timegap',$begin.'-'.$end);

        $js_time_start = tpCache('settlement.supplier_jstime_start');
        $js_time_end   = tpCache('settlement.supplier_jstime_end');
        $this->assign('js_time_start',$js_time_start);
        $this->assign('js_time_end',$js_time_end);
        return $this->fetch();
    }

    public function ajaxsettlement(){
        //搜索条件
        $keytype        = I('keytype');
        if($keytype == 'order_sn') $order_sn = I('keywords');
        $timegap        = I('timegap');
        $add_time_begin = I('add_time_begin');
        $add_time_end   = I('add_time_end');
        $status         = I('status');
        $where          = array();//搜索条件
        $where['suppliers_id'] = $this->suppliers_id; // 供货商ID

        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        if($status >= 0){
            $where['status'] = $status;
        }
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        if($add_time_begin && $add_time_end){
            //@new 新后台UI参数
            $begin = strtotime($add_time_begin);
            $end   = strtotime($add_time_end);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('order_red_settlement')->where($where)->count();
        $Page  = new AjaxPage($count,20);
        $show  = $Page->show();
        //获取订单列表
        $orderList = M('order_red_settlement')->where($where)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();

    }


}
