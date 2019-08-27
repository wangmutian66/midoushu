<?php

/**
 * tpshop
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */
 
namespace app\admin\logic;

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
     * 改变用户信息
     * @param int $sid
     * @param array $data
     * @return array
     */
    public function updateSupplier($sid = 0, $data = array())
    {
        $db_res = M('suppliers')->where(array("suppliers_id" => $sid))->data($data)->save();
        if ($db_res) {
            return array(1, "供货商信息修改成功");
        } else {
            return array(0, "供货商信息修改失败");
        }
    }
    
    
    /**
     * 添加供货商
     * @param $Supplier
     * @return array
     */
    public function addSupplier($supplier)
    {
		$supplier_count = Db::name('suppliers')
				->where(function($query) use ($supplier){
					if ($supplier['suppliers_phone']) {
						$query->whereOr('suppliers_phone',$supplier['suppliers_phone']);
					}
				})
				->count();
		if ($supplier_count > 0) {
			return array('status' => -1, 'msg' => '账号已存在');
		}
    	$supplier['suppliers_password'] = encrypt($supplier['suppliers_password']); //md5
    	$supplier['add_time'] = time(); // 申请时间
    	$supplier_id = M('suppliers')->add($supplier);
    	if(!$supplier_id){
    		return array('status'=>-1,'msg'=>'添加失败');
    	}else{
    		return array('status'=>1,'msg'=>'添加成功');
    	}
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
        if( $begin ) $order_where['o.add_time'] = ['egt',$begin]; 
        if( $end )   $order_where['o.add_time'] = ['elt',$end]; 

        $list = Db::name('order')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.order_amount) as amount')
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

}