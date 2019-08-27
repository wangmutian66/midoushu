<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\appletred\controller;
use app\common\logic\RedCartLogic;
use app\common\logic\RedGoodsActivityLogic;
use app\common\logic\RedCouponLogic;
use app\common\logic\RedOrderLogic;
use app\common\model\GoodsRed;
use app\common\model\SpecRedGoodsPrice;
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
        $this->cartLogic = new RedCartLogic();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
        }else{
            $this->user_id = $this->request->param('user_id');
            $this->token = $this->request->param('token');
         
            $isUsers = M('users')->where(['user_id'=>$this->user_id , 'token'=>$this->token])->find();
            if(!$isUsers){
                exit(formt('',201,'用户不存在'));
            }
           
        }
        
    }

    public function index(){
        
        if ($this->user_id == 0 ||  $this->token == ''){
            exit(formt('',201,'请登录'));
        }
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList(); //用户购物车
       
        $goods_arr = array(); // 商品ID
        foreach ($cartList as $k => $val) {
           
            $goods_arr[$k]['id'] = $val['id'];
            $goods_arr[$k]['user_id'] = $val['user_id'];
            $goods_arr[$k]['goods_id'] = $val['goods_id'];
            $goods_arr[$k]['goods_name'] = $val['goods_name'];
            $goods_arr[$k]['goods_price'] = $val['goods_price'];
            $goods_arr[$k]['selected'] = $val['selected'];
            $goods_arr[$k]['goods_num'] = $val['goods_num'];
           
           
            if (strstr(goods_thum_images($val['goods_id'],400,400,'red'),'http')) {
                     $goods_arr[$k]['original_img']=goods_thum_images($val['goods_id'],400,400,'red');
                }else{
                     $goods_arr[$k]['original_img']=URL.goods_thum_images($val['goods_id'],400,400,'red');
                }
            $goods_arr[$k]['spec_key_name'] = $val['spec_key_name'];
            $goods_arr[$k]['store_count'] = $val['goods']['store_count'];
            $midouInfo = getMidou($val['goods_id']);
            $goods_arr[$k]['midou']       = $midouInfo['midou'];
            $goods_arr[$k]['midou_money'] = $midouInfo['midou_money'];
            $goods_arr[$k]['midou_index'] = $midouInfo['midou_index'];
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
        $counts = M('GoodsRed')->where('is_hot=1 and is_on_sale=1 and is_check=1')->count();
        $hot_goods = M('GoodsRed')->where('is_hot=1 and is_on_sale=1 and is_check=1')->page($p,$rows)->order('on_time  desc')->select();
        $arr_hot_goods = array();
        foreach ($hot_goods as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
          
            $arr_hot_goods[$k]['goods_id'] = $val['goods_id'];
            $arr_hot_goods[$k]['goods_name'] = $val['goods_name'];
            $arr_hot_goods[$k]['goods_price'] = $val['shop_price'];
           
            if (strstr(goods_thum_images($val['goods_id'],400,400,'red'),'http')) {
                     $arr_hot_goods[$k]['original_img']=goods_thum_images($val['goods_id'],400,400,'red');
                }else{
                     $arr_hot_goods[$k]['original_img']=URL.goods_thum_images($val['goods_id'],400,400,'red');
                }
          
            $arr_hot_goods[$k]['midou']       = $midouInfo['midou'];
            $arr_hot_goods[$k]['midou_money'] = $midouInfo['midou_money'];
            $arr_hot_goods[$k]['midou_index'] = $midouInfo['midou_index'];
        }
        $arr_hot_goods['p'] = $p;
        $arr_hot_goods['rows'] = $rows;
        $arr_hot_goods['count'] = $counts;
        return formt($arr_hot_goods);
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
        $cartLogic = new RedCartLogic();
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
        $cartLogic = new RedCartLogic();
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
     * 删除购物车商品
     */
    public function delete(){
        $cart_id= $this->request->param('id');
        if ($cart_id == 0 ||  empty($cart_id)){
            exit(formt('',201,'参数错误'));
        }
        $cart_ids[0] = $cart_id;
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if($result !== false){
            exit(formt('','200','删除成功'));
        }else{
            exit(formt('',201,'删除失败'));
        }
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
     * 将商品加入购物车
     */
    function AddCart()
    {   
        $user_id=I('user_id/d');
        $token=I('token');
        if ($user_id == 0 ||  $token == ''){
            exit(formt('',201,'请登录'));
        }
        $goods_id  = $this->request->param('goods_id/d');  // 商品id
        $goods_num = $this->request->param('goods_num/d'); // 商品数量
        $item_id   = $this->request->param('item_id/d');   // 商品规格id
        if(empty($goods_id)){
            exit(formt('','201','请选择要购买的商品'));
        }
        if(empty($goods_num)){
            exit(formt('','201','购买商品数量不能为0'));
        }
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($user_id);
        $cartLogic->setGoodsModel($goods_id);
        if($item_id){
            $cartLogic->setSpecGoodsPriceModel($item_id);
            $cartLogic->setSpecGoodsIdModel($item_id); // 商品规格id
        }
        $cartLogic->setGoodsBuyNum($goods_num);
        $result = $cartLogic->addGoodsToCart();
        if($result){
            return formt($result);
        }
    }






    /**
     * 购物车第二步确定页面
     */
    public function cartSure(){
       
        $user_id=I('user_id/d');
        $token=I('token');
        if ($user_id == 0 ||  $token == ''){
            exit(formt('',201,'请登录'));
        }
        $cartIds  = $this->request->param("cartIds/a"); // 购物车id
        $goods_id  = $this->request->param("goods_id/d"); // 商品id
        $goods_num = $this->request->param("goods_num/d");// 商品数量
        $item_id   = $this->request->param("item_id/a");  // 商品规格id
        $action    = $this->request->param("action");     // 行为
      
        $cartLogic   = new RedCartLogic();
        $couponLogic = new RedCouponLogic();
        $cartLogic->setUserId($user_id);
        //立即购买
        if($action == 'buy_now'){
            if(empty($goods_id)){
                exit(formt('','201','请选择要购买的商品'));
            }
            if(empty($goods_num)){
                exit(formt('','201','购买商品数量不能为0'));
            }
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
               
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                exit(formt('','201',$result['msg']));
            }

            $result['result']['buy_goods']['item_id'] = $item_id;

            $cartList['cartList'][0] = $result['result']['buy_goods'];
            $cartGoodsTotalNum = $goods_num;

        }else{
            if ($cartLogic->getUserCartOrderCount() == 0){
                exit(formt('','201','你的购物车没有选中商品'));
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
        }
        // 按供货商 分
        $goods_arr = array();
        foreach ($cartList['cartList'] as $k => $val) {
            if($action != 'buy_now') $val = json_decode($val,true);
            $val['goods_fee'] = $val['member_goods_price']*$val['goods_num'];
            if(empty($val['goods']['original_img'])){
                $val['goods']['original_img'] = URL."/public/images/icon_goods_thumb_empty_300.png";
            }
            $midouInfo = getMidou($val['goods_id']);
            $goods_arr[$k]['midou']       = $midouInfo['midou'];
            $goods_arr[$k]['midou_money'] = $midouInfo['midou_money'];
            $goods_arr[$k]['midou_index'] = $midouInfo['midou_index'];
            $goods_arr[$k]['id'] = $val['id'];
            $goods_arr[$k]['user_id'] = $val['user_id'];
            $goods_arr[$k]['goods_id'] = $val['goods_id'];
            $goods_arr[$k]['goods_name'] = $val['goods_name'];
            $goods_arr[$k]['goods_price'] = $val['goods_price'];
            $goods_arr[$k]['selected'] = $val['selected'];
            $goods_arr[$k]['goods_num'] = $val['goods_num'];
            $goods_arr[$k]['is_allreturn'] = $val['is_allreturn'];
           
             if (strstr(goods_thum_images($val['goods_id'],400,400,'red'),'http')) {
                     $goods_arr[$k]['original_img']=goods_thum_images($val['goods_id'],400,400,'red');
                }else{
                     $goods_arr[$k]['original_img']=URL.goods_thum_images($val['goods_id'],400,400,'red');
                }
            $goods_arr[$k]['spec_key_name'] = $val['spec_key_name'];
            $goods_arr[$k]['store_count'] = $val['goods']['store_count'];
            $goods_arr[$k]['suppliers_id'] = $val['goods']['suppliers_id'];
            $goods_arr[$k]['shippinglist'] =  M('Plugin')->where("`type` = 'shipping' and is_default = 1 and suppliers_id = ".$val['suppliers_id'])->field('name,desc')->cache(true,TPSHOP_CACHE_TIME)->select();  // 物流公司
        }
       
        return formt($goods_arr);
        
    }


    public function cartOrder(){

        if($this->user_id == 0){
            exit(formt('','201','登录超时请重新登录'));
        }
        $suppliers_id  = $this->request->param("suppliers_id/a");  // 供货商id
        $address_id    = $this->request->param("address_id/d");    // 收货地址id
         $action        = $this->request->param("action");      // 立即购买
         $goods_id      = $this->request->param("goods_id/d");  // 商品id
        $goods_num     = $this->request->param("goods_num/d"); // 商品数量
        $item_id       = $this->request->param("item_id/d");   // 商品规格id
         $midoumoney  = $this->request->param("midou/a");  // 供货商id
       
        if ($action != 'buy_now') {
            foreach ($suppliers_id as $key => $value) {
                // dump($value);
                $midou[$value] = midoucart($value,$this->user_id,'midou');
                $midou_money[$value] = midoucart($value,$this->user_id,'midou_money');
                $midou_use_percent[$value] = midoucart($value,$this->user_id,'midou_use_percent');

            }
        }else{

            foreach ($suppliers_id as $key => $value) {
                $midou[$value][$goods_id][$item_id] = $midoumoney;
                $midou_money[$value][$goods_id][$item_id] =$midoumoney;
                $midou_use_percent[$value][$goods_id][$item_id] =tpCache('shoppingred.midou_use_percent');

            }

        }
      
        $midou             = $midou; 
        $midou_money       = $midou_money; 
        $max_midou         = $midou; 
        $midou_rate        = tpCache('shoppingred.midou_rate'); 
        $midou_use_percent = $midou_use_percent; 
        //$shipping_code = I("shipping_code/a"); // 物流编号
        //$invoice_title = I('invoice_title');   // 发票
        $coupon_id     = $this->request->param("coupon_id/d");     // 优惠券id
        $pay_points    = $this->request->param("pay_points/d",0);  // 使用积分
        $user_money    = $this->request->param("user_money/f",0);  // 使用余额
        $user_note     = $this->request->param("user_note/a");     // 买家留言
        $act           = $this->request->param("act/s");
        //$paypwd        = I("paypwd",'');       // 支付密码
        //立即购买 才会用到
        
       
        //$user_money = $user_money ? $user_money : 0;
        // dump($midou);die();
        $cartLogic = new RedCartLogic();
        $cartLogic->setUserId($this->user_id);
        if(empty($midou) && empty($midou_money)){
            exit(json_encode(array('status'=>-2,'msg'=>'购买米豆与金额有误，请重新下单！','result'=>null))); // 返回结果状态
        }
        if($action == 'buy_now'){

            // 获取商品模型 商品信息
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
                // $cartLogic->setSpecGoodsIdModel($item_id); // 商品规格id
            }
            $cartLogic->setGoodsBuyNum($goods_num); 
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                exit(formt('','201','商品信息错误'));
            }
            $result['result']['buy_goods']['item_id'] = $item_id;
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
        // dump($order_goods_arr);die();
        // 所有订单总和
        $car_price = array(
         
            'payables'          => 0, // 应付金额
           
            'midouFee'          => 0, // 使用米豆
            'midou_moneyFee'    => 0, // 支付现金部分
            'max_midouFee'      => 0, 
          
        );

        $shipping_code = array();
        $store_prices = array();
        foreach ($suppliers_id as $key=>$val) {
            $plugin_goods_shipping = M('plugin')->where(array('type'=>'shipping','status'=>1,'is_default'=>1,'suppliers_id'=>$val))->field('code')->find();
            if($plugin_goods_shipping) $shipping_code[$val] = $plugin_goods_shipping['code'];
            else $shipping_code[$val] = '';
            $result = calculate_price_red($this->user_id,$order_goods_arr[$val],$shipping_code[$key],0,$address['province'],$address['city'],$address['district'],$pay_points,$user_money,$coupon_id,$red_envelope_id, $midou[$key], $midou_money[$key]);
            if($result['status'] < 0)  return formt($result);
            $store_prices[$key]['payables']          += number_format($result['result']['order_amount'], 2, '.', ''); // 应付金额
            $store_prices[$key]['midouFee']        = $result['result']['order_midou'] ;//使用米豆
            $store_prices[$key]['midou_moneyFee']     =  number_format($result['result']['order_midou_money'], 2, '.', '') ;
            $store_prices[$key]['max_midouFee']       =  $result['result']['order_max_midou'];
            $store_prices[$key]['postFee'] += $result['result']['shipping_price'];     // 物流费
            $store_prices[$key]['order_prom_amount'] += $result['result']['order_prom_amount'];  // 订单优惠活动优惠了多少钱
            $store_price[$val] = array(
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
           
            $car_price['payables']          += number_format($result['result']['order_amount'], 2, '.', ''); // 应付金额
            $car_price['midouFee']          += $result['result']['order_midou'];        // 使用米豆
            $car_price['midou_moneyFee']    += $result['result']['order_midou_money'];  // 支付现金部分
            $car_price['midou_moneyFee']    =   bcadd($result['result']['order_midou_money'],$car_price['midou_moneyFee'],3);
            $car_price['midou_moneyFee']    =   number_format($car_price['midou_moneyFee'], 2, '.', ''); 
            $car_price['max_midouFee']      += $result['result']['order_max_midou']; 
           
        }
        if(!$address_id)  exit(formt('','201','请先填写收货人信息')); // 返回结果状态
        
        
        // 提交订单
        if ( $act == 'submit_order') {
            $pay_name = '';
            $orderLogic = new RedOrderLogic();
            $orderLogic->setAction($action);
            $orderLogic->setCartList($order_goods_arr);
            $result = $orderLogic->addStoreOrder($this->user_id,$address_id,$suppliers_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$store_price,$user_note,$pay_name, $midou_rate, $midou, $midou_money, $max_midou, $midou_use_percent); // 添加订单
            return formt($result);       
        }

        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price,'result2'=>$store_prices); // 返回结果状态
        return formt($return_arr);
    }







    
   
    

    public function payMidou(){
            $order_num = I('order_num/d');   // 订单数量           
            $order_id  = I('order_id/d');    // 订单id
            $order_sn  = I('order_sn');      // 订单号
            $user_id = I('user_id/d', 0);
            $user = M('users')->where('user_id', $user_id)->find();
            if(!$user_id){
              
                return formt('',201,'请先登录');
            }

            $paypwd = I('get.paypwd/s');
            if ($user['is_lock'] == 1) {
               
                return formt('',201,'账号异常已被锁定，不能使用余额支付！');
                // 用户被冻结不能使用余额支付
            }
            if (empty($user['paypwd'])) {
               
                return formt('',201,'请先设置支付密码');
            }
            if (empty($paypwd)) {
              
                return formt('',201,'请输入支付密码');
            }
            if (encrypt($paypwd) !== $user['paypwd']) {
              
                return formt('',201,'支付密码错误');
            }


            if($order_num == 1){
                $order[0] = M('order_red')->where(['order_id' => $order_id])->find();
                if($order[0]['pay_status'] == 1){
                 
                    return formt('',201,'此订单，已完成支付!');
                }
            } else {
                $order = M('order_red')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();
            }

            if(empty($order) || $order[0]['order_status'] > 1){
           
                return formt('',201,'非法操作!');
            }
            foreach ($order as $key => $value) {
                $midou_money += $value['midou_money'];
                $midou +=$value['midou'];
            }
            if($user['midou'] < $midou){
                
                return formt('',201,'米豆余额不足!');
            }
     
            $n = 0;
            foreach ($order as $k => $val) {
                update_pay_status_red($val['order_sn']);
                $n++;
            }
            if($n == $order_num){
                return formt('',200,'支付成功！');
            } else {
                return formt('',201,'支付失败，如已扣除相应米豆，请联系管理员找回米豆');
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

    #余额支付 相关
    #TK
    #2018年5月31日16:46:46
    public function balance(){
        $user_id = I('user_id/d', 0);
        $user = M('users')->where('user_id', $user_id)->find();
        $paypwd = I('post.paypsw/s');
        $is_midou = I('is_midou/d','0');
        if ($user['is_lock'] == 1) {
            return formt('',201,'账号异常已被锁定，不能使用余额支付！');
            // 用户被冻结不能使用余额支付
        }
        if (empty($user['paypwd'])) {
            return formt('',201,'请先设置支付密码');
        }
        if (empty($paypwd)) {
            $res['status']  =   0;
            return formt('',201,'请输入支付密码');
        }
        if (encrypt($paypwd) !== $user['paypwd']) {
            return formt('',201,'支付密码错误');
        }

        $order_id  = I('order_id/d',0);
        $order_sn  = I('order_sn','');
        $order_num = I('order_num/d',1);

        if($order_id || $order_sn ){
            if($order_sn)
                $where['order_sn'] = ['eq',$order_sn];
            else
                $where['order_id'] = ['eq',$order_id];

            $midou = 0;
            $user_info = M('users')->where("user_id", $user['user_id'])->find();// 找出这个用户
         

                $table_name = 'order_red';
                if($order_num > 1){
                    if(!$order_sn)$order_sn = M($table_name)->where($where)->getField('order_sn');
                    $where_or['parent_sn']  = ['eq',$order_sn];
                    $order_list = M($table_name)->where($where)->whereOr($where_or)->select();                
                } else {
                    $order_list = M($table_name)->where($where)->select();
                }
                foreach ($order_list as $key => $value) {
                    $user_money += $value['order_amount'];
                    $midou += $value['midou'];
                }

                if($order_num > 1){
                    M($table_name)->where($where)->whereOr($where_or)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
                } else {
                    M($table_name)->where($where)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
                }
                
                if($midou > $user_info['midou']){
                    return formt('',201,"米豆余额不足，可用余额为：" . $user_info['midou']);
                }
                if ($user_money && ($user_money > $user_info['user_money'])){
                   return formt('',201,'用户余额不足，可用余额为:'. tk_money_format($user_info['user_money']));
                }
            

            // dump($order_list);die;
            foreach ($order_list as $key => $value) {
        
                if(tpCache('shopping.reduce') == 2) {

                    if ($value['order_prom_type'] == 6) {
                        $team = \app\common\model\TeamActivity::get($value['order_prom_id']);
                        if ($team['team_type'] != 2) {             
                            if($is_midou == 0){
                                $res = minus_stock($value);
                            }else{
                                $res = minus_stock_red($value);
                            }
                        }
                    } else {
                        if($is_midou == 0){
                            $res = minus_stock($value);
                        }else{
                            $res = minus_stock_red($value);
                        }
                    }
                }
                if(isset($res) && $res['status'] == 0){
                    return formt($res);
                }
                $c = $value;
                if(isset($c['midou'])){
                    $c['midou'] =   $c['midou'] *   -1;
                    change_midou($c,'米豆商城下单消费');
                }

                accountLog($user_info['user_id'],($value['order_amount'] * -1),($midou * -1),0,"现金商城下单消费",0,0,$value['order_id'],$value['order_sn']);
                //2018-9-25 王牧田修改  订单提交支付后直接确认（余额付款）
                $order_update_sql[] =   ['order_id'=>$value['order_id'],'pay_time'=>NOW_TIME,'pay_status'=>1,'order_status'=>1];
            }

            model($table_name)->saveAll($order_update_sql);
            return formt('',200,'余额支付成功！');
        
        }
    }


/*
     * 订单支付页面
     */
    public function cart4(){

        // $order_id    = I('order_id/d');
        $order_sn    = I('order_sn');
        $user_id    = I('user_id');
        $order_where = "user_id = ".$user_id;

        if($order_sn){
            $order_where .= " AND order_sn ='".$order_sn."'";
            $order[0] = M('order_red')->where($order_where)->find();

            if($order[0]['order_status'] == 3){
                exit(formt('','201','该订单已取消'));
            }

            // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
            if($order[0]['pay_status'] == 1){            
                exit(formt('','201','该订单已支付过'));
            }
            $order_amounts = $order[0]['order_midou'];
            $order_num = 1;
        } 
        // dump($order);die();
        if($order_sn){
            $order_where .= " AND (order_sn ='".$order_sn."' OR parent_sn='".$order_sn."')";
            $order = M('order_red')->where($order_where)->select();
            $order_amounts = M('order_red')->where($order_where)->sum('order_midou');
            $order_num = count($order);
        } 

        if(empty($user_id)){
            exit(formt('','201','请登录'));
        }


        $cart['order_sn']=$order_sn;
        $cart['order_id']=$order[0]['order_id'];
        $cart['order_amounts']=$order_amounts;
        $cart['order']=$order;
        $cart['order_num']=$order_num;
        $cart['pay_date']=date('Y-m-d', strtotime("+1 day"));
         exit(formt($cart));
    }
    /**
     * 添加多个订单
     * @param $user_id|用户id
     * @param $address_id|地址id
     * @param $shipping_code|物流编号
     * @param $invoice_title|发票
     * @param int $coupon_id|优惠券id
     * @param $car_price|各种价格
     * @param string $user_note|用户备注
     * @return array
     */
    public function addStoreOrder($user_id,$address_id,$suppliers_id,$shipping_code,$invoice_title,$coupon_id = 0,$car_price=[],$store_price=[],$user_note=[],$pay_name='',$is_allreturn = 0)
    {
        // 0插入订单 order
        $address = M('UserAddress')->where("address_id", $address_id)->find();

        $parent_sn = $order_sn = $this->get_order_sn();
        $px = 1;
        $order_sn_str = '';

     

        foreach ($suppliers_id as $key) {
            //if($store_price[$key]['is_allreturn']) $is_allreturn = 1; else $is_allreturn = 0;
            if($shipping_code[$key]){
                $shipping = M('Plugin')->where("code ='".$shipping_code[$key]."' AND is_default = 1 AND suppliers_id = ".$key)->cache(true,TPSHOP_CACHE_TIME)->find();
            } else {
                $shipping = array('name'=>'包邮');
                $shipping_code[$key] = '';
            } 
            $data = array(
                    'order_sn'          => $order_sn, // 订单编号
                    'user_id'           => $user_id, // 用户id
                    'suppliers_id'      => $key, // 供货商id
                    'consignee'         => $address['consignee'], // 收货人
                    'province'          => $address['province'],//'省份id',
                    'city'              => $address['city'],//'城市id',
                    'district'          => $address['district'],//'县',
                    'twon'              => $address['twon'],// '街道',
                    'address'           => $address['address'],//'详细地址',
                    'mobile'            => $address['mobile'],//'手机',
                    'zipcode'           => $address['zipcode'],//'邮编',
                    'email'             => $address['email'],//'邮箱',
                    'shipping_code'     => $shipping_code[$key],//'物流编号',
                    'shipping_name'     => $shipping['name'], //'物流名称',                为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
                    'goods_price'       => $store_price[$key]['goodsFee'],//'商品价格',
                    'shipping_price'    => $store_price[$key]['postFee'],//'物流价格',
                    'user_money'        => $store_price[$key]['balance'],//'使用余额',
                    'coupon_price'      => $store_price[$key]['couponFee'],//'使用优惠券',
                    'integral'          => ($store_price[$key]['pointsFee'] * tpCache('shopping.point_rate')), //'使用积分',
                    'integral_money'    => $store_price[$key]['pointsFee'],//'使用积分抵多少钱',
                    'total_amount'      => ($store_price[$key]['goodsFee'] + $store_price[$key]['postFee']),// 订单总额
                    'order_amount'      => $store_price[$key]['payables'],//'应付款金额',
                    'add_time'          => time(), // 下单时间
                    'order_prom_id'     => $store_price[$key]['order_prom_id'],//'订单优惠活动id',
                    'order_prom_amount' => $store_price[$key]['order_prom_amount'],//'订单优惠活动优惠了多少钱',
                    'user_note'         => $user_note[$key], // 用户下单备注
                    'pay_name'          => $pay_name,//支付方式，可能是余额支付或积分兑换，后面其他支付方式会替换
                    'tk_cost_price'     => $store_price[$key]['tk_cost_price'],      //计算出来的成本价
                    'tk_cost_operating' => $store_price[$key]['tk_cost_operating'],  //计算出来的运营成本价
                    'is_allreturn'      => $is_allreturn,       //是否全返
            );

            if($px > 1){
                $data['order_sn']   = $this->get_order_sn(); // 订单编号;
                $data['parent_sn']  = $parent_sn;                    // 父单单号 
            }

            $order = new Order();
            $order->data($data,true);
            $orderSaveResult = $order->save();
            if($orderSaveResult === false){return array('status'=>-8,'msg'=>'添加订单失败','result'=>NULL);}

            // 记录订单操作日志
            $action_info = array(
                'order_id'    =>$order['order_id'],
                'action_user' =>0,
                'action_note' => '您提交了订单，请等待系统确认',
                'status_desc' => '提交订单', //''
                'log_time'    =>time(),
            );
            M('order_action')->insertGetId($action_info);

            // 1插入order_goods 表
            if($this->action == 'buy_now'){
                $cartList = $this->cartList[$key];
            }else{
                $cartList = M('Cart')->where(['user_id'=>$user_id,'is_allreturn'=>$is_allreturn,'selected'=>1,'suppliers_id'=>$key])->select();
            }
            foreach($cartList as $k => $val)
            {
                $goods = M('goods')->where("goods_id", $val['goods_id'])->cache(true,TPSHOP_CACHE_TIME)->find();
                $data2['order_id']           = $order['order_id'];         // 订单id
                $data2['goods_id']           = $val['goods_id'];           // 商品id
                $data2['goods_name']         = $val['goods_name'];         // 商品名称
                $data2['goods_sn']           = $val['goods_sn'];           // 商品货号
                $data2['goods_num']          = $val['goods_num'];          // 购买数量
                $data2['market_price']       = $val['market_price'];       // 市场价
                $data2['goods_price']        = $val['goods_price'];        // 商品价  为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
                $data2['spec_key']           = $val['spec_key'];           // 商品规格
                $data2['spec_key_name']      = $val['spec_key_name'];      // 商品规格名称
                $data2['member_goods_price'] = $val['member_goods_price']; // 会员折扣价
                $data2['cost_price']         = $val['cost_price'];         // 成本价
                $data2['cost_operating']     = $val['cost_operating'];     // 运营成本价
                $data2['prom_type']          = $val['prom_type'];          // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                $data2['prom_id']            = $val['prom_id'];            // 活动id
                $data2['suppliers_id']       = $val['suppliers_id'];       // 供货商ID
                $data2['is_allreturn']       = $is_allreturn;     // 是否参与全返

                $data2['is_tg']              = $goods['is_tgy_good'];

                $order_goods_id              = M("OrderGoods")->insertGetId($data2);
            }

            if(tpCache('shopping.reduce') == 1){
                $r = minus_stock($order); //下单减库存
              //  dump( $r);die;
                if(isset($r['status']) && $r['status'] === 0){
                    return array('status'=>0,'msg'=>$r['info'],'result'=>''); // 返回新增的订单id
                }
            }

            // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
            if($data['order_amount'] == 0)
            {
                update_pay_status($order['order_sn']);
            }

            // 4 删除已提交订单商品
            if($this->action != 'buy_now'){
                M('Cart')->where(['user_id' => $user_id,'selected' => 1,'suppliers_id'=>$key])->delete();
            }       

            //分销开关全局
            $distribut_switch = tpCache('distribut.switch');
            if($distribut_switch  == 1 && file_exists(APP_PATH.'common/logic/DistributLogic.php'))
            {
                $distributLogic = new \app\common\logic\DistributLogic();
                $distributLogic->rebateLog($order); // 生成分成记录
            }
            $order_sn_str .= '['.$order['order_sn'].']';
            $px++;
        }
        


        //用户下单, 发送短信给商家
        $res = checkEnableSendSms("3");
        $sender = tpCache("shop_info.mobile");
        if($res && $res['status'] ==1 && !empty($sender)){

            $params = array('consignee'=>$order['consignee'] , 'mobile' => $order['mobile']);
            $resp = sendSms("3", $sender, $params);
        }

        #下单成功发送模板消息提醒
        #张洪凯 20198-10-12
        $infodata = M('Order')->field('user_id,order_id,order_sn,order_amount,add_time,suppliers_id,province,city,district,address')->where("order_id=".$order['order_id'])->find();
        if($infodata){
            $wechat = new \app\common\logic\WxLogic;
            $wechat->sendTemplateMsgOnSubmitOrder($infodata);

        }


        return array('status'=>1,'msg'=>'提交订单成功','result'=>$parent_sn); // 返回新增的订单id
    }
}
