<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\applet_app\controller;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\model\FlashSale;
use app\common\model\GroupBuy;
use think\Db;
use think\Page;
use app\common\logic\ActivityLogic;

class Activity extends MobileBase {
    public function index(){      
        return $this->fetch();
    }

    // 热销商品
    public function hot_sale()
    {
        $p = I('p/d',1);
        $map['is_on_sale'] = 1;
        $map['is_check']   = 1;
        $map['is_hot']     = 1;
        $map['is_allreturn'] = 0;
        $count = M('goods')->where($map)->count();
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $list = M('goods')->where($map)->page($p.','.$Page->listRows)->order('sort ASC')->select();

        foreach ($list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $list[$k] = $val;
        }
        $hot_sale['list']=goodsimgurl($list);
        $pages['nowPages']=$p;
        $pages['totalPages']=$Page->totalPages;
        exit(formt(['listData'=>$hot_sale,'page'=>$pages]));
       
    }

    // 新品商品
    public function new_sale()
    {
        $p = I('p/d',1);
        $sort = I('sort','sort');              // 排序
        $sort_asc = I('sort_asc','asc');           // 排序
        $price = I('price','');                    // 价钱
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0'));     // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        $price  && ($filter_param['price'] = $price);                        //加入帅选条件中
        $map['is_on_sale'] = 1;
        $map['is_check']   = 1;
        $map['is_new']     = 1;
        $map['is_allreturn'] = 0;
        $count = M('goods')->where($map)->count();
        $pagesize =  C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $list = M('goods')->where($map)->field('goods_id,goods_name,shop_price,comment_count,original_img')->page($p.','.$Page->listRows)->order("$sort $sort_asc")->select();
        foreach ($list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $list[$k] = $val;
        }
        $pages['nowPages']=$p;
        $pages['totalPages']=$Page->totalPages;
        exit(formt(['listData'=>$list,'page'=>$pages]));
    }


   

 
 

   

    
   
  
    
}