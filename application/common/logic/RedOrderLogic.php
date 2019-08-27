<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\common\logic;

use app\common\model\OrderRed;
use app\common\model\SpecRedGoodsPrice;
use think\Db;
/**
 * Class orderLogic
 * @package Common\Logic
 */
class RedOrderLogic
{
    protected $user_id=0;
	protected $action;
	protected $cartList;
    public function setUserId($user_id){
        $this->user_id=$user_id;
    }
	public function setAction($action){
		$this->action = $action;
	}
	public function setCartList($cartList){
		$this->cartList = $cartList;
	}
	/**
	 * 取消订单
	 * @param $user_id|用户ID
	 * @param $order_id|订单ID
	 * @param string $action_note 操作备注
	 * @return array
	 */
	public function cancel_order($user_id,$order_id,$action_note='您取消了订单'){
		$order = M('order_red')->where(array('order_id'=>$order_id,'user_id'=>$user_id))->find();

		//检查是否未支付订单 已支付联系客服处理退款
		if(empty($order))
			return array('status'=>-1,'msg'=>'订单不存在','result'=>'');
		if($order['order_status'] == 3){
			return array('status'=>-1,'msg'=>'该订单已取消','result'=>'');
		}
		//检查是否未支付的订单
		if($order['pay_status'] > 0 || $order['order_status'] > 0)
			return array('status'=>-1,'msg'=>'支付状态或订单状态不允许','result'=>'');

        //判断是否是线下订单
        if($order['is_store']==1){
            return $this->store_cancel_order($order_id);
            exit();
        }

		//获取记录表信息
		//$log = M('account_log')->where(array('order_id'=>$order_id))->find();
		//有余额支付的情况
		if($order['user_money'] > 0 || $order['integral'] > 0){
			accountLog($user_id,$order['user_money'],$order['midou'],$order['integral'],"订单取消，退回{$order['user_money']}元,{$order['integral']}积分",0,0,$order['order_id'],$order['order_sn']);
		}

		if($order['coupon_price'] >0){
			$res = array('use_time'=>0,'status'=>0,'order_id'=>0);
			M('coupon_red_list')->where(array('order_id'=>$order_id,'uid'=>$user_id))->save($res);
		}

		$row = M('order_red')->where(array('order_id'=>$order_id,'user_id'=>$user_id))->save(array('order_status'=>3,'cancel_time'=>time()));
		if(tpCache('shopping.reduce') == 1){
			$this->alterReturnGoodsInventory($order);
		}
		$data['order_id'] = $order_id;
		$data['action_user'] = 0;
		$data['action_note'] = $action_note;
		$data['order_status'] = 3;
		$data['pay_status'] = $order['pay_status'];
		$data['shipping_status'] = $order['shipping_status'];
		$data['log_time'] = time();
		$data['status_desc'] = '用户取消订单';
		M('order_red_action')->add($data);//订单操作记录
		if(!$row)
			return array('status'=>-1,'msg'=>'操作失败','result'=>'');
		return array('status'=>1,'msg'=>'操作成功','result'=>'');

	}

    /**
     * [实体店线下取消订单]
     * @author 王牧田
     * @date 2018-11-27
     * @param $user_id
     * @param $order_id
     */
	public function store_cancel_order($order_id){

        $where['or.pay_status'] = 0;   //未支付
        $where['or.is_store'] = 1;     //是线下订单
        $where['or.store_id'] = ['neq','0'];  //实体店id 不是0
        $where['or.order_status'] = ['in',[0,1]]; //订单状态是 待确认 和 已确认
        $where['or.order_id'] = $order_id;
        //查询所有
        $orderRedGoods = db('order_red_goods org')
            ->join("__ORDER_RED__ or","org.order_id = or.order_id")
            ->join("__SPEC_RED_GOODS_PRICE__ srgp","srgp.key = org.spec_key and srgp.goods_id = org.goods_id","left")
            ->field("or.order_id,srgp.item_id,org.goods_id,or.store_id,org.goods_num")
            ->where($where)
            ->find();


        $orgwhere['item_id'] = empty($orderRedGoods['item_id'])?0:$orderRedGoods['item_id'];
        $orgwhere['goods_id'] = $orderRedGoods['goods_id'];
        $orgwhere['store_id'] = $orderRedGoods['store_id'];

        $sql = db('store_goods_stock')->where($orgwhere)->setInc('stock',$orderRedGoods['goods_num']);


        //订单取消
        $reslt = db('order_red')->alias('or')->where($where)->save(["order_status"=>3]);
        return array('status'=>1,'msg'=>'操作成功','result'=>'');
        //return $reslt;

    }

