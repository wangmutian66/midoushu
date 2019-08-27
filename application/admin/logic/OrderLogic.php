<?php

/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Date: 2015-09-14
 */


namespace app\admin\logic;

use app\common\model\SpecGoodsPrice;
use think\Model;
use think\Db;
class OrderLogic extends Model
{
    /**
     * @param array $condition  搜索条件
     * @param string $order   排序方式
     * @param int $start    limit开始行
     * @param int $page_size  获取数量
     */
    public function getOrderList($condition,$order='',$start=0,$page_size=20){
        $res = M('order')->where($condition)->limit("$start,$page_size")->order($order)->select();
        return $res;
    }

    /**
     * 获取订单商品详情
     * @param $order_id
     * @param string $is_send
     * @return mixed
     */
    public function getOrderGoods($order_id,$is_send =''){
        if($is_send){
            $where=" and o.is_send < $is_send";
        }
        $sql = "SELECT g.*,o.*,(o.goods_num * o.member_goods_price) AS goods_total FROM __PREFIX__order_goods o ".
            "LEFT JOIN __PREFIX__goods g ON o.goods_id = g.goods_id WHERE o.order_id = $order_id ".$where;
        $res = DB::query($sql);
        return $res;
    }


    /*
     * 获取订单信息
     */
    public function getOrderInfo($order_id)
    {
        //  订单总金额查询语句		
        $order = M('order')->where("order_id = $order_id")->find();
        $order['address2'] = $this->getAddressName($order['province'],$order['city'],$order['district']);
        $order['address2'] = $order['address2'].$order['address'];		
        return $order;
    }

    /*
     * 根据商品型号获取商品
     */
    public function get_spec_goods($goods_id_arr){
    	if(!is_array($goods_id_arr)) return false;
		foreach($goods_id_arr as $key => $val) 
		{
			$arr   = array();
			$goods = M('goods')->where("goods_id = $key")->find();
			$arr['goods_id']           = $key; // 商品id
			$arr['goods_name']         = $goods['goods_name'];
			$arr['goods_sn']           = $goods['goods_sn'];
			$arr['market_price']       = $goods['market_price'];
			$arr['goods_price']        = $goods['shop_price'];
			$arr['cost_price']         = $goods['cost_price'];
			$arr['member_goods_price'] = $goods['shop_price'];
            $arr['suppliers_id']       = $goods['suppliers_id'];  
			foreach($val as $k => $v)
			{
				$arr['goods_num'] = $v['goods_num']; // 购买数量
				// 如果这商品有规格
				if($k != 'key')
				{
					$arr['spec_key'] = $k;
					$spec_goods = M('spec_goods_price')->where("goods_id = $key and `key` = '{$k}'")->find();
					$arr['spec_key_name'] = $spec_goods['key_name'];
					$arr['member_goods_price'] = $arr['goods_price'] = $spec_goods['price'];
					$arr['sku'] = $spec_goods['sku']; // 参考 sku  http://www.zhihu.com/question/19841574
				}
				$order_goods[] = $arr;
			}
		}
		return $order_goods;	
    }

    /*
     * 订单操作记录
     */
    public function orderActionLog($order_id,$action,$note=''){
        $order = M('order')->where(array('order_id'=>$order_id))->find();
        $data['order_id']        = $order_id;
        $data['action_user']     = session('admin_id');
        $data['action_note']     = $note;
        $data['order_status']    = $order['order_status'];
        $data['pay_status']      = $order['pay_status'];
        $data['shipping_status'] = $order['shipping_status'];
        $data['refuse_status']   = $order['refuse_status'];
        $data['log_time']        = time();
        $data['status_desc']     = $action;
        return M('order_action')->add($data);//订单操作记录
    }

    /*
     * 订单拒绝发货申请记录
     */
    public function orderRefuseLog($order_id,$suppliers_id=0,$action,$refuse_status,$note=''){
        $order = M('order')->where(array('order_id'=>$order_id))->find();
        $data['order_id']        = $order_id;
        $data['action_user']     = session('admin_id');
        $data['action_note']     = $note;
        $data['refuse_status']   = $refuse_status;
        $data['log_time']        = time();
        $data['status_desc']     = $action;
        $data['suppliers_id']    = $suppliers_id;
        return M('order_refuse')->add($data);//订单操作记录
    }

