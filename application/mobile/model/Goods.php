<?php
namespace  app\mobile\model;
use think\Model;

#现金区商品模型
class Goods extends Model{

    #数据表名称
    protected $table = 'tp_goods';
    #主键
    protected $pk = 'goods_id';
    #查询条件
    protected $goods_basic_where;


    #初始化
    protected function initialize(){

        $this->goods_basic_where['is_on_sale']   =  ['eq',1];
        $this->goods_basic_where['is_check']   =  ['eq',1];

    }

    #现金区商品
    public function getIndexGoods(){

        $this->goods_basic_where['goods_id'] = ['not in','2977,2978,2979'];
        $cash_num = 9;
        $cash_goods_list = $this
            ->field('goods_id,shop_price,goods_name')
            ->where($this->goods_basic_where)
            ->order("sort,on_time desc")
            ->limit($cash_num)
            ->cache('index_cash_goods_list',TPSHOP_CACHE_TIME)
            ->select();

        $goodList = array();
        foreach ($cash_goods_list as $k => $val) {
            $val = $val->toArray();
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou'] : '';
            $goodList[] = $val;
        }

        return $goodList;

    }

    #猜你喜欢
    public function getGuessLikeGoods($where,$order,$page,$pagesize,$listtype=0){

        $guess_goods_list = $this
            ->field('goods_id,shop_price,goods_name')
            ->where($where)
            ->order($order)
            ->limit(($page-1)*9,$pagesize)
            ->cache(false,"guess_goods_list_{$page}",TPSHOP_CACHE_TIME)
            ->select();

        $goods_id = array();
        $goodList = array();
        foreach ($guess_goods_list as $k => $val) {
            $val = $val->toArray();
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou'] : '';
            $goods_id[] = $val['goods_id'];
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