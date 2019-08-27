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
use app\home\model\AccessLog;
use app\common\model\FlashSale;
use think\Page;

class Index extends MobileBase {
    public function __construct()
    {
        parent::__construct();
    }

    public function index(){
        
        $favourite_goods = M('goods')->where("is_allreturn=0 and is_on_sale=1 and is_check=1")->order('sort ASC')->limit(10)->field('goods_id,shop_price,goods_name')->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        foreach ($favourite_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou']: '';
            $favourite_goods[$k] = $val;
        }

        //秒杀商品
        $now_time = time();  //当前时间
        if(is_int($now_time/7200)){      //双整点时间，如：10:00, 12:00
            $start_time = $now_time;
        }else{
            $start_time = floor($now_time/7200)*7200; //取得前一个双整点时间
        }
        $end_time = $start_time+7200;   //结束时间
        $flash_sale_list = M('goods')->alias('g')
            ->field('g.goods_id,f.price,s.item_id')
            ->join('flash_sale f','g.goods_id = f.goods_id','LEFT')
            ->join('__SPEC_GOODS_PRICE__ s','s.prom_id = f.id AND g.goods_id = s.goods_id','LEFT')
            ->where("start_time = $start_time and end_time = $end_time")
            ->limit(3)->select();
            ///*首页广告位* -s-///
        $advwhere = 'start_time <= '.time().' and end_time >='.time() .' and enabled=1';
        $advmodel= M('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        $banner = $advmodel->where('pid=32')->where($advwhere)->limit(5)->field($advfield)->select();
        $addadvertising = $advmodel->where('pid=65')->where($advwhere)->limit(6)->field($advfield)->select();
        $advertising = $advmodel->where('pid=41')->where($advwhere)->limit(1)->field($advfield)->select();
        $newadvertising = $advmodel->where('pid=64')->where($advwhere)->limit(1)->field($advfield)->select();
        $newadvertisingleft = $advmodel->where('pid=45')->where($advwhere)->limit(1)->field($advfield)->select();
        $newadvertisingright =$advmodel->where('pid=46')->where($advwhere)->limit(1)->field($advfield)->select();
        $hotadvertising = $advmodel->where('pid=47')->where($advwhere)->limit(1)->field($advfield)->select();
        $adv6 = $advmodel->where('pid=100')->where($advwhere)->limit(1)->field($advfield)->select();
            ///*首页广告位* -e-///
        $goodsList['flash_sale_list']=$flash_sale_list;
        $goodsList['start_time']=$start_time;
        $goodsList['end_time']=$end_time;
        $goodsList['banner']=advurl($banner);
        $goodsList['addadvertising']=advurl($addadvertising);
        $goodsList['advertising']=advurl($advertising);
        $goodsList['newadvertising']=advurl($newadvertising);
        $goodsList['newadvertisingleft']=advurl($newadvertisingleft);
        $goodsList['newadvertisingright']=advurl($newadvertisingright);
        $goodsList['hotadvertising']=advurl($hotadvertising);
        $goodsList['favourite_goods']=goodsimgurl($favourite_goods);
        $goodsList['adv6']=advurl($adv6);
        return formt($goodsList);
    }
    
    public function ajaxGetMore(){
    	$p = I('p/d',1);

        $where = ['is_allreturn'=>0, 'is_on_sale'=>1, 'is_check'=>1];
    	$favourite_goods = db('goods')->where($where)->order('sort ASC')->page($p,C('PAGESIZE'))->field('goods_id,shop_price,goods_name')->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $count = db('goods')->where($where)->count();
        $Page = new Page($count, 10);
        foreach ($favourite_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $favourite_goods[$k] = $val;
        }
        $goodsList['favourite_goods']=goodsimgurl($favourite_goods);
        $page= object_to_array($Page);
        $goodsList['pages']['totalPages']=$page['totalPages'];
        return formt($goodsList);
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





    /**
     * [新版手机端米豆区首页]
     * @author 王牧田
     * @date 2018-11-16
     */
    public function indexrednew(){
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $goodsRedModel = db('goods_red');

        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        $goodsfield = ('goods_id,shop_price,goods_name');
        $banner = $advmodel->where('pid=32')->where($advwhere)->limit(5)->field($advfield)->select();

        $map['is_on_sale'] = $hotmap['is_on_sale'] = $newmap['is_on_sale'] = 1;
        $newmap['is_new'] = 1;
        $hotmap['is_hot'] = 1;
        //首页banner广告位
        $goodsList['banner']=advurl($banner);
        //米豆展区 滑动展示两排（每排15个）
        $goodsList["redexhibition"] = $goodsRedModel->where($map)->order("sort asc")->field($goodsfield)->limit(30)->select();
        foreach ($goodsList["redexhibition"] as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou']: '';
            $val['goods_thum_images'] = goods_thum_images($val['goods_id'],400,400,'red');
            $goodsList["redexhibition"][$k] = $val;
        }

        //新品推荐 滑动展示一排（20个）
        $goodsList["redrecommend"] = $goodsRedModel->where($newmap)->order("sort asc")->field($goodsfield)->limit(20)->select();
        foreach ($goodsList["redrecommend"] as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou']: '';
            $val['goods_thum_images'] = goods_thum_images($val['goods_id'],400,400,'red');
            $goodsList["redrecommend"][$k] = $val;
        }
        //热销精选 滑动展示一排（20个）
        $goodsList["hotrecommend"] = $goodsRedModel->where($hotmap)->order("sort asc")->field($goodsfield)->limit(20)->select();
        foreach ($goodsList["hotrecommend"] as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou']: '';
            $val['goods_thum_images'] = goods_thum_images($val['goods_id'],400,400,'red');
            $goodsList["hotrecommend"][$k] = $val;
        }
        //品类精选上方广告位
        $goodsList["advertising"] = $advmodel->where('pid=41')->where($advwhere)->limit(1)->field($advfield)->select();
        //分类区
        $goodsList["cat_list"] = M('goods_red_category')->cache(true)->where("parent_id = 0")->order('sort_order')->field("id,mobile_name")->select();//所有分类

            
        //品类精选下面的广告位
        //品类精选下面的商品

        return formt($goodsList);

    }



    /**
     * [新版手机端现金区首页]
     * @author 王牧田
     * @date 2018-11-16
     */
    public function indexnew(){
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $goodsRedModel = db('goods');

        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        $goodsfield = ('goods_id,shop_price,goods_name');
        $banner = $advmodel->where('pid=32')->where($advwhere)->limit(5)->field($advfield)->select();

        $map['is_on_sale'] = $hotmap['is_on_sale'] = $newmap['is_on_sale'] = 1;
        $newmap['is_new'] = 1;
        $hotmap['is_hot'] = 1;
        //首页banner广告位
        $goodsList['banner']=advurl($banner);

        //新品推荐 滑动展示一排（20个）
        $goodsList["recommend"] = $goodsRedModel->where($newmap)->order("sort asc")->field($goodsfield)->limit(20)->select();
        foreach ($goodsList["recommend"] as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou']: '';
            $val['goods_thum_images'] = goods_thum_images($val['goods_id'],400,400);
            $goodsList["recommend"][$k] = $val;
        }
        //热销精选 滑动展示一排（20个）
        $goodsList["hotrecommend"] = $goodsRedModel->where($hotmap)->order("sort asc")->field($goodsfield)->limit(20)->select();
        foreach ($goodsList["hotrecommend"] as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou']: '';
            $val['goods_thum_images'] = goods_thum_images($val['goods_id'],400,400);
            $goodsList["hotrecommend"][$k] = $val;
        }
        //品类精选上方广告位
        $goodsList["advertising"] = $advmodel->where('pid=41')->where($advwhere)->limit(1)->field($advfield)->select();
        //分类区
        $goodsList["cat_list"] = M('goods_category')->cache(true)->where("parent_id = 0")->order('sort_order')->field("id,mobile_name")->select();//所有分类


        //品类精选下面的广告位
        //品类精选下面的商品

        $goods_basic_where['is_on_sale']    =   ['eq',1];
        $goods_basic_where['is_check']    =   ['eq',1];
        $goods_show_quantity = 6;
        $index_category = cache('mobile_index_category');
        if(empty($index_category)){
            $index_category = db('goods_category')->where('is_mobile_show',1)->field('id,image,mobile_name')->select();
            if(is_array($index_category)){
                foreach ($index_category as $key => $value) {
                    $son_ids = getCatGrandson($value['id']);
                    $index_category[$key]['goods_list']   =   db('goods')
                        ->field('goods_id,cat_id,goods_sn,goods_name,market_price,shop_price,cost_price,cost_operating')
                        ->where('cat_id','in',$son_ids)
                        ->where($goods_basic_where)
                        ->order('sort asc')
                        ->limit($goods_show_quantity)
                        ->select();
                    foreach ($index_category[$key]['goods_list'] as $k => $val) {
                        $midouInfo = returnMidou($val['goods_id']);
                        $val['back_midou'] = $midouInfo['midou'];
                        $val['goods_thum_images'] = goods_thum_images($val['goods_id'],400,400);
                        $index_category[$key]['goods_list'][$k] = $val;
                    }
                }
            }
            cache('mobile_index_category_mobile',$index_category);
        }

        //现金区栏目图片
        $goodsList['index_category'] = $index_category;
        return formt($goodsList);

    }


    /**
     * [现金去首页]
     * @author 王牧田
     * @date 2019-01-28
     * @return mixed
     */
    public function index_cash(){


        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        $banner = $advmodel->where('pid=32')->where($advwhere)->limit(5)->field($advfield)->select();


        //首页banner广告位
        $goodsList['banner']=advurl($banner);


        $goodsModel = db('goods g');
        $goodsfield = ('g.goods_id,g.shop_price,g.goods_name,g.suppliers_id');

        $hotmap['g.is_on_sale'] = $newmap['g.is_on_sale'] = 1;
        $hotmap['g.is_check'] = $newmap['g.is_check'] = 1;
        $newmap['g.is_new'] = 1;
        $hotmap['g.is_hot'] = 1;
        $hotmap['g.goods_id'] = $newmap['g.goods_id'] = ['not in','2977,2978,2979'];

        //新品推荐 滑动展示一排（12个）
        #提取数量
        $recommend_num = 6;
        $recommend_list = cache('mobile_recommend_list');
        if(empty($recommend_list)) {
            $recommend_list = $goodsModel
                ->join('suppliers s', 'g.suppliers_id = s.suppliers_id','left')
                ->where($newmap)
                ->order("new_sort desc")
                ->field($goodsfield.',s.suppliers_name')
                ->limit($recommend_num)
                ->select();
            foreach ($recommend_list as $k => $val) {
                // 可返米豆
                $midouInfo = returnMidou($val['goods_id']);
                $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou'] : '';
                $val['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
                $recommend_list[$k] = $val;
            }
            cache('mobile_recommend_list',$recommend_list);
        }

        $goodsList['recommend_list'] = $recommend_list;
        //热销精选 滑动展示一排（15个）
        #提取数量
        $hot_num = 6;
        $hot_list = cache('mobile_hot_list');
        if(empty($hot_list)) {
            $hot_list = $goodsModel
                ->join('suppliers s', 'g.suppliers_id = s.suppliers_id','left')
                ->where($hotmap)
                ->order("hot_sort desc")
                ->field($goodsfield.',s.suppliers_name')
                ->limit($hot_num)
                ->select();
            foreach ($hot_list as $k => $val) {
                // 可返米豆
                $midouInfo = returnMidou($val['goods_id']);
                $val['back_midou'] = isset($midouInfo['midou']) ? $midouInfo['midou'] : '';
                $val['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
                $hot_list[$k] = $val;
            }
            cache('mobile_hot_list',$hot_list);
        }

        $goodsList['hot_list'] = $hot_list;
        //品类精选
        $goods_show_quantity = 8;
        $goods_basic_where['g.is_on_sale'] =   ['eq',1];
        $goods_basic_where['g.is_check']   =   ['eq',1];
        $goods_basic_where['g.goods_id']   =   ['not in','2977,2978,2979'];
        $index_category = cache('mobile_index_category');
        if(empty($index_category)){
            $index_category = db('goods_category')->where(['is_mobile_show'=>1,'parent_id'=>0])->order("sort_order")->field('id,image,mobile_name')->select();

            if(is_array($index_category)){
                foreach ($index_category as $key => $value) {
                    $son_ids = getCatGrandson($value['id']);
                    $index_category[$key]['goods_list']   =   db('goods g')
                        ->field('g.goods_id,g.cat_id,g.goods_sn,g.goods_name,g.market_price,g.shop_price,g.cost_price,g.cost_operating,g.suppliers_id,s.suppliers_name')
                        ->join('suppliers s', 'g.suppliers_id = s.suppliers_id','left')
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

        $goodsList['index_category'] = $index_category;
        #猜你喜欢
//        $sort     = I('sort/s','shop_price');     // 排序
//        $sort_asc = I('sort_asc/s','asc');  // 排序
//        $price1   = I('price1/f',0);        // 价钱
//        $price2   = I('price2/f',0);        // 价钱


        #每次进入首页清除存储的猜你喜欢加载过的商品ID
        session('goods_ids',null);
        session('goods_page',null);
//        $goodsList['sort'] = $sort;
//        $goodsList['sort_asc'] = $sort_asc;
//        $goodsList['price1'] = $price1;
//        $goodsList['price2'] = $price2;
//        dump($goodsList);exit();
        return formt($goodsList);
    }


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
            $douding_list[$k]['midou_index'] = $midouInfo['midou_index'];
            $douding_list[$k]['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
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
            $recommend_list[$k]['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
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
            $hot_list[$k]['midou_index'] = $midouInfo['midou_index'];
            $hot_list[$k]['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
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
                        $index_category[$key]['goods_list'][$k]['midou_index'] = $midouInfo['midou_index'];
                        $index_category[$key]['goods_list'][$k]['goods_thum_images'] = goods_thum_images($val['goods_id'], 400, 400);
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


    /**
     * [新版首页]
     * @author 王牧田
     * @date 2018-11-19
     */
    public function Home(){
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        $goodsfield = ('goods_id,shop_price,goods_name,market_price');
        //banner图片
        $banner = $advmodel->where('pid=78')->where($advwhere)->limit(5)->field($advfield)->select();
        $bannermidou = $advmodel->where('pid=75')->where($advwhere)->limit(1)->field($advfield)->select();
        //公告列表
        $notice_where = "(article_type = 0 OR article_type = 1) AND is_open = 1";
        $announcement_list = Db::name('article_notice')->where($notice_where)->cache('mobile_article_notice')->order('add_time desc')->limit(1)->select();
        #米豆专区
        $goods_basic_where['is_on_sale']    =   ['eq',1];
        $goods_basic_where['is_check']    =   ['eq',1];
        $midou_goods_list = M('goods_red')
            ->where($goods_basic_where)
            ->order('sort ASC')
            ->limit(6)
            ->field($goodsfield)
            ->cache('midou_goods_listmobile',TPSHOP_CACHE_TIME)
            ->select();

        foreach ($midou_goods_list as $v=>$row){
            $midou_goods_list[$v]["midou"]=$row["shop_price"]/10;
            $midou_goods_list[$v]['goods_thum_images'] = goods_thum_images($row['goods_id'],400,400);
        }

        #现金专区
        $goods_show_quantity = 6;
        $index_category = cache('mobile_index_category');
        if(empty($index_category)){
            $index_category = db('goods_category')->where('is_mobile_show',1)->field('id,image,mobile_name')->select();
            if(is_array($index_category)){
                foreach ($index_category as $key => $value) {
                    $son_ids = getCatGrandson($value['id']);
                    $index_category[$key]['goods_list']   =   db('goods')
                        ->field('goods_id,cat_id,goods_sn,goods_name,market_price,shop_price,cost_price,cost_operating')
                        ->where('cat_id','in',$son_ids)
                        ->where($goods_basic_where)
                        ->order('sort asc')
                        ->limit($goods_show_quantity)
                        ->select();
                    foreach ($index_category[$key]['goods_list'] as $k => $val) {
                        $midouInfo = returnMidou($val['goods_id']);
                        $val['back_midou'] = $midouInfo['midou'];
                        $val['goods_thum_images'] = goods_thum_images($val['goods_id'],400,400);
                        $index_category[$key]['goods_list'][$k] = $val;
                    }
                }
            }
            cache('mobile_index_category_mobile',$index_category);
        }

        //推荐速达

        $tjsd['newProducts'] = URL."/template/mobile/new2/static/images/new_index/adver_01.png";  //新品推荐
        $tjsd['sellHot'] = URL."/template/mobile/new2/static/images/new_index/adver_03.png";      //优品热卖
        $tjsd['concentrate'] = URL."/template/mobile/new2/static/images/new_index/adver_02.png";        //专题精选
        $tjsd['miaosha'] = URL."/template/mobile/new2/static/images/new_index/adver_04.png";        //秒杀
        $tjsd['pozan'] = URL."/template/mobile/new2/static/images/new_index/adver_05.png";          //品牌直供
        $tjsd['eveyday1tao'] = URL."/template/mobile/new2/static/images/new_index/adver_06.png";   //每日一淘



        //首页上面的轮播图
        $homedata['banner'] = $banner;
        //商城头条
        $homedata['announcement_list'] = $announcement_list;
        //推荐速达部分的图片
        $homedata['tjsd'] = $tjsd;
        //米豆专区下面的广告位
        $homedata['bannermidou'] = $bannermidou;
        //米豆专区下面的商品
        $homedata['midou_goods_list'] = $midou_goods_list;
        //现金区栏目图片
        $homedata['index_category'] = $index_category;

        return formt($homedata);

    }

    /**
     * [每日一淘]
     * @author 吴超群
     * @date 2018-1-28
     */
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
            $products_list[$key]['goods_img']=goods_thum_images($value['goods_id'],400,400);
            $commentWhere = ['is_show' => 1, 'goods_id' => $value['goods_id'], 'parent_id' => 0, 'user_id' => ['gt', 0]];
            $products_list[$key]['praise'] = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        }
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        //banner图片
        $banner = $advmodel->where('pid=97')->where($advwhere)->limit(5)->field($advfield)->select();
        return formt(['listData'=>$products_list,'banner'=>$banner]);
    }

    /**
     * [新品上架]
     * @author 吴超群
     * @date 2018-1-29
     */
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
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        //banner图片
        $banner = $advmodel->where('pid=92')->where($advwhere)->limit(5)->field($advfield)->select();
        return formt(['listData'=>$products_list,'banner'=>$banner]);
    }

    /**
     * [特卖专区]
     * @author 吴超群
     * @date 2018-1-29
     */
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
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        //banner图片
        $banner = $advmodel->where('pid=108')->where($advwhere)->limit(5)->field($advfield)->select();
        $this->assign('products_list',$products_list);
        return formt(['listData'=>$products_list,'banner'=>$banner]);
    }

    //优品热卖
    public function sellHot(){
        $p = I('p/d',1);
        $cat_id = I('cat_id/d',0);
        $hot_goods_where['is_on_sale'] = 1;
        $hot_goods_where['is_check'] = 1;
        $hot_goods_where['is_hot_sell'] = 1;
        $hot_goods_where['is_allreturn'] = 0;
        if($cat_id > 0){
            $hot_goods_where['cat_id'] = $cat_id;
        }
        $count =db('goods')
            ->alias('g')
            ->where($hot_goods_where)
            ->field('g.goods_id,cat_id,goods_name,shop_price')
            ->order('hot_sell_sort desc')
            ->count();
        $Page = new Page($count, 10);
        $goods_list =db('goods')
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
        $page= object_to_array($Page);
        $pages['totalPages']=$page['totalPages'];
        $pages['nowPages']=$p;

        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        //banner图片
        $banner = $advmodel->where('pid=93')->where($advwhere)->limit(1)->field($advfield)->select();
        return formt(['listData'=>$goods_list,'category'=>$sellHotCategoryList,'page'=>$pages,'banner'=>$banner]);
    }



    public function brandStraight(){
        $p = I('p/d',1);
        $cat_id = I('cat_id/d',0);

        $brand_goods_where['is_on_sale'] = 1;
        $brand_goods_where['is_check'] = 1;
        $brand_goods_where['is_brand_sell'] = 1;
        $brand_goods_where['is_allreturn'] = 0;
        if($cat_id > 0){
            $brand_goods_where['cat_id'] = $cat_id;
        }
        $count =db('goods')
            ->alias('g')
            ->where($brand_goods_where)
            ->field('g.goods_id,cat_id,goods_name,shop_price')
            ->order('hot_sell_sort desc')
            ->count();
        $Page = new Page($count, 10);
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

        $page= object_to_array($Page);
        $pages['totalPages']=$page['totalPages'];
        $pages['nowPages']=$p;

        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        //banner图片
        $banner = $advmodel->where('pid=94')->where($advwhere)->limit(1)->field($advfield)->select();
        return formt(['listData'=>$goods_list,'category'=>$brandStraightCategoryList,'page'=>$pages,'banner'=>$banner]);
    }

    //专题精选
    public function projectfine(){
        $p = I('p/d',1);
        $adv_where['pid'] = ['eq',104];
        $adv_where['enabled'] = ['eq',1];
        $adv_where['start_time'] = ['elt',time()];
        $adv_where['end_time'] = ['egt',time()];
        $adv_where['is_open'] = ['eq',1];
        $count = db('ad')
            ->alias('ad')
            ->field('ad_link,ad_code,ad_name')
            ->join('ad_position adv','adv.position_id=ad.pid')
            ->where($adv_where)
            ->order('orderby,ad_id desc')
            //->page($p,C('PAGESIZE'))
            ->page($p,10)
            ->count();
        $Page = new Page($count, 10);
        $adv_list = Db::name('ad')
            ->alias('ad')
            ->field('ad_link,ad_code,ad_name')
            ->join('ad_position adv','adv.position_id=ad.pid')
            ->where($adv_where)
            ->order('orderby,ad_id desc')
            //->page($p,C('PAGESIZE'))
            ->page($p,C('PAGESIZE'))
            ->select();
        $page= object_to_array($Page);
        $pages['totalPages']=$page['totalPages'];
        $pages['nowPages']=$p;
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        //banner图片
        $banner = $advmodel->where('pid=103')->where($advwhere)->limit(1)->field($advfield)->select();
        return formt(['listData'=>$adv_list,'banner'=>$banner,'page'=>$pages,'banner'=>$banner]);
    }

}