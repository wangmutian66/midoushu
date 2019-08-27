<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\supplier\controller;
use app\supplier\logic\RedGoodsLogic;
use app\supplier\logic\RedSearchWordLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

class GoodsRed extends Base {

    /**
     *  商品列表
     */
    public function goodsList(){      
        $GoodsLogic = new RedGoodsLogic($this->suppliers_id);        
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList',$categoryList);
        return $this->fetch();
    }
    
    /**
     *  商品列表
     */
    public function ajaxGoodsList(){            
        
        $where = ' 1 = 1 AND suppliers_id='.$this->suppliers_id; // 搜索条件                               
        I('intro')    && $where .= " and ".I('intro')." = 1" ;        
        (I('is_on_sale') !== '') && $where .= " and is_on_sale = ".I('is_on_sale') ;     
        (I('is_check') !== '') && $where .= " and is_check = ".I('is_check') ;    
        (I('is_allreturn') !== '') && $where .= " and is_allreturn = ".I('is_allreturn');

        $cat_id = I('cat_id');
        // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where .= " and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')" ;
        }
        
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandsonRed($cat_id); 
            $where .= " and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        $count = M('GoodsRed')->where($where)->count();
        $Page  = new AjaxPage($count,20);

        $show = $Page->show();
        //$order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $order_str = "`goods_id` desc";
        $goodsList = M('GoodsRed')->where($where)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();

        $midou_use_percent = $this->tpshop_config['shoppingred_midou_use_percent']; // 购买商品 使用米豆 比率
        $midou_rate        = $this->tpshop_config['shoppingred_midou_rate'];        // 米豆兑换比
        foreach ($goodsList as $k => $val) {
            $midouInfo = getMidou($val['goods_id']);
            $goodsList[$k]['midou']       = $midouInfo['midou'];
            $goodsList[$k]['midou_money'] = $midouInfo['midou_money'];
        }

