<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\mobile\controller;
use app\common\logic\JssdkLogic;
use app\common\logic\SearchWordLogic;
use Think\Db;
use Think\Cache;
use app\home\model\AccessLog;
use app\common\model\FlashSale;
use app\mobile\model\GoodsRed;
use app\mobile\model\Goods;

class Index extends MobileBase {

    protected $goods;
    protected $goods_red;

    public function __construct()
    {
        parent::__construct();
        $this->goods = new Goods();
        $this->goods_red = new GoodsRed();

    }

    public function index(){

        $this->redirect(url('mobile/Index/index_new'));

    }


    public function index2(){

        $sort     = I('sort','sort');     // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        $price1    = I('price1','');        // 价钱
        $price2    = I('price2','');        // 价钱
        //首页现金专区热卖商品
        $hot_goods = M('goods')
            ->where("is_hot=1 and is_allreturn=0 and is_on_sale=1 and is_check=1 and is_tgy_good=0")
            ->order('sort ASC')
            ->limit(4)
            ->cache('hot_goods',TPSHOP_CACHE_TIME)
            ->select();
        foreach ($hot_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $hot_goods[$k] = $val;
        }
        $this->assign('hot_goods',$hot_goods);


       


        //公告列表
        $list = Db::name('article_notice')->where("(article_type = 0 OR article_type = 1) AND is_open = 1")->order('add_time desc')->select();
        $this->assign('list',$list);


       
        $this->assign('return_goods',$return_goods);
        $this->assign('sort', $sort);
        $this->assign('sort_asc', $sort_asc);
        $this->assign('price1', $price1);
        $this->assign('price2', $price2);
        return $this->fetch('index2');

    }


