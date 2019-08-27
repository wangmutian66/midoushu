<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\mobileyxyp\controller;
use app\common\logic\YxypGoodsLogic;
use app\common\logic\YxypGoodsActivityLogic;
use app\common\model\FlashYxypSale;
use app\common\model\GroupYxypBuy;
use think\Db;
use think\Page;
use app\common\logic\YxypActivityLogic;

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
        $map['is_yxyp']     = 1;
        $count = M('goods')->where($map)->count();
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $this->assign('page', $show);// 赋值分页输出
        $list = M('goods')
                ->where($map)
                ->limit($Page->firstRow.','.$Page->listRows)
                ->order('yxyp_sort asc')
                ->select();
       foreach ($list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $list[$k] = $val;
        }
        $this->assign('goodsList', $list);
        if(I('is_ajax')){
            return $this->fetch('ajax_hot_sale');
        }
        return $this->fetch();
    }

    // 新品商品
    public function new_sale()
    {
        $map['is_on_sale'] = 1;
        $map['is_check']   = 1;
        $map['is_new']     = 1;
        $map['is_yxyp']     = 1;
        $count = M('goods')->where($map)->count();
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $this->assign('page', $show);// 赋值分页输出
        $list = M('goods')
                ->where($map)
                ->limit($Page->firstRow.','.$Page->listRows)
                ->order('yxyp_sort asc')
                ->select();
        foreach ($list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $list[$k] = $val;
        }
        $this->assign('goodsList', $list);

        if(I('is_ajax')){
            return $this->fetch('ajax_hot_sale');
        }
        return $this->fetch();
    }


    /**
     * 团购活动列表
     */
    public function group_list()
    {
        $type =I('get.type');
        //以最新新品排序
        if ($type == 'new') {
            $order = 'gb.start_time';
        } elseif ($type == 'comment') {
            $order = 'g.comment_count';
        } else {
            $order = '';
        }
        $group_by_where = array(
            'gb.start_time'=>array('lt',time()),
            'gb.end_time'=>array('gt',time()),
            'g.is_on_sale'=>1,
            'g.is_check'=>1
        );
        $GroupBuy = new GroupYxypBuy();
    	$count =  $GroupBuy->alias('gb')->join('__GOODS_YXYP__ g', 'g.goods_id = gb.goods_id')->where($group_by_where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
    	$page = new Page($count,$pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
    	$show = $page->show();  // 分页显示输出
    	$this->assign('page',$show);    // 赋值分页输出
        $list = $GroupBuy
            ->alias('gb')
            ->join('__GOODS_YXYP__ g', 'gb.goods_id=g.goods_id AND g.prom_type=2')
            ->where($group_by_where)
            ->page($page->firstRow, $page->listRows)
            ->order($order)
            ->select();
        $this->assign('list', $list);
        if(I('is_ajax')) {
            return $this->fetch('ajax_group_list');      //输出分页
        }
        return $this->fetch();
    }

    /**
     * 活动商品列表
     */
    public function discount_list(){
        $prom_id = I('id/d');    //活动ID
        $where = array(     //条件
            'is_on_sale'=>1,
            'is_check'=>1,
            'prom_type'=>3,
            'prom_id'=>$prom_id,
        );
        $count =  M('goods_yxyp')->where($where)->count(); // 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $prom_list = Db::name('goods_yxyp')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select(); //活动对应的商品
        $spec_goods_price = Db::name('specYxypGoodsPrice')->where(['prom_type'=>3,'prom_id'=>$prom_id])->select(); //规格
        foreach($prom_list as $gk =>$goods){  //将商品，规格组合
            foreach($spec_goods_price as $spk =>$sgp){
                if($goods['goods_id']==$sgp['goods_id']){
                    $prom_list[$gk]['spec_goods_price']=$sgp;
                }
            }
        }
        foreach($prom_list as $gk =>$goods){  //计算优惠价格
            $PromGoodsLogicuse = new \app\common\logic\YxypPromGoodsLogic($goods,$goods['spec_goods_price']);
            if(!empty($goods['spec_goods_price'])){
                $prom_list[$gk]['prom_price']=$PromGoodsLogicuse->getPromotionPrice($goods['spec_goods_price']['price']);
            }else{
                $prom_list[$gk]['prom_price']=$PromGoodsLogicuse->getPromotionPrice($goods['shop_price']);
            }
        }
        $this->assign('prom_list', $prom_list);
        if(I('is_ajax')){
            return $this->fetch('ajax_discount_list');
        }
        return $this->fetch();
    }

    /**
     * 商品活动页面
     * @author lxl
     * @time2017-1
     */
    public function promote_goods(){
        $now_time = time();
        $where = " start_time <= $now_time and end_time >= $now_time ";
        $count = M('prom_yxyp_goods')->where($where)->count();  // 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page  = new Page($count,$pagesize); //分页类
        $promote = M('prom_yxyp_goods')->field('id,title,start_time,end_time,prom_img')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();    //查询活动列表
        $this->assign('promote',$promote);
        if(I('is_ajax')){
            return $this->fetch('ajax_promote_goods');
        }
        return $this->fetch();
    }


    /**
     * 抢购活动列表页
     */
    public function flash_sale_list()
    {
        $time_space = flash_sale_time_space();
        $this->assign('time_space', $time_space);
        return $this->fetch();
    }

    /**
     * 抢购活动列表ajax
     */
    public function ajax_flash_sale()
    {
        $p          = I('p',1);
        $start_time = I('start_time');
        $end_time   = I('end_time');
        $where = array(
            'fl.start_time'=>array('egt',$start_time),
            'fl.end_time'=>array('elt',$end_time),
            'g.is_on_sale'=>1,
            'g.is_check'=>1
        );
        $FlashSale = new FlashYxypSale();
        $flash_sale_goods = $FlashSale->alias('fl')->join('__GOODS_YXYP__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice','goods'])
            ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
            ->where($where)
            ->page($p,10)
            ->select();
        $this->assign('flash_sale_goods',$flash_sale_goods);
        return $this->fetch();
    }

    public function coupon_list()
    {
        $atype = I('atype', 1);
        $user  = session('user');
        $p     = I('p', '');

        $activityLogic = new YxypActivityLogic();
        $result = $activityLogic->getCouponList($atype, $user['user_id'], $p);
        $this->assign('coupon_list', $result);
        if (request()->isAjax()) {
            return $this->fetch('ajax_coupon_list');
        }
        return $this->fetch();
    }

    /**
     * 领券
     */
    public function getCoupon()
    {
        $id = I('coupon_id/d');
        $user = session('user');
        $user['user_id'] = $user['user_id'] ?: 0;
        $activityLogic = new YxypActivityLogic();
        $return = $activityLogic->get_coupon($id, $user['user_id']);
        $this->ajaxReturn($return);
    }
    
    /**
     * 预售列表页
     */
    public function pre_sell_list()
    {
    	$goodsActivityLogic = new YxypGoodsActivityLogic();
    	$pre_sell_list = Db::name('goods_yxyp_activity')->where(array('act_type' => 1, 'is_finished' => 0))->select();
    	foreach ($pre_sell_list as $key => $val) {
    		$pre_sell_list[$key] = array_merge($pre_sell_list[$key], unserialize($pre_sell_list[$key]['ext_info']));
    		$pre_sell_list[$key]['act_status'] = $goodsActivityLogic->getPreStatusAttr($pre_sell_list[$key]);
    		$pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_list[$key]['act_id'], $pre_sell_list[$key]['goods_id']);
    		$pre_sell_list[$key] = array_merge($pre_sell_list[$key], $pre_count_info);
    		$pre_sell_list[$key]['price'] = $goodsActivityLogic->getPrePrice($pre_sell_list[$key]['total_goods'], $pre_sell_list[$key]['price_ladder']);
    	}
    	$this->assign('pre_sell_list', $pre_sell_list);
    	return $this->fetch();
    }
    
    /**
     *   预售详情页
     */
    public function pre_sell()
    {
    	$id = I('id/d', 0);
    	$pre_sell_info = M('goods_yxyp_activity')->where(array('act_id' => $id, 'act_type' => 1))->find();
    	if (empty($pre_sell_info)) {
    		$this->error('对不起，该预售商品不存在或者已经下架了', U('Mobileyxyp/Activity/pre_sell_list'));
    		exit();
    	}
    	$goods = M('goods_yxyp')->where(array('goods_id' => $pre_sell_info['goods_id']))->find();
    	if (empty($goods)) {
    		$this->error('对不起，该预售商品不存在或者已经下架了', U('Mobileyxyp/Activity/pre_sell_list'));
    		exit();
    	}
    
    	$pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
    	$goodsActivityLogic      = new YxypGoodsActivityLogic();
    	$pre_count_info          = $goodsActivityLogic->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']);//预售商品的订购数量和订单数量
    	$pre_sell_info['price']  = $goodsActivityLogic->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品价格
    	$pre_sell_info['amount'] = $goodsActivityLogic->getPreAmount($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品数额ing
    	if ($goods['brand_id']) {
    		$brand = M('brand_yxyp')->where(array('id' => $goods['brand_id']))->find();
    		$goods['brand_name'] = $brand['name'];
    	}
    	$goods_images_list = M('GoodsYxypImages')->where(array('goods_id' => $goods['goods_id']))->select(); // 商品 图册
    	$goods_attribute = M('GoodsYxypAttribute')->getField('attr_id,attr_name'); // 查询属性
    	$goods_attr_list = M('GoodsYxypAttr')->where(array('goods_id' => $goods['goods_id']))->select(); // 查询商品属性表
    	$goodsLogic = new YxypGoodsLogic();
    	$filter_spec = $goodsLogic->get_spec($goods['goods_id']);
    	$spec_goods_price = M('spec_yxyp_goods_price')->where(array('goods_id' => $goods['goods_id']))->getField("key,price,store_count"); // 规格 对应 价格 库存表
    	$commentStatistics = $goodsLogic->commentStatistics($goods['goods_id']);// 获取某个商品的评论统计
    	$this->assign('pre_count_info', $pre_count_info);//预售商品的订购数量和订单数量
    	$this->assign('commentStatistics', $commentStatistics);//评论概览
    	$this->assign('goods_attribute', $goods_attribute);//属性值
    	$this->assign('goods_attr_list', $goods_attr_list);//属性列表
    	$this->assign('filter_spec', $filter_spec);//规格参数
    	$this->assign('goods_images_list', $goods_images_list);//商品缩略图
    	$this->assign('spec_goods_price', json_encode($spec_goods_price, true)); // 规格 对应 价格 库存表\
    	$this->assign('siblings_cate', $goodsLogic->get_siblings_cate($goods['cat_id']));//相关分类
    	$this->assign('look_see', $goodsLogic->get_look_see($goods));//看了又看
    	$this->assign('pre_sell_info', $pre_sell_info);
    	$this->assign('goods', $goods);
    	return $this->fetch();
    }
    
}