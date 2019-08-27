<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\mobile\controller;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\model\FlashSale;
use app\common\model\GroupBuy;
use think\Db;
use think\Page;
use app\common\logic\ActivityLogic;
use app\common\model\SpecGoodsPrice;

class Activity extends MobileBase {
    public function index(){      
        return $this->fetch();
    }

    // 热销商品
    public function hot_sale()
    {
        $p = I('p/d',1);
        $sort  = I('sort','sort');     // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        $start_price  = I('post.price1'); // 输入框价钱
        $end_price    = I('post.price2');   // 输入框价钱   
        if ($start_price && $end_price) {
            $map['shop_price'] =  ['between',"$start_price,$end_price"];
        }
        $map['is_on_sale'] = ['eq',1];
        $map['is_check']   = ['eq',1];
        $map['is_hot']     = ['eq',1];
        $map['is_allreturn'] = ['eq',0];
       
        $list = M('goods')
                ->where($map)
                ->page($p,C('PAGESIZE'))
                ->order("$sort $sort_asc")
                ->select();

        foreach ($list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $list[$k] = $val;
        }

        $this->assign('goodsList', $list);
        if(I('is_ajax/d')){
            return $this->fetch('ajax_hot_sale');
        }
        $this->assign('sort', $sort);
        $this->assign('sort_asc', $sort_asc);
        $this->assign('price1', $start_price);
        $this->assign('price2', $end_price);
        return $this->fetch();
    }

    // 新品商品
    public function new_sale()
    {
        $p = I('p/d',1);
        $sort  = I('sort','new_sort');     // 排序
        $sort_asc = I('sort_asc','desc');  // 排序
        $start_price  = I('post.price1'); // 输入框价钱
        $end_price    = I('post.price2');   // 输入框价钱   
        if ($start_price && $end_price) {
            $map['shop_price'] =  ['between',"$start_price,$end_price"];
        }
        $map['is_on_sale'] = ['eq',1];
        $map['is_check']   = ['eq',1];
        $map['is_new']     = ['eq',1];
        $map['is_allreturn'] = ['eq',0];
      
        // // $map['is_tgy_good'] = 0;
      
        $list = M('goods')
                ->where($map)
                ->page($p,C('PAGESIZE'))
                ->order("$sort $sort_asc")
                ->select();
        foreach ($list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $list[$k] = $val;
        }
        $this->assign('goodsList', $list);
        if(I('is_ajax/d')){
            return $this->fetch('ajax_hot_sale');
        }
        $this->assign('sort', $sort);
        $this->assign('sort_asc', $sort_asc);
        $this->assign('price1', $start_price);
        $this->assign('price2', $end_price);
        return $this->fetch('new_sale');
    }

    // 推荐商品
    public function recommend_sale()
    {
        $map['is_on_sale'] = 1;
        $map['is_check']   = 1;
        $map['is_allreturn'] = 0;
        // $map['is_tgy_good'] = 0;
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
        $this->assign('goodsList', $list);
        if(I('is_ajax')){
            return $this->fetch('ajax_hot_sale');
        }
        return $this->fetch('recommend_sale');
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
            'g.is_check'=>1,
            'g.is_tgy_good'=>0
        );
        $GroupBuy = new GroupBuy();
    	$count =  $GroupBuy->alias('gb')->join('__GOODS__ g', 'g.goods_id = gb.goods_id')->where($group_by_where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
    	$page = new Page($count,$pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
    	$show = $page->show();  // 分页显示输出
    	$this->assign('page',$show);    // 赋值分页输出
        $list = $GroupBuy
            ->alias('gb')
            ->join('__GOODS__ g', 'gb.goods_id=g.goods_id AND g.prom_type=2')
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
            'is_tgy_good'=>0
        );
        $count =  M('goods')->where($where)->count(); // 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $prom_list = Db::name('goods')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select(); //活动对应的商品
        $spec_goods_price = Db::name('specGoodsPrice')->where(['prom_type'=>3,'prom_id'=>$prom_id])->select(); //规格
        foreach($prom_list as $gk =>$goods){  //将商品，规格组合
            foreach($spec_goods_price as $spk =>$sgp){
                if($goods['goods_id']==$sgp['goods_id']){
                    $prom_list[$gk]['spec_goods_price']=$sgp;
                }
            }
        }
        foreach($prom_list as $gk =>$goods){  //计算优惠价格
            $PromGoodsLogicuse = new \app\common\logic\PromGoodsLogic($goods,$goods['spec_goods_price']);
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
        $count = M('prom_goods')->where($where)->count();  // 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page  = new Page($count,$pagesize); //分页类
        $promote = M('prom_goods')->field('id,title,start_time,end_time,prom_img')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();    //查询活动列表
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
        $p = I('p',1);
        $start_time = I('start_time');
        $end_time = I('end_time');
        $where = array(
            'fl.start_time'=>array('egt',$start_time),
            'fl.end_time'=>array('elt',$end_time),
            'g.is_on_sale'=>1,
            'g.is_check'=>1
        );
        $FlashSale = new FlashSale();
        $flash_sale_goods = $FlashSale->alias('fl')->join('__GOODS__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice','goods'])
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
        $user = session('user');
        $p = I('p', '');

        $activityLogic = new ActivityLogic();
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
        $activityLogic = new ActivityLogic();
        $return = $activityLogic->get_coupon($id, $user['user_id']);
        $this->ajaxReturn($return);
    }
    
