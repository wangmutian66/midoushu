<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\appletred\controller;
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


    
}