    /*
     * 获取订单商品总价格
     */
    public function getGoodsAmount($order_id){
        $sql = "SELECT SUM(goods_num * goods_price) AS goods_amount FROM __PREFIX__order_goods WHERE order_id = {$order_id}";
        $res = DB::query($sql);
        return $res[0]['goods_amount'];
    }

    /**
     * 得到发货单流水号
     */
    public function get_delivery_sn()
    {
//        /* 选择一个随机的方案 */send_http_status('310');
		mt_srand((double) microtime() * 1000000);
        return date('YmdHi') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /*
     * 获取当前可操作的按钮
     */
    public function getOrderButton($order){
        /*
         *  操作按钮汇总 ：付款、设为未付款、确认、取消确认、无效、去发货、确认收货、申请退货
         * 
         */
    	$os = $order['order_status'];    //订单状态
    	$ss = $order['shipping_status']; //发货状态
    	$ps = $order['pay_status'];      //支付状态
        $rs = $order['refuse_status'];   //拒绝发货状态
        $btn = array();
        if($order['pay_code'] == 'cod') { //付款
        	if($os == 0 && $ss == 0){
        		$btn['confirm'] = '确认';
        	}elseif($os == 1 && ($ss == 0 || $ss == 2) && $rs != 1 && $rs != 2){
        		$btn['delivery'] = '去发货';
        		$btn['cancel']   = '取消确认';
                if($rs < 0) $btn['refuse']   = '拒绝发货申请';
        	}elseif($ss == 1 && $os == 1 && $ps == 0){
        		//$btn['pay'] = '付款';
        	}elseif($ps == 1 && $ss == 1 && $os == 1){
        		//$btn['pay_cancel'] = '设为未付款';
        	}
        }else{
        	if($ps == 0 && $os == 0 || $ps == 2){
        		//$btn['pay'] = '付款';
        	}elseif($os == 0 && $ps == 1){
        		//$btn['pay_cancel'] = '设为未付款';
        		$btn['confirm'] = '确认';
        	}elseif($os == 1 && $ps == 1 && ($ss == 0 || $ss == 2) && $rs != 1 && $rs != 2 ){
        		$btn['cancel']   = '取消确认';
        		$btn['delivery'] = '去发货';
                if($rs < 0) $btn['refuse']   = '拒绝发货申请';
        	}
        } 

        if($os == 3 && $ps == 1){
            $btn['confirm'] = '确认';
        }

        if($rs == 1){
            $btn['refuse_yes'] = '同意';
            $btn['refuse_no']  = '驳回';
        }
               
        if($ss == 1 && $os == 1 && $ps == 1){
         //    $btn['delivery_confirm'] = '确认收货';
        	$btn['refund'] = '申请退货';
        }elseif($os == 2 || $os == 4){
        	$btn['refund'] = '申请退货';
        }elseif($os == 3 || $os == 5){
        	$btn['remove'] = '移除';
        }

        if($os != 5){
        	$btn['invalid'] = '无效';
        }
        return $btn;
    }

    // 订单操作 更新状态
    public function orderProcessHandle($order_id,$act,$ext=array()){
    	$updata = array();
    	switch ($act){
    		case 'pay': //付款
               	$order_sn = M('order')->where("order_id = $order_id")->getField("order_sn");
                update_pay_status($order_sn,$ext); // 调用确认收货按钮
    			return true;    			
    		case 'pay_cancel': //取消付款
    			$updata['pay_status'] = 0;
    			$this->order_pay_cancel($order_id);
    			return true;
    		case 'confirm': //确认订单
    			$updata['order_status'] = 1;
    			break;
    		case 'cancel': //取消确认
    			$updata['order_status'] = 0;
    			break;
    		case 'invalid': //作废订单
    			$updata['order_status'] = 5;
    			break;
    		case 'remove': //移除订单
    			$this->delOrder($order_id);
    			break;
    		case 'delivery_confirm'://确认收货
    			confirm_order($order_id); // 调用确认收货按钮
    			return true;
            case 'refuse': // 拒绝发货申请
                $updata['refuse_status'] = 1;
                break;
            case 'refuse_yes': // 拒绝发货申请
                $updata['refuse_status'] = 2;
                break;
            case 'refuse_no': // 拒绝发货申请
                $updata['refuse_status'] = 3;
                break;
            case 'refuse_qx': // 拒绝发货申请
                $updata['refuse_status'] = 0;
                break;
    		default:
    			return true;
    	}
    	return M('order')->where("order_id=$order_id")->save($updata);//改变订单状态
    }
    
    
    //管理员取消付款
    function order_pay_cancel($order_id)
    {
    	//如果这笔订单已经取消付款过了
    	$count = M('order')->where("order_id = $order_id and pay_status = 1")->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    	if($count == 0) return false;
    	// 找出对应的订单
    	$order = M('order')->where("order_id = $order_id")->find();
    	// 增加对应商品的库存
        $orderGoodsArr = M('OrderGoods')->where("order_id = $order_id")->select();
    	foreach($orderGoodsArr as $key => $val)
    	{
    		if(!empty($val['spec_key']))// 有选择规格的商品
    		{   // 先到规格表里面增加数量 再重新刷新一个 这件商品的总数量
				$SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
				$specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
				$specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
    			refresh_stock($val['goods_id']);
    		}else{
				$specGoodsPrice = null;
    			M('Goods')->where("goods_id = {$val['goods_id']}")->setInc('store_count',$val['goods_num']); // 增加商品总数量
    		}
    		M('Goods')->where("goods_id = {$val['goods_id']}")->setDec('sales_sum',$val['goods_num']); // 减少商品销售量
			//更新活动商品购买量
			if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
				$GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
				$goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
				$prom = $goodsPromLogic->getPromModel();
				if ($prom['is_end'] == 0) {
					$tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
					M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
					M($tb)->where("id", $val['prom_id'])->setInc('order_num');
				}
			}
    	}
    	// 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
    	M('order')->where("order_id=$order_id")->save(array('pay_status'=>0));
    	update_user_level($order['user_id']);
    	// 记录订单操作日志
    	logOrder($order['order_id'],'订单取消付款','付款取消',$order['user_id']);
    	//分销设置
    	//M('rebate_log')->where("order_id = {$order['order_id']}")->save(array('status'=>0));
    }
    
