<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\storemobile\controller; 
use app\admin\logic\OrderLogic;
use think\Controller;
use think\Config;
use think\Page;
use think\Db;
class Rebate extends Base {

    public function index(){
        

        /*查询本公司下方所有实体店*/
       
        $store_id = $this->store_id;
        if($key_word = I('get.key_words/s')) $where['real_name'] = ['like',"%{$key_word}%"] ;
    
            if($store_id){
                $where['parent_id'] =   ['eq',$store_id];
            }
            #   如果是成员流水
           /* $where ['parent_id']= ['eq',$this->store_id];
            */
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
        return $this->fetch('index');
    }

     public function staff(){

        /*查询本公司下方所有实体店*/
       
        $store_id = $this->store_id;
        if($key_word = I('get.key_words/s')) $where['real_name'] = ['like',"%{$key_word}%"] ;
       
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
        
        
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch('staff');
    }

   



    function Sweep(){
        
        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year"))));
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $condition = array();

        $keywords = I('keywords','','trim');
        if($keywords){
            $condition['real_name'] =   ['eq',$keywords];
        }

        $condition['store_id'] =  ['eq',$this->store_id];
        
        if($pay_status = I('get.pay_status')){
            if($pay_status == 2){
                $condition['pay_status']    =   ['eq',0];
            }elseif($pay_status == 1){
                $condition['pay_status']    =   ['eq',1];
            }
        }


            $count = M('staff_paid')->alias('a')->where($condition)->join('staff staff','staff.id = a.staff_id')->count();
            $pager = new Page($count,15);
            $list = M('staff_paid')->alias('a')->where($condition)
                        ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname')
                        ->order('a.id desc')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('users user','user.user_id = a.user_id')
                        ->join('company store','store.cid = staff.store_id')
                        ->limit($pager->firstRow.','.$pager->listRows)
                        ->select();
        
       
        $this->assign('add_time_begin',date('Y-m-d',$begin));
        $this->assign('add_time_end',date('Y-m-d',$end));
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch('sweep');
    }

    function saoma(){
        
        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year"))));
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $condition = array();

        $keywords = I('keywords','','trim');
        if($keywords){
            $condition['real_name'] =   ['eq',$keywords];
        }
        
        $condition['staff.store_id'] =  ['eq',$this->store_id];
        
         if($pay_status = I('get.pay_status')){
            if($pay_status == 2){
                $condition['pay_status']    =   ['eq',0];
            }elseif($pay_status == 1){
                $condition['pay_status']    =   ['eq',1];
            }
        }

        $count = M('staff_mypays')->alias('a')->where($condition)->join('staff staff','staff.id = a.staff_id')->count();
        $pager = new Page($count,15);
        $list = M('staff_mypays')->alias('a')->where($condition)
                    ->field('a.*,staff.real_name staff_name,user.mobile,user.nickname')
                    ->order('a.id desc')
                    ->join('staff staff','staff.id = a.staff_id')
                    ->join('users user','user.user_id = a.user_id')
                    ->join('company store','store.cid = staff.store_id')
                    ->limit($pager->firstRow.','.$pager->listRows)
                    ->select();
        
       
        $this->assign('add_time_begin',date('Y-m-d',$begin));
        $this->assign('add_time_end',date('Y-m-d',$end));
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch('saoma');
    }


    /**
     * [线下流水订单]
     * @author 王牧田
     * @date 2018-11-24
     */
    public function OrderStore(){

        $begin = strtotime(I('add_time_begin',date('Y-m-d',strtotime("-1 year"))));
        $end = strtotime(I('add_time_end',date('Y-m-d',strtotime('+1 days'))));

        $where = array();



        if($begin && $end){
            $where['add_time'] = array('between',"$begin,$end");
        }
        $where['order_prom_type'] = array('lt',5);
        $order_sn = I('order_sn');
        $order_sn ? $where['order_sn'] = trim($order_sn) : false;

        I('order_status') != '' ? $where['order_status'] = I('order_status') : false;
        I('shipping_status') != '' ? $where['shipping_status'] = I('shipping_status') : false;

        $store_id = $this->store_id;
        $where['is_store'] = 1;
        $where['store_id'] = $store_id;
        $count = M('order_red')->alias('order')->where($where)->count();
        $pager = new Page($count,15);
        $orderList = db('order_red')->where($where)->order("order_id desc")->limit($pager->firstRow.','.$pager->listRows)->select();

        $order_status = C('ORDER_STATUS');
        $this->assign('order_status',$order_status);
        $this->assign('add_time_begin',date('Y-m-d',$begin));
        $this->assign('add_time_end',date('Y-m-d',$end));
        $this->assign('pager',$pager);
        $this->assign('orderList',$orderList);
        return $this->fetch();
    }

}