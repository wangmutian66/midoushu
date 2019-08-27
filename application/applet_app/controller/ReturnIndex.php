<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\applet_app\controller;
use app\common\logic\JssdkLogic;
use Think\Db;
class ReturnIndex extends MobileBase {

    public function index(){
       
        $favourite_goods = M('goods')->where("is_allreturn=1 and is_on_sale=1 and is_check=1")->order('sort ASC')->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品

        foreach ($favourite_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $favourite_goods[$k] = $val;
        }
        $list['favourite_goods']=$favourite_goods;
        exit(formt($list));
     
    }

   
 

    
    public function ajaxGetMore(){
    	$p = I('p/d',1);
        $where = ['is_allreturn'=>1, 'is_on_sale'=>1, 'is_check'=>1];
    	$favourite_goods = Db::name('goods')->where($where)->order('sort ASC')->page($p,C('PAGESIZE'))->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品

        foreach ($favourite_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $favourite_goods[$k] = $val;
        }
        $ajaxGetMore['favourite_goods']=$favourite_goods;
        exit(formt($ajaxGetMore));
    }
    
   
       
}