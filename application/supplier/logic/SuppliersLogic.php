<?php

/**
 * tpshop
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */
 
namespace app\supplier\logic;

use think\Model;
use think\Db;

class SuppliersLogic extends Model
{    
    
    /**
     * 获取指定供货商信息
     * @param $uid int 用户SID
     * @param bool $relation 是否关联查询
     *
     * @return mixed 找到返回数组
     */
    public function detail($sid, $relation = true)
    {
        $suppliers = M('suppliers')->where(array('suppliers_id' => $sid))->relation($relation)->find();
        return $suppliers;
    }
    

    /**
     * 获取供货商销售额
     * @param $Supplier
     * @return num
     */  
    
    public function getSalemoney($sid,$begin="",$end="")
    {
        $order_where['o.suppliers_id'] = $sid;
        $order_where['o.pay_status']   = 1;
        $order_where['o.order_status'] = ['in','2,4'];
        if( $begin ) $order_where['o.add_time'] = ['egt',$begin]; 
        if( $end )   $order_where['o.add_time'] = ['elt',$end]; 

        $list = Db::name('order')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.tk_cost_price) as amount')
            ->join('suppliers u','o.suppliers_id=u.suppliers_id','LEFT')
            ->where($order_where)
            ->group('o.suppliers_id')
            ->find();   //以用户ID分组查询

        if($list['amount'])
            $salemoney = $list['amount'];
        else 
            $salemoney = 0;

        return $salemoney;
    }


    /**
     * 提现记录
     * @author lxl 2017-4-26
     * @param $user_id
     * @param int $withdrawals_status 提现状态 0:申请中 1:申请成功 2:申请失败
     * @return mixed
     */
    public function get_withdrawals_log($suppliers_id,$withdrawals_status=''){
        $withdrawals_log_where = ['suppliers_id'=>$suppliers_id];
        if($withdrawals_status){
            $withdrawals_log_where['status']=$withdrawals_status;
        }
        $count = M('suppliers_withdrawals')->where($withdrawals_log_where)->count();
        $Page = new Page($count, 15);
        $withdrawals_log = M('suppliers_withdrawals')->where($withdrawals_log_where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$withdrawals_log,
            'show'      =>$Page->show_cxpc()
        ];
        return $return;
    }

    

}