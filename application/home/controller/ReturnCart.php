<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 *
 */ 
 
namespace app\home\controller; 
use app\common\logic\UserAddressLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\CouponLogic;
use app\common\logic\IntegralLogic;
use app\common\logic\CartLogic;
use app\common\logic\OrderLogic;
use app\common\logic\PickupLogic;
use app\common\model\SpecGoodsPrice;
use app\common\model\Goods;
use app\common\model\Order;
use think\Db;
class ReturnCart extends ReturnBase {

    public $cartLogic; // 购物车逻辑操作类
    public $user_id = 0;
    public $user = array();    
    /**
     * 初始化函数
     */
    public function _initialize()
    {
        parent::_initialize();
        header("Content-type: text/html; charset=utf-8");
        $this->cartLogic = new CartLogic();
        if (session('?user')) { //判断会员登录
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            // 给用户计算会员价 登录前后不一样   屏蔽了
        #    dump($user);die;
            if ($user) {
            }
        }
    }

    public function index(){
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getReturnCartList(); //用户购物车
    #    dump($cartList);die;
        $goods_arr = array(); // 商品ID
        foreach ($cartList as $k => $val) {
            $val = json_decode($val,true);
            $val['goods_fee'] = $val['member_goods_price']*$val['goods_num'];
            $goods_arr[] = $val;
        }
        $cartList = array_group_by($goods_arr, 'suppliers_id');
    #    dump($cartList);die;
        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum(1);//获取用户购物车商品总数
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
        $cartLogic = new CartLogic();
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
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart['id'],$cart['goods_num']);
        $this->ajaxReturn($result);
    }
    /**
     * 删除购物车商品
     */
    public function delete(){
        $cart_ids = input('cart_ids/a',[]);
        $cartLogic = new CartLogic();
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
        $goods_id     = I("goods_id/d");     // 商品id
        $goods_num    = I("goods_num/d");    // 商品数量
        $item_id      = I("item_id/d");      // 商品规格id
        if(empty($goods_id)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>'']);
        }
        if(empty($goods_num)){
            $this->ajaxReturn(['status'=>-2,'msg'=>'购买商品数量不能为0','result'=>'']);
        }
        if($goods_num > 200){
            $this->ajaxReturn(['status'=>-3,'msg'=>'购买商品数量大于200','result'=>'']);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id); // 用户ID
        $cartLogic->setGoodsModel($goods_id);  // 设置商品模型
        if($item_id){
            $cartLogic->setSpecGoodsPriceModel($item_id); // 商品规格
        }
        $cartLogic->setGoodsBuyNum($goods_num); // 商品数量
        $result = $cartLogic->addReturnGoodsToCart(); // 添加购物车      
        $this->ajaxReturn($result);
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart2(){
        $goods_id  = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id   = input("item_id/d");  // 商品规格id
        $action    = input("action");     // 行为
        if ($this->user_id == 0){  // 判断会员登录
            $this->error('请先登录', U('Home/User/login'));
        }
        $cartLogic   = new CartLogic();
        $couponLogic = new CouponLogic();
        $cartLogic->setUserId($this->user_id); // 设置用户ID
        //立即购买
        if($action == 'buy_now'){
            if(empty($goods_id)){ 
                $this->error('请选择要购买的商品');
            }
            $goods_id;
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
            $cartList['cartList'][0] = $result['result']['buy_goods'];
            $cartGoodsTotalNum = $goods_num;

        }else{
            if ($cartLogic->getUserCartOrderCount(1) == 0){
                $this->error('你的购物车没有选中商品', 'Cart/index');
            }
            $cartList['cartList'] = $cartLogic->getReturnCartList(1); // 获取用户选中的购物车商品
        }
        
        // 按供货商 分
        $goods_arr = array();
        foreach ($cartList['cartList'] as $k => $val) {
            if($action != 'buy_now') $val = json_decode($val,true);
            else $val['goods'] = json_decode($val['goods'],true);
            $val['goods_fee'] = $val['member_goods_price']*$val['goods_num'];
            $goods_arr[] = $val;
        }

        //$cartList['cartList'] = array_group_by($goods_arr, 'suppliers_id');
        $cartList['cartList'] = $goods_arr;

        // 获取 供货商物流
        foreach ($cartList['cartList'] as $k => $val) {
            $cartList['cartList'][$k]['shippinglist'] = M('Plugin')->where("`type` = 'shipping' and is_default = 1 and suppliers_id = ".$val['suppliers_id']." and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();  // 物流公司
        }

        $cartGoodsList      = get_arr_column($cartList,'goods');
        $cartGoodsId        = get_arr_column($cartGoodsList,'goods_id');
        $cartGoodsCatId     = get_arr_column($cartGoodsList,'cat_id');
        $cartPriceInfo      = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
        $userCouponList     = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId); //用户可用的优惠券列表
        $cartList           = array_merge($cartList,$cartPriceInfo);
        $userCartCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);
        $this->assign('userCartCouponList', $userCartCouponList);  //优惠券，用able判断是否可用
        $this->assign('cartGoodsTotalNum', $cartGoodsTotalNum);
        $this->assign('cartList', $cartList['cartList']);          // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo);            //商品优惠总价
        return $this->fetch();
    }

   
    /*
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress(){
        $address_list = M('UserAddress')->where(['user_id'=>$this->user_id,'is_pickup'=>0])->select();
        if($address_list){
        	$area_id = array();
        	foreach ($address_list as $val){
        		$area_id[] = $val['province'];
                        $area_id[] = $val['city'];
                        $area_id[] = $val['district'];
                        $area_id[] = $val['twon'];                        
        	}    
                $area_id = array_filter($area_id);
        	$area_id = implode(',', $area_id);
        	$regionList = M('region')->where("id", "in", $area_id)->getField('id,name');
        	$this->assign('regionList', $regionList);
        }
        $address_where['is_default'] = 1;
        $c = M('UserAddress')->where(['user_id'=>$this->user_id,'is_default'=>1,'is_pickup'=>0])->count(); // 看看有没默认收货地址
        if((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }
    /**
     * @author dyr
     * @time 2016.08.22
     * 获取自提点信息
     */
    public function ajaxPickup()
    {
        $province_id = I('province_id/d');
        $city_id = I('city_id/d');
        $district_id = I('district_id/d');
        if (empty($province_id) || empty($city_id) || empty($district_id)) {
            exit("<script>alert('参数错误');</script>");
        }
        $user_address = new UserAddressLogic();
        $address_list = $user_address->getUserPickup($this->user_id);
        $pickup = new PickupLogic();
        $pickup_list = $pickup->getPickupItemByPCD($province_id, $city_id, $district_id);
        $this->assign('pickup_list', $pickup_list);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_pickup');
    }

    /**
     * @author dyr
     * @time 2016.08.22
     * 更换自提点
     */
    public function replace_pickup()
    {
        $province_id  = I('get.province_id/d');
        $city_id      = I('get.city_id/d');
        $district_id  = I('get.district_id/d');
        $region_model = M('region');
        $call_back    = I('get.call_back');
        if (IS_POST) {
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功
        }
        $address = array('province' => $province_id, 'city' => $city_id, 'district' => $district_id);
        $p = $region_model->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = $region_model->where(array('parent_id' => $province_id, 'level' => 2))->select();
        $d = $region_model->where(array('parent_id' => $city_id, 'level' => 3))->select();
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        $this->assign('call_back', $call_back);
        return $this->fetch();
    }

    /**
     * @author dyr
     * @time 2016.08.22
     * 更换自提点
     */
    public function ajax_PickupPoint()
    {
        $province_id = I('province_id/d');
        $city_id = I('city_id/d');
        $district_id = I('district_id/d');
        $pick_up_model = new PickupLogic();
        $pick_up_list = $pick_up_model->getPickupListByPCD($province_id,$city_id,$district_id);
        exit(json_encode($pick_up_list));
    }


    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){
        if($this->user_id == 0){
            exit(json_encode(array('status'=>-100,'msg'=>"登录超时请重新登录!",'result'=>null))); // 返回结果状态
        }

        $car_id          = I("car_id/a");        // 供货商id
        $suppliers_id    = I("suppliers_id/a");  // 供货商id
        $address_id      = I("address_id/d");    // 收货地址id
        //$shipping_code   = I("shipping_code/a"); // 物流编号  
        //$invoice_title   = I('invoice_title'); // 发票
        $coupon_id       = I("coupon_id/d");     // 优惠券id
        $pay_points      = I("pay_points/d",0);  // 使用积分
        $user_money      = I("user_money/f",0);  // 使用余额        
        $user_note       = I("user_note/a");     // 用户留言
        //$paypwd          = I("paypwd",'');     // 支付密码
        //立即购买 才会用到
        $goods_id        = input("goods_id/d");  // 商品id
        $goods_num       = input("goods_num/d"); // 商品数量
        $item_id         = input("item_id/d");   // 商品规格id
        $action          = input("action");      // 立即购买

        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id); // 设置用户ID

        if($action == 'buy_now'){
            // 获取商品模型 商品信息
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                $this->ajaxReturn($result);
            }
            $order_goods[0] = $result['result']['buy_goods'];
            $car_id = $suppliers_id;
            $order_goods_arr = array_group_by($order_goods, 'suppliers_id'); // 分组后的 订单商品 数据
        }else{
            $userCartList = $cartLogic->getReturnCartList(1); // 获取已选择的购物车
            if($userCartList){
                $order_goods = collection($userCartList)->toArray();
            }else{
                exit(json_encode(array('status'=>-2,'msg'=>'你的购物车没有选中商品','result'=>null))); // 返回结果状态
            }
            foreach ($userCartList as $cartKey => $cartVal) {
                if($cartVal->goods_num > $cartVal->limit_num){
                    exit(json_encode(['status' => 0, 'msg' => $cartVal->goods_name.'购买数量不能大于'.$cartVal->limit_num, 'result' => ['limit_num'=>$cartVal->limit_num]]));
                }
            }
            $order_goods_arr = array_group_by($order_goods, 'id'); // 分组后的 订单商品 数据
        }

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
            'tk_cost_price'     => 0, //老王添加的成本价  前台不需要显示出来
            'tk_cost_operating' => 0, //运营成本价  前台不需要显示出来
        );

        $shipping_code = array();

        foreach ($car_id as $key) {

            $sid = $suppliers_id[$key]; 
            $plugin_goods_shipping = M('plugin')->where(array('type'=>'shipping','status'=>1,'is_default'=>1,'suppliers_id'=>$sid))->field('code')->find();
            if($plugin_goods_shipping) $shipping_code[$key] = $plugin_goods_shipping['code'];
            else $shipping_code[$key] = '';

            $result = calculate_price($this->user_id,$order_goods_arr[$key],$shipping_code[$key],0,$address['province'],$address['city'],$address['district'],$pay_points,$user_money,$coupon_id,$red_envelope_id);
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
                'tk_cost_price'     => $result['result']['tk_cost_price'],      //老王添加的成本价  前台不需要显示出来
                'tk_cost_operating' => $result['result']['tk_cost_operating'],  //运营成本价  前台不需要显示出来
                'is_allreturn'      => $order_goods_arr[$key][0]['is_allreturn'],
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
            $car_price['tk_cost_price']     += $result['result']['tk_cost_price'];      // 成本价
            $car_price['tk_cost_operating'] += $result['result']['tk_cost_operating'];  // 运营成本价
        }

        if(!$address_id) exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息','result'=>$car_price))); // 返回结果状态
        //if(!$shipping_code) exit(json_encode(array('status'=>-4,'msg'=>'请选择物流信息','result'=>$car_price))); // 返回结果状态
        
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $pay_name = '';
            $orderLogic = new OrderLogic();
            $orderLogic->setAction($action);
            $orderLogic->setCartList($order_goods_arr);
            if($action == 'buy_now'){
                $result = $orderLogic->addStoreOrder($this->user_id,$address_id,$suppliers_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$store_price,$user_note,$pay_name,1); // 添加订单  
            } else {
                $result = $orderLogic->addReturnStoreOrder($this->user_id,$address_id,$car_id,$suppliers_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$store_price,$user_note,$pay_name); // 添加订单                
            }
            exit(json_encode($result));            
        }

        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price,'result2'=>$store_price); // 返回结果状态
        exit(json_encode($return_arr));  
        exit();        
    }	
    /**
     * ajax 获取订单商品价格 或者提交 订单
	 * 已经用心方法 这个方法 cart9  准备作废
     */
   
    /*
     * 订单支付页面
     */
    public function cart4(){

        $order_id    = I('order_id/d');
        $order_sn    = I('order_sn');
        $order_where = "user_id = ".$this->user_id;
        if($order_id){
            $order_where .= " AND is_allreturn = 1 AND order_id =".$order_id;
            $order[0] = M('Order')->where($order_where)->find();

            if($order[0]['order_status'] == 3){
                $this->error('该订单已取消',U("Home/Order/order_detail",array('id'=>$order_id)));
            }

            // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
            if($order[0]['pay_status'] == 1){            
                $order_detail_url = U("Home/Order/order_detail",array('id'=>$order[0]['order_id']));
                header("Location: $order_detail_url");
                exit;
            }

            $order_num = 1;
        } 
        
        if($order_sn){
            $order_where .= " AND is_allreturn = 1 AND order_sn ='".$order_sn."' OR parent_sn='".$order_sn."'";
            $order = M('Order')->where($order_where)->select();

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
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where('order_id','in',$oids)->getField('prom_type',true);
        $no_cod_order_prom_type = ['4,5'];//预售订单，虚拟订单不支持货到付款
        foreach ($order as $k => $val) {
            if(in_array($val['order_prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                $payment_where['code'] = array('neq','cod');
            }
        }

        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');
        
        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);            
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);        
            }                
        }                
        
        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片        
        $this->assign('paymentList',$paymentList);        
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('bankCodeList',$bankCodeList); 
        $this->assign('order_num',$order_num);         
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));

        return $this->fetch();
    }
 
    
    //ajax 请求购物车列表
    public function header_cart_list()
    {
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getReturnCartList();
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList);
    	$this->assign('cartList', $cartList); // 购物车的商品
    	$this->assign('cartPriceInfo', $cartPriceInfo); // 总计
        $template = I('template','header_cart_list');    	 
        return $this->fetch($template);		 
    }

    /**
     * 预售商品下单流程
     */
    public function pre_sell_cart()
    {
        $act_id = I('act_id/d');
        $goods_num = I('goods_num/d');
        if(empty($act_id)){
            $this->error('没有选择需要购买商品');
        }
        if(empty($goods_num)){
            $this->error('购买商品数量不能为0', U('Home/Activity/pre_sell', array('act_id' => $act_id)));
        }
        if($this->user_id == 0){
            $this->error('请先登录',U('Home/User/login'));
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
        $shippingList = Db::name('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();// 物流公司
        $point_rate = tpCache('shopping.point_rate');
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
            if($this->user['pay_points'] >= $car_price['points'] || $user_money>0){
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

    /**
     * 优惠券兑换
     */
    public function cartCouponExchange()
    {
        $coupon_code = input('coupon_code');
        $couponLogic = new CouponLogic;
        $return = $couponLogic->exchangeCoupon($this->user_id, $coupon_code);
        $this->ajaxReturn($return);
    }

    #余额支付 相关
    #TK
    #2018年5月31日16:46:46
    function balance(){
        ak_get_pays($this->user);
        /*$paypwd = I('post.paypsw/s');
        if ($this->user['is_lock'] == 1) {
            $res['status']  =   0;
            $res['info']    =   '账号异常已被锁定，不能使用余额支付！';
            $this->ajaxReturn($res);
            // 用户被冻结不能使用余额支付
        }
        if (empty($this->user['paypwd'])) {
            $res['status']  =   0;
            $res['info']    =   '请先设置支付密码';
            $this->ajaxReturn($res);
        }
        if (empty($paypwd)) {
            $res['status']  =   0;
            $res['info']    =   '请输入支付密码';
            $this->ajaxReturn($res);
        }
        if (encrypt($paypwd) !== $this->user['paypwd']) {
            $res['status']  =   0;
            $res['info']    =   '支付密码错误';
            $this->ajaxReturn($res);
        }
        if($order_id = I('order_id/d',0)){
            $where['order_id']  =   ['eq',$order_id];
            $order_sn = db('order')->where($where)->getField('order_sn');
            $where_or['parent_sn']    =   ['eq',$order_sn];
            $order_list = db('order')->where($where)->whereOr($where_or)->select();
            foreach ($order_list as $key => $value) {
                if($value['pay_status'] == 1){
                    $res['status']  =   1;
                    $res['info']    =   '该订单已经支付';
                    $this->ajaxReturn($res);
                }
                $user_money += $value['order_amount'];
            }
            #$user_money
            $user = M('users')->where("user_id", $this->user['user_id'])->find();// 找出这个用户
            if ($user_money && ($user_money > $user['user_money'])){
                $res['status']  =   0;
                $res['info']    =   "你的账户可用余额为:" . tk_money_format($user['user_money']);
                $this->ajaxReturn($res);
            }
            foreach ($order_list as $key => $value) {
                // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
                if(tpCache('shopping.reduce') == 2) {
                    if ($value['order_prom_type'] == 6) {
                        $team = \app\common\model\TeamActivity::get($value['order_prom_id']);
                        if ($team['team_type'] != 2) {
                            minus_stock($value);
                        }
                    } else {
                        minus_stock($value);
                    }
                }
                //用户支付, 发送短信给商家

                accountLog($this->user_id,($value['order_amount'] * -1),0,0,"现金商城下单消费",0,0,$value['order_id'],$value['order_sn']);
                $order_update_sql[] =   ['order_id'=>$value['order_id'],'pay_time'=>NOW_TIME,'pay_status'=>1];
            }
            model('order')->saveAll($order_update_sql);
            $res['status']  =   1;
            $res['info']    =   '余额支付成功！';
            $this->ajaxReturn($res);
        }*/
    }

}