	public function addReturnGoods($rec_id,$order)
	{
		$data = I('post.');
        $data['type']   =   $data['t'];
		$confirm_time_config = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
		$confirm_time = $confirm_time_config * 24 * 60 * 60;
		if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
			return ['result'=>-1,'msg'=>'已经超过' . ($confirm_time_config ?: 0) . "天内退货时间"];
		}
        
        $img = $this->uploadReturnGoodsImg();
        if ($img['status'] !== 1) {
            return $img;
        }
        $data['imgs'] = $img['result'] ?: ($data['imgs'] ?: ''); //兼容小程序，多传imgs
	
		$data['addtime'] = time();
		$data['user_id'] = $order['user_id'];
		$order_goods = M('order_red_goods')->where(array('rec_id'=>$rec_id))->find();

        if($data['type'] < 2){
            //退款申请，若该商品有赠送积分或优惠券，在平台操作退款时需要追回
            //$rate = round($order_goods['midou_money']*$data['goods_num']/$order['midou_money'],2);
            if($order['midou'] > 0) $rate_midou = round($order_goods['midou']*$data['goods_num']/$order['midou'],2);
            else $rate_midou = 0;

            if($order['order_amount']>0 && $order['order_amount']>$order['shipping_price'] && !empty($order['pay_code'])){
                $data['midou']    = $data['refund_midou']    = $order_goods['midou']*$data['goods_num'];       //退款米豆
                $data['money']    = $data['refund_money']    = $order_goods['midou_money']*$data['goods_num'];      //退款金额
                $data['deposit']  = $data['refund_deposit']  = $order['user_money'];        //该退余额支付部分
                //$data['refund_integral'] = floor($rate*$order['integral']);   //该退积分支付
            }else{
                if( $order['order_amount']>$order['shipping_price'] )
                    $data['deposit'] = $data['refund_deposit'] = $order['user_money']+($order['order_amount'] - $order['shipping_price']); //该退余额支付部分
                else
                    $data['deposit'] = $data['refund_deposit'] = $order['user_money'];
                
                $data['midou']    = $data['refund_midou']    = $rate_midou*$order['midou'];    //退款米豆
                $data['integral'] = $data['refund_integral'] = floor($order['integral']);//该退积分支付
            }
        }
        $data['suppliers_id']   =   $order_goods['suppliers_id'];
        $data['consignee'] = $order['consignee'];
        $data['mobile']    = $order['mobile'];

		if(!empty($data['id'])){
			$result = M('return_red_goods')->where(array('id'=>$data['id']))->save($data);
		}else{
			$result = M('return_red_goods')->add($data);
		}
	
