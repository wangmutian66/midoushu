<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\supplier\controller; 
use app\supplier\logic\GoodsLogic;
use app\supplier\logic\SearchWordLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;
class Goods extends Base {

	/**
     * 析构函数
     */
    function _initialize() 
    {
    	$this->suppliers_id = Session('suppliers.suppliers_id');
        $this->assign('suppliers_id',$this->suppliers_id);
        parent::_initialize();
   } 

    public function index(){
    	exit('正在开发');
        return $this->fetch();
    }
   
    /**
     *  商品列表
     */
    public function goodsList(){       
        $GoodsLogic = new GoodsLogic();        
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
            $grandson_ids = getCatGrandson($cat_id); 
            $where .= " and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        $count = M('goods')->where($where)->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('Goods')->where($where)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();

        $catList = D('goods_category')->select();
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
                $goods = M('goods')->field('goods_id,goods_name')->where(array('goods_id' => array('eq', $goods_id)))->select();
                $goods_info = M('goods')->where(array('goods_id' => array('eq', $goods_id)))->find();
            }
            $this->assign('goods_info',$goods_info);
        } else {
            $goods_id_array = I('get.goods_id_array');
            if (!empty($goods_id_array)) {
                $goods = M('goods')->field('goods_id,goods_name')->where(array('goods_id' => array('IN', $goods_id_array)))->select();
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
            M('Goods')->save($data);
        } else {
            //个体消息
            if (!empty($goods)) {
                foreach ($goods as $key) {
                    M('goods')->where('goods_id = '.$key)->save($data);
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }


    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {
        $GoodsLogic = new GoodsLogic();
        $Goods      = new \app\supplier\model\Goods();
        $goods_id = I('goods_id');
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if ((I('is_ajax') == 1) && IS_POST) {
            // 数据验证
            $virtual_indate = input('post.virtual_indate');//虚拟商品有效期
            $return_url =  U('/supplier/Goods/goodsList');

            $data = input('post.');
            $validate = \think\Loader::validate('Goods');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            }
            $data['virtual_indate'] = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            $data['exchange_integral'] = ($data['is_virtual'] == 1) ? 0 : $data['exchange_integral'];
            $Goods->data($data, true); // 收集数据
            $Goods->on_time = time(); // 上架时间
            I('cat_id_2') && ($Goods->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($Goods->cat_id = I('cat_id_3'));

            I('extend_cat_id_2') && ($Goods->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($Goods->extend_cat_id = I('extend_cat_id_3'));
            $Goods->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
            $Goods->spec_type = $Goods->goods_type;
            $price_ladder = array();
            if ($Goods->ladder_amount[0] > 0) {
                foreach ($Goods->ladder_amount as $key => $value) {
                    $price_ladder[$key]['amount'] = intval($Goods->ladder_amount[$key]);
                    $price_ladder[$key]['price']  = floatval($Goods->ladder_price[$key]);
                }
                $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
                $price_ladder_max = count($price_ladder);
                if ($price_ladder[$price_ladder_max - 1]['price'] >= $Goods->shop_price) {
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
                $Goods->price_ladder = serialize($price_ladder);
            } else {
                $Goods->price_ladder = '';
            }
            if ($type == 2) {
                $Goods->isUpdate(true)->save(); // 写入数据到数据库
                // 修改商品后购物车的商品价格也修改一下
                M('cart')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                    'market_price' => I('market_price'), //市场价
                    'goods_price'  => I('shop_price'), // 本店价
                    'member_goods_price' => I('shop_price'), // 会员折扣价
                    'cost_price'         => I('cost_price'),     // 成本价
                ));
            } else {
                $Goods->save(); // 写入数据到数据库
                $goods_id = $insert_id = $Goods->getLastInsID();
                db('goods')->where('goods_id',$goods_id)->setField('sort',$goods_id);
            }
            $Goods->afterSave($goods_id);
            $GoodsLogic->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => $return_url),
            );
            $this->ajaxReturn($return_arr);
        }

        $goodsInfo = M('Goods')->where('goods_id=' . I('GET.id', 0) . ' AND suppliers_id='.$this->suppliers_id)->find();
        if ($goodsInfo['price_ladder']) {
            $goodsInfo['price_ladder'] = unserialize($goodsInfo['price_ladder']);
        }
        $level_cat  = $GoodsLogic->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        //$level_cat2 = $GoodsLogic->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list   = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $brandList  = $GoodsLogic->getSortBrands();
        $goodsType  = M("GoodsType")->select();


        $suppliers_id = $this->suppliers_id;
        if($suppliers_id) $shipping_where['suppliers_id'] = array('eq', $suppliers_id);
        $shipping_where['status'] = 1;
        $shipping_where['is_default'] = 1;
        $shipping_where['type'] = array('eq', 'shipping');
        $plugin_shipping = M('plugin')->where($shipping_where)->select();//插件物流


        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        $this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsImages")->where('goods_id =' . I('GET.id', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        return $this->fetch('_goods');
    } 

    public function get_plugin_shipping(){
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
        $GoodsLogic = new GoodsLogic();
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
        $ordergoods_count = Db::name('OrderGoods')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($ordergoods_count)
        {
            $goods_count_ids = implode(',',$ordergoods_count);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$goods_count_ids}】的商品有订单,不得删除!",'data'  =>'']);
        }
         // 商品团购
        $groupBuy_goods = M('group_buy')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($groupBuy_goods)
        {
            $groupBuy_goods_ids = implode(',',$groupBuy_goods);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$groupBuy_goods_ids}】的商品有团购,不得删除!",'data'  =>'']);
        }
        // 删除此商品        
        M("Goods")->whereIn('goods_id',$goods_ids)->delete();  //商品表
        M("cart")->whereIn('goods_id',$goods_ids)->delete();  // 购物车
        M("comment")->whereIn('goods_id',$goods_ids)->delete();  //商品评论
        M("goods_consult")->whereIn('goods_id',$goods_ids)->delete();  //商品咨询
        M("goods_images")->whereIn('goods_id',$goods_ids)->delete();  //商品相册
        M("spec_goods_price")->whereIn('goods_id',$goods_ids)->delete();  //商品规格
        M("spec_image")->whereIn('goods_id',$goods_ids)->delete();  //商品规格图片
        M("goods_attr")->whereIn('goods_id',$goods_ids)->delete();  //商品属性
        M("goods_collect")->whereIn('goods_id',$goods_ids)->delete();  //商品收藏

        $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Supplier/goods/goodsList")]);
    }

    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect(){
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;        
        $GoodsLogic = new GoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('Spec')->where("type_id = ".I('get.spec_type/d'))->order('`order` desc')->select();
        foreach($specList as $k => $v)        
            $specList[$k]['spec_item'] = M('SpecItem')->where("spec_id = ".$v['id'])->order('id')->getField('id,item'); // 获取规格项                
        
        $items_id = M('SpecGoodsPrice')->where('goods_id = '.$goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);       
        
        // 获取商品规格图片                
        if($goods_id)
        {
           $specImageList = M('SpecImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');                 
        }        
        $goodsinfo = M('goods')->where('goods_id',$goods_id)->find();     
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
         $GoodsLogic = new GoodsLogic();
         $goods_id = I('goods_id/d') ? I('goods_id/d') : 0;
         $str = $GoodsLogic->getSpecInput($goods_id ,I('post.spec_arr/a',[[]]));
         exit($str);   
    }


    // 商品库存记录
    public function stock_list(){

        $suppliers_id = $this->suppliers_id;

        $model = M('stock_log');
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

        $count = $model->alias('stock')->field('stock.id')->join('__GOODS__ goods','goods.goods_id = stock.goods_id ','LEFT')->where($map)->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出

        // 查找库存日志
        $stock_list = $model->alias('stock')->field('stock.*,goods.suppliers_id')->join('__GOODS__ goods','goods.goods_id = stock.goods_id','LEFT')->where($map)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();

        $this->assign('stock_list',$stock_list);
        return $this->fetch();
    }


    /**
     * 初始化商品关键词搜索
     */
    public function initGoodsSearchWord(){
        $searchWordLogic = new SearchWordLogic();
        $successNum = $searchWordLogic->initGoodsSearchWord($this->suppliers_id);
        $this->success('成功初始化'.$successNum.'个搜索关键词');
    }
    
   
}