<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\applet\controller;
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
        $map['is_on_sale'] = 1;
        $map['is_check']   = 1;
        $map['is_hot']     = 1;
        $map['is_allreturn'] = 0;
        $count = M('goods')->where($map)->count();
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $this->assign('page', $show);// 赋值分页输出
        $list = M('goods')->where($map)->limit($Page->firstRow.','.$Page->listRows)->order('sort ASC')->select();

        foreach ($list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $list[$k] = $val;
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
        $map['is_allreturn'] = 0;
        $count = M('goods')->where($map)->count();
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $this->assign('page', $show);// 赋值分页输出
        $list = M('goods')->where($map)->limit($Page->firstRow.','.$Page->listRows)->order('sort desc')->select();
        foreach ($list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $list[$k] = $val;
        }
        $new_sale['list']=goodsimgurl($list);
        exit(formt($new_sale));
    }


   

 
 

   

    
   
  
    
}