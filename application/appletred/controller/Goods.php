<?php
/**
 * tpshop
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\appletred\controller;
use app\common\logic\JssdkLogic;
use app\common\logic\RedGoodsLogic;
use app\common\logic\RedGoodsPromFactory;
use app\common\model\SpecRedGoodsPrice;
use app\common\logic\SearchWordLogic;
use think\AjaxPage;
use think\Page;
use think\Db;
class Goods extends MobileBase {
    public function index(){
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        $categorylist =  get_goods_category_tree();
        return formt($categorylist);
    }

    /**
     * 商品列表页
     */
    public function goodsList(){
    	$filter_param = array();          // 帅选数组
    	$id       = I('id/d',1);          // 当前分类id
    	$brand_id = I('brand_id/d',0);
    	$spec     = I('spec',0);          // 规格
    	$attr     = I('attr','');         // 属性
    	$sort     = I('sort','sort');     // 排序
    	$sort_asc = I('sort_asc','asc');  // 排序
    	$price    = I('price','');        // 价钱
    	$start_price = trim(I('start_price','0')); // 输入框价钱
    	$end_price   = trim(I('end_price','0'));   // 输入框价钱
    	if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱   	 
    	$filter_param['id'] = $id; //加入帅选条件中
    	$brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
    	$spec      && ($filter_param['spec']     = $spec);     //加入帅选条件中
    	$attr      && ($filter_param['attr']     = $attr);     //加入帅选条件中
    	$price     && ($filter_param['price']    = $price);    //加入帅选条件中
         
    	$goodsLogic = new RedGoodsLogic(); // 前台商品操作逻辑类
    	// 分类菜单显示
    	$goodsCate = M('GoodsRedCategory')->where("id", $id)->find();// 当前分类
    	$cateArr = $goodsLogic->get_goods_cate($goodsCate);
    	 
    	// 帅选 品牌 规格 属性 价格
    	$cat_id_arr = getCatGrandsonRed ($id);
        $goods_where = ['is_on_sale' => 1, 'is_check'=>1, 'exchange_integral' => 0,'cat_id'=>['in',$cat_id_arr]];
    	$filter_goods_id = Db::name('goods_red')->where($goods_where)->cache(true)->getField("goods_id",true);

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

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel =I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel,$cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }
    	 
    	$filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
    	$filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'goodsList'); // 帅选的价格期间
    	$filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList'); // 获取指定分类下的帅选品牌
    	$filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格
    	$filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性
    	
    	$count = count($filter_goods_id);
    	$page  = new Page($count,C('PAGESIZE'));
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
    	$goods_category = M('goods_red_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
    	$goods['goods_list']=$goods_list;
        $goods['goods_category']=$goods_category;
        $goods['goods_images']=$goods_images;
        $goods['filter_menu']=$filter_menu;
        $goods['filter_spec']=$filter_spec;
        $goods['filter_attr']=$filter_attr;
        $goods['filter_brand']=$filter_brand;
        $goods['filter_price']=$filter_price;
        $goods['goodsCate']=$goodsCate;
        $goods['cateArr']=$cateArr;
        $goods['filter_param']=$filter_param;
        $goods['id']=$id;
        $page= object_to_array($page);
        $goods['page']['totalPages']=$page['totalPages'];
        exit(formt($goods));
       
    }

    /**
     * 商品列表页 ajax 翻页请求 搜索
     */
    public function ajaxGoodsList() {
        $where ='';

        $cat_id  = I("id/d",0); // 所选择的商品分类id
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandsonRed($cat_id);
            $where .= " WHERE cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        $result = DB::query("select count(1) as count from __PREFIX__goods_red $where ");
        $count = $result[0]['count'];
        $page = new AjaxPage($count,10);

        $order = " order by goods_id desc"; // 排序
        $limit = " limit ".$page->firstRow.','.$page->listRows;
        $list = DB::query("select *  from __PREFIX__goods_red $where $order $limit");
        foreach ($list as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $list[$k]['midou']       = $midouInfo['midou'];
            $list[$k]['midou_money'] = $midouInfo['midou_money'];
            $list[$k]['midou_index'] = $midouInfo['midou_index'];
        }

      
        exit(formt($list));
    }