    /**
     *	处理发货单
     * @param array $data  查询数量
     */
    public function deliveryHandle($data){

		$order = $this->getOrderInfo($data['order_id']);
		$orderGoods = $this->getOrderGoods($data['order_id']);
		$selectgoods = $data['goods'];
        if($data['shipping'] == 1){
            return $this->updateOrderShipping($data,$order);
            exit;
        }
        $arr1 = array("'","&quot;","‘","’","“","”");
        $arr2 = array("","","","","","");
        $data['invoice_no'] = str_replace($arr1,$arr2,$data['invoice_no']);
        $data['invoice_no'] = trim($data['invoice_no']);
        if($data['invoice_no'] == ''){
            return false;
        }

        //添加快递查询信息到快递查询表
        //start
        $getcom_url = config('delivery')['getcom_url'];
        $invoice_key = config('delivery')['key'];

        #根据单号查询快递公司编码（不一定十分准确）
        $reslist = httpRequest($getcom_url."?num=".$data['invoice_no']."&key=".$invoice_key);
        $reslist = json_decode($reslist,true);

        #如果填写的是多个单号，返回的结果将为空，禁止提交
        if(is_array($reslist) && !empty($reslist)){
            $codes_keys = array_keys(config('delivery')['wuliuarr']);
            $shipping_code = '';
            foreach($reslist as $cde){
                if(in_array($cde['comCode'],$codes_keys)){
                    $shipping_code = $cde['comCode'];
                    break;
                }
            }

            #如果通过单号查询到的快递公司编码为空，则单号填写有误，拒绝提交
            if(!$shipping_code) return false;

            $data['shipping_code'] = $shipping_code;

            #快递订阅推送
            $callbackurl = config('delivery')['callbackurl'];
            $post_data["schema"] = 'json';
            $post_data["param"] = '{"company":"'.$data['shipping_code'].'","number":"'.$data['invoice_no'].'","from":"","to":"","key":"'.$invoice_key.'","parameters":{"callbackurl":"'.$callbackurl.'?orderid='.$data['order_id'].'","salt":"'.get_rand_str(15,0,1).'","resultv2":"1"}}';
            #订阅请求地址
            $posturl = 'http://www.kuaidi100.com/poll';
            $o="";
            foreach ($post_data as $k=>$v)
            {
                $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
            }
            $post_data=substr($o,0,-1);
            $kuaidiresult = httpRequest($posturl,'POST',$post_data);
            $kuaidi = @json_decode($kuaidiresult,true);


            $returnCode = $kuaidi['returnCode'];
            $message = $kuaidi['message'];

        }



        $updata['shipping_code'] = $data['shipping_code'];
        $updata['shipping_name'] = $data['shipping_name'];
        M('order')->where(['order_id'=>$data['order_id']])->save($updata); //改变物流信息

		$data['order_sn']       = $order['order_sn'];
		$data['delivery_sn']    = $this->get_delivery_sn();
		$data['zipcode']        = $order['zipcode'];
		$data['user_id']        = $order['user_id'];
		$data['admin_id']       = session('admin_id');
		$data['consignee']      = $order['consignee'];
		$data['mobile']         = $order['mobile'];
		$data['country']        = $order['country'];
		$data['province']       = $order['province'];
		$data['city']           = $order['city'];
		$data['district']       = $order['district'];
		$data['address']        = $order['address'];
		$data['shipping_price'] = $order['shipping_price'];
		$data['create_time']    = time();         
		$did = M('delivery_doc')->add($data);
		$is_delivery = 0;
		foreach ($orderGoods as $k=>$v){
			if($v['is_send'] >= 1){
				$is_delivery++;
			}			
			if($v['is_send'] == 0 && in_array($v['rec_id'],$selectgoods)){
				$res['is_send'] = 1;
				$res['delivery_id'] = $did;
				$r = M('order_goods')->where("rec_id=".$v['rec_id'])->save($res);//改变订单商品发货状态
				$is_delivery++;
			}
		}
		$updata['shipping_time'] = time();
		if($is_delivery == count($orderGoods)){
			$updata['shipping_status'] = 1;
		}else{
			$updata['shipping_status'] = 2;
		}
		M('order')->where("order_id=".$data['order_id'])->save($updata);//改变订单状态
		$s = $this->orderActionLog($order['order_id'],'delivery',$data['note']);//操作日志

        foreach($orderGoods as $key=>$val){
            update_stock_log($order['user_id'], (-1*$val['goods_num']), $val, $order['order_sn']);//库存日志
        }

        #快递推送记录存储
        #张洪凯  2018-11-26
        $kresult = M('delivery_log')->where("order_id=".$data['order_id'])->find();
        if(!$kresult){
            $kdata['order_id'] = $data['order_id'];
            $kdata['shipping_code'] = $data['shipping_code'];
            $kdata['invoice_no'] = $data['invoice_no'];
            $kdata['addtime'] = time();
            $kdata['orderstatus'] =  $returnCode;
            $kdata['ordermessage']  =  $message;
            $kdata['subtime']   =  time();
            M('delivery_log')->save($kdata);
        }


        //2018-10-12 张洪凯 商家发货发送微信模板消息提醒
        $wechat = new \app\common\logic\WxLogic;
        $infoarr = [
            'user_id'       =>  $data['user_id'],
            'order_id'      =>  $data['order_id'],
            'order_sn'      =>  $data['order_sn'],
            'shipping_name' =>  $data['shipping_name'],
            'invoice_no'    =>  $data['invoice_no']
        ];
        $wechat->sendTemplateMsgOnDeliver($infoarr);
		
		//商家发货, 发送短信给客户
		$res = checkEnableSendSms("5");
		if($res && $res['status'] ==1){
		    $user_id = $data['user_id'];
		    $users = M('users')->where('user_id', $user_id)->getField('user_id , nickname , mobile' , true);
		    if($users){
		        $nickname = $users[$user_id]['nickname'];
		        $sender = $users[$user_id]['mobile'];
		        $params = array('user_name'=>$nickname , 'consignee'=>$data['consignee']);
		        $resp = sendSms("5", $sender, $params,'');
		    }
		}
		
		return $s && $r;
    }

