<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\mobilered\controller;
use app\common\logic\RedCartLogic;
use app\common\logic\RedGoodsActivityLogic;
use app\common\logic\RedCouponLogic;
use app\common\logic\RedOrderLogic;
use app\common\model\GoodsRed;
use app\common\model\SpecRedGoodsPrice;
use app\common\logic\IntegralLogic;
use Qiniu\Config;
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
        $this->cartLogic = new RedCartLogic();

        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息

            // 给用户计算会员价 登录前后不一样
            if ($user) {
                /*$user['discount'] = (empty($user['discount'])) ? 1 : $user['discount'];
                if ($user['discount'] != 1) {
                    $c = Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->where('member_goods_price = goods_price')->count();
                    $c && Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->update(['member_goods_price' => ['exp', 'goods_price*' . $user['discount']]]);
                }*/
            }
        }
    }

    public function index(){
        $store_id = I('get.store_id',0);

        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList(0,$store_id); //用户购物车
        #  dump($cartList);die;
        $goods_arr = array(); // 商品ID
        foreach ($cartList as $k => $val) {
            $val = json_decode($val,true);
            $val['goods_fee'] = $val['member_goods_price']*$val['goods_num'];
            $goods_arr[] = $val;
        }
        $cartList = array_group_by($goods_arr, 'suppliers_id');
        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数

        $hot_goods = M('GoodsRed')->where('is_hot=1 and is_on_sale=1 and is_check=1')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();
        foreach ($hot_goods as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $val['midou']       = $midouInfo['midou'];
            $val['midou_money'] = $midouInfo['midou_money'];
            $val['midou_index'] = $midouInfo['midou_index'];

            $hot_goods[$k] = $val;
        }
        $this->assign('hot_goods', $hot_goods);
        $this->assign('userCartGoodsTypeNum', $userCartGoodsTypeNum);
        $this->assign('cartList', $cartList);//购物车列表
        return $this->fetch();
    }

    /**
     * 更新购物车，并返回计算结果
     */
    public function AsyncUpdateCart()
    {
        $cart = input('cart/a', []);
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->AsyncUpdateCart($cart);
        $this->ajaxReturn($result);
    }

    /**
     *  购物车加减
     */
    public function changeNum(){
        $cart = input('cart/a',[]);
        if (empty($cart)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new RedCartLogic();
        $result = $cartLogic->changeNum($cart['id'],$cart['goods_num']);
        $this->ajaxReturn($result);
    }

    /**
     * 删除购物车商品
     */
    public function delete(){
        $cart_ids = input('cart_ids/a',[]);
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if($result !== false){
            $this->ajaxReturn(['status'=>1,'msg'=>'删除成功','result'=>$result]);
        }else{
            $this->ajaxReturn(['status'=>0,'msg'=>'删除失败','result'=>$result]);
        }
    }


    /**
     * ajax 将商品加入购物车
     */
    function ajaxAddCart()
    {
        $goods_id  = I("goods_id/d");  // 商品id
        $goods_num = I("goods_num/d"); // 商品数量
        $item_id   = I("item_id/d");   // 商品规格id
        $store_id  = I("store_id",0);
        if(empty($goods_id)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>'']);
        }
        if(empty($goods_num)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>'']);
        }
        if($goods_num > 200){
            $this->ajaxReturn(['status'=>0,'msg'=>'购买商品数量大于200','result'=>'']);
        }
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id); // 用户ID
        $cartLogic->setGoodsModel($goods_id);  // 设置商品模型
        $cartLogic->setStoreId($store_id);
        if($item_id){
            $cartLogic->setSpecGoodsPriceModel($item_id); // 商品规格
            $cartLogic->setSpecGoodsIdModel($item_id); // 商品规格id
        }
        $cartLogic->setGoodsBuyNum($goods_num); // 商品数量
        $result = $cartLogic->addGoodsToCart(); // 添加购物车
        $this->ajaxReturn($result);
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart2(){

        $goods_id  = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id   = input("item_id/d");  // 商品规格id
        $action    = input("action");     //
        $store_id  = input("get.store_id",0);
        if ($this->user_id == 0){
            $this->error('请先登录', U('Mobile/User/login'));
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
        if(empty($address) && $store_id==0){
            header("Location: ".U('Mobilered/User/add_address',array('source'=>'cart2','goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action)));
            exit;
        }else{
            $this->assign('address',$address);
        }

        $cartLogic   = new RedCartLogic();
        $couponLogic = new RedCouponLogic();
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
                $cartLogic->setSpecGoodsIdModel($item_id); // 商品规格id
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $cartLogic->setStoreId($store_id);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                $this->error($result['msg']);
            }

            $result['result']['buy_goods']['item_id'] = $item_id;
            $cartList['cartList'][0] = $result['result']['buy_goods'];
            $cartGoodsTotalNum = $goods_num;

        }else{
            if ($cartLogic->getUserCartOrderCount() == 0){
                $this->error('你的购物车没有选中商品', 'Cart/index');
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
        }

        // 按供货商 分
        $goods_arr = array();
        foreach ($cartList['cartList'] as $k => $val) {
            if($action != 'buy_now') $val = json_decode($val,true);
            $val['goods_fee'] = $val['member_goods_price']*$val['goods_num'];
            $goods_arr[] = $val;
        }
        $cartList['cartList'] = array_group_by($goods_arr, 'suppliers_id');
        // 获取 供货商物流
        foreach ($cartList['cartList'] as $k => $val) {
            $cartList['cartList'][$k][0]['shippinglist'] = M('Plugin')->where("`type` = 'shipping' and is_default = 1 and suppliers_id = ".$val[0]['suppliers_id']." and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();  // 物流公司
        }

        $cartGoodsList      = get_arr_column($cartList,'goods');
        $cartGoodsId        = get_arr_column($cartGoodsList,'goods_id');
        $cartGoodsCatId     = get_arr_column($cartGoodsList,'cat_id');
        $cartPriceInfo      = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
        $userCouponList     = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId); //用户可用的优惠券列表
        $cartList           = array_merge($cartList,$cartPriceInfo);
        $userCartCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);
        //是否固定米豆
        $config = tpCache('shop_info');
        // 会员米豆余额
        $user_midou = get_user_midou($this->user_id);
        $this->assign('user_midou', $user_midou);
        $this->assign('is_fixedrd', $config['is_fixedrd']);
        $this->assign('userCartCouponList', $userCartCouponList);  //优惠券，用able判断是否可用
        $this->assign('cartGoodsTotalNum', $cartGoodsTotalNum);
        $this->assign('cartList', $cartList['cartList']);          // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo);            //商品优惠总价
        return $this->fetch();
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart21()
    {
        $goods_id  = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id   = input("item_id/d");  // 商品规格id
        $action    = input("action");     // 行为

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
        $cartLogic = new RedCartLogic();
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
        //halt($shippingList);
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

    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){

        if($this->user_id == 0){
            exit(json_encode(array('status'=>-100,'msg'=>"登录超时请重新登录!",'result'=>null))); // 返回结果状态
        }

        $suppliers_id      = I("suppliers_id/a");  // 供货商id
        $address_id        = I("address_id/d",0);    // 收货地址id
        //$shipping_code     = I("shipping_code/a"); // 物流编号 
        $midou             = I("midou/a"); 
        $midou_money       = I("midou_money/a"); 
        $max_midou         = I("max_midou/a"); 
        $midou_rate        = tpCache('shoppingred.midou_rate'); 
        $midou_use_percent = I("midou_use_percent/a"); 
        //$invoice_title   = I('invoice_title'); // 发票
        $coupon_id         = I("coupon_id/d");     // 优惠券id
        $pay_points        = I("pay_points/d",0);  // 使用积分
        $user_money        = I("user_money/f",0);  // 使用余额        
        $user_note         = I("user_note/a");     // 用户留言
        //$paypwd          = I("paypwd",'');     // 支付密码
        //立即购买 才会用到
        $goods_id        = input("goods_id/d");  // 商品id
        $goods_num       = input("goods_num/d"); // 商品数量
        $item_id         = input("item_id/d");   // 商品规格id
        $action          = input("action");      // 立即购买
        $red_envelope_id = input('red_envelope_id/d',0); // 红包
        $store_id        = input('store_id',0); //实体店id
        //$user_money = $user_money ? $user_money : 0;
        if ($action == 'buy_now' && !empty($store_id)) {

            $midous[$suppliers_id['0']][$goods_id][$item_id] = $midou['0'];
            $midou_moneys[$suppliers_id['0']][$goods_id][$item_id] =$midou_money['0'];
            $midou_use_percents[$suppliers_id['0']][$goods_id][$item_id] =tpCache('shoppingred.midou_use_percent');
            $midou             = $midous; 
            $midou_money       = $midou_moneys; 
            $max_midou         = $midous;
            $midou_use_percent         = $midou_use_percents;  
        }
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setStoreId($store_id);
        if(empty($midou) && empty($midou_money)){
            exit(json_encode(array('status'=>-2,'msg'=>'购买米豆与金额有误，请重新下单！','result'=>null))); // 返回结果状态
        }

        if($action == 'buy_now'){

            // 获取商品模型 商品信息
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
                $cartLogic->setSpecGoodsIdModel($item_id); // 商品规格id
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                $this->ajaxReturn($result);
            }
            $result['result']['buy_goods']['item_id'] = $item_id;
            $order_goods[0] = $result['result']['buy_goods'];

        }else{

            $userCartList = $cartLogic->getCartList(1);
            if($userCartList){
                $order_goods = collection($userCartList)->toArray();
            }else{
                exit(json_encode(array('status'=>-2,'msg'=>'你的购物车没有选中商品','result'=>null))); // 返回结果状态
            }


            foreach ($userCartList as $cartKey => $cartVal) {
                if($store_id == 0){
                    if($cartVal->goods_num > $cartVal->limit_num){
                        exit(json_encode(['status' => 0, 'msg' => $cartVal->goods_name.'购买数量不能大于'.$cartVal->limit_num, 'result' => ['limit_num'=>$cartVal->limit_num]]));
                    }
                    if($cartVal->goods->purchase > 0 && $cartVal->goods_num > $cartVal->goods->purchase){
                        exit(json_encode(['status' => 0, 'msg' => $cartVal->goods_name.'购买数量不能大于'.$cartVal->goods->purchase, 'result' => ['limit_num'=>$cartVal->goods->purchase]]));
                    }
                }else{

                    $where['store_id'] = $cartVal->store_id;
                    $where['goods_id'] = $cartVal->goods_id;
                    $where['item_id'] = $cartVal->item_id;
                    $stock = db('store_goods_stock')->where($where)->value('stock');
                    if($cartVal->goods_num > $stock){
                        exit(json_encode(['status' => 0, 'msg' => $cartVal->goods_name.'购买数量不能大于'.$stock, 'result' => ['limit_num'=>$cartVal->limit_num]]));
                    }

                }

            }

        }

        $order_goods_arr = array_group_by($order_goods, 'suppliers_id'); // 分组后的 订单商品 数据

        $address = M('UserAddress')->where("address_id", $address_id)->find();
        // 所有订单总和
        $car_price = array(
            'postFee'           => 0, // 物流费
            'couponFee'         => 0, // 优惠券
            'RedEnvelopeFee'    => 0, // 红包            
            'balance'           => 0, // 使用用户余额
            'pointsFee'         => 0, // 积分支付            
            'payables'          => 0, // 应付金额
            'goodsFee'          => 0, // 商品价格            
            'order_prom_id'     => 0, // 订单优惠活动id
            'order_prom_amount' => 0, // 订单优惠活动优惠了多少钱
            'midouFee'          => 0, // 使用米豆
            'midou_moneyFee'    => 0, // 支付现金部分
            'max_midouFee'      => 0, 
            'tk_cost_price'     => 0, //老王添加的成本价  前台不需要显示出来
            'tk_cost_operating' => 0, //运营成本价  前台不需要显示出来
        );

        $shipping_code = array();
        foreach ($suppliers_id as $key) {

            $plugin_goods_shipping = M('plugin')->where(array('type'=>'shipping','status'=>1,'is_default'=>1,'suppliers_id'=>$key))->field('code')->find();
            if($plugin_goods_shipping) $shipping_code[$key] = $plugin_goods_shipping['code'];
            else $shipping_code[$key] = '';

            $result = calculate_price_red($this->user_id,$order_goods_arr[$key],$shipping_code[$key],0,$address['province'],$address['city'],$address['district'],$pay_points,$user_money,$coupon_id,$red_envelope_id, $midou[$key], $midou_money[$key]);
            if($result['status'] < 0) exit(json_encode($result));
            $store_price[$key] = array(
                'postFee'           => $result['result']['shipping_price'],     // 物流费
                'couponFee'         => $result['result']['coupon_price'],       // 优惠券
                'RedEnvelopeFee'    => $result['result']['red_envelope_price'], // 红包            
                'balance'           => $result['result']['user_money'],         // 使用用户余额
                'pointsFee'         => $result['result']['integral_money'],     // 积分支付            
                'payables'          => number_format($result['result']['order_amount'], 2, '.', ''), // 应付金额
                'goodsFee'          => $result['result']['goods_price'],        // 商品价格            
                'order_prom_id'     => $result['result']['order_prom_id'],      // 订单优惠活动id
                'order_prom_amount' => $result['result']['order_prom_amount'],  // 订单优惠活动优惠了多少钱
                'midouFee'          => $result['result']['order_midou'],              // 使用米豆
                'midou_moneyFee'    => number_format($result['result']['order_midou_money'], 2, '.', ''),      // 支付现金部分
                'max_midouFee'      => $result['result']['order_max_midou'],
                'tk_cost_price'     => $result['result']['tk_cost_price'],      //老王添加的成本价  前台不需要显示出来
                'tk_cost_operating' => $result['result']['tk_cost_operating'],  //运营成本价  前台不需要显示出来
            );
            $car_price['postFee']           += $result['result']['shipping_price'];     // 物流费
            $car_price['couponFee']         += $result['result']['coupon_price'];       // 优惠券
            $car_price['RedEnvelopeFee']    += $result['result']['red_envelope_price']; // 红包
            $car_price['balance']           += $result['result']['user_money'];         // 使用用户余额
            $car_price['pointsFee']         += $result['result']['integral_money'];     // 积分支付
            $car_price['payables']          += number_format($result['result']['order_amount'], 2, '.', ''); // 应付金额
            $car_price['goodsFee']          += $result['result']['goods_price'];        // 商品价格
            $car_price['order_prom_id']     += $result['result']['order_prom_id'];      // 订单优惠活动id
            $car_price['order_prom_amount'] += $result['result']['order_prom_amount'];  // 订单优惠活动优惠了多少钱
            $car_price['midouFee']          += $result['result']['order_midou'];        // 使用米豆
        #    $car_price['midou_moneyFee']    += $result['result']['order_midou_money'];  // 支付现金部分
            $car_price['midou_moneyFee']    =   bcadd($result['result']['order_midou_money'],$car_price['midou_moneyFee'],3);
            $car_price['midou_moneyFee']    =   number_format($car_price['midou_moneyFee'], 2, '.', ''); 
            $car_price['max_midouFee']      += $result['result']['order_max_midou']; 
            $car_price['tk_cost_price']     += $result['result']['tk_cost_price'];      // 订单优惠活动优惠了多少钱
            $car_price['tk_cost_operating'] += $result['result']['tk_cost_operating'];  // 运营成本价
        }


        if(!$address_id && $store_id==0) exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息1','result'=>$car_price))); // 返回结果状态
        //if(!$shipping_code) exit(json_encode(array('status'=>-4,'msg'=>'请选择物流信息','result'=>$car_price))); // 返回结果状态
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $pay_name = '';
            $orderLogic = new RedOrderLogic();
            $orderLogic->setAction($action);
            $orderLogic->setCartList($order_goods_arr);
            $result = $orderLogic->addStoreOrder($this->user_id,$address_id,$suppliers_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$store_price,$user_note,$pay_name, $midou_rate, $midou, $midou_money, $max_midou, $midou_use_percent,$store_id); // 添加订单
            if (!empty($store_id)) {
                $orderids = M("order_red")->where('order_sn',$result['result'])->find();
                $result['order_id']=$orderids['order_id'];
            }
            exit(json_encode($result));
        }

        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price,'result2'=>$store_price); // 返回结果状态
        exit(json_encode($return_arr));  
    }
    /*
     * 订单支付页面
     */
    public function cart4(){

        $order_id    = I('order_id/d');
        $order_sn    = I('order_sn');
        $order_where = "user_id=".$this->user_id;

        if($order_id){
            $order_where .= " AND order_id =".$order_id;
            $order[0] = M('OrderRed')->where($order_where)->find();

            if($order[0]['order_status'] == 3){
                $this->error('该订单已取消',U("Mobilered/Order/order_detail",array('id'=>$order_id)));
            }

            // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
            if($order[0]['pay_status'] == 1){            
                $order_detail_url = U("Mobilered/Order/order_detail",array('id'=>$order[0]['order_id']));
                header("Location: $order_detail_url");
                exit;
            }
            $midous        = $order[0]['midou'];
            $order_amounts = $order[0]['order_amount'];
            $order_num = 1;
        } 

        if($order_sn){
            $order_where .= " AND (order_sn ='".$order_sn."' OR parent_sn='".$order_sn."')";
            $order  = M('OrderRed')->where($order_where)->select();
            $midous = M('OrderRed')->where($order_where)->sum('midou');
            $order_amounts = M('OrderRed')->where($order_where)->sum('order_amount');
            $order_num = count($order);
        } 

        if(empty($order) || empty($this->user_id)){
            $order_order_list = U("Mobile/User/login");
            header("Location: $order_order_list");
            exit;
        }

        $user_midou = get_user_midou($this->user_id);

        if($user_midou < $midous){

            if(empty($order_id)){
                $order_id=db('order_red')->where(["order_sn"=>$order_sn])->value("order_id");
            }
            $param["orderid"]= $order_id;
            $this->request_post(U('Mobilered/Cart/nopayRuturnOrder'),$param);

            $this->error('米豆余额不足');
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

        $orderGoodsPromType = M('order_red_goods')->where('order_id','in',$oids)->getField('prom_type',true);
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
            $payment_where['scene'] = array('in',array('0','1'));
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


        $orderid=[];
        foreach ($order as $row){
            $orderid[]=$row["order_id"];
        }

        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('midous',$midous);
        $this->assign('order_amounts',$order_amounts);
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('orderid',json_encode($orderid));
        $this->assign('paypwd',$this->user['paypwd']);
        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('order_num',$order_num);      
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }


    #余额支付 相关
    #TK
    #2018年5月31日16:46:46
     function balance(){
        ak_get_pays($this->user,1);
    }

    public function payMidou(){

            $order_num = I('order_num/d');   // 订单数量           
            $order_id  = I('order_id/d');    // 订单id 
            $order_sn  = I('order_sn');      // 订单号
            $store_id = I('store_id',0);


            session('red_order_id',$order_id); // 最近支付的一笔订单 id
            session('red_order_sn',$order_sn); // 最近支付的一笔订单 id

            if(!$this->user_id){
                $res['status']  =   0;
                $res['info']    =   '请先登录';
                $this->ajaxReturn($res);
            }
            $paypwd = I('get.paypwd/s');
            if ($this->user['is_lock'] == 1) {
                $res['status']  =   0;
                $res['info']    =   '账号异常已被锁定，不能使用余额支付！';
                exit(json_encode($res));
                // 用户被冻结不能使用余额支付
            }
            if (empty($store_id)) {
                
                if (empty($this->user['paypwd'])) {
                    $res['status']  =   0;
                    $res['info']    =   '请先设置支付密码';
                    exit(json_encode($res));
                }
                if (empty($paypwd)) {
                    $res['status']  =   0;
                    $res['info']    =   '请输入支付密码';
                    exit(json_encode($res));
                }
                if (encrypt($paypwd) !== $this->user['paypwd']) {
                    $res['status']  =   0;
                    $res['info']    =   '支付密码错误';
                    exit(json_encode($res));
                }
            }

            if($order_num == 1){
                $order[0] = M('order_red')->where(['order_id' => $order_id])->find();
                if($order[0]['pay_status'] == 1){
                    $res['status']  =   0;
                    $res['info']    =   '此订单，已完成支付!';
                    exit(json_encode($res));
                }
            } else {
                $order = M('order_red')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();
            }


            if(empty($order) || $order[0]['order_status'] > 1){
                $res['status']  =   0;
                $res['info']    =   '非法操作!';
                exit(json_encode($res));
            }
            foreach ($order as $key => $value) {
                $midou_money += $value['midou_money'];
                $midou +=$value['midou'];
            }

            if($this->user['midou'] < $midou){
                $res['status']  =   0;
                $res['info']    =   '米豆余额不足!';
                exit(json_encode($res));
            }
           
            $n = 0;
            foreach ($order as $k => $val) {
                update_pay_status_red($val['order_sn'],['order_num'=>$order_num],$store_id);
             //   update_one_pay_status_red($val['order_sn'],['order_num'=>$order_num]);
                $n++;
            }

            if($n == $order_num){
               /* $this->assign('total_amount', 0);
                $this->success('支付成功！',U("Mobilered/Order/order_list"));*/

                $res['status']  =   1;
                $res['info']    =   '支付成功！';
                exit(json_encode($res));
            } else {
            #    return $this->fetch('Payment/error');
                $res['status']  =   0;
                $res['info']    =   '支付失败，如已扣除相应米豆，请联系管理员找回米豆';
                exit(json_encode($res));
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
        $act_id     = I('act_id/d');
        $goods_num  = I('goods_num/d');
        $address_id = I('address_id/d');
        if(empty($act_id)){
            $this->error('没有选择需要购买商品');
        }
        if(empty($goods_num)){
            $this->error('购买商品数量不能为0', U('Mobilered/Activity/pre_sell', array('act_id' => $act_id)));
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
        $pre_sell_info = M('goods_red_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        if(empty($pre_sell_info)){
            $this->error('商品不存在或已下架',U('Mobile/Activity/pre_sell_list'));
        }
        $pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
        if ($pre_sell_info['act_count'] + $goods_num > $pre_sell_info['restrict_amount']) {
            $buy_num = $pre_sell_info['restrict_amount'] - $pre_sell_info['act_count'];
            $this->error('预售商品库存不足，还剩下' . $buy_num . '件', U('Mobile/Activity/pre_sell', array('id' => $act_id)));
        }
        $goodsActivityLogic = new RedGoodsActivityLogic();
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
                exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息2','result'=>null))); // 返回结果状态
            }
            if(empty($shipping_code)){
                exit(json_encode(array('status'=>-4,'msg'=>'请选择物流信息','result'=>null))); // 返回结果状态
            }
            $orderLogic = new RedOrderLogic();
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
        $goods_id  = input('goods_id/d');
        $item_id   = input('item_id/d');
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
        $goods = GoodsRed::get($goods_id);
        if(empty($goods)){
            $this->ajaxReturn(['status'=>0,'msg'=>'该商品不存在']);
        }
        $Integral = new RedIntegralLogic();
        if(!empty($item_id)){
            $specGoodsPrice = SpecRedGoodsPrice::get($item_id);
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
        $goods_id   = input('goods_id/d');
        $item_id    = input('item_id/d');
        $goods_num  = input('goods_num/d');
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
        $Goods = new GoodsRed();
        $goods = $Goods->where(['goods_id'=>$goods_id])->find();
        if(empty($goods)){
            $this->error('该商品不存在');
        }
        if (empty($item_id)) {
            $goods_spec_list = SpecRedGoodsPrice::all(['goods_id' => $goods_id]);
            if (count($goods_spec_list) > 0) {
                $this->error('请传递规格参数');
            }
            $goods_price = $goods['shop_price'];
            //没有规格
        } else {
            //有规格
            $specGoodsPrice = SpecRedGoodsPrice::get(['item_id'=>$item_id,'goods_id'=>$goods_id]);
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
        $goods_id      = input('goods_id/d');
        $item_id       = input('item_id/d');
        $goods_num     = input('goods_num/d');
        $address_id    = input("address_id/d"); //  收货地址id
        $shipping_code = input("shipping_code/s"); //  物流编号
        $user_note     = input('user_note'); // 给卖家留言
        $invoice_title = input('invoice_title'); // 发票
        $user_money    = input("user_money/f", 0); //  使用余额
        $pwd           = input('pwd');
        $user_money    = $user_money ? $user_money : 0;
        if (empty($address_id)){
            $this->ajaxReturn(['status' => -3, 'msg' => '请先填写收货人信息3', 'result' => null]);
        }
        if(empty($shipping_code)){
            $this->ajaxReturn(['status' => -4, 'msg' => '请选择物流信息', 'result' => null]);
        }
        $address = Db::name('user_address')->where("address_id", $address_id)->find();
        if(empty($address)){
            $this->ajaxReturn(['status' => -3, 'msg' => '请先填写收货人信息4', 'result' => null]);
        }
        $Goods = new GoodsRed();
        $goods = $Goods::get($goods_id);
        $Integral = new RedIntegralLogic();
        $Integral->setUser($this->user);
        $Integral->setGoods($goods);
        if($item_id){
            $specGoodsPrice = SpecRedGoodsPrice::get($item_id);
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
            'postFee'   => $result['result']['shipping_price'], // 物流费
            'balance'   => $result['result']['user_money'], // 使用用户余额
            'payables'  => number_format($result['result']['order_amount'], 2, '.', ''), // 订单总额 减去 积分 减去余额 减去优惠券 优惠活动
            'pointsFee' => $result['result']['integral_money'], // 积分抵扣的金额
            'points'    => $result['result']['total_integral'], // 积分支付
            'goodsFee'  => $result['result']['goods_price'],// 总商品价格
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
            $map['user_id'] = $this->user_id;
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


    /**
     * [线下订单未支付取消订单]
     * @author 王牧田
     * @date 2018-11-22
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function nopayRuturnOrder(){

        $orderid = $_POST['orderid'];

        file_put_contents("./public/orderid.txt",json_encode($_POST));
        $where['or.order_id'] = ["in",$orderid];
        $where['or.pay_status'] = 0;   //未支付
        $where['or.is_store'] = 1;     //是线下订单
        $where['or.store_id'] = ['neq','0'];  //实体店id 不是0
        $where['or.order_status'] = ['in',[0,1]]; //订单状态是 待确认 和 已确认

        //查询所有
        $orderRedGoods = db('order_red_goods org')
            ->join("__ORDER_RED__ or","org.order_id = or.order_id","left")
            ->join("__SPEC_RED_GOODS_PRICE__ srgp","srgp.key = org.spec_key","left")
            ->field("or.order_id,srgp.item_id,org.goods_id,or.store_id,org.goods_num")
            ->where($where)
            ->order("or.order_id desc")
            ->select();


        //循环查询返回库存
        foreach ($orderRedGoods as $org){
            $orgwhere['item_id'] = empty($org['item_id'])?0:$org['item_id'];
            $orgwhere['goods_id'] = $org['goods_id'];
            $orgwhere['store_id'] = $org['store_id'];
            db('store_goods_stock')->where($orgwhere)->setInc('stock',$org['goods_num']);
        }

        //订单取消
        $reslt = db('order_red')->alias('or')->where($where)->save(["order_status"=>3]);
        return json($reslt);
    }


    /**
     * 模拟post进行url请求
     * @param string $url
     * @param string $param
     */
    function request_post($url = '', $param = []) {
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $u =  $http_type . $_SERVER['HTTP_HOST'];
        $url = $u.$url;
        if (empty($url) || empty($param)) {
            return false;
        }

        $o = "";
        foreach ($param as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);

        //$postUrl = $url;
        //$curlPost = $param;
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$url);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);

        return $data;
    }



}
