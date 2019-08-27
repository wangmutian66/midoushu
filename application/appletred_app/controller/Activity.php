<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\appletred_app\controller;
use app\common\logic\RedGoodsLogic;
use app\common\logic\RedGoodsActivityLogic;
use app\common\model\FlashRedSale;
use app\common\model\GroupRedBuy;
use think\Db;
use think\Page;
use app\common\logic\RedActivityLogic;

class Activity extends MobileBase {
    public function index(){      
        return $this->fetch();
    }

    // 热销商品
    public function hot_sale()
    {
        $map['is_on_sale'] = 1;
        $map['is_check']   = 1;
        $map['is_hot']     = 1;
        $count = M('goods_red')->where($map)->count();
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $this->assign('page', $show);// 赋值分页输出
        $list = M('goods_red')->where($map)->limit($Page->firstRow.','.$Page->listRows)->order('sort ASC')->select();
        foreach ($list as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $list[$k]['midou']       = $midouInfo['midou'];
            $list[$k]['midou_money'] = $midouInfo['midou_money'];
            $list[$k]['midou_index'] = $midouInfo['midou_index'];
        }
        
        $hot_sale['list']=goodsimgurl($list);
        exit(formt($hot_sale));
        
    }

    // 新品商品
    public function new_sale()
    {
        $map['is_on_sale'] = 1;
        $map['is_check']   = 1;
        $map['is_new']     = 1;
        $count = M('goods_red')->where($map)->count();
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $list = M('goods_red')->where($map)->limit($Page->firstRow.','.$Page->listRows)->order('sort desc')->select();
        foreach ($list as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $list[$k]['midou']       = $midouInfo['midou'];
            $list[$k]['midou_money'] = $midouInfo['midou_money'];
            $list[$k]['midou_index'] = $midouInfo['midou_index'];
        }
     
        $new_sale['list']=goodsimgurl($list);
      
        exit(formt($new_sale));
    }



    public function douding_sale()
    {
        $resultdata = [];
        $p = I('p/d',1);
        $sort  = I('sort','dou_sort');     // 排序
        $sort_asc = I('sort_asc','desc');  // 排序
        $price1  = I('post.price1'); // 输入框价钱
        $price2    = I('post.price2');   // 输入框价钱
        $midou_rate        = tpCache('shoppingred.midou_rate');        // 米豆兑换比
        $start_price       = num_flaot3(($price1*$midou_rate));           // 兑换后的米豆.
        $end_price         = num_flaot3(($price2*$midou_rate));           // 兑换后的米豆

        if ($start_price && $end_price) {
            $map['shop_price'] =  ['between',"$start_price,$end_price"];
        }

        $map['is_on_sale'] = ['eq',1];
        $map['is_check']   = ['eq',1];
        $map['is_douding']   = ['eq',1];
        $map['store_count']  = ['gt',0];
        $list = M('goods_red')
            ->where($map)
            ->field("goods_id,goods_name,market_price,comment_count")
            ->page($p,C('PAGESIZE'))
            ->order("$sort $sort_asc")
            ->select();
        foreach ($list as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $list[$k]['goods_thum_images'] =  goods_thum_images($val['goods_id'], 400, 400,'red');
            $list[$k]['midou_index'] = $midouInfo['midou_index'];
        }
        $resultdata['goodsList'] = $list;
        if(I('is_ajax/d',0)){
            return $this->fetch('ajax_hot_sale');
        }
        $resultdata['sort'] = $sort;
        $resultdata['sort_asc'] = $sort_asc;
        $resultdata['price1'] = $price1;
        $resultdata['price2'] = $price2;


        exit(formt($resultdata));
    }
}