<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\homered\controller; 
use app\common\logic\RedGoodsPromFactory;
use app\common\logic\RedSearchWordLogic;
use app\common\logic\RedGoodsLogic;
use app\common\model\SpecRedGoodsPrice;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use think\Cookie;
class Goods extends Base {
    public function index(){      
        return $this->fetch();
    }
   /**
    * 商品详情页
    */ 
    public function goodsInfo(){
        //C('TOKEN_ON',true);

        $goodsLogic = new RedGoodsLogic();
        $goods_id   = I("get.id/d");
        $Goods      = new \app\common\model\GoodsRed();
        $goods      = $Goods::get($goods_id); // 获取商品信息

        /*if(empty($goods) || ($goods['is_check'] == 0)){
            $this->error('该商品信息无效',U('Index/index'));
        }
*/
        if(empty($goods) || ($goods['is_on_sale'] == 0) || ($goods['is_virtual']==1 && $goods['virtual_indate'] <= time())){
        	$this->error('该商品已经下架',U('Index/index'));
        }

        // 米豆换算
        $midouInfo = getMidou($goods_id);
        $goods['midou']       = $midouInfo['midou'];
        $goods['midou_money'] = $midouInfo['midou_money'];
        $goods['midou_index'] = $midouInfo['midou_index'];

        if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods); // 加入用户浏览记录
        }

        if($goods['brand_id']){
            $goods['brand_name'] = M('brand_red')->where("id",$goods['brand_id'])->getField('name');  // 品牌 暂不需要
        }

        $goods_images_list = M('GoodsRedImages')->where("goods_id", $goods_id)->select(); // 商品 图册
        $goods_attribute   = M('GoodsRedAttribute')->getField('attr_id,attr_name');       // 查询属性
        $goods_attr_list   = M('GoodsRedAttr')->where("goods_id", $goods_id)->select();   // 查询商品属性表
	    $filter_spec       = $goodsLogic->get_spec($goods_id);
        $freight_free      = tpCache('shopping.freight_free');                            // 全场满多少免运费
        $spec_goods_price  = M('spec_red_goods_price')->where("goods_id", $goods_id)->getField("key,item_id,price,store_count"); // 规格 对应 价格 库存表

        M('GoodsRed')->where("goods_id", $goods_id)->save(array('click_count'=>$goods['click_count']+1 ));                       //统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);                                                          // 获取某个商品的评论统计
        $point_rate = tpCache('shopping.point_rate');

        $region_id = Cookie::get('district_id');  // 区域id？？？

        if ($region_id) {
            $dispatching = $goodsLogic->getGoodsDispatching($goods['goods_id'], $region_id);  // 商品物流配送和运费
            $this->assign('dispatching', $dispatching);
        }

        $iscollect = $goodsLogic->isCollectGoods($this->user_id,$goods_id);              // 是否收藏商品
        // dump($goods->toArray());die();
        $this->assign('freight_free', $freight_free);                                    // 全场满多少免运费
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true));          // 规格 对应 价格 库存表
        $this->assign('navigate_goods',navigate_goods($goods_id,1));                     // 面包屑导航
        $this->assign('commentStatistics',$commentStatistics);                           // 评论概览
        $this->assign('goods_attribute',$goods_attribute);                               // 属性值     
        $this->assign('goods_attr_list',$goods_attr_list);                               // 属性列表
        $this->assign('filter_spec',$filter_spec);                                       // 规格参数
        $this->assign('goods_images_list',$goods_images_list);                           // 商品缩略图
        $this->assign('siblings_cate',$goodsLogic->get_siblings_cate($goods['cat_id'])); // 相关分类
        $this->assign('look_see',$goodsLogic->get_look_see($goods));                     // 看了又看   
        $this->assign('iscollect',$iscollect);                                           // 是否收藏   
        $this->assign('goods',$goods->toArray());
        $this->assign('point_rate',$point_rate);
        return $this->fetch();
    }
    // 活动
    public function activity(){
        $goods_id  = input('goods_id/d');//商品id
        $item_id   = input('item_id/d');//规格id
        $goods_num = input('goods_num/d');//欲购买的商品数量
        $Goods = new \app\common\model\GoodsRed();
        $goods = $Goods::get($goods_id);
        // 米豆换算
        if($item_id){
            $midouInfo = getMidou($goods_id,$item_id);
            $goods['midou']       = $midouInfo['midou'];
            $goods['midou_money'] = $midouInfo['midou_money'];
            $goods['midou_index'] = $midouInfo['midou_index'];
        } else {
            $midouInfo = getMidou($goods_id);
            $goods['midou']       = $midouInfo['midou'];
            $goods['midou_money'] = $midouInfo['midou_money'];
            $goods['midou_index'] = $midouInfo['midou_index'];
        }

        if(!empty($goods['price_ladder'])){
            $goodsLogic = new RedGoodsLogic();
            $price_ladder = unserialize($goods['price_ladder']);
            $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
        }
        $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
    }

    /**
     * 获取可发货地址
     */
    public function getRegion()
    {
        $goodsLogic = new RedGoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        $region_list['status'] = 1;
        $this->ajaxReturn($region_list);
    }
    
    /**
     * 商品列表页
     */
    public function goodsList(){ 
        
        $key = md5($_SERVER['REQUEST_URI'].I('start_price').'_'.I('end_price'));
        $html = S($key);
        if(!empty($html))
        {
            return $html;
        }
        
        $filter_param = array();                         // 帅选数组                        
        $id           = I('get.id/d',1);                 // 当前分类id
        $brand_id     = I('get.brand_id',0);
        $spec         = I('get.spec',0);                 // 规格 
        $attr         = I('get.attr','');                // 属性        
        $sort         = I('get.sort','sort');            // 排序
        $sort_asc     = I('get.sort_asc','asc');         // 排序
        $price        = I('get.price','');               // 价钱
        $start_price  = trim(I('post.start_price','0')); // 输入框价钱
        $end_price    = trim(I('post.end_price','0'));   // 输入框价钱        
        $midou_rate   = tpCache('shoppingred.midou_rate');        // 米豆兑换比
        $start_price  = num_flaot3(($start_price*$midou_rate));           // 兑换后的米豆.
        $end_price    = num_flaot3(($end_price*$midou_rate));   
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
     
        $filter_param2['id'] = $filter_param['id'] = $id;                            //加入帅选条件中                       
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $spec     && ($filter_param['spec'] = $spec);         //加入帅选条件中
        $attr     && ($filter_param['attr'] = $attr);         //加入帅选条件中
        $price    && ($filter_param['price'] = $price);       //加入帅选条件中

        $goodsLogic = new RedGoodsLogic(); // 前台商品操作逻辑类
        
        // 分类菜单显示
        $goodsCate = M('GoodsRedCategory')->where("id", $id)->find();// 当前分类
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆        
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 帅选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandsonRed ($id);
        $goods_where = ['is_on_sale' => 1, 'is_check' => 1, 'exchange_integral' => 0,'cat_id'=>['in',$cat_id_arr]];
        $filter_goods_id2 = $filter_goods_id = Db::name('goods_red')->where($goods_where)->cache(true)->getField("goods_id",true);
        // 过滤帅选的结果集里面找商品        
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id    
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        if($spec)// 规格
        {
            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
        }
        if($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_3); // 获取多个帅选条件的结果 的交集
        }

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id2,$filter_param2,'goodsList'); // 帅选的价格期间         
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList'); // 获取指定分类下的帅选品牌
        $filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格        
        $filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性        

        $count = count($filter_goods_id);
        $page = new Page($count,20);
        if($count > 0)
        {
            $goods_list = M('goods_red')->where("goods_id","in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
            foreach ($goods_list as $k => $val) {
                // 米豆换算
                $midouInfo = getMidou($val['goods_id']);
                $goods_list[$k]['midou']       = $midouInfo['midou'];
                $goods_list[$k]['midou_money'] = $midouInfo['midou_money'];
                $goods_list[$k]['midou_index'] = $midouInfo['midou_index'];
            }
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if($filter_goods_id2)
            $goods_images = M('goods_red_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        // print_r($filter_menu);         
        $goods_category = M('goods_red_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $navigate_cat = navigate_goods($id); // 面包屑导航         
        $this->assign('goods_list',$goods_list);
        $this->assign('navigate_cat',$navigate_cat);
        $this->assign('goods_category',$goods_category);                
        $this->assign('goods_images',$goods_images);  // 相册图片
        $this->assign('filter_menu',$filter_menu);    // 帅选菜单
        $this->assign('filter_spec',$filter_spec);    // 帅选规格
        $this->assign('filter_attr',$filter_attr);    // 帅选属性
        $this->assign('filter_brand',$filter_brand);  // 列表页帅选属性 - 商品品牌
        $this->assign('filter_price',$filter_price);  // 帅选的价格期间
        $this->assign('goodsCate',$goodsCate);
        $this->assign('cateArr',$cateArr);
        $this->assign('filter_param',$filter_param);  // 帅选条件
        $this->assign('cat_id',$id);
        $this->assign('page',$page);                  // 赋值分页输出        
        $html = $this->fetch();        
        S($key,$html);
        return $html;
    }    

    /**
     *  查询配送地址，并执行回调函数
     */
    public function region()
    {
        $fid = I('fid/d');
        $callback = I('callback');
        $parent_region = M('region')->field('id,name')->where(array('parent_id'=>$fid))->cache(true)->select();
        echo $callback.'('.json_encode($parent_region).')';
        exit;
    }

    /**
     * 商品物流配送和运费
     */
    public function dispatching()
    {        
        $goods_id     = I('goods_id/d');     // 商品ID
        $region_id    = I('region_id/d');    // 地区
        $goods_logic = new RedGoodsLogic();
        $dispatching_data = $goods_logic->getGoodsDispatching($goods_id,$region_id);
        $this->ajaxReturn($dispatching_data);
    }

    /**
     * 商品搜索列表页
     */
    public function search()
    {
        //C('URL_MODEL',0);
        $filter_param = array();               // 帅选数组                        
        $id           = I('get.id/d', 0);      // 当前分类id
        $brand_id     = I('brand_id', 0);
        $sort         = I('sort', 'goods_id'); // 排序
        $sort_asc     = I('sort_asc', 'asc');  // 排序
        $price        = I('price', '');        // 价钱
        $start_price  = trim(I('start_price', '0')); // 输入框价钱
        $end_price    = trim(I('end_price', '0')); // 输入框价钱
        $midou_rate   = tpCache('shoppingred.midou_rate');        // 米豆兑换比
        $start_price  = num_flaot3(($start_price*$midou_rate));           // 兑换后的米豆.
        $end_price    = num_flaot3(($end_price*$midou_rate));   
        if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
        $q = urldecode(trim(I('q', ''))); // 关键字搜索
        empty($q) && $this->error('请输入搜索词');
        $id && ($filter_param['id'] = $id);                   //加入帅选条件中                       
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $price && ($filter_param['price'] = $price);          //加入帅选条件中
        $q && ($_GET['q'] = $filter_param['q'] = $q);         //加入帅选条件中
        $goodsLogic      = new RedGoodsLogic();               //前台商品操作逻辑类
        $SearchWordLogic = new RedSearchWordLogic();
        $where = $SearchWordLogic->getSearchWordWhere($q);
        $where['is_on_sale'] = 1;
        $where['is_check']   = 1;
        $where['exchange_integral'] = 0;//不检索积分商品
        Db::name('search_red_word')->where('keywords', $q)->setInc('search_num');
        $goodsHaveSearchWord = Db::name('goods_red')->where($where)->count();
        if ($goodsHaveSearchWord) {
            $SearchWordIsHave = Db::name('search_red_word')->where('keywords',$q)->find();
            if($SearchWordIsHave){
                Db::name('search_red_word')->where('id',$SearchWordIsHave['id'])->update(['goods_num'=>$goodsHaveSearchWord]);
            }else{
                $SearchWordData = [
                    'keywords' => $q,
                    'pinyin_full' => $SearchWordLogic->getPinyinFull($q),
                    'pinyin_simple' => $SearchWordLogic->getPinyinSimple($q),
                    'search_num' => 1,
                    'goods_num' => $goodsHaveSearchWord
                ];
                Db::name('search_red_word')->insert($SearchWordData);
            }
        }
        if ($id) {
            $cat_id_arr = getCatGrandsonRed($id);
            $where['cat_id'] = array('in', implode(',', $cat_id_arr));
        }
        $search_goods    = M('goods_red')->where($where)->getField('goods_id,cat_id');
        $filter_goods_id = array_keys($search_goods);
        $filter_cat_id   = array_unique($search_goods); // 分类需要去重
        if ($filter_cat_id) {
            $cateArr = M('goods_red_category')->where("id", "in", implode(',', $filter_cat_id))->select();
            $tmp = $filter_param;
            foreach ($cateArr as $k => $v) {
                $tmp['id'] = $v['id'];
                $cateArr[$k]['href'] = U("/Homered/Goods/search", $tmp);
            }
        }
        // 过滤帅选的结果集里面找商品        
        if ($brand_id || $price) {
            // 品牌或者价格
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'search'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'search'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'search'); // 获取指定分类下的帅选品牌

        $count = count($filter_goods_id);
        $page = new Page($count, 20);
        if ($count > 0) {
            $goods_list = M('goods_red')->where(['is_on_sale' => 1, 'is_check' => 1, 'goods_id' => ['in', implode(',', $filter_goods_id)]])->order("$sort $sort_asc")->limit($page->firstRow . ',' . $page->listRows)->select();
            
            foreach ($goods_list as $k => $val) {
                // 米豆换算
                $midouInfo = getMidou($val['goods_id']);
                $goods_list[$k]['midou']       = $midouInfo['midou'];
                $goods_list[$k]['midou_money'] = $midouInfo['midou_money'];
                $goods_list[$k]['midou_index'] = $midouInfo['midou_index'];
            }

            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if ($filter_goods_id2)
                $goods_images = M('goods_red_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->select();
        }
        // dump($price);die();
        $this->assign('goods_list', $goods_list);
        $this->assign('goods_images', $goods_images);  // 相册图片
        $this->assign('filter_menu', $filter_menu);  // 帅选菜单
        $this->assign('filter_brand', $filter_brand);  // 列表页帅选属性 - 商品品牌
        $this->assign('filter_price', $filter_price);// 帅选的价格期间
        $this->assign('cateArr', $cateArr);
        $this->assign('filter_param', $filter_param); // 帅选条件
        $this->assign('cat_id', $id);
        $this->assign('page', $page);// 赋值分页输出
        $this->assign('q', I('q'));
        C('TOKEN_ON', false);
        return $this->fetch();
    }
    
    /**
     * 商品咨询ajax分页
     */
    public function ajax_consult(){
        $goods_id     = I("goods_id/d", '0');
        $consult_type = I('consult_type', '0'); // 0全部咨询  1 商品咨询 2 支付咨询 3 配送 4 售后
        $where        = ['parent_id' => 0, 'goods_id' => $goods_id,'is_show'=>1];
        if ($consult_type > 0) {
            $where['consult_type'] = $consult_type;
        }
        $count = M('GoodsRedConsult')->where($where)->count();
        $page = new AjaxPage($count, 5);
        $show = $page->show();
        $consultList = M('GoodsRedConsult')->where($where)->order("id desc")->limit($page->firstRow . ',' . $page->listRows)->order('add_time desc')->select();
        foreach($consultList as $key =>$list){
            $consultList[$key]['replyList'] = M('GoodsRedConsult')->where(['parent_id' => $list['id'],'is_show'=>1])->order('add_time desc')->select();
        }
        $this->assign('consultCount', $count);// 商品咨询数量
        $this->assign('consultList', $consultList );// 商品咨询
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }
    
    /**
     * 商品评论ajax分页
     */
    public function ajaxComment(){        
        $goods_id    = I("goods_id/d",'0');        
        $commentType = I('commentType','1'); // 1 全部 2好评 3 中评 4差评
        $where = ['is_show'=>1,'goods_id'=>$goods_id,'parent_id'=>0];
        if($commentType==5){
            $where['img'] = ['<>',''];
        }else{
        	$typeArr = array('1'=>'0,1,2,3,4,5','2'=>'4,5','3'=>'3','4'=>'0,1,2');
            $where['ceil((deliver_rank + goods_rank + service_rank) / 3)'] = ['in',$typeArr[$commentType]];
        }
        $count = M('CommentRed')->where($where)->count();                
        
        $page = new AjaxPage($count,10);
        $show = $page->show();   
       
        $list = M('CommentRed')->alias('c')->join('__USERS__ u','u.user_id = c.user_id','LEFT')->where($where)->order("add_time desc")->limit($page->firstRow.','.$page->listRows)->select();
         
//        $replyList = M('Comment')->where(['is_show'=>1,'goods_id'=>$goods_id,'parent_id'=>['>',0]])->order("add_time desc")->select();
        
        foreach($list as $k => $v){
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $replyList[$v['comment_id']] = M('CommentRed')->where(['is_show'=>1,'goods_id'=>$goods_id,'parent_id'=>$v['comment_id']])->order("add_time desc")->select();
        }
        $this->assign('commentlist',$list);// 商品评论
        $this->assign('replyList',$replyList); // 管理员回复
        $this->assign('page',$show);// 赋值分页输出        
        return $this->fetch();        
    }    
    
    /**
     *  商品咨询
     */
    public function goodsConsult(){
        C('TOKEN_ON', true);
        $goods_id     = I("goods_id/d", '0'); // 商品id
        $store_id     = I("store_id/d", '0'); // 商品id
        $consult_type = I("consult_type", '1'); // 商品咨询类型
        $username     = I("username", 'TPshop用户'); // 网友咨询
        $content      = I("content"); // 咨询内容
        $verify       = new Verify();
        if (!$verify->check(I('post.verify_code'), 'consult')) {
            $this->error("验证码错误");
        }
        $data = array(
            'goods_id' => $goods_id,
            'consult_type' => $consult_type,
            'username' => $username,
            'content' => $content,
            'store_id' => $store_id,
            'is_show' => 1,
            'add_time' => time(),
        );
        Db::name('goodsRedConsult')->add($data);
        $this->success('咨询已提交!', U('/Homered/Goods/goodsInfo', array('id' => $goods_id)));
    }
    
    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods()
    {
        $goods_id = I('goods_id/d');
        $goodsLogic = new RedGoodsLogic();        
        $result = $goodsLogic->collect_goods(cookie('user_id'),$goods_id);
        exit(json_encode($result));
    }
    
    /**
     * 加入购物车弹出
     */
    public function open_add_cart()
    {        
         return $this->fetch();
    }

    /**
     * 积分商城
     */
    public function integralMall()
    {
        $cat_id     = I('get.id/d');
        $minValue   = I('get.minValue');
        $maxValue   = I('get.maxValue');
        $brandType  = I('get.brandType');
        $point_rate = tpCache('shopping.point_rate');
        $is_new     = I('get.is_new',0);
        $exchange   = I('get.exchange',0);
        $goods_where = array(
            'is_on_sale' => 1,  //是否上架
            'is_check'   => 1,  //是否通过审核
            'is_virtual' =>0,
        );
        //积分兑换筛选
        $exchange_integral_where_array = array(array('gt',0));
        // 分类id
        if (!empty($cat_id)) {
            $goods_where['cat_id'] = array('in', getCatGrandsonRed($cat_id));
        }
        //积分截止范围
        if (!empty($maxValue)) {
            array_push($exchange_integral_where_array, array('elt', $maxValue));
        }
        //积分起始范围
        if (!empty($minValue)) {
            array_push($exchange_integral_where_array, array('egt', $minValue));
        }
        //积分+金额
        if ($brandType == 1) {
            array_push($exchange_integral_where_array, array('exp', ' < shop_price* ' . $point_rate));
        }
        //全部积分
        if ($brandType == 2) {
            array_push($exchange_integral_where_array, array('exp', ' = shop_price* ' . $point_rate));
        }
        //新品
        if($is_new == 1){
            $goods_where['is_new'] = $is_new;
        }
        //我能兑换
        $user_id = cookie('user_id');
        if ($exchange == 1 && !empty($user_id)) {
            $user_pay_points = intval(M('users')->where(array('user_id' => $user_id))->getField('pay_points'));
            if ($user_pay_points !== false) {
                array_push($exchange_integral_where_array, array('lt', $user_pay_points));
            }
        }

        $goods_where['exchange_integral'] =  $exchange_integral_where_array;
        $goods_list_count = M('goods_red')->where($goods_where)->count();   //总页数
        $page = new Page($goods_list_count, 15);
        $goods_list = M('goods_red')->where($goods_where)->limit($page->firstRow . ',' . $page->listRows)->select();
        $goods_category = M('goods_red_category')->where(array('level' => 1))->select();

        $this->assign('goods_list', $goods_list);
        $this->assign('page', $page->show());
        $this->assign('goods_list_count',$goods_list_count);
        $this->assign('goods_category', $goods_category);//商品1级分类
        $this->assign('point_rate', $point_rate);//兑换率
        $this->assign('nowPage',$page->nowPage);// 当前页
        $this->assign('totalPages',$page->totalPages);//总页数
        return $this->fetch();
    }

    /**
     * 全部商品分类
     * @author lxl
     * @time17-4-18
     */
    public function all_category(){
        return $this->fetch();
    }

    /**
     * 全部品牌列表
     * @author lxl
     * @time17-4-18
     */
    public function all_brand(){
        return $this->fetch();
    }
    
}