    /**
     * 修改订单发货信息
     * @param array $data
     * @param array $order
     * @return bool|mixed
     */
    public function updateOrderShipping($data=[],$order=[]){
        #去除单号两边的空白
        $arr1 = array("'","&quot;","‘","’","“","”");
        $arr2 = array("","","","","","");
        $data['invoice_no'] = str_replace($arr1,$arr2,$data['invoice_no']);
        $data['invoice_no'] = trim($data['invoice_no']);
        if($data['invoice_no'] == ''){
            return false;
        }

        $getcom_url = config('delivery')['getcom_url'];
        $invoice_key = config('delivery')['key'];

        #根据单号查询快递公司编码（不一定十分准确）
        $reslist = httpRequest($getcom_url."?num=".$data['invoice_no']."&key=".$invoice_key);
        $reslist = json_decode($reslist,true);

        #如果填写的是多个单号，返回的结果将为空，禁止提交
        if(is_array($reslist) &&  !empty($reslist)){
            $codes_keys = array_keys(config('delivery')['wuliuarr']);
            $shipping_code = '';
            foreach($reslist as $cde){
                if(in_array($cde['comCode'],$codes_keys)){
                    $shipping_code = $cde['comCode'];
                    break;
                }
            }

            #如果通过单号查询到的快递公司编码为空，则单号填写有误，拒绝提交
            if(!$shipping_code) return false;

            $data['shipping_code'] = $shipping_code;
            #快递推送
            #2018-11-27  张洪凯
            $callbackurl = config('delivery')['callbackurl'];
            $post_data["schema"] = 'json';
            $post_data["param"] = '{"company":"'.$data['shipping_code'].'","number":"'.$data['invoice_no'].'","from":"","to":"","key":"'.$invoice_key.'","parameters":{"callbackurl":"'.$callbackurl.'?orderid='.$data['order_id'].'","salt":"'.get_rand_str(15,0,1).'","resultv2":"1"}}';
            #订阅请求地址
            $posturl = 'http://www.kuaidi100.com/poll';
            $o="";
            foreach ($post_data as $k=>$v)
            {
                $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
            }
            $post_data=substr($o,0,-1);
            $kuaidiresult = httpRequest($posturl,'POST',$post_data);
            $kuaidi = @json_decode($kuaidiresult,true);


            $returnCode = $kuaidi['returnCode'];
            $message = $kuaidi['message'];
        }


        $updata['shipping_code'] = $data['shipping_code'];
        $updata['shipping_name'] = $data['shipping_name'];
        M('order')->where(['order_id'=>$data['order_id']])->save($updata); //改变物流信息
        $updata['invoice_no'] = $data['invoice_no'];
        $updata['note']       = $data['note'];
        $delivery_res = db('delivery_doc')->where(['order_id'=>$data['order_id']])->save($updata);  //改变售后的信息
        if ($delivery_res !== false){

            #快递推送记录存储
            #张洪凯  2018-11-27
            $kresult = M('delivery_log')->where("order_id=".$data['order_id'])->find();
            $kdata['shipping_code'] = $data['shipping_code'];
            $kdata['invoice_no'] = $data['invoice_no'];
            $kdata['orderstatus'] =  $returnCode;
            $kdata['ordermessage']  =  $message;
            $kdata['subtime']   =  time();
            if(!$kresult){
                $kdata['order_id'] = $data['order_id'];
                $kdata['addtime'] = time();
                M('delivery_log')->save($kdata);
            }else{
                M('delivery_log')->where(['id'=>$kresult['id']])->update($kdata);
            }

            return $this->orderActionLog($order['order_id'],'订单修改发货信息',$data['note']);//操作日志
        }else{
            return false;
        }

    }

