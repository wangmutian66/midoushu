<?php
namespace Withdraw;

use think\Db;
/*
@author 	王文凯
@version 	2.0
*/
class StoreWithdraw
{
	protected $weixin_obj;
	protected $pay_money = 0;
	protected $partner_trade_no = '';
	protected $store_info = [];
	protected $is_card = 0;		//是否使用银行卡付款，默认为零钱付款，1的时候是银行卡付款
	protected $open_id = '';
	protected $log_id = 0;			// store_withdraw_log 记录表的ID 
	public function __construct(){
		include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
        $this->weixin_obj = new \weixin();
	}

	#设置支付金额
	public function setPayMoney($PayMoney)
    {
        $this->pay_money = $PayMoney;
    }
    #设置订单号码
    public function setPartnerTradeNo($PartnerTradeNo){
    	$this->partner_trade_no = $PartnerTradeNo;
    }
    #设置实体店信息
    public function setStoreInfo($StoreInfo){
    	$this->store_info = $StoreInfo;
        $where['mobile']    =   ['eq',$this->store_info['receivable_mobile']];
        $open_id = db('users u')->join('oauth_users ou','ou.user_id = u.user_id')->where($where)->value('ou.openid');
        $open_id && $this->open_id = $open_id;
    }

    public function setIsCard($IsCard=0){
    	$this->is_card = $IsCard;
    }
    /*
	实体店扣款
    */
    public function withdrawDeduction(){
    	$store_data['store_money']  =   ['exp'," store_money - {$this->pay_money}"];
        $store_data['v']    =   ['exp','v +1'];
        $store_where['cid'] =   $this->store_info['cid'];
        $store_where['v']   =   ['eq',$this->store_info['v']];
        $store_result = db::name('company')->where($store_where)->save($store_data);
       	return $store_result;
    }

    /*支付最后一步*/
    public function withdrawPay(){
    	if($this->partner_trade_no){
        	$this->store_withdraw_log_id = db::name('store_withdraw_log')->where('partner_trade_no',$this->partner_trade_no)->getField('id');
        }else{
        	/*设置支付单号*/
        	$this->setPartnerTradeNo($this->get_store_withdraw_no());
            $this->store_withdraw_log_id = $this->create_order();
        }
        if($this->is_card == 0){
        	$res = $this->store_withdraw_pay_lq();
        }else{
			$res = $this->store_withdraw_pay_yhk();
        }
    	return $res;
    }


    /*
	* 支付到零钱
	* @access public 
	* @return json array 
	*/
    function store_withdraw_pay_lq(){
        $weixinpay['openid']    =   $this->open_id;
        $weixinpay['money']     =   $this->pay_money;
        $weixinpay['partner_trade_no']  =   $this->partner_trade_no;
        $r = $this->weixin_obj->tk_transfer_lq($weixinpay);
        if($r['status'] == 1){
            $res['status']  =   0;
            $res['info']    =    "微信系统提示：{$r['msg']}  请在提现记录中重新申请";
        }else{
            if($r['result_code'] == 'SUCCESS'){
                $save_data['result_code']   =   $r['result_code'];
                $save_data['payment_no']   =   $r['payment_no'];
                $save_data['payment_time']   =   $r['payment_time'];
                db::name('store_withdraw_log')->where('id',$this->store_withdraw_log_id)->save($save_data);
                $res['status'] = 1;
                $res['info']   =   '提现成功！请在微信零钱中查询资金';
            }else{
                $res['status'] =   0;
                $res['info']   =   '系统繁忙，请在提现记录中重新申请';
            }
        }
        return $res;
    }

	/*
	* 支付到银行卡
	* @access public 
	* @param array $money 付款金额
	* @param array $store_info 实体店信息 
	* @param array $partner_trade_no 付款单号 
	* @return json array 
	*/
	function store_withdraw_pay_yhk(){
        $weixinpay['bank_code'] =   $this->store_info['bank_code'];                 //收款方开户行
        $weixinpay['enc_bank_no'] =   $this->store_info['enc_bank_no'];             //收款方银行卡号
        $weixinpay['enc_true_name'] =   $this->store_info['enc_true_name'];         //收款方用户名
        $weixinpay['money']    =   $this->pay_money;
        $weixinpay['partner_trade_no'] =   $this->partner_trade_no;
        $r = $this->weixin_obj->tk_transfer_yhk($weixinpay);
        if($r['status'] == 1){
            $res['status']  =   0;
            $res['info']    =    "微信系统提示：{$r['msg']}  请在提现记录中重新申请";
        }else{
            if($r['result_code'] == 'SUCCESS'){
                $save_data['cmms_amt']   =   $r['cmms_amt'] / 100;
                $save_data['payment_no']   =   $r['payment_no'];
                $save_data['result_code']   =   $r['result_code'];
                
                db::name('store_withdraw_log')->where('id',$this->store_withdraw_log_id)->save($save_data);
                \think\cache::rm("store_{$this->store_id}");
                $res['status'] = 1;
                $res['info']   =   '微信侧受理成功！请等待银行处理该申请';
            }else{
                $res['status'] =   0;
                $res['info']   =   '系统繁忙，请在提现记录中重新申请';
            }
        }
        return $res;
    }


