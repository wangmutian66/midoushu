<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */

namespace app\common\logic;

use app\common\model\GroupYxypBuy;
use app\common\model\SpecYxypGoodsPrice;
use app\common\model\GoodsYxyp;
use think\Model;
use think\db;

/**
 * 团购逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class YxypGroupBuyLogic extends Prom
{
    protected $GroupBuy;//团购模型
    protected $goods;//商品模型
    protected $specGoodsPrice;//商品规格模型

    public function __construct($goods,$specGoodsPrice)
    {
        exit('出错了');
        parent::__construct();
        $this->goods = $goods;
        $this->specGoodsPrice = $specGoodsPrice;
        if($this->specGoodsPrice){
            //活动商品有规格，规格和活动是一对一
            $this->GroupBuy = GroupYxypBuy::get($specGoodsPrice['prom_id']);
        }else{
            //活动商品没有规格，活动和商品是一对一
            $this->GroupBuy = GroupYxypBuy::get($this->goods['prom_id']);
        }
        if ($this->GroupBuy) {
            //每次初始化都检测活动是否失效，如果失效就更新活动和商品恢复成普通商品
            if ($this->checkActivityIsEnd() && $this->GroupBuy['is_end'] == 0) {
                if($this->specGoodsPrice){
                    Db::name('spec_yxyp_goods_price')->where('item_id', $this->specGoodsPrice['item_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    $goodsPromCount = Db::name('spec_yxyp_goods_price')->where('goods_id', $this->specGoodsPrice['goods_id'])->where('prom_type','>',0)->count('item_id');
                    if($goodsPromCount == 0){
                        Db::name('goods_yxyp')->where("goods_id", $this->specGoodsPrice['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    }
                    unset($this->specGoodsPrice);
                    $this->specGoodsPrice = SpecYxypGoodsPrice::get($specGoodsPrice['item_id']);
                }else{
                    Db::name('goods_yxyp')->where("goods_id", $this->GroupBuy['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                }
                $this->GroupBuy->is_end = 1;
                $this->GroupBuy->save();
                unset($this->goods);
                $this->goods = GoodsYxyp::get($goods['goods_id']);
            }
        }
    }
    /**
     * 获取团购剩余库存
     */
    public function getPromotionSurplus(){
        return $this->GroupBuy['goods_num'] - $this->GroupBuy['buy_num'];
    }
    public function getPromModel(){
        return $this->GroupBuy;
    }
    /**
     * 获取虚拟参与人数
     * @return number
     */
    public function getVirtualNum(){
        return $this->GroupBuy['virtual_num'] + $this->GroupBuy['buy_num'];
    }
    /**
     * 活动是否正在进行
     * @return bool
     */
    public function checkActivityIsAble(){
        if (empty($this->GroupBuy)) {
            return false;
        }
        if(time() > $this->GroupBuy['start_time'] && time() < $this->GroupBuy['end_time' ] && $this->GroupBuy['is_end'] == 0){
            return true;
        }
        return false;
    }
    /**
     * 活动是否结束
     * @return bool
     */
    public function checkActivityIsEnd(){
        if(empty($this->GroupBuy)){
            return true;
        }
        if($this->GroupBuy['buy_num'] >= $this->GroupBuy['goods_num']){
            return true;
        }
        if(time() > $this->GroupBuy['end_time']){
            return true;
        }
        return false;
    }
    /**
     * 获取商品原始数据
     * @return Goods
     */
    public function getGoodsInfo()
    {
        return $this->goods;
    }
    /**
     * 获取商品转换活动商品的数据
     * @return static
     */
    public function getActivityGoodsInfo(){
        if($this->specGoodsPrice){
            //活动商品有规格，规格和活动是一对一
            $activityGoods = $this->specGoodsPrice;
        }else{
            //活动商品没有规格，活动和商品是一对一
            $activityGoods = $this->goods;
        }
        $activityGoods['activity_title'] = $this->GroupBuy['title'];
        $activityGoods['market_price'] = $this->goods['shop_price'];
        $activityGoods['shop_price'] = $this->GroupBuy['price'];
        $activityGoods['store_count'] = $this->GroupBuy['store_count'];
        $activityGoods['start_time'] = $this->GroupBuy['start_time'];
        $activityGoods['end_time'] = $this->GroupBuy['end_time'];
        $activityGoods['virtual_num'] = $this->GroupBuy['virtual_num'] + $this->GroupBuy['order_num'];
        return $activityGoods;
    }

    /**
     * 该活动是否已经失效
     */
    public function IsAble(){
        if(empty($this->GroupBuy)){
            return false;
        }
        if($this->GroupBuy['buy_num'] >= $this->GroupBuy['goods_num']){
            return false;
        }
        if(time() > $this->GroupBuy['end_time']){
            return false;
        }
        if($this->GroupBuy['is_end'] == 1){
            return false;
        }
        return true;
    }
    /**
     * @param $buyGoods
     * @return array
     */
    public function buyNow($buyGoods){
        //活动是否已经结束
        if($this->GroupBuy['is_end'] == 1 || empty($this->GroupBuy)){
            return array('status' => 0, 'msg' => '团购活动已结束', 'result' => '');
        }
        if($this->checkActivityIsAble()){
            $groupBuyPurchase = $this->GroupBuy['goods_num'] - $this->GroupBuy['buy_num'];//团购剩余库存
            if($buyGoods['goods_num'] > $groupBuyPurchase){
                return array('status' => 0, 'msg' => '商品库存不足，剩余'.$groupBuyPurchase, 'result' => '');
            }
            $buyGoods['member_goods_price'] = $this->GroupBuy['price'];
            $buyGoods['prom_type'] = 2;
            $buyGoods['prom_id'] = $this->GroupBuy['id'];
        }
        return array('status' => 1, 'msg' => 'success', 'result' => ['buy_goods'=>$buyGoods]);
    }

}