		if($result){
			return ['status'=>1,'msg'=>'申请成功'];
		}
		return ['status'=>-1,'msg'=>'申请失败'];
	}
    
    /**
     * 上传退换货图片，兼容小程序
     * @return array
     */
    public function uploadReturnGoodsImg()
    {
        $return_imgs = '';
        if ($_FILES['return_imgs']['tmp_name']) {
			$files = request()->file("return_imgs");
            if (is_object($files)) {
                $files = [$files]; //可能是一张图片，小程序情况
            }
			$image_upload_limit_size = config('image_upload_limit_size');
			$validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
			$dir = 'public/upload/return_goods/';
			if (!($_exists = file_exists($dir))){
				$isMk = mkdir($dir);
			}
			$parentDir = date('Ymd');
			foreach($files as $key => $file){
				$info = $file->rule($parentDir)->validate($validate)->move($dir, true);
				if($info){
					$filename = $info->getFilename();
					$new_name = '/'.$dir.$parentDir.'/'.$filename;
					$return_imgs[]= $new_name;
				}else{
                    return ['status' => -1, 'msg' => $file->getError()];//上传错误提示错误信息
				}
			}
			if (!empty($return_imgs)) {
				$return_imgs = implode(',', $return_imgs);// 上传的图片文件
			}
		}
        
        return ['status' => 1, 'msg' => '操作成功', 'result' => $return_imgs];
    }
	
    /**
     * 获取可申请退换货订单商品
     * @param $sale_t
     * @param $keywords
     * @param $user_id
     * @return array
     */
    public function getReturnGoodsIndex($sale_t, $keywords, $user_id)
    {
        if($keywords){
            $condition['order_sn'] = $keywords;
        }
        if($sale_t == 1){
            //三个月内
            $condition['add_time'] = array('gt' , 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)');
        }else if($sale_t == 2){
            //三个月前
            $condition['add_time'] = array('lt' , 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)');
        }
    	$condition['user_id'] = $user_id;
    	$condition['pay_status'] = 1;
    	$condition['shipping_status'] = 1;
    	$condition['deleted'] = 0;
    	$count = M('order')->where($condition)->count();
    	$Page  = new \think\Page($count,10);
    	$show = $Page->show();
    	$order_list = M('order_red')->where($condition)->order('order_id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
    	
    	foreach ($order_list as $k=>$v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $data = M('order_red_goods')->where(['order_id'=>$v['order_id'],'is_send'=>['lt',2]])->select();
            if(!empty($data)){
                $order_list[$k]['goods_list'] = $data;
            }else{
                unset($order_list[$k]);  //除去没有可申请的订单
            }
    	}
        return [
            'order_list' => $order_list,
            'page' => $show
        ];
    }

    /**
     * 获取退货列表
     * @param type $keywords
     * @param type $addtime
     * @param type $status
     * @return type
     */
    public function getReturnGoodsList($keywords, $addtime, $status, $user_id = 0)
	{
		if($keywords){
            $where['order_sn|goods_name'] = array('like',"%$keywords%");
    	}
    	if($status === '0' || !empty($status)){
            $where['status'] = $status;
    	}
    	if($addtime == 1){
            $where['addtime'] = array('gt',(time()-90*24*3600));
    	}
    	if($addtime == 2){
            $where['addtime'] = array('lt',(time()-90*24*3600));
    	}
    	$query = M('return_red_goods')->alias('r')->field('r.*,g.goods_name')
                ->join('__ORDER_RED__ o', 'r.order_id = o.order_id AND o.deleted = 0 AND o.user_id='.$user_id)
                ->join('__GOODS_RED__ g', 'r.goods_id = g.goods_id', 'LEFT')
                ->where($where);
        $query2 = clone $query;
        $count = $query->count();
    	$page = new \think\Page($count,10);
    	$list = $query2->order("id desc")->limit($page->firstRow, $page->listRows)->select();
    	$goods_id_arr = get_arr_column($list, 'goods_id');
    	if(!empty($goods_id_arr)) {
            $goodsList = M('goods_red')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');
        }
        
        return [
            'goodsList' => $goodsList,
            'return_list' => $list,
            'page' => $page->show()
        ];
	}
    
    /**
     * 删除订单
     * @param type $order_id
     * @return type
     */
    public function delOrder($order_id)
    {
        $validate = validate('order_red');
        if (!$validate->scene('del')->check(['order_id' => $order_id])) {
            return ['status' => 0, 'msg' => $validate->getError()];
        }
        if(empty($this->user_id))return ['status'=>-1,'msg'=>'非法操作'];
        $row = M('order_red')->where(['user_id'=>$this->user_id,'order_id'=>$order_id])->update(['deleted'=>1]);
        if (!$row) {
            M('order_red_goods')->where(['order_id'=>$order_id])->update(['deleted'=>1]);
            return ['status'=>-1,'msg'=>'删除失败'];
        }
        return ['status'=>1,'msg'=>'删除成功'];
    }
    
    /**
     * 记录取消订单
     */
    public function recordRefundOrder($user_id, $order_id, $user_note, $consignee, $mobile)
    {
        $order = M('order_red')->where(['order_id' => $order_id, 'user_id' => $user_id])->find();
        
    	if (!$order) {
    		return ['status' => -1, 'msg' => '订单不存在'];
    	}
    	$order_return_num = M('return_red_goods')->where(['order_id' => $order_id, 'user_id' => $user_id,'status'=>['neq',5]])->count();
    	if($order_return_num > 0){
    		return ['status' => -1, 'msg' => '该订单中有商品正在申请售后'];
    	}
    	$order_status = 3;//已取消
    	$order_info = [
        	'user_note' => $user_note,
        	'consignee' => $consignee,
        	'mobile'    => $mobile,
        	'order_status'=> $order_status,
            'cancel_time' => time()
    	];
    
    	$result = M('order_red')->where(['order_id' => $order_id])->update($order_info);
    	if (!$result) {
    		return ['status' => 0, 'msg' => '操作失败'];
    	}
    
    	$data['order_id'] = $order_id;
    	$data['action_user'] = $user_id;
    	$data['action_note'] = $user_note;
    	$data['order_status'] = $order_status;
    	$data['pay_status'] = $order['pay_status'];
    	$data['shipping_status'] = $order['shipping_status'];
    	$data['log_time'] = time();
    	$data['status_desc'] = '用户取消已付款订单';
        M('order_red_action')->add($data);//订单操作记录
    	return ['status' => 1, 'msg' => '提交成功'];
    }



    /**
     * 记录取消订单 反冲库存
     */
    public function recordRefundStock($user_id,$order_id)
    {
        $order = M('order_red')->where(['order_id' => $order_id, 'user_id' => $user_id])->find();

        if($order["store_id"] == 0)
        {
            return ['status' => 1, 'msg' => '取消成功'];
        }

    	if (!$order) {
    		return ['status' => -1, 'msg' => '订单不存在'];
        }
        $order_red = M('order_red')->where('order_id',$order_id)->find();
        $order_red_goods = M('order_red_goods')->where('order_id',$order_id)->find();
        $item_id = M('spec_red_goods_price')->where(["goods_id"=>$order_red_goods["goods_id"],"key"=>$order_red_goods["spec_key"]])->value("item_id");
        $item_id = empty($item_id)?0:$item_id;
        $query = M('store_goods_stock')->where(['store_id' => $order_red['store_id'], 'goods_id' => $order_red_goods['goods_id'],'item_id'=>$item_id])->setInc('stock',$order_red_goods['goods_num']);

       if($query != false){
    	    return ['status' => 1, 'msg' => '取消成功'];
       }else{
            return ['status' => 0, 'msg' => '库存反冲失败'];
       }
    }




	/**
	 * 	生成兑换码
	 * 长度 =3位 + 4位 + 2位 + 3位  + 1位 + 5位随机  = 18位
	 * @param $order
	 * @return mixed
	 */
	function make_virtual_code($order){
		$order_goods = M('order_red_goods')->where(array('order_id'=>$order['order_id']))->find();
		$goods = M('goods_red')->where(array('goods_id'=>$order_goods['goods_id']))->find();
		M('order_red')->where(array('order_id'=>$order['order_id']))->save(array('order_status'=>1,'shipping_time'=>time()));
		$perfix = mt_rand(100,999);
		$perfix .= sprintf('%04d', $order['user_id'] % 10000)
				. sprintf('%02d', (int) $order['user_id'] % 100).sprintf('%03d', (float) microtime() * 1000);

		for ($i = 0; $i < $order_goods['goods_num']; $i++) {
			$order_code[$i]['order_id'] = $order['order_id'];
			$order_code[$i]['user_id'] = $order['user_id'];
			$order_code[$i]['vr_code'] = $perfix. sprintf('%02d', (int) $i % 100) . rand(5,1);
			$order_code[$i]['pay_price'] = $goods['shop_price'];
			$order_code[$i]['vr_indate'] = $goods['virtual_indate'];
			$order_code[$i]['vr_invalid_refund'] = $goods['virtual_refund'];
		}

		$res = checkEnableSendSms("7");

		//生成虚拟订单, 向用户发送短信提醒
		if($res && $res['status'] ==1){
			$sender = $order['mobile'];
			$goods_name = $goods['goods_name'];
			$goods_name = getSubstr($goods_name, 0, 10);
			$params = array('goods_name'=>$goods_name);
			sendSms("7", $sender, $params);
		}

		return M('vr_order_code')->insertAll($order_code);
	}

	/**
	 * 自动取消订单
	 */
	public function abolishOrder(){
		$set_time=1; //自动取消时间/天 默认1天
		$abolishtime = strtotime("-$set_time day");
		$order_where = [
				'user_id'      =>$this->user_id,
				'add_time'     =>['lt',$abolishtime],
				'pay_status'   =>0,
				'order_status' => 0
		];
		$order = Db::name('order_red')->where($order_where)->getField('order_id',true);
		foreach($order as $key =>$value){
			$result = $this->cancel_order($this->user_id,$value);
		}
		return $result;
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
	public function addStoreOrder($user_id,$address_id,$suppliers_id,$shipping_code,$invoice_title,$coupon_id = 0,$car_price=[],$store_price=[],$user_note=[],$pay_name='', $midou_rate, $midou, $midou_money, $max_midou, $midou_use_percent,$store_id=0)
	{
		// 0插入订单 order
		$address = M('UserAddress')->where("address_id", $address_id)->find();
        //是线下订单时没有收货地址
		if($store_id!=0 && empty($address)){
            $address["consignee"] = "";
            $address["province"] = "";
            $address["city"] = "";
            $address["district"] = "";
            $address["twon"] = "";
            $address["address"] = "";
            $address["mobile"] = "";
            $address["zipcode"] = "";
            $address["email"] = "";
        }

		$parent_sn = $order_sn = $this->get_order_sn();
		$px = 1;
		$order_sn_str = '';
		foreach ($suppliers_id as $key) {
			if($shipping_code[$key]){
                $shipping = M('Plugin')->where("code ='".$shipping_code[$key]."' AND is_default = 1 AND suppliers_id = ".$key)->cache(true,TPSHOP_CACHE_TIME)->find();
            } else {
                $shipping = array('name'=>'包邮');
                $shipping_code[$key] = '';
            } 
            $data = array(
                    'order_sn'         => $order_sn, // 订单编号
                    'user_id'          => $user_id, // 用户id
                    'suppliers_id'     => $key, // 供货商id
                    'consignee'        => $address['consignee'], // 收货人
                    'province'         => $address['province'],//'省份id',
                    'city'             => $address['city'],//'城市id',
                    'district'         => $address['district'],//'县',
                    'twon'             => $address['twon'],// '街道',
                    'address'          => $address['address'],//'详细地址',
                    'mobile'           => $address['mobile'],//'手机',
                    'zipcode'          => $address['zipcode'],//'邮编',
                    'email'            => $address['email'],//'邮箱',
                    'shipping_code'    => $shipping_code[$key],//'物流编号',
                    'shipping_name'    => $shipping['name'], //'物流名称',                为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
                    //'invoice_title'    => $invoice_title[$key], //'发票抬头',
                    'goods_price'      => $store_price[$key]['goodsFee'],//'商品价格',
                    'midou'            => $store_price[$key]['midouFee'],//'米豆',
                    'order_midou'      => $store_price[$key]['midouFee'],//'米豆',
                    'midou_money'      => $store_price[$key]['midou_moneyFee'],//'现金部分',
                    'max_midou'        => $store_price[$key]['max_midouFee'],
                    'shipping_price'   => $store_price[$key]['postFee'],//'物流价格',
                    'user_money'       => $store_price[$key]['balance'],//'使用余额',
                    'coupon_price'     => $store_price[$key]['couponFee'],//'使用优惠券',
                    'integral'         => ($store_price[$key]['pointsFee'] * tpCache('shopping.point_rate')), //'使用积分',
                    'integral_money'   => $store_price[$key]['pointsFee'],//'使用积分抵多少钱',
                    'total_amount'     => ($store_price[$key]['goodsFee'] + $store_price[$key]['postFee']),// 订单总额
                    'order_amount'     => $store_price[$key]['payables'],//'应付款金额',
                    'add_time'         => time(), // 下单时间
                    'order_prom_id'    => $store_price[$key]['order_prom_id'],//'订单优惠活动id',
                    'order_prom_amount'=> $store_price[$key]['order_prom_amount'],//'订单优惠活动优惠了多少钱',
                    'user_note'        => $user_note[$key], // 用户下单备注
                    'pay_name'         => $pay_name,//支付方式，可能是余额支付或积分兑换，后面其他支付方式会替换
                    'midou_rate'       => $midou_rate,//米豆兑换比
                    'tk_cost_price'    => $store_price[$key]['tk_cost_price'], //计算出来的成本价
                    'tk_cost_operating'=> $store_price[$key]['tk_cost_operating'], //计算出来的运营成本价
            );
            if($px > 1){
                $data['order_sn']   = $this->get_order_sn(); // 订单编号;
                $data['parent_sn']  = $parent_sn;                    // 父单单号 
            }
            $data['is_store'] = 0;

            if($store_id != 0){
                $data['shipping_time'] = NOW_TIME;
                $data['is_store'] = 1; //实体店购物
                //$data['order_status']=1; //订单已确认
                //$data['shipping_status']=1;//订单已发货   -> 此處修改
                $data['store_id'] = $store_id;
            }

            $order = new OrderRed();
            $order->data($data,true);
            $orderSaveResult = $order->save();
            if($orderSaveResult === false){return array('status'=>-8,'msg'=>'添加订单失败','result'=>NULL);}

            // 记录订单操作日志
            $action_info = array(
                    'order_id'    => $order['order_id'],
                    'action_user' => 0,
                    'action_note' => '您提交了订单，请等待系统确认',
                    'status_desc' => '提交订单', //''
                    'log_time'    => NOW_TIME,
            );
            M('order_red_action')->insertGetId($action_info);

            // 1插入order_goods 表
            if($this->action == 'buy_now'){
                $cartList = $this->cartList[$key];
            }else{
                $cartList = M('CartRed')->where(['user_id'=>$user_id,'selected'=>1,'suppliers_id'=>$key])->select();
            }
            foreach($cartList as $k => $val)
            {
                $goods = M('goods_red')->where("goods_id", $val['goods_id'])->cache(true,TPSHOP_CACHE_TIME)->find();
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
                $data2['give_integral']      = $goods['give_integral'];    // 购买商品赠送积分
                $data2['prom_type']          = $val['prom_type'];          // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                $data2['prom_id']            = $val['prom_id'];            // 活动id
                $data2['suppliers_id']       = $val['suppliers_id'];       // 供货商ID
                $data2['midou_rate']         = $midou_rate;                // 米豆兑换比
                $data2['midou']              = $midou[$key][$val['goods_id']][$val['item_id']];
                $data2['midou_money']        = $midou_money[$key][$val['goods_id']][$val['item_id']];
                $data2['max_midou']          = $max_midou[$key][$val['goods_id']][$val['item_id']];
                $data2['midou_use_percent']  = $midou_use_percent[$key][$val['goods_id']][$val['item_id']];  // 购买商品 使用米豆 比率
                $order_goods_id              = M("OrderRedGoods")->insertGetId($data2);

            }

            if(tpCache('shopping.reduce') == 1){
                if($store_id != 0){
                    store_minus_stock_red($order,$store_id); //下单减库存
                }else{
                    minus_stock_red($order); //下单减库存
                }


            }

            // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
            // if($data['order_amount'] == 0 && $data['midou'] > 0)
            // {
            //     $user_midou = get_user_midou($user_id);
            //     if($data['midou'] > $user_midou || ($user_midou == 0 && $data['midou'] == 0)){
            //         $this->error('用户米豆余额不足！', U("User/index"));
            //     } else {
            //         update_pay_status_red($order['order_sn']);
            //     }

            // }

			// 4 删除已提交订单商品
			if($this->action != 'buy_now'){
				M('CartRed')->where(['user_id' => $user_id,'selected' => 1,'suppliers_id'=>$key])->delete();
			}		
			$order_sn_str .= '['.$order['order_sn'].']';
			$px++;
		}
		

		// 如果有微信公众号 则推送一条消息到微信
		/*$user = M('OauthUsers')->where(['user_id'=>$user_id , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
		if($user)
		{
			$wx_user = M('wx_user')->find();
			$jssdk = new JssdkLogic($wx_user['appid'],$wx_user['appsecret']);
			$wx_content = "你刚刚下了一笔订单:{$order_sn_str} 尽快支付,过期失效!";
			$jssdk->push_msg($user['openid'],$wx_content);
		}*/

		//用户下单, 发送短信给商家
		$res = checkEnableSendSms("3");
		$sender = tpCache("shop_info.mobile");
		if($res && $res['status'] ==1 && !empty($sender)){

			$params = array('consignee'=>$order['consignee'] , 'mobile' => $order['mobile']);
			$resp = sendSms("3", $sender, $params);
		}

        #用户购买供货商的商品给供货商发微信消息提醒
        #张洪凯 20198-10-13
        $infodata = M('order_red')->field('user_id,order_id,order_sn,midou order_amount,add_time,suppliers_id,province,city,district,address')->where("order_id=".$order['order_id'])->find();
        if($infodata){
            $infodata['red'] = 1;

            $wechat = new \app\common\logic\WxLogic;
            #下单成功发送模板消息提醒
            $wechat->sendTemplateMsgOnSubmitOrder($infodata);

        }


		return array('status'=>1,'msg'=>'提交订单成功','result'=>$parent_sn); // 返回新增的订单id
	}

	
	/**
	 * 获取订单 order_sn
	 * @return string
	 */
	public function get_order_sn()
	{
	    $order_sn = null;
	    // 保证不会有重复订单号存在
	    while(true){
	        $order_sn = 'midou'.date('YmdHis').rand(1000,9999); // 订单编号	        
	        $order_sn_count = M('order_red')->where("order_sn = '".$order_sn."'")->count();
	        if($order_sn_count == 0)
	            break;
	    }
	    
	    return $order_sn;
	}

    /*
     * 订单操作记录
     */
    public function orderActionLog($order_id,$action,$note=''){
        $order = M('order_red')->where(array('order_id'=>$order_id))->find();
        $data['order_id'] = $order_id;
        $data['action_user'] = session('admin_id');
        $data['action_note'] = $note;
        $data['order_status'] = $order['order_status'];
        $data['pay_status'] = $order['pay_status'];
        $data['shipping_status'] = $order['shipping_status'];
        $data['log_time'] = time();
        $data['status_desc'] = $action;
        return M('order_red_action')->add($data);//订单操作记录
    }
	/**
	 * 取消订单后改变库存，根据不同的规格，商品活动修改对应的库存
	 * @param $order
     * @param $rec_id|订单商品表id 如果有只返还订单某个商品的库存,没有返还整个订单
     */
    public function alterReturnGoodsInventory($order, $rec_id='')
	{
        if($rec_id){
            $orderGoodsWhere['rec_id'] = $rec_id;
            $retunn_info = Db::name('return_red_goods')->where($orderGoodsWhere)->select(); //查找购买数量和购买规格
            $order_goods_prom = Db::name('order_red_goods')->where($orderGoodsWhere)->find(); //购买时参加的活动
            $order_goods = $retunn_info;
            $order_goods[0]['prom_type'] = $order_goods_prom['prom_type'];
            $order_goods[0]['prom_id'] = $order_goods_prom['prom_id'];
        }else{
            $orderGoodsWhere = ['order_id'=>$order['order_id']];
            $order_goods = Db::name('order_red_goods')->where($orderGoodsWhere)->select(); //查找购买数量和购买规格
        }

        $store_id = $order["store_id"];
        $SpecGoodsPrice = new SpecRedGoodsPrice();
		foreach($order_goods as $key=>$val){

		    if($store_id == 0){
		        //线上订单
                if(!empty($val['spec_key'])){ // 先到规格表里面扣除数量
                    $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
                    if($specGoodsPrice){
                        $specGoodsPrice->store_count = $specGoodsPrice->store_count + $val['goods_num'];
                        $specGoodsPrice->save();//有规格则增加商品对应规格的库存
                    }
                }else{
                    M('goods_red')->where(['goods_id' => $val['goods_id']])->setInc('store_count', $val['goods_num']);//没有规格则增加商品库存
                }
            }else{
		        //线下订单

                $item_id = M('spec_red_goods_price')->where(["goods_id"=>$val["goods_id"],"key"=>$val["spec_key"]])->value("item_id");

                $item_id = empty($item_id)?0:$item_id;

                M('store_goods_stock')->where(['store_id' => $store_id, 'goods_id' => $val['goods_id'],'item_id'=>$item_id])->setInc('stock',$val['goods_num']);

		    }



			update_stock_log_red($order['user_id'], $val['goods_num'], $val, $order['order_sn']);//库存日志
			Db::name('GoodsRed')->where("goods_id", $val['goods_id'])->setDec('sales_sum', $val['goods_num']); // 减少商品销售量
			//更新活动商品购买量
			if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
				$GoodsPromFactory = new RedGoodsPromFactory();
				$goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
				$prom = $goodsPromLogic->getPromModel();
				if ($prom['is_end'] == 0) {
					$tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
					M($tb)->where("id", $val['prom_id'])->setDec('buy_num', $val['goods_num']);
					M($tb)->where("id", $val['prom_id'])->setDec('order_num',$val['goods_num']);
				}
			}
		}


        unset($SpecGoodsPrice);

	}


	/**
     * 获取待付款订单数
     */
    public function getWaitPayOrderNum($user_id)
    {
        (!$user_id) && $user_id = 0;
        $where = 'user_id = '.$user_id.' AND pay_status = 0 AND order_status = 0 AND pay_code !="cod"';
        $num = M('order_red')
            ->where($where)
            ->count();
        return $num;
    }

	/**
     * 获取待发货订单数
     */
    public function getWaitPostOrderNum($user_id)
    {
        (!$user_id) && $user_id = 0;
        $where = 'user_id = '.$user_id.' AND (pay_status=1 OR pay_code="cod") AND shipping_status !=1 AND order_status in(0,1)';
        $num = M('order_red')
            ->where($where)
            ->count();
        return $num;
    }

	/**
     * 获取待收货订单数
     */
    public function getWaitReceiveOrderNum($user_id)
    {
        (!$user_id) && $user_id = 0;
        $where = 'user_id = '.$user_id.' AND shipping_status=1 AND order_status = 1 ';
        $num = M('order_red')
            ->where($where)
            ->count();
        return $num;
    }

}