<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\applet\controller;
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
            $isUsers = M('users')->where(['user_id'=>$this->user_id , 'token'=>$this->token])->find();
            if(!$isUsers){
                exit(formt('',201,'用户不存在'));
            }
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
        $is_allreturn= empty($this->request->param('is_allreturn'))? 0 : $this->request->param('is_allreturn');
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList(0,$is_allreturn); //用户购物车 0 为现金购物车  1为福利购物车

        $goods_arr = array(); // 商品ID
        foreach($cartList as $k => $val){
            if(empty($val['goods']['original_img'])){
                $val['goods']['original_img'] = URL."/public/images/icon_goods_thumb_empty_300.png";
            }
            $goods_arr[$k]['id'] = $val['id'];
            $goods_arr[$k]['user_id'] = $val['user_id'];
            $goods_arr[$k]['goods_id'] = $val['goods_id'];
            $goods_arr[$k]['goods_name'] = $val['goods_name'];
            $goods_arr[$k]['goods_price'] = $val['goods_price'];
            $goods_arr[$k]['selected'] = 0;
            $goods_arr[$k]['goods_num'] = $val['goods_num'];
            $goods_arr[$k]['is_allreturn'] = $val['is_allreturn'];
             if (strstr(goods_thum_images($val['goods_id'],400,400),'http')) {
                     $goods_arr[$k]['original_img']=goods_thum_images($val['goods_id'],400,400);
                }else{
                     $goods_arr[$k]['original_img']=URL.goods_thum_images($val['goods_id'],400,400);
                }
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
        $is_allreturn= empty($this->request->param('is_allreturn'))? 0 : $this->request->param('is_allreturn');
        $counts_all = M('Goods')->where('is_hot=1 and is_allreturn='.$is_allreturn.' and is_on_sale=1 and is_check=1')->count();
        $counts = ceil($counts_all/$rows);
        $hot_goods = M('Goods')->where('is_hot=1 and is_allreturn='.$is_allreturn.' and is_on_sale=1 and is_check=1')->page($p,$rows)->order('on_time  desc')->select();
        $arr_hot_goods = array();
        foreach ($hot_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            if(empty($val['goods']['original_img'])){
                $val['goods']['original_img'] = URL."/public/images/icon_goods_thumb_empty_300.png";
            }
            $arr_hot_goods[$k]['goods_id'] = $val['goods_id'];
            $arr_hot_goods[$k]['goods_name'] = $val['goods_name'];
            $arr_hot_goods[$k]['goods_price'] = $val['shop_price'];
            $arr_hot_goods[$k]['is_allreturn'] = $val['is_allreturn'];
             if (strstr(goods_thum_images($val['goods_id'],400,400),'http')) {
                     $arr_hot_goods[$k]['original_img']=goods_thum_images($val['goods_id'],400,400);
                }else{
                     $arr_hot_goods[$k]['original_img']=URL.goods_thum_images($val['goods_id'],400,400);
                }
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
        $user_id=I('user_id/d');
        $token=I('token');
        if ($user_id == 0 ||  $token == ''){
            exit(formt('',201,'请登录'));
        }
        $cartIds  = $this->request->param("cartIds/a"); // 购物车id
        $goods_id  = $this->request->param("goods_id/d"); // 商品id
        $goods_num = $this->request->param("goods_num/d");// 商品数量
        $item_id   = $this->request->param("item_id/d");  // 商品规格id
        $action    = $this->request->param("action");     // 行为
      
        $cartLogic = new CartLogic();
        $couponLogic = new CouponLogic();
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
                $val['goods']['original_img'] = URL."/public/images/icon_goods_thumb_empty_300.png";
            }
            $goods_arr[$k]['id'] = $val['id'];  
            $goods_arr[$k]['user_id'] = $val['user_id'];
            $goods_arr[$k]['goods_id'] = $val['goods_id'];
            $goods_arr[$k]['goods_name'] = $val['goods_name'];
            $goods_arr[$k]['goods_price'] = $val['goods_price'];
            $goods_arr[$k]['selected'] = $val['selected'];
            $goods_arr[$k]['goods_num'] = $val['goods_num'];
            $goods_arr[$k]['is_allreturn'] = $val['is_allreturn'];
            $goods_arr[$k]['original_img'] = URL.$val['goods']['original_img'];
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
        $suppliers_id  = $this->request->param("suppliers_id/a");  // 供货商id
        $address_id    = $this->request->param("address_id/d");    // 收货地址id
        $coupon_id     = $this->request->param("coupon_id/d");     // 优惠券id
        $pay_points    = $this->request->param("pay_points/d",0);  // 使用积分
        $user_money    = $this->request->param("user_money/f",0);  // 使用余额
        $user_note     = $this->request->param("user_note/a",'');     // 买家留言
        $act           = $this->request->param("act/s");
        //立即购买 才会用到
        $goods_id      = $this->request->param("goods_id/d");  // 商品id
        $goods_num     = $this->request->param("goods_num/d"); // 商品数量
        $item_id       = $this->request->param("item_id/d");   // 商品规格id
        $action        = $this->request->param("action");      // 立即购买
        
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
        $store_prices = array();
        foreach ($suppliers_id as $key=>$val) {
            $plugin_goods_shipping = M('plugin')->where(array('type'=>'shipping','status'=>1,'is_default'=>1,'suppliers_id'=>$val))->field('code')->find();
            if($plugin_goods_shipping) $shipping_code[$val] = $plugin_goods_shipping['code'];
            else $shipping_code[$val] = '';

            $result = calculate_price($this->user_id,$order_goods_arr[$val],$shipping_code[$val],0,$address['province'],$address['city'],$address['district'],$pay_points,$user_money,$coupon_id,$red_envelope_id);
            if($result['status'] < 0)  return formt($result);
            $store_prices[$key]['payables']          += number_format($result['result']['order_amount'], 2, '.', ''); // 应付金额
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
                'tk_cost_price'     => $result['result']['tk_cost_price'],      //老王添加的成本价  前台不需要显示出来
                'tk_cost_operating' => $result['result']['tk_cost_operating'],  //运营成本价  前台不需要显示出来
            );
            $car_price['payables']          += number_format($result['result']['order_amount'], 2, '.', ''); // 应付金额
            
        }
        if(!$address_id)  exit(formt('','201','请先填写收货人信息')); // 返回结果状态
        
        
        // 提交订单
        if ( $act == 'submit_order') {
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

        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price,'result2'=>$store_prices); // 返回结果状态
        return formt($return_arr);
    }


 


    /*
     * 订单支付页面
     */
    public function cart4(){

        $order_sn    = I('order_sn');
        $user_id    = I('user_id');
        $order_where = "user_id = ".$user_id;


        if($order_sn){
            $order_where .= " AND order_sn =".$order_sn;
            $order[0] = M('Order')->where($order_where)->find();

            if($order[0]['order_status'] == 3){
                exit(formt('','201','该订单已取消'));
            }

            // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
            if($order[0]['pay_status'] == 1){            
                exit(formt('','201','该订单已支付过'));
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
            $cartLogic->setSpecGoodsPriceModel($item_id);// 商品规格
            // $cartLogic->setSpecGoodsIdModel($item_id); // 商品规格id
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
        $user_id = I('user_id/d', 0);
        $user = M('users')->where('user_id', $user_id)->find();
        $paypwd = I('paypwd/s');
        
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
           
                // 订单数据表
                $table_name = 'order';
                // 订单数量大于1
                if($order_num > 1){
                    // 如果不存在 订单号
                    if(!$order_sn)$order_sn = M($table_name)->where($where)->getField('order_sn');
                    $where_or['parent_sn']  = ['eq',$order_sn];
                    // 获取全部订单列表
                    $order_list = M($table_name)->where($where)->whereOr($where_or)->select();                
                } else {
                    $order_list = M($table_name)->where($where)->select();
                }
                foreach ($order_list as $key => $value) {
                    if($value['pay_status'] == 1){
                        return formt('',201,'该订单已经支付!');
                    }
                    $user_money += $value['order_amount'];
                }

                if($order_num > 1){
                    M($table_name)->where($where)->whereOr($where_or)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
                } else {
                    M($table_name)->where($where)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
                }


                if ($user_money && ($user_money > $user_info['user_money'])){
                    return formt('',201,'你的账户可用余额为:'. tk_money_format($user_info['user_money']));
                }
                
            

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
}