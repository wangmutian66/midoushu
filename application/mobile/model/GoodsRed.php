<?php
namespace  app\mobile\model;
use think\Model;

#米豆区商品模型
class GoodsRed extends Model{

    protected $goods_basic_where;


    #初始化
    protected function initialize(){

        $this->goods_basic_where['is_on_sale']   =  ['eq',1];
        $this->goods_basic_where['is_check']   =  ['eq',1];

    }

    #米豆区商品
    public function getIndexRedGoods(){
        $midou_num = 9;
        $midou_goods_list = $this
            ->field('goods_id,market_price,goods_name')
            ->where($this->goods_basic_where)
            ->order('sort,on_time desc')
            ->limit($midou_num)
            ->cache('index_midou_goods_list',TPSHOP_CACHE_TIME)
            ->select();

        $goodList = array();
        foreach ($midou_goods_list as $k => $val) {
            $val = $val->toArray();
            $midouInfo = getMidou($val['goods_id']);
            $val['midou_index'] = $midouInfo['midou_index'];
            $goodList[] = $val;
        }

        return $goodList;
    }


    #猜你喜欢
    public function getGuessLikeGoods($where,$order,$page,$pagesize,$listtype=0){

        $guess_goods_list = $this
            ->field('goods_id,shop_price,goods_name,market_price')
            ->where($where)
            ->order($order)
            ->limit(($page-1)*9,$pagesize)
            ->cache(false,"midou_guess_goods_list_{$page}",TPSHOP_CACHE_TIME)
            ->select();

        $goods_id = array();
        $goodList = array();
        foreach ($guess_goods_list as $k => $val) {
            $val = $val->toArray();
            $midouInfo = getMidou($val['goods_id']);
            $val['midou_index'] = $midouInfo['midou_index'];
            $goodList[] = $val;
        }

        #排除每次随机显示的商品，避免显示重复的商品
        if(session('goods_page') != $page && $listtype == 0){
            $ids = implode(',',array_values($goods_id));
            if(session('goods_ids')){
                session('goods_ids',session('goods_ids').','.$ids);
            }else{
                session('goods_ids',$ids);
            }
            session('goods_page',$page);
        }

        return $goodList;

    }

}