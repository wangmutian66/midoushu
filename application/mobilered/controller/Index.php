<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\mobilered\controller;
use app\common\logic\JssdkLogic;
use Think\Db;
use app\common\logic\RedGoodsLogic;
use app\mobile\model\GoodsRed;

class Index extends MobileBase {


    public function index(){

        $this->redirect(url('mobilered/Index/index_red'));
        session('store_id',null);
        /*
            //获取微信配置
            $wechat_list = M('wx_user')->select();
            $wechat_config = $wechat_list[0];
            $this->weixin_config = $wechat_config;        
            // 微信Jssdk 操作类 用分享朋友圈 JS            
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();              
            print_r($signPackage);
        */
        $sort     = I('sort','sort');     // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        $price1    = I('price1','');        // 价钱
        $price2    = I('price2','');        // 价钱
        $hot_goods = M('goods_red')->where("is_hot=1 and is_on_sale=1 and is_check=1")->order("$sort $sort_asc")->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页热卖商品
        $thems = M('goods_red_category')->where('level=1')->order('sort_order')->limit(9)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('thems',$thems);
        $this->assign('hot_goods',$hot_goods);
        $favourite_goods = M('goods_red')->where("is_on_sale=1 and is_check=1")->order("$sort $sort_asc")->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品

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
        $this->assign('newgoodsList',$newgoodsList);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('favourite_goods',$favourite_goods);
        $this->assign('sort', $sort);
        $this->assign('sort_asc', $sort_asc);
        $this->assign('price1', $price1);
        $this->assign('price2', $price2);
    
        return $this->fetch('index');

    }

     

    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }

    /**
     * 模板列表
     */
    public function mobanlist(){
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach($arr as $key => $val)
        {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";            
        }        
    }
    
    /**
     * 商品列表页
     */
    public function goodsList(){
        $id = I('get.id/d',0); // 当前分类id
        $lists = getCatGrandsonRed($id);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    
    public function ajaxGetMore(){
    	$p = I('p/d','1');
        $sort     = I('sort','sort');     // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        
        $price1 = I('price1');        // 价钱
        $price2 = I('price2'); 

        $midou_rate   = tpCache('shoppingred.midou_rate');        // 米豆兑换比
        $midou1       = num_flaot3(($price1*$midou_rate));           // 兑换后的米豆.
        $midou2       = num_flaot3(($price2*$midou_rate));           // 兑换后的米豆
        
        # LX  
        if ($price1) {
            $where['shop_price'] =  ['between',"{$midou1},{$midou2}"];
        }
        $where['is_on_sale'] = ['eq',1];
        $where['is_check'] = ['eq',1];

    	$favourite_goods = Db::name('goods_red')
                            ->where($where)
                            ->order("$sort $sort_asc")
                            ->page($p,C('PAGESIZE'))
                            ->cache(true,TPSHOP_CACHE_TIME)
                            ->select();//首页推荐商品
        foreach ($favourite_goods as $k => $val) {
            $rand_str = get_rand_str(6,1,1);
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $val['midou']       = $midouInfo['midou'];
            $val['midou_money'] = $midouInfo['midou_money'];
            $val['midou_index'] = $midouInfo['midou_index'];
            $val['rand_str']    = $rand_str;
            $favourite_goods[$k] = $val;
        }
    	$this->assign('favourite_goods',$favourite_goods);
    	return $this->fetch();
    }
    
    //微信Jssdk 操作类 用分享朋友圈 JS
    public function ajaxGetWxConfig(){
    	$askUrl = I('askUrl');//分享URL
    	$weixin_config = M('wx_user')->find(); //获取微信配置
    	$jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
    	$signPackage = $jssdk->GetSignPackage(urldecode($askUrl));
    	if($signPackage){
    		$this->ajaxReturn($signPackage,'JSON');
    	}else{
    		return false;
    	}
    }

    #米豆区
    #张洪凯  2018-11-22
    public function index_red(){

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
            $douding_list[$k]['midou_index'] = $midouInfo['midou_index'];
        }

        $this->assign('douding_list',$douding_list);

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
        }
        $this->assign('recommend_list',$recommend_list);

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
            $hot_list[$k]['midou_index'] = $midouInfo['midou_index'];
        }
        $this->assign('hot_list',$hot_list);

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
                        $index_category[$key]['goods_list'][$k]['midou_index'] = $midouInfo['midou_index'];
                    }
                }
            }
            cache('mobile_red_index_category',$index_category);
        }
        $this->assign('index_category',$index_category);


        #猜你喜欢
        $sort     = I('sort/s','shop_price');     // 排序
        $sort_asc = I('sort_asc/s','asc');  // 排序
        $price1   = I('price1/f',0);        // 价钱
        $price2   = I('price2/f',0);        // 价钱


        #每次进入首页清除存储的猜你喜欢加载过的商品ID
        session('goods_ids',null);
        session('goods_page',null);

        $this->assign('sort',$sort);
        $this->assign('sort_asc',$sort_asc);
        $this->assign('price1',$price1);
        $this->assign('price2',$price2);


        return $this->fetch();
    }

    public function ajaxGuessMore(){

        $p = I('p/d',1);
        $pagesize = 8;

        $sort     = I('sort/s','RAND()');     // 排序
        $sort_asc = I('sort_asc/s','desc');  // 排序
        $price1   = I('price1/f',0);        // 价钱
        $price2   = I('price2/f',0);        // 价钱

        $goods_basic_where['is_on_sale']  =   ['eq',1];
        $goods_basic_where['is_check']    =   ['eq',1];

        $not_goods_id = '';
        if(session('goods_ids')){
            $not_goods_id .= ','.session('goods_ids');
        }
        $goods_basic_where['goods_id']    =   ['not in',$not_goods_id];

        if ($price1>=0 && $price2) {
            #米豆兑换比
            $midou_rate   = tpCache('shoppingred.midou_rate');
            #兑换后的米豆.
            $midou1       = num_flaot3(($price1*$midou_rate));
            #兑换后的米豆
            $midou2       = num_flaot3(($price2*$midou_rate));

            $goods_basic_where['shop_price'] =  ['between',"{$midou1},{$midou2}"];
        }

        $order = $sort == "rand" ? "RAND()" : "$sort $sort_asc";
        $goods_red = new GoodsRed();
        $guess_goods_list = $goods_red->getGuessLikeGoods($goods_basic_where,$order,$p,$pagesize);

        $this->assign('guess_goods_list',$guess_goods_list);
        return $this->fetch('ajaxGuessMore');
    }

    #猜你喜欢更多页
    public function guesslike(){

        $p = I('p/d',1);

        $goods_basic_where['is_on_sale']    =   ['eq',1];
        $goods_basic_where['is_check']    =   ['eq',1];

        $pagesize = 8;

        $order = "RAND()";

        $goods_red = new GoodsRed();
        $guess_goods_list = $goods_red->getGuessLikeGoods($goods_basic_where,$order,$p,$pagesize);
        foreach ($guess_goods_list as $key => $value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $guess_goods_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }
        $this->assign('goodsList',$guess_goods_list);
        if(I('is_ajax')) {
            return $this->fetch('activity/ajax_hot_sale');
        }else{
            session('goods_ids',null);
            session('goods_page',null);
        }
        return $this->fetch();
    }


    #品类精选
    #张洪凯  2018-11-22
    public function index8category(){
        $cateModel = db("goods_red_category");
        $category_where['is_show'] = ['eq',1];
        $catid = I('catid/d',0);
        #是否获取默认子分类
        $get_cat_flag = false;
        if($catid == 0){
            $parentid = 0;
            $get_cat_flag = true;
            $cat_id = 0;
        }else{
            $cat_id = $catid;
            #显示对应分类
            /*$parent_id = $cateModel->where($category_where)->where("id=$catid")->value('parent_id');
            $parentid = $parent_id;*/

            #只显示二级分类
            $parent_id = $cateModel->where($category_where)->where("id=$catid")->value('parent_id');
            if($parent_id == 0){
                $parentid = $catid;
            }else{
                $parent_id2 = $cateModel->where($category_where)->where("id=$parent_id")->value('parent_id');
                if($parent_id2 == 0){
                    $parentid = $parent_id;
                }else{
                    $parentid = $parent_id2;
                }
            }
            $get_cat_flag = true;
        }


        if($get_cat_flag){
            $catid = $cateModel->where($category_where)->where("parent_id={$parentid}")->order('sort_order')->limit(1)->value('id');
        }

        #分类列表
        $cate_list = $cateModel->field('id,name')->where($category_where)->where("parent_id=$parentid")->order('sort_order')->select();

        #父级分类名称
        $parentName = $cateModel->where("id=$parentid")->getField('name');


        $this->assign('cate_list',$cate_list);
        $this->assign('cate_count',count($cate_list));
        $this->assign('catid',$catid);
        $this->assign('cat_id',$cat_id);
        $this->assign('parentName',$parentName);
        return $this->fetch();
    }

    public function ajaxIndex8category(){
        $p = I('p/d',1);
        $catid = I('catid/d',0);
        $cat_id = I('cat_id/d',0);
        $keywords = I('keywords/s','');

        if($cat_id > 0){
            $cat_id_arr = getCatGrandsonRed($cat_id);
        }else{
            $cat_id_arr = getCatGrandsonRed($catid);
        }

        $goods_where = ['is_on_sale' => 1,'is_check' => 1, 'exchange_integral' => 0,'cat_id'=>['in',$cat_id_arr]];
        $goods_where['store_count'] = ['gt',0];

        if($keywords != ''){
            $goods_where['goods_name'] = ['like',"%$keywords%"];
        }

        $goods_list = Db::name('goods_red')
            ->alias('g')
            ->where($goods_where)
            ->field('g.goods_id,goods_name,market_price,shop_price,sales_sum as sellcount')
            ->order('shop_price asc')
            ->page($p,C('PAGESIZE'))
            ->cache(false,"red_ajaxIndex8category)_{$catid}_{$p}",TPSHOP_CACHE_TIME)
            ->select();
        foreach($goods_list as $key=>$value) {
            $midouInfo = getMidou($value['goods_id']);
            $goods_list[$key]['midou_index'] = $midouInfo['midou_index'];
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $goods_list[$key]['praise'] = M('comment_red')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();

        }

        $this->assign('goods_list',$goods_list);
        return $this->fetch('ajaxIndex8category');
    }


       
}