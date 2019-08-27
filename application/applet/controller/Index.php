<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\applet\controller;
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






}