<?php
namespace app\auto\controller;
use think\Cache;
use think\Controller;
use app\common\model\SpecRedGoodsPrice;
/**
 *
 * Created by PhpStorm.
 * User: 王牧田
 * Date: 2018/9/7
 * Time: 16:26
 */

class Returnorder extends Controller{

    /**
     * [一定时间后自动取消下订单后一段时间不支付]
     * @author 王牧田
     * @date 2018年9月7日
     */
    public function autoReturnOrder(){
        $where['pay_status'] = ['eq',0];
	    $starttime = NOW_TIME - 14400;     //4个小时
        $where['add_time'] = ['lt',$starttime];
        $reslt = db('order')->where($where)->save(["order_status"=>3]);
        return $reslt;
    }


    /**
     * [线下订单未支付直接取消订单]
     * @author 王牧田
     * @date 2018年11月22日
     */
    public function autoReturnStoreRedOrder(){
        $where['or.pay_status'] = 0;   //未支付
        $where['or.is_store'] = 1;     //是线下订单
        $where['or.store_id'] = ['neq','0'];  //实体店id 不是0
        $where['or.order_status'] = ['in',[0,1]]; //订单状态是 待确认 和 已确认
        $starttime = NOW_TIME - 1200;     //20分钟
        $where['or.add_time'] = ['lt',$starttime];
        //查询所有
        $orderRedGoods = db('order_red_goods org')
            ->join("__ORDER_RED__ or","org.order_id = or.order_id")
            ->join("__SPEC_RED_GOODS_PRICE__ srgp","srgp.key = org.spec_key and srgp.goods_id = org.goods_id","left")
            ->field("or.order_id,srgp.item_id,org.goods_id,or.store_id,org.goods_num")
            ->where($where)
            ->order("or.order_id desc")
            ->select();




        //循环查询返回库存
        foreach ($orderRedGoods as $org){
            if($org['item_id']){
                $orgwhere['item_id'] = $org['item_id'];
            }
            $orgwhere['goods_id'] = $org['goods_id'];
            $orgwhere['store_id'] = $org['store_id'];
            db('store_goods_stock')->where($orgwhere)->setInc('stock',$org['goods_num']);
        }

        //订单取消
        $reslt = db('order_red')->alias('or')->where($where)->save(["order_status"=>3]);
        return $reslt;
    }






    //线上订单支付后取消订单
     public function refund_order()
     {
        $where['or.pay_status'] = 0;   //未支付
        $where['or.is_store'] = 0;     //是线上订单
        $where['or.store_id'] = 0;  //实体店id 不是0
        $where['or.order_status'] = ['in',[0,1]]; //订单状态是 待确认 和 已确认
        $starttime = NOW_TIME - 14400;     //4个小时
        $where['or.add_time'] = ['lt',$starttime];
         //查询所有
        $orderRedGoods = db('order_red_goods org')
            ->join("__ORDER_RED__ or","org.order_id = or.order_id")
            ->join("__SPEC_RED_GOODS_PRICE__ srgp","srgp.key = org.spec_key","left")
            ->field("or.order_id,org.spec_key,srgp.item_id,org.goods_id,or.store_id,org.goods_num")
            ->where($where)
            ->order("or.order_id desc")
            ->select();


        //循环查询返回库存
        foreach ($orderRedGoods as $key=>$val){
            if(!empty($val['spec_key'])){ // 先到规格表里面扣除数量
				$SpecGoodsPrice = new SpecRedGoodsPrice();
				$specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
				if($specGoodsPrice){
					$specGoodsPrice->store_count = $specGoodsPrice->store_count + $val['goods_num'];
					$specGoodsPrice->save();//有规格则增加商品对应规格的库存
				}
			}else{
				M('goods_red')->where(['goods_id' => $val['goods_id']])->setInc('store_count', $val['goods_num']);//没有规格则增加商品库存
			}
        }
        //订单取消
        $reslt = db('order_red')->alias('or')->where($where)->save(["order_status"=>3]);
        return $reslt;
     }



}