    /**
     * 预售列表页
     */
    public function pre_sell_list()
    {
    	$goodsActivityLogic = new GoodsActivityLogic();
    	$pre_sell_list = Db::name('goods_activity')->where(array('act_type' => 1, 'is_finished' => 0))->select();
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
    	$pre_sell_info = M('goods_activity')->where(array('act_id' => $id, 'act_type' => 1))->find();
    	if (empty($pre_sell_info)) {
    		$this->error('对不起，该预售商品不存在或者已经下架了', U('Home/Activity/pre_sell_list'));
    		exit();
    	}
    	$goods = M('goods')->where(array('goods_id' => $pre_sell_info['goods_id']))->find();
    	if (empty($goods)) {
    		$this->error('对不起，该预售商品不存在或者已经下架了', U('Home/Activity/pre_sell_list'));
    		exit();
    	}
    
    	$pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
    	$goodsActivityLogic = new GoodsActivityLogic();
    	$pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']);//预售商品的订购数量和订单数量
    	$pre_sell_info['price'] = $goodsActivityLogic->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品价格
    	$pre_sell_info['amount'] = $goodsActivityLogic->getPreAmount($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品数额ing
    	if ($goods['brand_id']) {
    		$brand = M('brand')->where(array('id' => $goods['brand_id']))->find();
    		$goods['brand_name'] = $brand['name'];
    	}
    	$goods_images_list = M('GoodsImages')->where(array('goods_id' => $goods['goods_id']))->select(); // 商品 图册
    	$goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
    	$goods_attr_list = M('GoodsAttr')->where(array('goods_id' => $goods['goods_id']))->select(); // 查询商品属性表
    	$goodsLogic = new GoodsLogic();
    	$filter_spec = $goodsLogic->get_spec($goods['goods_id']);
    	$spec_goods_price = M('spec_goods_price')->where(array('goods_id' => $goods['goods_id']))->getField("key,price,store_count"); // 规格 对应 价格 库存表
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

    //推广员申请购买的产品
    public function tgy_sale()
    {
        $is_ajax = I('is_ajax', 0);
        $staff_id = I('staff_id/d',0);

        //$cat_id = I('cat_id/d',0);

        //推广员审核限制配置信息  goods_unit 1 金额  2件数
        $goods_area = tpCache('basic.goods_area');
        $goods_unit = tpCache('basic.goods_unit');
        $goods_unit_con = tpCache('basic.goods_unit_con');

        if($goods_area == 1){
            $goods_table_name = "goods";
            $category_table_name = "GoodsCategory";
        }else{
            $goods_table_name = "goods_yxyp";
            $category_table_name = "GoodsYxypCategory";
        }

        $user_id = cookie('user_id');

        if($user_id == 0){
            $this->error('请登录！',U('Mobile/user/login'));
        }

        if ($is_ajax == 0 && $staff_id > 0) {

            //查询推广员申请信息
            $apply_info = M('apply_promoters')->field('id,staff_id')->where("user_id=$user_id and status=0 and staff_id=$staff_id")->find();

            if($apply_info){
                //获取已完成订单的购买总金额或总件数
                $order_where['order_status'] = ['in', '2,4'];
                $order_where['o.user_id'] = ['eq', $user_id];
                $order_where['og.tg_ok'] = ['eq', 0];
                $order_where['og.is_tg'] = ['eq', 1];
                #提取现金区购买的商品记录
                $order_list1 = Db::name('order')
                    ->alias('o')
                    ->field('o.order_id,og.goods_id,og.goods_num,og.goods_price')
                    ->join('order_goods og','o.order_id=og.order_id','left')
                    ->where($order_where)
                    ->select();
                #提取一乡一品区购买的商品记录
                $order_list2 = Db::name('order_yxyp')
                    ->alias('o')
                    ->field('o.order_id,og.goods_id,og.goods_num,og.goods_price')
                    ->join('order_yxyp_goods og','o.order_id=og.order_id','left')
                    ->where($order_where)
                    ->select();
                #将两个区购买记录合并，计算推广员申请一共购买了多少
                $order_list = array_merge($order_list1,$order_list2);

                $total_moeny = 0;
                $total_num = 0;
                foreach ($order_list as $order) {
                    if ($goods_unit == 1) {
                        //金额
                        $total_moeny += $order['goods_num'] * $order['goods_price'];
                    } else {
                        //件数
                        $total_num += $order['goods_num'];
                    }
                }

                if ($goods_unit == 1) {
                    //如果设置的是金额
                    if ($total_moeny >= $goods_unit_con || $apply_info['status'] == 3) {
                        $diff_money = 0;
                        //如果达标跳转到首页
                        //$this->redirect(U('Mobile/Index/index'));

                    } else {
                        //如果不达标，计算还差多少金额达标
                        $diff_money = $goods_unit_con - $total_moeny;
                    }

                } else {
                    //如果设置的是件数
                    if ($total_num >= $goods_unit_con || $apply_info['status'] == 3) {
                        $diff_num = 0;
                        //如果件数达标跳转到首页
                        //$this->redirect(U('Mobile/Index/index'));
                    } else {
                        //如果不达标，计算还差多少件数达标
                        $diff_num = $goods_unit_con - $total_num;
                    }

                }

                #获取实体店名称
                $store = db('company')
                    ->alias('com')
                    ->field('cname')
                    ->join('staff staff','com.cid=staff.store_id')
                    ->where('staff.id='.$apply_info["staff_id"])
                    ->find();
                if($store){
                    $storeName = $store['cname'];
                    $this->assign('storeName',$storeName);
                }

                $this->assign('goods_unit', $goods_unit);
                $this->assign('goods_unit_con', $goods_unit_con);
                $this->assign('total_moeny', $total_moeny);
                $this->assign('total_num', $total_num);
                $this->assign('diff_money', $diff_money);
                $this->assign('diff_num', $diff_num);
                $this->assign('unit_str', $goods_unit == 1 ? '元' : '件');
                $this->assign('apply_flag','no');
            }else{
                $apply = M('apply_promoters')->field('id,staff_id')->where("user_id=$user_id and staff_id=$staff_id and status=3")->order("create_time desc")->find();
                #获取实体店名称
                $store = db('company')
                    ->alias('com')
                    ->field('cname')
                    ->join('staff staff','com.cid=staff.store_id')
                    ->where('staff.id='.$apply["staff_id"])
                    ->find();
                if($store){
                    $storeName = $store['cname'];
                    $this->assign('storeName',$storeName);
                }
                $this->assign('apply_flag','yes');
                //$this->redirect('index/index');
            }


        }


        $filter_param = array();          // 帅选数组
        $id       = I('id/d',0);          // 当前分类id
        $sort     = I('sort','sort'); // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        $filter_param['id'] = $id; //加入帅选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类

        //筛选
        $cat_id_arr = getCatGrandson($id,$category_table_name);
        $goods_where = ['is_on_sale' => 1, 'is_tgy_good'=>1, 'is_allreturn' => 0, 'is_check' => 1, 'exchange_integral' => 0,'cat_id'=>['in',$cat_id_arr]];
        $filter_goods_id = Db::name($goods_table_name)->where($goods_where)->getField("goods_id",true);

        unset($goods_where['is_allreturn'],$goods_where['cat_id']);
        $goods_where['g.is_allreturn'] = 0;
        if($goods_area == 1) $goods_where['g.is_allreturn'] = 0;
        $cateArr = M($goods_table_name)
            ->alias('g')
            ->distinct(true)
            ->field('g.cat_id id,cate.name')
            ->where($goods_where)
            ->join('goods_category cate','cate.id=g.cat_id')
            ->select();



        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel =I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel,$cat_id_arr,$goods_table_name);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

        $count = count($filter_goods_id);
        $page = new Page($count,C('PAGESIZE'));
        if($count > 0)
        {

            $goods_list = M($goods_table_name)->where("goods_id","in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();

            foreach ($goods_list as $k => $val) {
                // 可返米豆
                $midouInfo = returnMidou($val['goods_id']);
                // $val['back_midou'] = $midouInfo['midou'];
                $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou']: '';
                $goods_list[$k] = $val;
            }

        }

        $this->assign('goodsList',$goods_list);
        $this->assign('cateArr',$cateArr);
        $this->assign('filter_param',$filter_param); // 帅选条件
        $this->assign('cat_id',$id);
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');


        $this->assign('goods_area',$goods_area);
        if ($is_ajax) {
            return $this->fetch('ajax_tgy_sale');
        }
        return $this->fetch('tgy_sale');
    }
}
   