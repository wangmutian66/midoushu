<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\appletred_app\controller;
use app\common\logic\JssdkLogic;
use Think\Db;
use app\home\model\AccessLog;
use app\common\model\FlashSale;
use think\Page;

class Index extends MobileBase {
    public function __construct()
    {
        parent::__construct();
    }
    public function index(){
        // session('store_id',null);
        $hot_goods = M('goods_red')->where("is_hot=1 and is_on_sale=1 and is_check=1")->order('sort ASC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页热卖商品
        $thems = M('goods_red_category')->where('level=1')->order('sort_order')->limit(9)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('thems',$thems);
        $this->assign('hot_goods',$hot_goods);
        $favourite_goods = M('goods_red')->where("is_on_sale=1 and is_check=1")->order('sort ASC')->limit(10)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品

        foreach ($favourite_goods as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $val['midou']       = $midouInfo['midou'];
            $val['midou_money'] = $midouInfo['midou_money'];
            $val['midou_index'] = $midouInfo['midou_index'];
            $favourite_goods[$k] = $val;
        }


        // 首页新品推荐
        $new_goods_where['is_on_sale'] = 1;
        $new_goods_where['is_check']   = 1;
        $new_goods_where['is_new']       = 1;
        $newcount = Db::name('goods_red')
            ->where($new_goods_where)
            ->limit(20)
            ->count();
        $max_num_new = $newcount-6;
        if($max_num_new < 0)$max_num_new = 0;
        $startnum_new = rand(0,$max_num_new);
        
        $newgoodsList = Db::name('goods_red')
            ->where($new_goods_where)
            ->cache(true,5)
            ->limit($startnum_new.',10')
            ->select();


        foreach ($newgoodsList as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $val['midou']       = $midouInfo['midou'];
            $val['midou_money'] = $midouInfo['midou_money'];
            $val['midou_index'] = $midouInfo['midou_index'];

            $newgoodsList[$k] = $val;
        }
        $advwhere = 'start_time <= '.time().' and end_time >='.time().' and enabled=1';
        $advmodel= M('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        $banner = $advmodel->where('pid=54')->where($advwhere)->limit(5)->field($advfield)->select();
        $addadvertising = $advmodel->where('pid=66')->where($advwhere)->limit(6)->field($advfield)->select();
        // $adv1 = $advmodel->where('pid=43')->where($advwhere)->limit(1)->field($advfield)->select();

        $adv2 = $advmodel->where('pid=44')->where($advwhere)->limit(1)->field($advfield)->select();
        $adv3 = $advmodel->where('pid=51')->where($advwhere)->limit(1)->field($advfield)->select();
        $adv4 =$advmodel->where('pid=52')->where($advwhere)->limit(1)->field($advfield)->select();

        $adv5 = $advmodel->where('pid=75')->where($advwhere)->limit(1)->field($advfield)->select();
        $adv6 = $advmodel->where('pid=100')->where($advwhere)->limit(1)->field($advfield)->select();
        $goodsList['banner']=advurl($banner);
        $goodsList['addadvertising']=advurl($addadvertising);
        // $goodsList['adv1']=advurl($adv1);
        $goodsList['adv2']=advurl($adv2);
        $goodsList['adv3']=advurl($adv3);
        $goodsList['adv4']=advurl($adv4);
        $goodsList['adv5']=advurl($adv5);
        $goodsList['adv6']=advurl($adv6);
        $goodsList['newgoodsList']=goodsimgurl($newgoodsList);
        $goodsList['start_time']=$start_time;
        $goodsList['end_time']=$end_time;
        $goodsList['favourite_goods']=goodsimgurl($favourite_goods);
        // dump(advurl($addadvertising));die();
        return formt($goodsList);
       
    }

  
    
    
   
    
       public function ajaxGetMore(){
        $p = I('p/d',1);

        $where = ['is_on_sale'=>1, 'is_check'=>1];
        $favourite_goods = db('goods_red')->where($where)->order('sort ASC')->page($p,C('PAGESIZE'))->field('goods_id,shop_price,goods_name')->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $count = db('goods')->where($where)->count();
        $Page = new Page($count, 10);
        // dump($favourite_goods);die();
        foreach ($favourite_goods as $k => $val) {
            // 可返米豆
            $rand_str = get_rand_str(6,1,1);
    //         // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $val['midou']       = $midouInfo['midou'];
            $val['midou_money'] = $midouInfo['midou_money'];
            $val['midou_index'] = $midouInfo['midou_index'];

            $val['rand_str']    = $rand_str;

            $favourite_goods[$k] = $val;
        }
        $goodsList['favourite_goods']=goodsimgurl($favourite_goods);
        $page= object_to_array($Page);
        $goodsList['pages']['totalPages']=$page['totalPages'];
        return formt($goodsList);
    }

    #米豆区
    public function index_red(){
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        $banner = $advmodel->where('pid=106')->where($advwhere)->limit(5)->field($advfield)->select();


        //首页banner广告位
        $goodsList['banner']=advurl($banner);
        $goodsModel = db('goods_red');
        $goodsfield = ('goods_id,market_price,goods_name');

        $goods_basic_where['is_on_sale']    =   ['eq',1];
        $goods_basic_where['is_check']    =   ['eq',1];
        $goods_basic_where['store_count']    =   ['gt',0];
        $doudingmap['is_douding'] = 1;
        $newmap['is_new'] = 1;
        $hotmap['is_hot'] = 1;


        #豆丁专区
        #提取6个
        $douding_num = 6;
        $douding_list = $goodsModel
            ->where($goods_basic_where)
            ->where($doudingmap)
            ->field($goodsfield)
            ->order("dou_sort desc")
            ->limit($douding_num)
            ->cache('red_douding_list',TPSHOP_CACHE_TIME)
            ->select();
        foreach ($douding_list as $k => $val) {
            $midouInfo = getMidou($val['goods_id']);
            $douding_list[$k]['goods_thum_images'] =  goods_thum_images($val['goods_id'], 400, 400,'red');
            $douding_list[$k]['midou_index'] = $midouInfo['midou_index'];
        }
        $goodsList['douding_list'] = $douding_list;
        #新品推荐
        #提取6个
        $recommend_num = 6;
        $recommend_list = $goodsModel
            ->where($goods_basic_where)
            ->where($newmap)
            ->field($goodsfield)
            ->order("new_sort desc")
            ->limit($recommend_num)
            ->cache('red_recommend_list',TPSHOP_CACHE_TIME)
            ->select();
        foreach ($recommend_list as $k => $val) {
            $midouInfo = getMidou($val['goods_id']);
            $recommend_list[$k]['midou_index'] = $midouInfo['midou_index'];
            //$goods_val[goods_id]|goods_thum_images=400,400,'red'
            $val['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400,'red');
            $recommend_list[$k] = $val;
        }

        $goodsList['recommend_list'] = $recommend_list;
        #热销精选
        #提取6个
        $hot_num = 6;
        $hot_list = $goodsModel
            ->field($goodsfield)
            ->where($goods_basic_where)
            ->where($hotmap)
            ->order("hot_sort desc")
            ->limit($hot_num)
            ->cache('red_hot_list',TPSHOP_CACHE_TIME)
            ->select();
        foreach ($hot_list as $k => $val) {
            $midouInfo = getMidou($val['goods_id']);
            $hot_list[$k]['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400,'red');
            $hot_list[$k]['midou_index'] = $midouInfo['midou_index'];
        }
        $goodsList['hot_list'] = $hot_list;
        #品类精选

        $goods_show_num = 8;
        $index_category = cache('mobile_red_index_category');
        if(empty($index_category)){
            $index_category = db('goods_red_category')->where(['is_mobile_show'=>1,'parent_id'=>0])->order("sort_order")->field('id,image,mobile_name')->select();
            if(is_array($index_category)){
                foreach ($index_category as $key => $value) {
                    $son_ids = getCatGrandsonRed($value['id']);
                    $index_category[$key]['goods_list']   =   db('goods_red')
                        ->field($goodsfield)
                        ->where('cat_id','in',$son_ids)
                        ->where($goods_basic_where)
                        ->order('shop_price asc')
                        ->limit($goods_show_num)
                        ->select();
                    foreach ($index_category[$key]['goods_list'] as $k => $val) {
                        $midouInfo = getMidou($val['goods_id']);
                        $index_category[$key]['goods_list'][$k]['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400,'red');
                        $index_category[$key]['goods_list'][$k]['midou_index'] = $midouInfo['midou_index'];
                    }
                }
            }
            cache('mobile_red_index_category',$index_category);
        }
        $goodsList['index_category'] = $index_category;
        #猜你喜欢
//        $sort     = I('sort/s','shop_price');// 排序
//        $sort_asc = I('sort_asc/s','asc');  // 排序
//        $price1   = I('price1/f',0);        // 价钱
//        $price2   = I('price2/f',0);        // 价钱

        #每次进入首页清除存储的猜你喜欢加载过的商品ID
        session('goods_ids',null);
        session('goods_page',null);
        return formt($goodsList);
    }
}