    /*
	* 支付检测
	* @access public 
	* @return boolean 
	*/
    function payCheck(){
    	if($this->pay_money < 1){
    		$res['status']  =   0;
            $res['info']    =   '支付金额不能小于1元';
            return $res;
    	}
    	if($this->is_card == 0){
	        if(empty($this->open_id)){
	            $res['status'] =   0;
	            $res['info']   =  "请使用手机号码 {$this->store_info['receivable_mobile']} 在本公众号内注册，否则无法打款";
	            return $res;
	        }
    	}else{
    		if(empty($this->store_info)){
	    		$res['status']  =   0;
	            $res['info']    =   '实体店信息不能为空';
	            return $res;
	    	}
	    	if($this->store_info['enc_true_name'] == ''){
	            $res['status']  =   0;
	            $res['info']    =   '收款方用户名不存在';
	            return $res;
	        }
	        if($this->store_info['enc_bank_no'] == ''){
	            $res['status']  =   0;
	            $res['info']    =   '收款方银行卡号不存在';
	            return $res;
	        }
	        if($this->store_info['bank_code'] == ''){
	            $res['status']  =   0;
	            $res['info']    =   '收款方开户行不存在';
	            return $res;
	        }
    	}
    	$res['status'] = 1;
    	$res['info']	=	'检测成功';
    	return $res;
    }
	
    

    /*生成商户订单号*/
    function get_store_withdraw_no(){
        while(true){
	        $store_withdraw_no = time(). rand(1000,9999);          
	        $store_withdraw_count = db::name('store_withdraw_log')->where('partner_trade_no',$store_withdraw_no)->count();
	        if($store_withdraw_count == 0)
	            break;
	    }
	    return $store_withdraw_no;
    }
    /*创建订单*/
    function create_order(){
        $insert_data['store_id']  =   $this->store_info['cid'];		
        $insert_data['store_name']    =   $this->store_info['cname'];
        $insert_data['txje']  =  $this->pay_money;
        $insert_data['create_time']   =   NOW_TIME;
        $insert_data['nonce_str'] =  md5(time().rand(1000,9999));
        $insert_data['partner_trade_no'] = $this->partner_trade_no;
        $insert_data['is_card']   =   $this->is_card;
        if($this->is_card == 0){
        	$insert_data['receivable_mobile']     =   $this->store_info['receivable_mobile'];
            $insert_data['cmms_amt']    =   0;
        }else{
            $insert_data['enc_true_name']   =   $this->store_info['enc_true_name'];
            $insert_data['enc_bank_no'] =    $this->store_info['enc_bank_no'];
            $insert_data['bank_code']   =   $this->store_info['bank_code'];
            $insert_data['cmms_amt']    =   $this->calculation_fee($this->pay_money);
        }
        return db::name('store_withdraw_log')->insertGetId($insert_data);
    }


    /*计算手续费*/
    function calculation_fee($money){
        $fee = bcmul($money,0.001,9);
        if($fee < 1){
            $fee = 1;
        }elseif($fee > 25){
            $fee = 25;
        }
        return $fee;
    }

    /*
    获取订单状态
    */
    function get_weixin_status($param){
        if(isset($param['id'])){
            $where['id']    =   ['eq',$param['id']];
        }
        $param['store_id'] && $where['store_id']  =   ['eq',$param['store_id']];
        $result = db('store_withdraw_log')->cache(true)->where($where)->find();
        
        if($result['partner_trade_no'] && $result['is_card'] == 1){
            $r = $this->weixin_obj->query_bank($result['partner_trade_no']);
            if($r['status'] == 1){
                $res['system_status'] = 0;
                $res['info']  =   $r['msg'];
                return $res;
            }
            $save_data['status']    =   $r['status'];
            $save_data['reason']    =   $r['reason'];
            db('store_withdraw_log')->where('partner_trade_no',$result['partner_trade_no'])->save($save_data);
           
            $r['system_status'] =   1;
            $r['status']    =   query_bank_status($r['status']);
            return $r;
        }else{
            $r = $weixin_obj->gettransfer($result['partner_trade_no']);
            if($r['status'] == 1){
                $res['system_status'] = 0;
                $res['info']  =   $r['msg'];
                return $res;
            }
            $r['status']    =   query_change_status($r['status']);
            return $r;
        }
    }


}