        $catList = D('GoodsRedCategory')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList',$catList);
        $this->assign('goodsList',$goodsList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     *
     * @time 2018/04/19
     * @author liyi
     * 审核商品
     */
    public function is_check()
    {
        $t = I('get.t');
        $goods = array();
        if($t == 1){
            $goods_id = I('get.goods_id');
            if (!empty($goods_id)) {
                $goods = M('goodsRed')->field('goods_id,goods_name')->where(array('goods_id' => array('eq', $goods_id)))->select();
                $goods_info = M('goodsRed')->where(array('goods_id' => array('eq', $goods_id)))->find();
            }
            $this->assign('goods_info',$goods_info);
        } else {
            $goods_id_array = I('get.goods_id_array');
            if (!empty($goods_id_array)) {
                $goods = M('goods_red')->field('goods_id,goods_name')->where(array('goods_id' => array('IN', $goods_id_array)))->select();
            }
        }
        $this->assign('goods',$goods);
        return $this->fetch();
    }

    /**
     * 更改商品审核
     * @author dyr
     * @time  2018/04/19
     */
    public function dois_check()
    {
        $call_back = I('call_back');      //回调方法
        $no_remark = I('post.no_remark'); //内容
        $is_check  = I('post.is_check'); //内容
        $type      = I('post.type', 0);   //个体or全体
        $goods     = I('post.goods/a');   //个体id
        $data = array(
            'no_remark'   => $no_remark,
            'is_check'    => $is_check,
            'last_update' => time()
        );

        if ($type == 1) {
            //全体用户系统消息
            M('goods_red')->save($data);
        } else {
            //个体消息
            if (!empty($goods)) {
                foreach ($goods as $key) {
                    M('goods_red')->where('goods_id = '.$key)->save($data);
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }
    

     
    // 商品库存记录
    public function stock_list(){

        $suppliers_id = $this->suppliers_id;

    	$model = M('stock_red_log');
    	$map = array();
    	$mtype = I('mtype');
    	if($mtype == 1){
    		$map['stock'] = array('gt',0);
    	}
    	if($mtype == -1){
    		$map['stock'] = array('lt',0);
    	}
    	$goods_name = I('goods_name');
        if($goods_name){
            $map['stock.goods_name'] = array('like',"%$goods_name%");
        }
        $map['goods.suppliers_id'] = array('eq',$this->suppliers_id);

    	$ctime = urldecode(I('ctime'));
    	if($ctime){
    		$gap = explode(' - ', $ctime);
    		$this->assign('start_time',$gap[0]);
    		$this->assign('end_time',$gap[1]);
    		$this->assign('ctime',$gap[0].' - '.$gap[1]);
    		$map['ctime'] = array(array('gt',strtotime($gap[0])),array('lt',strtotime($gap[1])));
    	}

    	$count = $model->alias('stock')->field('stock.id')->join('__GOODS_RED__ goods','goods.goods_id = stock.goods_id','LEFT')->where($map)->count();
    	$Page  = new Page($count,20);
    	$show = $Page->show();
    	$this->assign('pager',$Page);
    	$this->assign('page',$show);// 赋值分页输出
 
    	$stock_list = $model->alias('stock')->field('stock.*')->join('__GOODS_RED__ goods','goods.goods_id = stock.goods_id','LEFT')->where($map)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        // echo $model->getlastsql();die;
    	$this->assign('stock_list',$stock_list);
    	return $this->fetch();
    }

    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {
        $suppliers_id = session('suppliers.suppliers_id');

        $GoodsLogicRed = new RedGoodsLogic();
        $GoodsRed = new \app\supplier\model\GoodsRed();
        $goods_id = I('goods_id');
        $goods_ids = I('id');
        ///实体店商品库存
        $store_id = $_POST['shitiid'];
        $stock = $_POST['stock'];
        $item_id = $_POST['storegoodstype'];
        if ($goods_ids) {
            $res=M('store_goods_supplices')->where(["goods_id"=>$goods_ids,"is_examine"=>["in",["0","2"]]])->select();
            $sgoodslist = array(); //想要的结果
            foreach ($res as $k => $v) {
              $sgoodslist[$v['store_id']][] = $v;
              $comid = M('Company')->where('cid='.$v['store_id'])->find();
              // $sgoodsliste[$v['store_id']]['parentid'] = $comid['parent_id'];
              $sgoodsliste[$comid['parent_id']] = $comid['parent_id'];
            }
        }
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if ((I('is_ajax') == 1) && IS_POST) {
            $return_url =  U('/supplier/GoodsRed/goodsList/');
            
            if ($stock) {
               //添加实体店家商品库存
                $a = array("store_id"=>$store_id);
                $b = array(stock=>$stock);
                $c = array(item_id=>$item_id);
                $test = array("a"=>"store_id","b"=>"stock","c"=>"item_id");

                $result = array();
                for($i=0;$i<count($a["store_id"]);$i++){

                    foreach($test as $key=>$value){
                        $result[$i]['goods_id'] = $goods_id;
                        $result[$i]['is_examine'] = '0';
                        $result[$i]['create_time'] = time();
                        $result[$i][$value] = ${$key}[$value][$i];
                        $result[$i]['supplier_id'] = $suppliers_id;
                    }
                }


                //$suppliers_id
                M('store_goods_supplices')->where(['goods_id'=>$goods_id,'is_examine'=>"0","supplier_id"=>$suppliers_id])->delete();
                M('store_goods_supplices')->insertAll($result);
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => $return_url),
                );
                $this->ajaxReturn($return_arr);
            }
            // 数据验证
            $virtual_indate = input('post.virtual_indate');//虚拟商品有效期

            $data = input('post.');
            $validate = \think\Loader::validate('GoodsRed');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg'    => $error_msg[0],
                    'data'   => $error,
                );
                $this->ajaxReturn($return_arr);
            }
            $data['virtual_indate'] = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            $data['exchange_integral'] = ($data['is_virtual'] == 1) ? 0 : $data['exchange_integral'];
            $GoodsRed->data($data, true); // 收集数据
            $GoodsRed->on_time = time(); // 上架时间
            I('cat_id_2') && ($GoodsRed->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($GoodsRed->cat_id = I('cat_id_3'));

            I('extend_cat_id_2') && ($GoodsRed->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($GoodsRed->extend_cat_id = I('extend_cat_id_3'));
            $GoodsRed->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $GoodsRed->shipping_area_ids = $GoodsRed->shipping_area_ids ? $GoodsRed->shipping_area_ids : '';
            $GoodsRed->spec_type = $GoodsRed->goods_type;
            $price_ladder = array();
            if ($GoodsRed->ladder_amount[0] > 0) {
                foreach ($GoodsRed->ladder_amount as $key => $value) {
                    $price_ladder[$key]['amount'] = intval($GoodsRed->ladder_amount[$key]);
                    $price_ladder[$key]['price'] = floatval($GoodsRed->ladder_price[$key]);
                }
                $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
                $price_ladder_max = count($price_ladder);
                if ($price_ladder[$price_ladder_max - 1]['price'] >= $GoodsRed->shop_price) {
                    $return_arr = array(
                        'msg' => '价格阶梯最大金额不能大于商品原价！',
                        'status' => -0,
                        'data' => array('url' => $return_url)
                    );
                    $this->ajaxReturn($return_arr);
                }
                if ($price_ladder[0]['amount'] <= 0 || $price_ladder[0]['price'] <= 0) {
                    $return_arr = array(
                        'msg' => '您没有输入有效的价格阶梯！',
                        'status' => -0,
                        'data' => array('url' => $return_url)
                    );
                    $this->ajaxReturn($return_arr);
                }
                $GoodsRed->price_ladder = serialize($price_ladder);
            } else {
                $GoodsRed->price_ladder = '';
            }
            if ($type == 2) {
                $GoodsRed->isUpdate(true)->save(); // 写入数据到数据库
                // 修改商品后购物车的商品价格也修改一下  米豆部分需要修改
                // 米豆这里要写根据金额直接计算的函数
                M('cart_red')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                    'market_price'       => I('market_price'), //市场价
                    'goods_price'        => I('shop_price'), // 本店价
                    'member_goods_price' => I('shop_price'), // 会员折扣价
                    'cost_price'         => I('cost_price'),     // 成本价
                ));
            } else {
                $GoodsRed->save(); // 写入数据到数据库
                $goods_id = $insert_id = $GoodsRed->getLastInsID();
                db('goods')->where('goods_id',$goods_id)->setField('sort',$goods_id);
            }
            $GoodsRed->afterSave($goods_id);
            $GoodsLogicRed->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => $return_url),
            );
            $this->ajaxReturn($return_arr);
        }

        $goodsInfo = M('GoodsRed')->where('goods_id=' . I('GET.id', 0))->find();
        if ($goodsInfo['price_ladder']) {
            $goodsInfo['price_ladder'] = unserialize($goodsInfo['price_ladder']);
        }
        $level_cat = $GoodsLogicRed->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        //$level_cat2 = $GoodsLogicRed->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list = M('goods_red_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        // $brandList = $GoodsLogicRed->getSortBrands();
        $goodsType = M("GoodsRedType")->select();
        if ($goods_ids) {
            $stockgoodsType = M("spec_red_goods_price")->where('goods_id='.$goods_ids)->select();
        }

        $suppliers_id = $this->suppliers_id;
        if($suppliers_id) $shipping_where['suppliers_id'] = array('eq', $suppliers_id);
        $shipping_where['status'] = 1;
        $shipping_where['is_default'] = 1;
        $shipping_where['type'] = array('eq', 'shipping');
        $plugin_shipping = M('plugin')->where($shipping_where)->select();//插件物流

        //查询实体店数据
        $companys =M('Company')->where('parent_id=0')->select();
        foreach ($companys as $key => $value) {
            $companys[$key]['child']= M('Company')->where('parent_id='.$value['cid'])->select();
        }
        $this->assign('stockgoodsType', $stockgoodsType);
        if ($stockgoodsType) {
            $stockstr="1";
        }else{
            $stockstr="2";
        }

        //已申请实体店显示
        $storeStock =M('store_goods_stock sgs')
            ->join('company c','c.cid = sgs.store_id','left')
            ->join('goods_red g','g.goods_id = sgs.goods_id','left')
            ->join('spec_red_goods_price sgp','sgs.item_id = sgp.item_id','left')
            ->field("sgs.*,c.cname,g.goods_name,sgp.key_name")
            ->where(["sgs.goods_id"=>$goods_ids,"sgs.is_examine"=>"1","supplier_id"=>$suppliers_id])
            ->group("c.cid,g.goods_id")
            ->select();

        $storeSupplices =M('store_goods_supplices sgs')
            ->join('company c','c.cid = sgs.store_id','left')
            ->join('goods_red g','g.goods_id = sgs.goods_id','left')
            ->join('spec_red_goods_price sgp','sgs.item_id = sgp.item_id','left')
            ->field("sgs.*,c.cname,g.goods_name,sgp.key_name")
            ->where(["sgs.goods_id"=>$goods_ids,"supplier_id"=>$suppliers_id])
            ->select();


        $this->assign('storeSupplices', $storeSupplices);
        $this->assign('storeStock', $storeStock);
        $this->assign('stockstr', $stockstr);
        $this->assign('sgoodslist', $sgoodslist);
        $store_id = db('store_goods_supplices')->where(['goods_id'=>$goods_ids])->column("store_id");
        $this->assign('sids', $store_id);
        // dump($sgoodslist);die();
        $this->assign('shiti', $companys);
        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        // $this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsRedImages")->where('goods_id =' . I('GET.id', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        return $this->fetch('_goods');
    } 


    public function get_plugin_shipping(){
        $suppliers_id = $this->suppliers_id;
        $shipping_where['status'] = 1;
        $shipping_where['type'] = array('eq', 'shipping');
        if($suppliers_id) $shipping_where['suppliers_id'] = array('eq', $suppliers_id);
        $plugin_shipping = M('plugin')->where($shipping_where)->select();//插件物流
        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        return $this->fetch();
    }
          

    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput(){
        $GoodsLogic = new RedGoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'],$_REQUEST['type_id']);
        exit($str);
    }
        
    /**
     * 删除商品
     */
    public function delGoods()
    {
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        $goods_ids = rtrim($ids,",");
        // 判断此商品是否有订单
        $ordergoods_count = Db::name('order_red_goods')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($ordergoods_count)
        {
            $goods_count_ids = implode(',',$ordergoods_count);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$goods_count_ids}】的商品有订单,不得删除!",'data'  =>'']);
        }

        // 删除此商品        
        M("goods_red")->whereIn('goods_id',$goods_ids)->delete();  //商品表
        M("cart_red")->whereIn('goods_id',$goods_ids)->delete();  // 购物车
        M("comment_red")->whereIn('goods_id',$goods_ids)->delete();  //商品评论
        M("goods_red_consult")->whereIn('goods_id',$goods_ids)->delete();  //商品咨询
        M("goods_red_images")->whereIn('goods_id',$goods_ids)->delete();  //商品相册
        M("spec_red_goods_price")->whereIn('goods_id',$goods_ids)->delete();  //商品规格
        M("spec_red_image")->whereIn('goods_id',$goods_ids)->delete();  //商品规格图片
        M("goods_red_attr")->whereIn('goods_id',$goods_ids)->delete();  //商品属性
        M("goods_red_collect")->whereIn('goods_id',$goods_ids)->delete();  //商品收藏

        $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Supplier/GoodsRed/goodsList")]);
    }
    
    
    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect(){
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;        
        $GoodsLogic = new RedGoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('SpecRed')->where("type_id = ".I('get.spec_type/d'))->order('`order` desc')->select();
        foreach($specList as $k => $v)        
            $specList[$k]['spec_item'] = M('SpecRedItem')->where("spec_id = ".$v['id'])->order('id')->getField('id,item'); // 获取规格项                
        
        $items_id = M('SpecRedGoodsPrice')->where('goods_id = '.$goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);       
        
        // 获取商品规格图片                
        if($goods_id)
        {
           $specImageList = M('SpecRedImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');                 
        }        
        $goodsinfo = M('goodsRed')->where('goods_id',$goods_id)->find();   
        $this->assign('specImageList',$specImageList);
        $this->assign('items_ids',$items_ids);
        $this->assign('specList',$specList);
        $this->assign('goodsinfo',$goodsinfo);
        return $this->fetch('ajax_spec_select');        
    }    
    
    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */    
    public function ajaxGetSpecInput(){     
         $GoodsLogic = new RedGoodsLogic();
         $goods_id = I('goods_id/d') ? I('goods_id/d') : 0;
         $str = $GoodsLogic->getSpecInput($goods_id ,I('post.spec_arr/a',[[]]));
         exit($str);   
    }
    
    /**
     * 删除商品相册图
     */
    public function del_goods_images()
    {
        $path = I('filename','');
        M('goods_red_images')->where("image_url = '$path'")->delete();
    }

    /**
     * 初始化商品关键词搜索
     */
    public function initGoodsSearchWord(){
        $searchWordLogic = new RedSearchWordLogic();
        $successNum = $searchWordLogic->initGoodsSearchWord($this->suppliers_id);
        $this->success('成功初始化'.$successNum.'个搜索关键词');
    }

    /**
     * 初始化地址json文件
     */
    public function initLocationJsonJs()
    {
        $goodsLogic = new RedGoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        file_put_contents(ROOT_PATH."public/js/locationJson.js", "var locationJsonInfoDyr = ".json_encode($region_list, JSON_UNESCAPED_UNICODE).';');
        $this->success('初始化地区json.js成功。文件位置为'.ROOT_PATH."public/js/locationJson.js");
    }


     /**
     * 判断实体店添加商品库存
     */    
    public function ajaxGetstoreSpecInput(){   
        $goods_id = I('goods_id/d');  
        $stockgoodsType = M("spec_red_goods_price")->where('goods_id='.$goods_id)->select();
        if ($stockgoodsType) {
            $str="1";
        }else{
            $str="2";
        }
        exit($str);   
    }
     
    /**
     * 实体店搜索
    */    
    public function ajaxGetshitidian(){   
        $cname = I('cname');  
        if (!empty($cname)) {
            $where = array(
                'cname'=>array('like','%'.$cname."%"),
                'parent_id'=>array('neq','0')
            );
            $comid = M('Company')->where($where)->field('cid,cname,ispush,isallow')->order("ispush desc,cid desc")->select();
        }
        exit(json_encode($comid));
        
       
    } 
    /**
     * 子公司
    */    
    public function ajaxGetzigongsi(){   
        $cid = I('cid');  
        if (!empty($cid)) {
            $where = array(
                'parent_id'=>array('eq',$cid),
            );
            $comid = M('Company')->where($where)->field('cid,cname,zexamine as ispush,sexamine as isallow')->order("ispush desc,cid desc")->select();
        }
        exit(json_encode($comid));
       
    }  
}