    /**
     * 获取地区名字
     * @param int $p
     * @param int $c
     * @param int $d
     * @return string
     */
    public function getAddressName($p=0,$c=0,$d=0){
        $p = M('region')->where(array('id'=>$p))->field('name')->find();
        $c = M('region')->where(array('id'=>$c))->field('name')->find();
        $d = M('region')->where(array('id'=>$d))->field('name')->find();
        return $p['name'].','.$c['name'].','.$d['name'].',';
    }

    /**
     * 删除订单
     */
    function delOrder($order_id){
        $order = M('order')->where(array('order_id'=>$order_id))->find();
        if(empty($order)){
            return ['status'=>-1,'msg'=>'订单不存在'];
        };
    	$del_order = M('order')->where(array('order_id'=>$order_id))->delete();
    	$del_order_goods = M('order_goods')->where(array('order_id'=>$order_id))->delete();
    	if(empty($del_order) && empty($del_order_goods)){
            return ['status'=>-1,'msg'=>'订单删除失败'];
        };
        return ['status'=>1,'msg'=>'删除成功'];
    }

	/**
	 * 当订单里商品都退货完成，将订单状态改成关闭
	 * @param $order_id
	 */
	function closeOrderByReturn($order_id)
	{
		$order_goods_list = Db::name('order_goods')->where(['order_id' => $order_id])->select();
		$order_goods_count = count($order_goods_list);
		$order_goods_return_count = 0;//退货个数
		for ($i = 0; $i < $order_goods_count; $i++) {
			if ($order_goods_list[$i]['is_send'] == 3) {
				$order_goods_return_count++;
			}
		}
		if ($order_goods_count == $order_goods_return_count) {
			 $res = Db::name('order')->where(['order_id' => $order_id])->update(['order_status' => 5]);
            if(!$res){
                return false;
            }
		}
        return true;
	}

