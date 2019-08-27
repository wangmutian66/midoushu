<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */

namespace app\supplier\logic;

use think\Model;
use think\db;
/**
 * 分类逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class StoreLogic extends Model
{


    public function count_store($condition){

         $count_store = Db::name('store_goods_stock')->alias('a')
                      ->where($condition)
                      ->field('a.*,b.cname')
                      ->join('company b','a.store_id=b.cid','LEFT')
                      ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
                      ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
                      ->count();
                return $count_store;       
    }


    /**
     *实体店供货明细
     */
    public function goods_store_list($condition,$order='',$start=0,$page_size=20)
    {

                global $goods_store;                
                $goods_store = Db::name('store_goods_stock')->alias('a')
                      ->where($condition)
                      ->field('a.*,b.cname,c.key_name,g.goods_name,g.suppliers_id')
                      ->join('company b','a.store_id=b.cid','LEFT')
                      ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
                      ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
                      ->limit("$start,$page_size")
                      ->order($order)
                      ->select();

                return $goods_store;               
    }


     /**
     *实体店申请记录
     */
    public function apply_store_list($condition,$order='',$start=0,$page_size=20)
    {

                global $apply_store;
                $apply_store = Db::name('store_goods_supplices')->alias('a')
                      ->where($condition)
                      ->field('a.*,b.cname,c.key_name,g.goods_name')
                      ->join('company b','a.store_id=b.cid','LEFT')
                      ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
                      ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
                      ->limit("$start,$page_size")
                      ->order($order)
                      ->select();


                return $apply_store;               
    }

}