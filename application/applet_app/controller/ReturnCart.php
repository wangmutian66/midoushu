<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\applet_app\controller;
use app\common\logic\CartLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\CouponLogic;
use app\common\logic\OrderLogic;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\logic\IntegralLogic;
use think\Db;
use think\Url;

class Cart extends MobileBase {

    public $cartLogic; // 购物车逻辑操作类    
    public $user_id = 0;
    public $user = array();
    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();
        $this->cartLogic = new CartLogic();

        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
        }else{
            $this->user_id = $this->request->param('user_id');
            $this->token = $this->request->param('token');
        }
    }
    /**
     * 用户购物车列表
     * @author wuchaoqun
     * @param int is_allreturn  0 为现金购物车  1为福利购物车
     * @return array
     */
    public function index(){

        if ($this->user_id == 0 ||  $this->token == ''){
            exit(formt('',201,'请登录'));
        }
        $isUsers = M('users')->where(['user_id'=>$this->user_id , 'token'=>$this->token])->find();
        if(!$isUsers){
            exit(formt('',201,'用户不存在'));
        }
        $is_allreturn= empty($this->request->param('is_allreturn'))? 1 : $this->request->param('is_allreturn');
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList(1,$is_allreturn); //用户购物车 0 为现金购物车  1为福利购物车

        $goods_arr = array(); // 商品ID
        foreach($cartList as $k => $val){
            if(empty($val['goods']['original_img'])){
                $val['goods']['original_img'] = "/public/images/icon_goods_thumb_empty_300.png";
            }
            $goods_arr[$k]['id'] = $val['id'];
            $goods_arr[$k]['user_id'] = $val['user_id'];
            $goods_arr[$k]['goods_id'] = $val['goods_id'];
            $goods_arr[$k]['goods_name'] = $val['goods_name'];
            $goods_arr[$k]['goods_price'] = $val['goods_price'];
            $goods_arr[$k]['selected'] = 0;
            $goods_arr[$k]['goods_num'] = $val['goods_num'];
            $goods_arr[$k]['is_allreturn'] = $val['is_allreturn'];
            $goods_arr[$k]['original_img'] = $val['goods']['original_img'];
            $goods_arr[$k]['spec_key_name'] = $val['spec_key_name'];
            $goods_arr[$k]['store_count'] = $val['goods']['store_count'];
        }
        return formt($goods_arr);
        
    }
    /**
     * @author wuchaoqun
     * 用户购物车热销推荐商品列表
     * @param int p 页数
     * @param int rows 条数
     * @param int is_allreturn  0 为现金购物车  1为福利购物车
     * @return array
     */
    public function hot_goods(){
        $p = empty($this->request->param('p'))? 1 : $this->request->param('p');//页数
        $rows =  empty($this->request->param('rows')) ? 10 : $this->request->param('rows');//条数
        $is_allreturn= empty($this->request->param('is_allreturn'))? 1 : $this->request->param('is_allreturn');
        $counts = M('Goods')->where('is_hot=1 and is_allreturn='.$is_allreturn.' and is_on_sale=1 and is_check=1')->cache(true,TPSHOP_CACHE_TIME)->count();
        $hot_goods = M('Goods')->where('is_hot=1 and is_allreturn='.$is_allreturn.' and is_on_sale=1 and is_check=1')->limit($p,$rows)->order('on_time  desc')->cache(true,TPSHOP_CACHE_TIME)->select();
        
        $arr_hot_goods = array();
        foreach ($hot_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            if(empty($val['goods']['original_img'])){
                $val['goods']['original_img'] = "/public/images/icon_goods_thumb_empty_300.png";
            }
            $arr_hot_goods[$k]['goods_id'] = $val['goods_id'];
            $arr_hot_goods[$k]['goods_name'] = $val['goods_name'];
            $arr_hot_goods[$k]['goods_price'] = $val['shop_price'];
            $arr_hot_goods[$k]['is_allreturn'] = $val['is_allreturn'];
            $arr_hot_goods[$k]['original_img'] = $val['goods']['original_img'];
            $arr_hot_goods[$k]['back_midou'] = $val['back_midou'];
        }
        return formt(['listData'=>$arr_hot_goods,'count'=>$counts]);
    }
 

    /**
     * 删除购物车商品
     * @param int cart_ids  购物车id
     * @return array
     */
    public function delete(){
        $cart_id= $this->request->param('id');
        if ($cart_id == 0 ||  empty($cart_id)){
            exit(formt('',201,'参数错误'));
        }
        $cart_ids[0] = $cart_id;
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if($result !== false){
            exit(formt('','200','删除成功'));
        }else{
            exit(formt('',201,'删除失败'));
        }
    }
    /**
     * 用户米豆购物车列表
     * @author wuchaoqun
     * @return array
     */
    public function mobilered_index(){
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList(); //用户购物车
        $goods_arr = array(); // 商品ID
        foreach ($cartList as $k => $val) {
            if(empty($val['goods']['original_img'])){
                $val['goods']['original_img'] = "/public/images/icon_goods_thumb_empty_300.png";
            }
            $goods_arr[$k]['id'] = $val['id'];
            $goods_arr[$k]['user_id'] = $val['user_id'];
            $goods_arr[$k]['goods_id'] = $val['goods_id'];
            $goods_arr[$k]['goods_name'] = $val['goods_name'];
            $goods_arr[$k]['goods_price'] = $val['goods_price'];
            $goods_arr[$k]['selected'] = $val['selected'];
            $goods_arr[$k]['goods_num'] = $val['goods_num'];
            $goods_arr[$k]['is_allreturn'] = $val['is_allreturn'];
            $goods_arr[$k]['original_img'] = $val['goods']['original_img'];
            $goods_arr[$k]['spec_key_name'] = $val['spec_key_name'];
            $goods_arr[$k]['store_count'] = $val['goods']['store_count'];
        }
        return formt($goods_arr);
    }
    /**
     * @author wuchaoqun
     * 用户米豆购物车热销推荐商品列表
     * @param int p 页数
     * @param int rows 条数
     * @return array
     */
    public function mobilered_hot_goods(){
        $p = empty($this->request->param('p'))? 1 : $this->request->param('p');//页数
        $rows =  empty($this->request->param('rows')) ? 10 : $this->request->param('rows');//条数
        $counts = M('GoodsRed')->where('is_hot=1 and is_on_sale=1 and is_check=1')->cache(true,TPSHOP_CACHE_TIME)->count();
        $hot_goods = M('GoodsRed')->where('is_hot=1 and is_on_sale=1 and is_check=1')->limit($p,$rows)->order('on_time  desc')->cache(true,TPSHOP_CACHE_TIME)->select();
        $arr_hot_goods = array();
        foreach ($hot_goods as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
          
            $arr_hot_goods[$k]['goods_id'] = $val['goods_id'];
            $arr_hot_goods[$k]['goods_name'] = $val['goods_name'];
            $arr_hot_goods[$k]['goods_price'] = $val['shop_price'];
            $arr_hot_goods[$k]['is_allreturn'] = $val['is_allreturn'];
            $arr_hot_goods[$k]['original_img'] = $val['goods']['original_img'];
            $arr_hot_goods['midou']       = $midouInfo['midou'];
            $arr_hot_goods['midou_money'] = $midouInfo['midou_money'];
            $arr_hot_goods['midou_index'] = $midouInfo['midou_index'];
        }
        $arr_hot_goods['p'] = $p;
        $arr_hot_goods['rows'] = $rows;
        $arr_hot_goods['counts'] = $counts;
        return formt($arr_hot_goods);
    }
   
     /**
     * @author wuchaoqun
     * 购物车加减计数
     * @param int goods_id 商品id
     * @param int goods_num 商品户数量
     * @return array
     */
    public function changeNum(){
        $id = $this->request->param('id');
        $goods_num = $this->request->param('goods_num');
        if($id == '' || empty($id)){
            exit(formt('','201','id错误'));
         }
        if($goods_num == '' || empty($goods_num)  || $goods_num < 1){
            exit(formt('','201','商品数量必须大于等于1'));
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($id,$goods_num);
        if($result['status'] == 1){
            return formt($result);
        }else if($result['status'] == 0){
            exit(formt('','201',$result['msg']));
        }else{
            exit(formt('','201','商品数量修改失败'));
        }
    }

     /**
     * @author wuchaoqun
     * 更新购物车，并返回计算结果
     * @param array cart[]
     * int goods_id 商品id
     * int goods_num 商品户数量
     * int selected 是否选中
     * @return array
     */
    public function AsyncUpdateCart(){
        $cart = $this->request->param('cart/a', []);
        if(empty($cart)){
            exit(formt('','201','数组参数错误'));
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->AsyncUpdateCart($cart);
        if(!$result){
            exit(formt('','201','获取商品信息失败'));
        }
        $back_goods = array();
        foreach ( $result['result']['cartList'] as $k => $val) {
            $back_goods[$k]['goods_id'] = $val['goods_id'];
            $back_goods[$k]['selected'] = $val['selected'];
            $back_goods[$k]['goods_num'] = $val['goods_num'];
            $back_goods[$k]['goods_fee'] = $val['goods_fee'];//节省了
        }
        return formt(['listData'=>$back_goods,'total_fee'=>$result['result']['total_fee'],'goods_num'=>$result['result']['goods_num']]);
    }

/**
     * 购物车确认信息地址  接口
     */
    public function addressSure(){
        $address_id = $this->request->param('address_id/d');//地址id
        if(!empty($address_id)){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id'=>$this->user_id])->field('address_id,city,district,consignee,address,mobile')->order(['is_default'=>'desc'])->find();
        }
        if(empty($address)){
            exit(formt('','200','地址为空,请填加地址'));
        }else{
            return formt($address);
        }
    }


   
    /**
     * 购物车确认信息页面
     */
    public function cartSure(){
        $cartIds  = $this->request->param("cartIds/a"); // 购物车id
        $goods_id  = $this->request->param("goods_id/d"); // 商品id
        $goods_num = $this->request->param("goods_num/d");// 商品数量
        $item_id   = $this->request->param("item_id/d");  // 商品规格id
        $action    = $this->request->param("action");     // 行为
       
        $cartLogic = new CartLogic();
        $couponLogic = new CouponLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if($action == 'buy_now'){
            if(empty($goods_id)){
                exit(formt('','201','请选择要购买的商品'));
            }
            if(empty($goods_num)){
                exit(formt('','201','购买商品数量不能为0'));
            }
            $cartLogic->setGoodsModel($goods_id);
            if($item_id && !empty($item_id)){
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                exit(formt('','201',$result['msg']));
            }
            $cartList['cartList'][0] = $result['result']['buy_goods'];
            $cartGoodsTotalNum = $goods_num;
        }else{
            if ($cartLogic->getUserCartOrderCount(0) == 0){
                exit(formt('','201','你的购物车没有选中商品'));
            }
            $cartList['cartList'] = $cartLogic->getCartListPart($cartIds,1); // 根据id查询购物车商品
        }

        // 按供货商 分
        $goods_arr = array();
        foreach ($cartList['cartList'] as $k => $val) {
            if($action != 'buy_now') $val = json_decode($val,true);
            $val['goods_fee'] = $val['member_goods_price']*$val['goods_num'];
            if(empty($val['goods']['original_img'])){
                $val['goods']['original_img'] = "/public/images/icon_goods_thumb_empty_300.png";
            }
            $goods_arr[$k]['id'] = $val['id'];
            $goods_arr[$k]['user_id'] = $val['user_id'];
            $goods_arr[$k]['goods_id'] = $val['goods_id'];
            $goods_arr[$k]['goods_name'] = $val['goods_name'];
            $goods_arr[$k]['goods_price'] = $val['goods_price'];
            $goods_arr[$k]['selected'] = $val['selected'];
            $goods_arr[$k]['goods_num'] = $val['goods_num'];
            $goods_arr[$k]['is_allreturn'] = $val['is_allreturn'];
            $goods_arr[$k]['original_img'] = $val['goods']['original_img'];
            $goods_arr[$k]['spec_key_name'] = $val['spec_key_name'];
            $goods_arr[$k]['store_count'] = $val['goods']['store_count'];
            $goods_arr[$k]['suppliers_id'] = $val['goods']['suppliers_id'];
            $goods_arr[$k]['shippinglist'] =  M('Plugin')->where("`type` = 'shipping' and is_default = 1 and suppliers_id = ".$val['suppliers_id'])->field('name,desc')->cache(true,TPSHOP_CACHE_TIME)->select();  // 物流公司
        }
      
        return formt($goods_arr);
      
    }

   

    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cartOrder(){

        if($this->user_id == 0){
            exit(formt('','201','登录超时请重新登录'));
        }

        $suppliers_id  = I("suppliers_id/a");  // 供货商id
        $address_id    = I("address_id/d");    // 收货地址id
        $coupon_id     = I("coupon_id/d");     // 优惠券id
        $pay_points    = I("pay_points/d",0);  // 使用积分
        $user_money    = I("user_money/f",0);  // 使用余额
        $user_note     = I("user_note/a");     // 买家留言
        //立即购买 才会用到
        $goods_id      = input("goods_id/d");  // 商品id
        $goods_num     = input("goods_num/d"); // 商品数量
        $item_id       = input("item_id/d");   // 商品规格id
        $action        = input("action");      // 立即购买
        
        
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        if($action == 'buy_now'){

            // 获取商品模型 商品信息
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                exit(formt('','201','商品信息错误'));
            }
            $order_goods[0] = $result['result']['buy_goods'];

        }else{

            $userCartList = $cartLogic->getCartList(1);
            if($userCartList){
                $order_goods = collection($userCartList)->toArray();
            }else{
                exit(formt('','201','你的购物车没有选中商品'));
            }
            foreach ($userCartList as $cartKey => $cartVal) {
                if($cartVal->goods_num > $cartVal->limit_num){
                    exit(formt('','201','购买数量不能大于'.$cartVal->limit_num));
                }
            }
        }

        $order_goods_arr = array_group_by($order_goods, 'suppliers_id'); // 分组后的 订单商品 数据

        $address = M('UserAddress')->where("address_id", $address_id)->find();

        // 所有订单总和
        $car_price = array(
          
            'payables'          => 0, // 应付金额
            
        );

        $shipping_code = array();
        $store_price = array();
        foreach ($suppliers_id as $key=>$val) {

            $plugin_goods_shipping = M('plugin')->where(array('type'=>'shipping','status'=>1,'is_default'=>1,'suppliers_id'=>$val))->field('code')->find();
            if($plugin_goods_shipping) $shipping_code[$val] = $plugin_goods_shipping['code'];
            else $shipping_code[$val] = '';

            $result = calculate_price($this->user_id,$order_goods_arr[$val],$shipping_code[$val],0,$address['province'],$address['city'],$address['district'],$pay_points,$user_money,$coupon_id,$red_envelope_id);
            if($result['status'] < 0)  return formt($result);
            $store_price[$key]['payables']          += number_format($result['result']['order_amount'], 2, '.', ''); // 应付金额
            $store_price[$key]['postFee'] += $result['result']['shipping_price'];     // 物流费
            $store_price[$key]['order_prom_amount'] += $result['result']['order_prom_amount'];  // 订单优惠活动优惠了多少钱
          
            $car_price['payables']          += number_format($result['result']['order_amount'], 2, '.', ''); // 应付金额
        
        }
        if(!$address_id)  exit(formt('','201','请先填写收货人信息')); // 返回结果状态
        
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $pay_name = '';
            $orderLogic = new OrderLogic();
            $orderLogic->setAction($action);
            $orderLogic->setCartList($order_goods_arr);
            $result = $orderLogic->addStoreOrder($this->user_id,$address_id,$suppliers_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$store_price,$user_note,$pay_name); // 添加订单
            if($result['status'] < 0) {
                exit(formt('','201','购买数量不能大于'.$cartVal->limit_num));
            }else{
                return formt($result);       
            }

        }

        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price,'result2'=>$store_price); // 返回结果状态
        return formt($return_arr);
    }


 /**
     * 购物车第二步确定页面
     */
    public function cart21()
    {
        $goods_id  = input("goods_id/d");  // 商品id
        $goods_num = input("goods_num/d"); // 商品数量
        $item_id   = input("item_id/d");   // 商品规格id
        $action    = input("action");      // 行为

        if($this->user_id == 0){
            $this->error('请先登录',U('Mobile/User/login'));
        }

        $address_id = I('address_id/d');
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id'=>$this->user_id])->order(['is_default'=>'desc'])->find();
        }
        if(empty($address)){
            $address = M('user_address')->where(['user_id'=>$this->user_id])->find();
        }
        if(empty($address)){
            header("Location: ".U('Mobile/User/add_address',array('source'=>'cart2')));
            exit;
        }else{
            $this->assign('address',$address);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if($action == 'buy_now'){
            if(empty($goods_id)){
                $this->error('请选择要购买的商品');
            }
            if(empty($goods_num)){
                $this->error('购买商品数量不能为0');
            }
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                $this->error($result['msg']);
            }
            $cartList[0] = $result['result']['buy_goods'];
        }else{
            if($cartLogic->getUserCartOrderCount() == 0){
                $this->error ('你的购物车没有选中商品','Cart/index');
            }
            $cartList = $cartLogic->getCartList(1); // 获取购物车商品
        }
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList);
        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        $couponWhere = [
            'c2.uid' => $this->user_id,
            'c1.use_end_time' => ['gt', time()],
            'c1.use_start_time' => ['lt', time()],
            'c1.condition' => ['elt', $cartPriceInfo['total_fee']]
        ];
        $couponList = Db::name('coupon')->alias('c1')
            ->join('__COUPON_LIST__ c2', ' c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0', 'inner')
            ->where($couponWhere)
            ->select();

        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();// 物流公司
        if($cartList) {
            $orderGoods = collection($cartList)->toArray();
        }
        foreach($shippingList as $k => $v) {
            $dispatchs = calculate_price($this->user_id, $orderGoods, $v['code'], 0, $address['province'], $address['city'], $address['district']);
            if ($dispatchs['status'] !== 1) {
                $this->error($dispatchs['msg']);
            }
            $shippingList[$k]['freight'] = $dispatchs['result']['shipping_price'];
        }
        $this->assign('couponList', $couponList); // 优惠券列表
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('cartList', $cartList); // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo); // 总计
        return $this->fetch();
    }







    /*
     * 订单支付页面
     */
    public function cart4(){

        $order_id    = I('order_id/d');
        $order_sn    = I('order_sn');
        $order_where = "user_id = ".$this->user_id;


        if($order_id){
            $order_where .= " AND order_id =".$order_id;
            $order[0] = M('Order')->where($order_where)->find();

            if($order[0]['order_status'] == 3){
                $this->error('该订单已取消',U("Mobile/Order/order_detail",array('id'=>$order_id)));
            }

            // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
            if($order[0]['pay_status'] == 1){            
                $order_detail_url = U("Mobile/Order/order_detail",array('id'=>$order[0]['order_id']));
                header("Location: $order_detail_url");
                exit;
            }
            $order_amounts = $order[0]['order_amount'];
            $order_num = 1;
        } 

        if($order_sn){
            $order_where .= " AND order_sn ='".$order_sn."' OR parent_sn='".$order_sn."'";
            $order = M('Order')->where($order_where)->select();
            $order_amounts = M('Order')->where($order_where)->sum('order_amount');
            $order_num = count($order);
        } 

        if(empty($order) || empty($this->user_id)){
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }

        $oids = '';
        $i = 1;
        foreach ($order as $k => $val) {
            if($i != 1) $oids .= ',';
            $oids .= $val['order_id'];
            $i++;
        }

        $payment_where = array(
            'type'=>'payment',
            'status'=>1,
            'scene'=>array('in',array(0,2))
        );

        $orderGoodsPromType = M('order_goods')->where('order_id','in',$oids)->getField('prom_type',true);
        $no_cod_order_prom_type = ['4,5'];//预售订单，虚拟订单不支持货到付款
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if(in_array($order['order_prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod'));
            }
        }else{
            if(in_array($val['order_prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('eq',1);
        };

        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            //判断当前浏览器显示支付方式
            if(($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())){
                unset($paymentList[$key]);
            }
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片

        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList',$paymentList);
        $this->assign('order_amounts',$order_amounts);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('order_num',$order_num);      
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }

    /**
     * 将商品加入购物车
     */
    function AddCart()
    {
        $goods_id  = $this->request->param('goods_id/d');  // 商品id
        $goods_num = $this->request->param('goods_num/d'); // 商品数量
        $item_id   = $this->request->param('item_id/d');   // 商品规格id
        if(empty($goods_id)){
            exit(formt('','201','请选择要购买的商品'));
        }
        if(empty($goods_num)){
            exit(formt('','201','购买商品数量不能为0'));
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setGoodsModel($goods_id);
        if($item_id){
            $cartLogic->setSpecGoodsPriceModel($item_id);
        }
        $cartLogic->setGoodsBuyNum($goods_num);
        $result = $cartLogic->addGoodsToCart();
        if($result){
            return formt($result);
        }
    }
    /**
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress(){
        $regionList = get_region_list();
        $address_list = M('UserAddress')->where("user_id", $this->user_id)->select();
        $c = M('UserAddress')->where("user_id = {$this->user_id} and is_default = 1")->count(); // 看看有没默认收货地址
        if((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;

        $this->assign('regionList', $regionList);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }

    /**
     * 预售商品下单流程
     */
    public function pre_sell_cart()
    {
        $act_id = I('act_id/d');
        $goods_num = I('goods_num/d');
        $address_id = I('address_id/d');
        if(empty($act_id)){
            $this->error('没有选择需要购买商品');
        }
        if(empty($goods_num)){
            $this->error('购买商品数量不能为0', U('Home/Activity/pre_sell', array('act_id' => $act_id)));
        }
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id'=>$this->user_id])->order(['is_default'=>'desc'])->find();
        }
        if(empty($address)){
            header("Location: ".U('Mobile/User/add_address',array('source'=>'pre_sell_cart','act_id'=>$act_id,'goods_num'=>$goods_num)));
            exit;
        }else{
            $this->assign('address',$address);
        }
        if($this->user_id == 0){
            $this->error('请先登录');
        }
        $pre_sell_info = M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        if(empty($pre_sell_info)){
            $this->error('商品不存在或已下架',U('Home/Activity/pre_sell_list'));
        }
        $pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
        if ($pre_sell_info['act_count'] + $goods_num > $pre_sell_info['restrict_amount']) {
            $buy_num = $pre_sell_info['restrict_amount'] - $pre_sell_info['act_count'];
            $this->error('预售商品库存不足，还剩下' . $buy_num . '件', U('Home/Activity/pre_sell', array('id' => $act_id)));
        }
        $goodsActivityLogic = new GoodsActivityLogic();
        $pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']);//预售商品的订购数量和订单数量
        $pre_sell_price['cut_price'] =$goodsActivityLogic->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品价格
        $pre_sell_price['goods_num'] = $goods_num;
        $pre_sell_price['deposit_price'] = floatval($pre_sell_info['deposit']);
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $invoice_title = I('invoice_title'); // 发票
            $shipping_code =  I("shipping_code"); //  物流编号
            $address_id = I("address_id/d"); //  收货地址id
            if(empty($address_id)){
                exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息','result'=>null))); // 返回结果状态
            }
            if(empty($shipping_code)){
                exit(json_encode(array('status'=>-4,'msg'=>'请选择物流信息','result'=>null))); // 返回结果状态
            }
            $orderLogic = new OrderLogic();
            $result = $orderLogic->addPreSellOrder($this->user_id, $address_id, $shipping_code, $invoice_title, $act_id, $pre_sell_price); // 添加订单
            exit(json_encode($result));
        }
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->select();// 物流公司
        $this->assign('pre_sell_info', $pre_sell_info);// 购物车的预售商品
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('pre_sell_price',$pre_sell_price);
        return $this->fetch();
    }

    /**
     * 兑换积分商品
     */
    public function buyIntegralGoods(){
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num');
        if(empty($this->user)){
            $this->ajaxReturn(['status'=>0,'msg'=>'请登录']);
        }
        if(empty($goods_id)){
            $this->ajaxReturn(['status'=>0,'msg'=>'非法操作']);
        }
        if(empty($goods_num)){
            $this->ajaxReturn(['status'=>0,'msg'=>'购买数不能为零']);
        }
        $goods = Goods::get($goods_id);
        if(empty($goods)){
            $this->ajaxReturn(['status'=>0,'msg'=>'该商品不存在']);
        }
        $Integral = new IntegralLogic();
        if(!empty($item_id)){
            $specGoodsPrice = SpecGoodsPrice::get($item_id);
            $Integral->setSpecGoodsPrice($specGoodsPrice);
        }
        $Integral->setUser($this->user);
        $Integral->setGoods($goods);
        $Integral->setBuyNum($goods_num);
        $result = $Integral->buy();
        $this->ajaxReturn($result);
    }

    /**
     *  积分商品结算页
     * @return mixed
     */
    public function integral(){
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        $address_id = input('address_id/d');
        if(empty($this->user)){
            $this->error('请登录');
        }
        if(empty($goods_id)){
            $this->error('非法操作');
        }
        if(empty($goods_num)){
            $this->error('购买数不能为零');
        }
        $Goods = new Goods();
        $goods = $Goods->where(['goods_id'=>$goods_id])->find();
        if(empty($goods)){
            $this->error('该商品不存在');
        }
        if (empty($item_id)) {
            $goods_spec_list = SpecGoodsPrice::all(['goods_id' => $goods_id]);
            if (count($goods_spec_list) > 0) {
                $this->error('请传递规格参数');
            }
            $goods_price = $goods['shop_price'];
            //没有规格
        } else {
            //有规格
            $specGoodsPrice = SpecGoodsPrice::get(['item_id'=>$item_id,'goods_id'=>$goods_id]);
            if ($goods_num > $specGoodsPrice['store_count']) {
                $this->error('该商品规格库存不足，剩余' . $specGoodsPrice['store_count'] . '份');
            }
            $goods_price = $specGoodsPrice['price'];
            $this->assign('specGoodsPrice', $specGoodsPrice);
        }
        if($address_id){
            $address = Db::name('user_address')->where("address_id" , $address_id)->find();
        }else{
            $address = Db::name('user_address')->where(['user_id' => $this->user_id])->order(['is_default' => 'desc'])->find();
        }
        if(empty($address)){
            header("Location: ".U('Mobile/User/add_address',array('source'=>'integral','goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id)));
            exit;
        }else{
            $this->assign('address',$address);
        }
        $shippingList = Db('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();// 物流公司
        $point_rate = tpCache('shopping.point_rate');
        $backUrl = Url::build('Goods/goodsInfo',['id'=>$goods_id,'item_id'=>$item_id]);
        $this->assign('backUrl', $backUrl);
        $this->assign('point_rate', $point_rate);
        $this->assign('goods', $goods);
        $this->assign('goods_price', $goods_price);
        $this->assign('goods_num',$goods_num);
        $this->assign('shippingList', $shippingList);
        return $this->fetch();
    }

    /**
     *  积分商品价格提交
     * @return mixed
     */
    public function integral2(){
        if ($this->user_id == 0){
            $this->ajaxReturn(['status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null]);
        }
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        $address_id = input("address_id/d"); //  收货地址id
        $shipping_code = input("shipping_code/s"); //  物流编号
        $user_note = input('user_note'); // 给卖家留言
        $invoice_title = input('invoice_title'); // 发票
        $user_money = input("user_money/f", 0); //  使用余额
        $pwd = input('pwd');
        $user_money = $user_money ? $user_money : 0;
        if (empty($address_id)){
            $this->ajaxReturn(['status' => -3, 'msg' => '请先填写收货人信息', 'result' => null]);
        }
        if(empty($shipping_code)){
            $this->ajaxReturn(['status' => -4, 'msg' => '请选择物流信息', 'result' => null]);
        }
        $address = Db::name('user_address')->where("address_id", $address_id)->find();
        if(empty($address)){
            $this->ajaxReturn(['status' => -3, 'msg' => '请先填写收货人信息', 'result' => null]);
        }
        $Goods = new Goods();
        $goods = $Goods::get($goods_id);
        $Integral = new IntegralLogic();
        $Integral->setUser($this->user);
        $Integral->setGoods($goods);
        if($item_id){
            $specGoodsPrice = SpecGoodsPrice::get($item_id);
            $Integral->setSpecGoodsPrice($specGoodsPrice);
        }
        $Integral->setAddress($address);
        $Integral->setShippingCode($shipping_code);
        $Integral->setBuyNum($goods_num);
        $Integral->setUserMoney($user_money);
        $result = $Integral->order();
        if ($result['status'] != 1){
            $this->ajaxReturn($result);
        }
        $car_price = array(
            'postFee' => $result['result']['shipping_price'], // 物流费
            'balance' => $result['result']['user_money'], // 使用用户余额
            'payables' => number_format($result['result']['order_amount'], 2, '.', ''), // 订单总额 减去 积分 减去余额 减去优惠券 优惠活动
            'pointsFee' => $result['result']['integral_money'], // 积分抵扣的金额
            'points' => $result['result']['total_integral'], // 积分支付
            'goodsFee' => $result['result']['goods_price'],// 总商品价格
            'goods_shipping'=>$result['result']['goods_shipping']
        );
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            // 排队人数
            $queue = \think\Cache::get('queue');
            if($queue >= 100){
                $this->ajaxReturn(['status' => -99, 'msg' => "当前人数过多请耐心排队!".$queue, 'result' => null]);
            }else{
                \think\Cache::inc('queue',1);
            }
            //购买设置必须使用积分购买，而用户的积分足以支付
            if( $this->user['pay_points'] >= $car_price['points'] || $user_money>0){
                if($this->user['is_lock'] == 1){
                    $this->ajaxReturn(['status'=>-5,'msg'=>"账号异常已被锁定，不能使用积分或余额支付！",'result'=>null]);// 用户被冻结不能使用余额支付
                }
                $payPwd =trim($pwd);
                if(encrypt($payPwd) != $this->user['paypwd']){
                    $this->ajaxReturn(['status'=>-5,'msg'=>"支付密码错误！",'result'=>null]);
                }
            }
            $result = $Integral->addOrder($invoice_title,$user_note); // 添加订单
            // 这个人处理完了再减少
            \think\Cache::dec('queue');
            $this->ajaxReturn($result);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $car_price]);
    }
    
     /**
     *  获取发票信息
     * @date2017/10/19 14:45
     */
    public function invoice(){

        $map = [];
        $map['user_id']=  $this->user_id;
        
        $field=[          
            'invoice_title',
            'taxpayer',
            'invoice_desc', 
        ];
        
        $info = M('user_extend')->field($field)->where($map)->find();
        if(empty($info)){
            $result=['status' => -1, 'msg' => 'N', 'result' =>''];
        }else{
            $result=['status' => 1, 'msg' => 'Y', 'result' => $info];
        }
        $this->ajaxReturn($result);            
    }
     /**
     *  保存发票信息
     * @date2017/10/19 14:45
     */
    public function save_invoice(){     
        
        if(IS_AJAX){
            
            //A.1获取发票信息
            $invoice_title = trim(I("invoice_title"));
            $taxpayer      = trim(I("taxpayer"));
            $invoice_desc  = trim(I("invoice_desc"));
            
            //B.1校验用户是否有历史发票记录
            $map            = [];
            $map['user_id'] =  $this->user_id;
            $info           = M('user_extend')->where($map)->find();
            
           //B.2发票信息
            $data=[];  
            $data['invoice_title'] = $invoice_title;
            $data['taxpayer']      = $taxpayer;  
            $data['invoice_desc']  = $invoice_desc;     
            
            //B.3发票抬头
            if($invoice_title=="个人"){
                $data['invoice_title'] ="个人";
                $data['taxpayer']      = "";
            }                              
            
            
            //是否存贮过发票信息
            if(empty($info)){   
                $data['user_id'] = $this->user_id;
                (M('user_extend')->add($data))?
                $status=1:$status=-1;                
             }else{
                (M('user_extend')->where($map)->save($data))?
                $status=1:$status=-1;                
            }            
            $result = ['status' => $status, 'msg' => '', 'result' =>''];           
            $this->ajaxReturn($result); 
            
        }      
    }

    #余额支付 相关
    #TK
    #2018年5月31日16:46:46
    function balance(){
        ak_get_pays($this->user);
    }
}