public function ajaxSearch()
    {
        $tp_config = M('config')->select();
        foreach($tp_config as $k => $v)
        {
              if($v['name'] == 'hot_keywords'){
                 $tpshop_config['hot_keywords'] = explode('|', $v['value']);
            }             
        } 
        exit(formt($tpshop_config));
    }
    /**
     * 商品详情页
     */
    public function goodsInfo(){
        C('TOKEN_ON',true);        
        $goodsLogic = new RedGoodsLogic();
        $goods_id   = I("get.id/d",'1501');
        $goodsModel = new \app\common\model\GoodsRed();
        $goods = $goodsModel::get($goods_id);
    
        $goods['share_desc'] = str_replace(PHP_EOL, ' ', $goods['goods_remark']);

        if(empty($goods) || ($goods['is_check'] == 0)){
            exit(formt('',201,'该商品信息无效'));
        }

        if(empty($goods) || ($goods['is_on_sale'] == 0) || ($goods['is_virtual']==1 && $goods['virtual_indate'] <= time())){
            exit(formt('',201,'此商品不存在或者已下架'));
        }
        // 促销商品
       

        // 浏览记录
        if (I('user_id/d')) {
            $goodsLogic->add_visit_log(I('user_id/d'), $goods);
        }

      
        $goodsInfos = $goods->toArray();

        // 米豆换算
        $midouInfo = getMidou($goods_id);
        $goodsInfos['midou']       = $midouInfo['midou'];
        $goodsInfos['midou_money'] = $midouInfo['midou_money'];
        $goodsInfos['midou_index'] = $midouInfo['midou_index'];

        $goods_images_list = M('GoodsRedImages')->where("goods_id", $goods_id)->select(); // 商品 图册
        foreach ($goods_images_list as $key => $value) {
            $goods_images_list[$key]['image_url']=URL.$value['image_url'];
        }
        $goods_attribute   = M('GoodsRedAttribute')->getField('attr_id,attr_name');       // 查询属性
        $goods_attr_list   = M('GoodsRedAttr')->where("goods_id", $goods_id)->select();   // 查询商品属性表
		$filter_spec       = $goodsLogic->get_spec($goods_id);
        $spec_goods_price  = M('spec_red_goods_price')->where("goods_id", $goods_id)->getField("key,item_id,price,store_count"); // 规格 对应 价格 库存表
        foreach ($spec_goods_price as $key => $value) {
             $midouInfo = getMidou($goods_id,$value['item_id']);
             $spec_goods_price[$key]['price']=$midouInfo['midou_index'];
        }
       $commentStatistics = $goodsLogic->commentStatistics($goods_id);              // 获取某个商品的评论统计
         if ($commentStatistics['c0']!='0') {
            $commentStatistics['haopinglv']=round($commentStatistics['c1']/$commentStatistics['c0'],3)*100;
        }else{
            $commentStatistics['haopinglv']='0';
        }
      	$goods['sale_num'] = M('order_red_goods')->where(['goods_id'=>$goods_id,'is_send'=>1])->count();
        //当前用户收藏
        $user_id = I('user_id/d');
        $collect = M('goods_red_collect')->where(array("goods_id"=>$goods_id ,"user_id"=>$user_id))->count();
        $goods_collect_count = M('goods_red_collect')->where(array("goods_id"=>$goods_id))->count(); //商品收藏数

        
        $point_rate = tpCache('shopping.point_rate');
        $goodsInfo['goods']=goodsinfoimgurl($goodsInfos);

        $goodsInfo['collect']=$collect;
        $goodsInfo['spec_red_goods_price']=$spec_goods_price;
        $goodsInfo['commentStatistics']=$commentStatistics;
        $goodsInfo['goods_attribute']=$goods_attribute;
        $goodsInfo['goods_attr_list']=$goods_attr_list;
        $goodsInfo['filter_spec']=$filter_spec;
        $goodsInfo['goods_images_list']=$goods_images_list;
        $goodsInfo['goods_collect_count']=$goods_collect_count;
        $goodsInfo['point_rate']=$point_rate;

        exit(formt($goodsInfo));
      
    }

    public function activity(){
      
        $goods_id = I('goods_id/d');//商品id
        $item_id = I('item_id/d');//规格id
        $goods_num = I('goods_num/d');//欲购买的商品数量
        $Goods = new \app\common\model\GoodsRed();
        $goods = $Goods::get($goods_id,'',true);

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

        //判断是不是实体店进入
        if(session('store_id')){
            $stock = db('store_goods_stock')->where(['store_id'=>session('store_id'),'goods_id'=>$goods_id])->value("stock");
            $goods['store_count'] = empty($stock)?0:$stock;
        }


        if(!empty($goods['price_ladder'])){
            $goodsLogic = new RedGoodsLogic();
            $price_ladder = unserialize($goods['price_ladder']);
            $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
        }

         exit(formt($goods,201,'该商品没有参与活动'));
    }


    /*
     * ajax获取商品评论
     */
    public function ajaxComment()
    {
        $goods_id    = I("goods_id/d", '1501');
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        if ($commentType == 5) {
            $where = array(
                'goods_id' => $goods_id, 'parent_id' => 0, 'img' => ['<>', ''],'is_show'=>1
            );
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
            $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
        }
        $goodsLogic = new RedGoodsLogic();
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);  
        $count = M('CommentRed')->where($where)->count();
        $page_count = C('PAGESIZE');
        $page = new AjaxPage($count, $page_count);
        $list = M('CommentRed')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->order("add_time desc")
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $replyList = M('CommentRed')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        foreach ($list as $k => $v) {
            // $list[$k]['img'] = unserialize($v['img']); // 晒单图片
              $list[$k]['add_time'] =date('Y-m-d H:i:s', $v['add_time']);
             $img = unserialize($v['img']); // 晒单图片
            $list[$k]['img'] = commentimg($img);
            $replyList[$v['comment_id']] = M('CommentRed')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order("add_time desc")->select();
        }
       
        $comment['commentStatistics']=$commentStatistics;
        $comment['goods_id']=$goods_id;
        $comment['commentlist']=$list;
        $comment['commentType']=$commentType;
        $comment['replyList']=$replyList;
        $comment['count']=$count;
        $comment['page_count']=$page_count;
        $comment['current_count']=$page_count * I('p');
        exit(formt($comment));
    }
    
    /*
     * 获取商品规格
     */
    public function goodsAttr(){
        $goods_id = I("get.goods_id/d",0);
        $goods_attribute = M('GoodsRedAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsRedAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $this->assign('goods_attr_list',$goods_attr_list);
        $this->assign('goods_attribute',$goods_attribute);
        return $this->fetch();
    }

   
     /**
     * 商品搜索列表页
     */
    public function search(){
    	$filter_param = array();              // 帅选数组
         $p = I('p/d');
         if ($p) {
            $p=$p;
         }else{
            $p='1';
         }
    	
    	$sort         = I('sort','sort'); // 排序
    	$sort_asc     = I('sort_asc','asc');  // 排序
    	$price        = I('price','');        // 价钱
    	$start_price  = trim(I('start_price','0')); // 输入框价钱
    	$end_price    = trim(I('end_price','0')); // 输入框价钱
    	if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱   	 
    	    	
    	$price  && ($filter_param['price'] = $price); //加入帅选条件中
        $q = urldecode(trim(I('q',''))); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
        $qtype = I('qtype','');
        $SearchWordLogic = new SearchWordLogic();
        $where = $SearchWordLogic->getSearchWordWhere($q);
        
        $where['is_on_sale'] = 1;
        $where['is_check']   = 1;
        $where['exchange_integral'] = 0;                                     //不检索积分商品
        if($qtype){
            $filter_param['qtype'] = $qtype;
            $where[$qtype] = 1;
        }
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
        
    	$goodsLogic = new RedGoodsLogic();
    	$filter_goods_id = M('goods_red')->where($where)->cache(true)->getField("goods_id",true);

    	// 过滤帅选的结果集里面找商品
    	if($brand_id || $price)// 品牌或者价格
    	{
    		$goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
    		$filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
    	}

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

    	$filter_menu  = $goodsLogic->get_filter_menu($filter_param,'search'); // 获取显示的帅选菜单
    	$filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'search'); // 帅选的价格期间
    	$filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'search'); // 获取指定分类下的帅选品牌

    	$count = count($filter_goods_id);
    	$page  = new Page($count,12);
    	if($count > 0)
    	{
    		$goods_list = M('goods_red')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();

            foreach ($goods_list as $k => $val) {
                // 米豆换算
                $midouInfo = getMidou($val['goods_id']);
                $goods_list[$k]['midou']       = $midouInfo['midou'];
                $goods_list[$k]['midou_money'] = $midouInfo['midou_money'];
                $goods_list[$k]['midou_index'] = $midouInfo['midou_index'];
                if (strstr(goods_thum_images($val['goods_id'],400,400,'red'),'http')) {
                     $goods_list[$k]['goodsimg']=goods_thum_images($val['goods_id'],400,400,'red');
                }else{
                     $goods_list[$k]['goodsimg']=URL.goods_thum_images($val['goods_id'],400,400,'red');
                }
            }

    		$filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
    		if($filter_goods_id2)
    			$goods_images = M('goods_red_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
    	}
    	$goods_category = M('goods_red_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
    	
        $searchlist['goods_list']=$goods_list;
        $searchlist['goods_category']=$goods_category;
        $searchlist['goods_images']=$goods_images;
        $searchlist['filter_menu']=$filter_menu;
        $searchlist['filter_price']=$filter_price;
        $searchlist['filter_param']=$filter_param;
        $searchlist['page']=$page;
        $searchlist['sort_asc']=$sort_asc == 'asc' ? 'desc' : 'asc';
        C('TOKEN_ON',false);
        exit(formt($searchlist));
    	
    }

   

   
    
    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods(){
        $goods_id = I('goods_id/d');
        $user_id = I('user_id/d');
        $goodsLogic = new RedGoodsLogic();
        $result = $goodsLogic->collect_goods($user_id,$goods_id);
        if ($result['status']=='1') {
            exit(formt('',200,$result['msg']));
        }else{
            exit(formt('',201,$result['msg']));
        }
    }


    /**
     * [当前实体店所对应的商品]
     * @author 王牧田
     * @date 2018-09-28
     * @return mixed
     */
    public function storegoodsList(){
        $p  = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
        $store_id  = empty($_REQUEST['store_id']) ? 0 : $_REQUEST['store_id'];


        $goods_id = db('store_goods_stock')->where(['store_id'=>$store_id])->column("goods_id");

        $where['goods_id']=['in',$goods_id];
        $where['is_on_sale']=1;
        $where['is_check']=1;
        $where['exchange_integral']=0;
        $good_list = db('goods_red')->where($where)->page($p,$size)->select();
        foreach ($good_list as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $good_list[$k]['midou']       = $midouInfo['midou'];
            $good_list[$k]['midou_money'] = $midouInfo['midou_money'];
            $good_list[$k]['midou_index'] = $midouInfo['midou_index'];
        }


        $this->assign('goods_list',$good_list);
        if(input('is_ajax')){
            return $this->fetch('ajaxGoodsList');
        }else{
            return $this->fetch();
        }

    }



    public function storehuifu($orderid,$is_store,$order_status,$shipping_status,$sql=true){

        $data['is_store']=$is_store;
        $data['order_status']=$order_status;
        $data['shipping_status']=$shipping_status;

        ($sql==="false")?$sql=false:false;
        dump($sql);
        $order_red=M('order_red')->where(['order_id'=>$orderid])->fetchsql($sql)->save($data);
        dump($order_red);
        exit();

    }






}