    /**
     * 退货，取消订单，处理优惠券
     * @param $return_info
     */
    public function disposereRurnOrderCoupon($return_info){
        $coupon_list = M('coupon_list')->where(['uid'=>$return_info['user_id'],'order_id'=>$return_info['order_id']])->find();    //有没有关于这个商品的优惠券
        if(!empty($coupon_list)){
            $update_coupon_data = ['status'=>0,'use_time'=>0,'order_id'=>0];
            M('coupon_list')->where(['id'=>$coupon_list['id'],'status'=>1])->save($update_coupon_data);//符合条件的，优惠券就退给他
        }
        //追回赠送优惠券,一般退款才会走这里
        $coupon_info = M('coupon_list')->where(['uid'=>$return_info['user_id'],'get_order_id'=>$return_info['order_id']])->find();
        if(!empty($coupon_info)){
            if($coupon_info['status'] == 1) { //如果优惠券被使用,那么从退款里扣
                $coupon = M('coupon')->where(array('id' => $coupon_info['cid']))->find();
                if ($return_info['refund_money'] > $coupon['money']) {
                    //退款金额大于优惠券金额，先从这里扣
                    $return_info['refund_money'] = $return_info['refund_money'] - $coupon['money'];
                    M('return_goods')->where(['id' => $return_info['id']])->save(['refund_money' => $return_info['refund_money']]);
                }else{
                    $return_info['refund_deposit'] = $return_info['refund_deposit'] - $coupon['money'];
                    M('return_goods')->where(['id' => $return_info['id']])->save(['refund_deposit' => $return_info['refund_deposit']]);
                }
            }else {
                M('coupon_list')->where(array('id' => $coupon_info['id']))->delete();
                M('coupon')->where(array('id' => $coupon_info['cid']))->setDec('send_num');
            }
        }
    }
    
    
    public function getRefundGoodsMoney($return_goods){
    	$order_goods = M('order_goods')->where(array('rec_id'=>$return_goods['rec_id']))->find();
    	if($return_goods['is_receive'] == 1){
    		if($order_goods['give_integral']>0){
    			$user = get_user_info($return_goods['user_id']);
    			if($order_goods['give_integral']>$user['pay_points']){
    				//积分被使用则从退款金额里扣
    				$return_goods['refund_money'] = $return_goods['refund_money'] - $order_goods['give_integral']/100;
    			}
    		}
    	    $coupon_info = M('coupon_list')->where(array('uid'=>$return_goods['user_id'],'get_order_id'=>$return_goods['order_id']))->find();
    		if(!empty($coupon_info)){
    			if($coupon_info['status'] == 1) { //如果优惠券被使用,那么从退款里扣
    				$coupon = M('coupon')->where(array('id' => $coupon_info['cid']))->find();
    				if ($return_goods['refund_money'] > $coupon['money']) {
    					$return_goods['refund_money'] = $return_goods['refund_money'] - $coupon['money'];//退款金额大于优惠券金额
    				}
    			}
    		}
    	}
    	return $return_goods['refund_money'];
    }
}