    public function index3(){
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }
    /**
     * 商品列表页
     */
    public function goodsList(){
        $id = I('get.id/d',0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    
    public function ajaxGetMore(){
    	$p = I('p/d',1);
        $sort  = I('sort','sort');     // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        
        $price1 = I('price1');        // 价钱
        $price2 = I('price2'); 

   
         # LX  
        if ($price1 && $price2) {
            $where['shop_price'] =  ['between',"{$price1},{$price2}"];
        }
        $where['is_on_sale'] = ['eq',1];
        $where['is_check'] = ['eq',1];
        $where['is_allreturn'] = ['eq',0];
        // $where['is_tgy_good'] = ['eq',0];
        // $where = ['is_allreturn'=>0, 'is_on_sale'=>1, 'is_tgy_good'=>0, 'is_check'=>1];
    	$favourite_goods = Db::name('goods')
                         ->where($where)
                         ->order("$sort $sort_asc")
                         ->page($p,C('PAGESIZE'))
                         ->cache(true,TPSHOP_CACHE_TIME)
                         ->select();//首页推荐商品
        foreach ($favourite_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $favourite_goods[$k] = $val;
        }
        
    	$this->assign('favourite_goods',$favourite_goods);
    	return $this->fetch();
    }

     public function ajaxGetMore2(){
        $p = I('p/d',1);
        $sort  = I('sort','sort');     // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        
        $price1 = I('price1');        // 价钱
        $price2 = I('price2'); 

      
         # LX  
        if ($price1 && $price2) {
            $where['shop_price'] =  ['between',"{$price1},{$price2}"];
        }
        $where['is_on_sale'] = ['eq',1];
        $where['is_check'] = ['eq',1];
        $where['is_allreturn'] = ['eq',0];
        // $where['is_tgy_good'] = ['eq',0];
        // $where = ['is_allreturn'=>0, 'is_on_sale'=>1, 'is_tgy_good'=>0, 'is_check'=>1];
        $favourite_goods = Db::name('goods')
                         ->where($where)
                         ->order("$sort $sort_asc")
                         ->page($p,C('PAGESIZE'))
                         ->cache(true,TPSHOP_CACHE_TIME)
                         ->select();//首页推荐商品
        foreach ($favourite_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
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

    /**
     * [手机端访问记录]
     * @author 王牧田
     * @date 2018年8月29日
     * @return mixed
     */
    public function mpublic_log(){
        $url = $_SERVER['HTTP_REFERER'];
        $ip = GetIP();
        $user_id = session('user.user_id');
        $user_id = empty($user_id)?0:$user_id;
        $al_project = new AccessLog();
        $lastal_url = $al_project->where(['al_ip'=>$ip,'user_id'=>$user_id])->order("al_id desc")->value('al_url');
        //->fetchSql(true)
        //判断是否登录 和 刷新后不重复添加数据库
        if($lastal_url !== $url){
            $tolowerurl = strtolower($url);
            //对商品进行处理
            $parram = "/id\/(.*?)\.html/is";
            preg_match_all($parram,$tolowerurl,$result);
            if(!empty($result[1][0])){
                if(strpos($tolowerurl,'mobile/goods') !== false){
                    //现金
                    $alData['al_status'] = 1;
                }else if(strpos($tolowerurl,'mobile/returngoods') !== false){
                    //福利商品
                    $alData['al_status'] = 2;
                }else if(strpos($tolowerurl,'mobilered/goods') !== false) {
                    //米豆
                    $alData['al_status'] = 3;
                }
                $alData['goods_id'] = $result[1][0];
            }

            //搜索内容处理
            $q = I('get.q');
            if(!empty($q)){
                $alData['al_keyword'] = $q;
            }
            $alData['user_id'] = $user_id;
            $alData['al_url'] = $url;
            $alData['create_time'] = time();
            $alData['al_type'] = 1;
            $alData['al_ip'] = $ip;
            $al_project->add($alData);
        }

    }

    #现金专区
    #2018-11-19
    #张洪凯
    public function index_cash(){

        $goodsModel = db('goods');
        $goodsfield = ('goods_id,shop_price,goods_name');

        $hotmap['is_on_sale'] = $newmap['is_on_sale'] = 1;
        $hotmap['is_check'] = $newmap['is_check'] = 1;
        $newmap['is_new'] = 1;
        $hotmap['is_hot'] = 1;
        $hotmap['goods_id'] = $newmap['goods_id'] = ['not in','2977,2978,2979'];

        //新品推荐 滑动展示一排（12个）
        #提取数量
        $recommend_num = 6;
        $recommend_list = cache('mobile_recommend_list');
        if(empty($recommend_list)) {
            $recommend_list = $goodsModel->where($newmap)->order("new_sort desc")->field($goodsfield)->limit($recommend_num)->select();
            foreach ($recommend_list as $k => $val) {
                // 可返米豆
                $midouInfo = returnMidou($val['goods_id']);
                $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou'] : '';
                $val['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
                $recommend_list[$k] = $val;
            }
            cache('mobile_recommend_list',$recommend_list);
        }
        $this->assign('recommend_list',$recommend_list);

        //热销精选 滑动展示一排（15个）
        #提取数量
        $hot_num = 6;
        $hot_list = cache('mobile_hot_list');
        if(empty($hot_list)) {
            $hot_list = $goodsModel->where($hotmap)->order("hot_sort desc")->field($goodsfield)->limit($hot_num)->select();
            foreach ($hot_list as $k => $val) {
                // 可返米豆
                $midouInfo = returnMidou($val['goods_id']);
                $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou'] : '';
                $val['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
                $hot_list[$k] = $val;
            }
            cache('mobile_hot_list',$hot_list);
        }
        $this->assign('hot_list',$hot_list);

        //品类精选
        $goods_show_quantity = 8;
        $goods_basic_where['is_on_sale'] =   ['eq',1];
        $goods_basic_where['is_check']   =   ['eq',1];
        $goods_basic_where['goods_id']   =   ['not in','2977,2978,2979'];
        $index_category = cache('mobile_index_category');
        if(empty($index_category)){
            $index_category = db('goods_category')->where(['is_mobile_show'=>1,'parent_id'=>0])->order("sort_order")->field('id,image,mobile_name')->select();
            if(is_array($index_category)){
                foreach ($index_category as $key => $value) {
                    $son_ids = getCatGrandson($value['id']);
                    $index_category[$key]['goods_list']   =   db('goods')
                        ->field('goods_id,cat_id,goods_sn,goods_name,market_price,shop_price,cost_price,cost_operating')
                        ->where('cat_id','in',$son_ids)
                        ->where($goods_basic_where)
                        ->order('listorder,on_time desc')
                        ->limit($goods_show_quantity)
                        ->select();
                    foreach ($index_category[$key]['goods_list'] as $k => $val) {
                        $midouInfo = returnMidou($val['goods_id']);
                        $val['back_midou'] = $midouInfo['midou'];
                        $index_category[$key]['goods_list'][$k] = $val;
                    }
                }
            }
            cache('mobile_index_category',$index_category);
        }
        $this->assign('index_category',$index_category);

        return $this->fetch();
    }


    /*新版首页*/
    #张洪凯
    public function index_new(){
        
        //公告列表
        $notice_where = "(article_type = 0 OR article_type = 1) AND is_open = 1";
        $announcement_list = Db::name('article_notice')->where($notice_where)->cache('mobile_article_notice')->order('is_top DESC,add_time desc')->limit(1)->select();
        $this->assign('announcement_list',$announcement_list);
        $goods_basic_where['is_on_sale']    =   ['eq',1];
        $goods_basic_where['is_check']    =   ['eq',1];
        $goods_basic_where['goods_id']    =   ['not in','2977,2978,2979'];

        #米豆专区
        $midou_goods_list = $this->goods_red->getIndexRedGoods();
        $this->assign('midou_goods_list',$midou_goods_list);

        #现金专区
        $cash_goods_list = $this->goods->getIndexGoods();
        $this->assign('cash_goods_list',$cash_goods_list);

        #猜你喜欢
        $sort     = I('sort/s','RAND()');     // 排序
        $sort_asc = I('sort_asc/s','desc');  // 排序
        $price1   = I('price1/f',0);        // 价钱
        $price2   = I('price2/f',0);        // 价钱


        #每次进入首页清除存储的猜你喜欢加载过的商品ID
        session('goods_ids',null);
        session('goods_page',null);

        $this->assign('sort',$sort);
        $this->assign('sort_asc',$sort_asc);
        $this->assign('price1',$price1);
        $this->assign('price2',$price2);

        return $this->fetch('index_new');
    }

    public function ajaxGuessMore(){

        $p = I('p/d',1);

        #控制最多显示150个商品
        if($p > 8){
            session('goods_ids',null);
            session('goods_page',null);
            return "";
        }

        if($p == 8){
            $pagesize = 6;
        }else{
            $pagesize = 9;
        }

        $sort     = I('sort/s','RAND()');     // 排序
        $sort_asc = I('sort_asc/s','desc');  // 排序
        $price1   = I('price1/f',0);        // 价钱
        $price2   = I('price2/f',0);        // 价钱

        $goods_basic_where['is_on_sale']  =   ['eq',1];
        $goods_basic_where['is_check']    =   ['eq',1];

        $not_goods_id = '2977,2978,2979';
        if(session('goods_ids')){
            $not_goods_id .= ','.session('goods_ids');
        }
        $goods_basic_where['goods_id']    =   ['not in',$not_goods_id];

        if ($price1>=0 && $price2) {
            $goods_basic_where['shop_price'] =  ['between',"{$price1},{$price2}"];
        }

        $order = $sort == "" ? "RAND()" : "$sort $sort_asc";

        $guess_goods_list = $this->goods->getGuessLikeGoods($goods_basic_where,$order,$p,$pagesize);

        $this->assign('guess_goods_list',$guess_goods_list);
        return $this->fetch('ajaxGuessMore');
    }

    #猜你喜欢更多页
    public function guesslike(){

        $p = I('p/d',1);

        $goods_basic_where['is_on_sale']    =   ['eq',1];
        $goods_basic_where['is_check']    =   ['eq',1];
        $goods_basic_where['goods_id'] = ['not in','2977,2978,2979'];

        $pagesize = 9;

        $order = "RAND()";
        $guess_goods_list = $this->goods->getGuessLikeGoods($goods_basic_where,$order,$p,$pagesize);
        foreach ($guess_goods_list as $key => $value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $guess_goods_list[$key]['comment_count'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
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


    //每日一淘二级页
    #2018-11-22
    #张洪凯
    public function eveyday1tao(){
        $products_where['is_on_sale']  =  ['eq',1];
        $products_where['is_check']  =  ['eq',1];
        $products_where['is_allreturn'] = ['eq',0];
        $products_where['is_tao_sell'] = ['eq',1];

        $products_list = db('goods')
            ->field('goods_id,cat_id,goods_name,market_price,shop_price,sales_sum as sellcount')
            ->where($products_where)
            ->cache('eveyday1tao')
            ->limit(0,C('PAGESIZE'))
            ->order('tao_sort desc')->select();
        foreach ($products_list as $key => $value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $products_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }

        $this->assign('products_list',$products_list);
        return $this->fetch('eveyday1tao');
    }

    public function ajaxeveyday1tao(){
        $p = I('p/d',1);
        $keywords = I('keywords/s','');

        $products_where['is_on_sale'] = ['eq',1];
        $products_where['is_check'] = ['eq',1];
        $products_where['is_allreturn'] = ['eq',0];
        $products_where['is_tao_sell'] = ['eq',1];

        if($keywords != ''){
            $products_where['goods_name'] = ['like',"%$keywords%"];
        }

        $products_list = db('goods')
            ->field('goods_id,cat_id,goods_name,market_price,shop_price,sales_sum as sellcount')
            ->where($products_where)
            ->order('tao_sort desc')
            ->page($p,C('PAGESIZE'))
            ->cache(false,"ajaxeveyday1tao_{$p}",TPSHOP_CACHE_TIME)
            ->select();
        foreach ($products_list as $key => $value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $products_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }

        $this->assign('products_list',$products_list);
        return $this->fetch('ajaxeveyday1tao');
    }

    //秒杀二级页
    public function miaosha(){
        return $this->fetch();
    }

    //品牌直供二级页
    #2018-11-21  张洪凯
    public function brandStraight(){
        #品牌直供分类，从商品中提取分类
        $category_where['g.is_brand_sell'] = ['eq',1];
        $category_where['is_on_sale'] = ['eq',1];
        $category_where['is_check'] = ['eq',1];
        $category_where['g.is_allreturn'] = ['eq',0];
        $category_where['goods_id'] = ['not in','2977,2978,2979'];
        $brandStraightCategoryList = db('goods')
            ->alias('g')
            ->distinct(true)
            ->field('cat_id,cate.name')
            ->join('goods_category cate','g.cat_id=cate.id')
            ->where($category_where)
            ->select();
        $this->assign('brandStraightCategoryList',$brandStraightCategoryList);
        return $this->fetch();
    }

    public function ajaxbrandStraight(){
        $p = I('p/d',1);
        $cat_id = I('cat_id/d',0);

        $brand_goods_where['is_on_sale'] = 1;
        $brand_goods_where['is_check'] = 1;
        $brand_goods_where['is_brand_sell'] = 1;
        $brand_goods_where['is_allreturn'] = 0;
        if($cat_id > 0){
            $brand_goods_where['cat_id'] = $cat_id;
        }

        $goods_list = Db::name('goods')
            ->alias('g')
            ->where($brand_goods_where)
            ->field('g.goods_id,cat_id,goods_name,shop_price')
            ->order('brand_sort desc')
            ->page($p,C('PAGESIZE'))
            ->cache("ajaxbrandStraight_{$cat_id}_{$p}",TPSHOP_CACHE_TIME)
            ->select();


        foreach ($goods_list as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou'] : '';
            $val['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
            $goods_list[$k] = $val;
        }

        $this->assign('goods_list',$goods_list);
        return $this->fetch('ajaxbrandStraight');
    }

    //首页八大品类二级页
    #2018-11-19
    #张洪凯
    public function index8category(){

        $cateModel = db("goods_category");
        $category_where['is_show'] = ['eq',1];
        $catid = I('catid/d',0);
        #是否获取默认子分类
        $get_cat_flag = false;
        if($catid == 0){
            $parentid = 0;
            $get_cat_flag = true;
            $cat_id = 0;
            $field = "id,mobile_name as name";
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
            $field = "id,name";
        }


        if($get_cat_flag){
            $catid = $cateModel->where($category_where)->where("parent_id={$parentid}")->order('sort_order')->limit(1)->value('id');
        }

        #分类列表
        $cate_list = $cateModel->field($field)->where($category_where)->where("parent_id=$parentid")->order('sort_order')->select();

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
            $cat_id_arr = getCatGrandson($cat_id);
        }else{
            $cat_id_arr = getCatGrandson($catid);
        }

        $goods_where = ['is_on_sale' => 1, 'is_allreturn' => 0, 'is_check' => 1, 'exchange_integral' => 0,'cat_id'=>['in',$cat_id_arr]];

        if($keywords != ''){
            $goods_where['goods_name'] = ['like',"%$keywords%"];
        }

        $goods_list = Db::name('goods')
            ->alias('g')
            ->where($goods_where)
            ->field('g.goods_id,cat_id,goods_sn,goods_name,market_price,shop_price,cost_price,cost_operating,sales_sum as sellcount')
            ->order('listorder,on_time desc')
            ->page($p,C('PAGESIZE'))
            ->cache(false,"ajaxIndex8category)_{$catid}_{$p}",TPSHOP_CACHE_TIME)
            ->select();
        foreach($goods_list as $key=>$value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $goods_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }

        $this->assign('goods_list',$goods_list);
        return $this->fetch('ajaxIndex8category');
    }


    //新品推荐
    public function newProducts(){
        $products_where['is_on_sale']  =  ['eq',1];
        $products_where['is_check']  =  ['eq',1];
        $products_where['is_allreturn'] = ['eq',0];
        $products_where['on_time'] = ['between time',[date('Y-m-d H:i:s',time()-24*3600*60),date('Y-m-d H:i:s')]];
        $products_where['goods_id'] = ['not in','2977,2978,2979'];


        $products_list = M('goods')
            ->field('goods_id,cat_id,goods_name,market_price,shop_price,sales_sum as sellcount')
            ->where($products_where)
            //->cache('newProducts')
            ->limit(0,C('PAGESIZE'))
            ->order('on_time desc')->select();
        foreach ($products_list as $key => $value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $products_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }

        $this->assign('products_list',$products_list);
        return $this->fetch('newProducts');
    }

    public function ajaxNewProducts(){
        $p = I('p/d',1);
        $keywords = I('keywords/s','');

        $products_where['is_on_sale'] = ['eq',1];
        $products_where['is_check'] = ['eq',1];
        $products_where['is_allreturn'] = ['eq',0];
        $products_where['on_time'] = ['between time',[date('Y-m-d H:i:s',time()-24*3600*60),date('Y-m-d H:i:s')]];
        $products_where['goods_id'] = ['not in','2977,2978,2979'];

        if($keywords != ''){
            $products_where['goods_name'] = ['like',"%$keywords%"];
        }

        $products_list = M('goods')
                         ->field('goods_id,cat_id,goods_name,market_price,shop_price,sales_sum as sellcount')
                         ->where($products_where)
                         ->order('on_time desc')
                         ->page($p,C('PAGESIZE'))
                         ->cache(false,"ajaxNewProducts_{$p}",TPSHOP_CACHE_TIME)
                         ->select();
        foreach ($products_list as $key => $value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $products_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }
        
        $this->assign('products_list',$products_list);
        return $this->fetch('ajaxNewProducts');
    }

    //特卖专区
    #张洪凯  2018-12-8
    public function specialSale(){
        $products_where['is_on_sale']  =  ['eq',1];
        $products_where['is_check']  =  ['eq',1];
        $products_where['is_allreturn'] = ['eq',0];
        $products_where['is_temai'] = ['eq',1];

        $products_list = db('goods')
            ->field('goods_id,cat_id,goods_name,market_price,shop_price,sales_sum as sellcount')
            ->where($products_where)
            ->cache('specialSale20181208',TPSHOP_CACHE_TIME)
            ->limit(0,C('PAGESIZE'))
            ->order('temai_sort desc')->select();
        foreach ($products_list as $key => $value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $products_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }

        $this->assign('products_list',$products_list);
        return $this->fetch('specialSale');
    }

    public function ajaxSpecialSale(){
        $p = I('p/d',1);
        $keywords = I('keywords/s','');

        $products_where['is_on_sale'] = ['eq',1];
        $products_where['is_check'] = ['eq',1];
        $products_where['is_allreturn'] = ['eq',0];
        $products_where['is_temai'] = ['eq',1];

        if($keywords != ''){
            $products_where['goods_name'] = ['like',"%$keywords%"];
        }

        $products_list = db('goods')
            ->field('goods_id,cat_id,goods_name,market_price,shop_price,sales_sum as sellcount')
            ->where($products_where)
            ->order('temai_sort desc')
            ->page($p,C('PAGESIZE'))
            ->cache("ajaxSpecialSale_{$p}",TPSHOP_CACHE_TIME)
            ->select();
        foreach ($products_list as $key => $value) {
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $products_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }

        $this->assign('products_list',$products_list);
        return $this->fetch('ajaxSpecialSale');
    }


    //优品热卖
    public function sellHot(){

        #优品热卖分类，从商品中提取分类
        #2018-11-21  张洪凯
        $category_where['g.is_hot_sell'] = ['eq',1];
        $category_where['is_on_sale'] = ['eq',1];
        $category_where['is_check'] = ['eq',1];
        $category_where['g.is_allreturn'] = ['eq',0];
        $sellHotCategoryList = db('goods')
            ->alias('g')
            ->distinct(true)
            ->field('cat_id,cate.name')
            ->join('goods_category cate','g.cat_id=cate.id')
            ->where($category_where)
            ->select();
        $this->assign('sellHotCategoryList',$sellHotCategoryList);


        return $this->fetch('sellHot');
    }

    public function ajaxGetSellHot(){
        $p = I('p/d',1);
        $cat_id = I('cat_id/d',0);

        $hot_goods_where['is_on_sale'] = 1;
        $hot_goods_where['is_check'] = 1;
        $hot_goods_where['is_hot_sell'] = 1;
        $hot_goods_where['is_allreturn'] = 0;
        if($cat_id > 0){
            $hot_goods_where['cat_id'] = $cat_id;
        }

        $goods_list = Db::name('goods')
            ->alias('g')
            ->where($hot_goods_where)
            ->field('g.goods_id,cat_id,goods_name,shop_price')
            ->order('hot_sell_sort desc')
            ->page($p,C('PAGESIZE'))
            ->cache("ajaxGetSellHot_{$cat_id}_{$p}",TPSHOP_CACHE_TIME)
            ->select();


            foreach ($goods_list as $k => $val) {
                // 可返米豆
                $midouInfo = returnMidou($val['goods_id']);
                $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou'] : '';
                $val['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
                $goods_list[$k] = $val;
            }

        $this->assign('goods_list',$goods_list);
        return $this->fetch('ajaxGetSellHot');
    }

    //专题精选
    #2018-11-22
    #张洪凯
    public function projectfine(){
        $p = I('p/d',0);
        $isajax = I('isajax/d',0);

        $adv_where['pid'] = ['eq',104];
        $adv_where['enabled'] = ['eq',1];
        $adv_where['start_time'] = ['elt',time()];
        $adv_where['end_time'] = ['egt',time()];
        $adv_where['is_open'] = ['eq',1];

        $adv_list = Db::name('ad')
            ->alias('ad')
            ->field('ad_link,ad_code,ad_name')
            ->join('ad_position adv','adv.position_id=ad.pid')
            ->where($adv_where)
            ->order('orderby,ad_id desc')
            //->page($p,C('PAGESIZE'))
            ->page($p,2)
            ->cache("ajaxGetSellHot_{$p}",TPSHOP_CACHE_TIME)
            ->select();

        $this->assign('adv_list',$adv_list);

        if($isajax == 1){
            return $this->fetch("ajaxprojectfine");
        }

        return $this->fetch();
    }

       
}