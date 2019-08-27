<?php
/**
 * tpshop 微信支付插件
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */

/**
 * 支付 逻辑定义
 * Class 
 * @package Home\Payment
 */

class weixin
{
    /**
     * 析构流函数
     */
    public function  __construct()
    {
        require_once("lib/WxPay.Api.php"); // 微信扫码支付demo 中的文件         
        require_once("example/WxPay.NativePay.php");
        require_once("example/WxPay.JsApiPay.php");

        $paymentPlugin = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化  
    #    dump($config_value);die;      
        WxPayConfig::$appid = $config_value['appid']; // * APPID：绑定支付的APPID（必须配置，开户邮件中可查看）
        WxPayConfig::$mchid = $config_value['mchid']; // * MCHID：商户号（必须配置，开户邮件中可查看）
        WxPayConfig::$smchid = isset($config_value['smchid']) ? $config_value['smchid'] : ''; // * SMCHID：服务商商户号（必须配置，开户邮件中可查看）
        WxPayConfig::$key = $config_value['key']; // KEY：商户支付密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）
        WxPayConfig::$appsecret = $config_value['appsecret']; // 公众帐号secert（仅JSAPI支付的时候需要配置)，                                      
    }    
    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $config    支付方式信息
     */
    function get_code($order, $config)
    {
        $notify_url = SITE_URL . '/index.php/Home/Payment/notifyUrl/pay_code/weixin'; // 接收微信支付异步通知回调地址，通知url必须为直接可访问的url，不能携带参数。

        if(stripos($order['order_sn'],'midou')!==false){
            $rand_str =  rand(100000,999999);     //米豆商城，增加了3个字符
        }else{
            $rand_str =  time();
        }
        
        $input = new WxPayUnifiedOrder();
        $input->SetBody($config['body']); // 商品描述
        $input->SetAttach("weixin"); // 附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
        $input->SetOut_trade_no($order['order_sn'] . $rand_str); // 商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号
        $input->SetTotal_fee($order['order_amount'] * 100); // 订单总金额，单位为分，详见支付金额
        $input->SetNotify_url($notify_url); // 接收微信支付异步通知回调地址，通知url必须为直接可访问的url，不能携带参数。
        $input->SetTrade_type("NATIVE"); // 交易类型   取值如下：JSAPI，NATIVE，APP，详细说明见参数规定    NATIVE--原生扫码支付
        $input->SetProduct_id("123456789"); // 商品ID trade_type=NATIVE，此参数必传。此id为二维码中包含的商品ID，商户自行定义。
        $notify = new NativePay();
        $result = $notify->GetPayUrl($input); // 获取生成二维码的地址
       /* dump($result);
        die;*/
        $url2 = $result["code_url"];
        if (empty($url2))
            return '没有获取到支付地址, 请检查支付配置' . print_r($result, true);
        return '<img alt="模式二扫码支付" src="/index.php?m=Home&c=Index&a=qr_code&data='.urlencode($url2).'" style="width:110px;height:110px;"/>';
    }

    /**
     * 服务器点对点响应操作给支付接口方调用
     * 
     */
    function response()
    {                        
        require_once("example/notify.php");  
        $notify = new PayNotifyCallBack();
        $notify->Handle(false);       
    }
    
    /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2()
    {
        // 微信扫码支付这里没有页面返回
    }

    function getJSAPI($order)
    {
    	if(stripos($order['order_sn'],'recharge') !== false){
            $rand_str = time();
    	#	$go_url = U('Mobile/User/points',array('type'=>'recharge'));
            $go_url = U('/Mobile/User');
    		$back_url = U('/Mobile/User/recharge_pay',array('order_id'=>$order['order_id']));
    	}elseif(stripos($order['order_sn'],'midou') !== false){
            $rand_str =rand(100000,999999);
            $go_url = U('/MobileRed/Order/order_detail',array('id'=>$order['order_id']));
            $back_url = U('/MobileRed/Cart/cart4',array('order_id'=>$order['order_id']));
        }else{
            $rand_str = time();
    		$go_url = U('Mobile/Order/order_detail',array('id'=>$order['order_id']));
    		$back_url = U('Mobile/Cart/cart4',array('order_id'=>$order['order_id']));
    	}
        //①、获取用户openid
        $tools = new JsApiPay();
        //$openId = $tools->GetOpenid();
        $openId = $_SESSION['openid'];
        //②、统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody("支付订单：".$order['order_sn']);
        $input->SetAttach("weixin");
        $input->SetOut_trade_no($order['order_sn'].$rand_str);
        $input->SetTotal_fee($order['order_amount']*100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag("tp_wx_pay");
        $input->SetNotify_url(SITE_URL.'/index.php/Home/Payment/notifyUrl/pay_code/weixin');
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        $order2 = WxPayApi::unifiedOrder($input);
        //echo '<font color="#f00"><b>统一下单支付单信息</b></font><br/>';
        //printf_info($order);exit;
        $jsApiParameters = $tools->GetJsApiParameters($order2);


        $html = <<<EOF
	<script type="text/javascript">
	//调用微信JS api 支付
	function jsApiCall()
	{
		WeixinJSBridge.invoke(
			'getBrandWCPayRequest',$jsApiParameters,
			function(res){
				//WeixinJSBridge.log(res.err_msg);
				 if(res.err_msg == "get_brand_wcpay_request:ok") {
				    location.href='$go_url';
				 }else{
				 	alert(res.err_code+res.err_desc+res.err_msg);
				    location.href='$back_url';
				 }
			}
		);
	}

	function callpay()
	{
		if (typeof WeixinJSBridge == "undefined"){
		    if( document.addEventListener ){
		        document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
		    }else if (document.attachEvent){
		        document.attachEvent('WeixinJSBridgeReady', jsApiCall);
		        document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
		    }
		}else{
		    jsApiCall();
		}
	}
	callpay();
	</script>
EOF;
        
    return $html;
    }

    function TKgetJSAPI($order)
    {
        if(stripos($order['paid_sn'],'staff_paid') !== false){
           /* $go_url = U('/Staff/Paid/Index',array('pay_status'=>'999'));
            $back_url = U('/Staff/Paid/Index');*/
             $go_url = U('/Staff/Paid/pay_status',['paid_sn'=>$order['paid_sn'],'id'=>$order['id']]);
             $back_url = U('/Staff/Paid/pay_status',['paid_sn'=>$order['paid_sn'],'id'=>$order['id']]);
        }else{
            $go_url = U('/Mobile/User/pay_status',array('paid_sn'=>$order['paid_sn']));
            $back_url =  U('/Mobile/User/pay_status',array('paid_sn'=>$order['paid_sn'],'recommend_id'=>$order['staff_id']));
        }
    #     dump($order);exit;  
        //①、获取用户openid
        $tools = new JsApiPay();
        //$openId = $tools->GetOpenid();
        $openId = $_SESSION['openid'];
        //②、统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody("支付订单：".$order['paid_sn']);
        $input->SetAttach("weixin");
        $input->SetOut_trade_no($order['paid_sn'].rand(1111,9999));
        $input->SetTotal_fee($order['money']*100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag("tp_wx_pay");
        $input->SetNotify_url(SITE_URL.'/index.php/Home/Payment/notifyUrl/pay_code/weixin');
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        $order2 = WxPayApi::unifiedOrder($input);
        //echo '<font color="#f00"><b>统一下单支付单信息</b></font><br/>';
   #     dump($order);exit;  
        $jsApiParameters = $tools->GetJsApiParameters($order2);
        ///file_put_contents("./public/pay.txt",$jsApiParameters.", openId:".$openId.", SetOut_trade_no:".$order['paid_sn']." order2:".json_encode($order2));
        $html = <<<EOF
    <script type="text/javascript">
    //调用微信JS api 支付
    function jsApiCall()
    {
        WeixinJSBridge.invoke(
            'getBrandWCPayRequest',$jsApiParameters,
            function(res){
                //WeixinJSBridge.log(res.err_msg);
                 if(res.err_msg == "get_brand_wcpay_request:ok") {
                    location.href='$go_url';
                 }else{
                    alert(res.err_code+res.err_desc+res.err_msg);
                    location.href='$back_url';
                 }
            }
        );
    }

    function callpay()
    {
        if (typeof WeixinJSBridge == "undefined"){
            if( document.addEventListener ){
                document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
            }else if (document.attachEvent){
                document.attachEvent('WeixinJSBridgeReady', jsApiCall);
                document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
            }
        }else{
            jsApiCall();
        }
    }
    callpay();
    </script>
EOF;
        
    return $html;
    }

    





    // 微信提现批量转账
    function transfer($data){
        header("Content-type: text/html; charset=utf-8");
        exit("请联系TPshop官网客服购买高级版支持此功能");
    }
    //企业付款到银行卡
    function tk_transfer_yhk($data){
        require_once("rsa.php");
        $rsa = new RSA(file_get_contents(dirname(THINK_PATH).'/plugins/payment/weixin/cert/tkp.pem'),'');
        $wxchat['mchid'] = WxPayConfig::$smchid;
        $wxchat['api_cert'] = '/plugins/payment/weixin/cert/a_cert.pem';       //apiclient_cert.pem
        $wxchat['api_key'] = '/plugins/payment/weixin/cert/a_key.pem'; //apiclient_key
        $wxchat['api_ca'] = '/plugins/payment/weixin/cert/rootca.pem';
        $webdata = array(
            'mch_id'     => $wxchat['mchid'],
            'partner_trade_no'=>$data['partner_trade_no'], //商户订单号，需要唯一
            'nonce_str' => md5(time().rand(1000,9999)),
            //'device_info' => '1000',
            'enc_bank_no' => $rsa->public_encrypt($data['enc_bank_no']),//转账用户的openid
            'enc_true_name'=> $rsa->public_encrypt($data['enc_true_name']),       
            'bank_code' =>  $data['bank_code'],
            'amount'   => $data['money'] * 100,     //付款金额：RMB分
            'desc' => empty($data['desc']) ? '米豆薯线下返款' : $data['desc'],
        );
     //   dump($webdata);die;
        foreach ($webdata as $k => $v) {
            $tarr[] =$k.'='.$v;
        }
        sort($tarr);
        $sign = implode($tarr, '&');
        $sign .= '&key='.WxPayConfig::$key;
        $webdata['sign']=strtoupper(md5($sign));
        $wget = $this->array2xml($webdata);
        $pay_url = 'https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank';
        $res = $this->http_post($pay_url, $wget, $wxchat);
        if(!$res){
            return array('status'=>1, 'msg'=>"微信服务器未响应，请在提现记录中重新点击提现申请" );
        }
        $content = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);
        if(strval($content->return_code) == 'FAIL'){
            return array('status'=>1, 'msg'=>strval($content->return_msg));
        }
        if(strval($content->result_code) == 'FAIL'){
            return array('status'=>1, 'msg'=>strval($content->err_code).':'.strval($content->err_code_des));
        }
        $rdata = array(
            'mchid'            => strval($content->mch_id),
            'partner_trade_no' => strval($content->partner_trade_no),
            'result_code'      => strval($content->result_code),
            'amount'       => strval($content->amount),
            'nonce_str'     => strval($content->nonce_str),
            'payment_no'     => strval($content->payment_no),
            'cmms_amt'     => strval($content->cmms_amt),
        );
        return $rdata;
    }
    /*rsa 加密*/
   /* function getpubkey(){
        $wxchat['api_cert'] = '/plugins/payment/weixin/cert/a_cert.pem';       //apiclient_cert.pem
        $wxchat['api_key'] = '/plugins/payment/weixin/cert/a_key.pem'; //apiclient_key
        $param = [
            'mch_id'    => WxPayConfig::$smchid,//商户ID
            'nonce_str' => md5(time()),
            'sign_type' => 'MD5'
        ];
        foreach ($param as $k => $v) {
            $tarr[] =$k.'='.$v;
        }
        sort($tarr);
        $sign = implode($tarr, '&');
        $sign .= '&key='.WxPayConfig::$key;
        $param['sign']=strtoupper(md5($sign));
        $wget = $this->array2xml($param);
      
        $url = 'https://fraud.mch.weixin.qq.com/risk/getpublickey';
        $res = $this->http_post($url, $wget, $wxchat);
    //    
        return $res;
    }*/


    #企业付款到零钱
    function tk_transfer_lq($data){
        $webdata = array(
                'mch_appid' => WxPayConfig::$appid,
                'mchid'     => WxPayConfig::$smchid,
                'nonce_str' => md5(time()),
                //'device_info' => '1000',
                'partner_trade_no'=>$data['partner_trade_no'], //商户订单号，需要唯一
                'openid' => $data['openid'],//转账用户的openid
                'check_name'=> 'NO_CHECK', //OPTION_CHECK不强制校验真实姓名, FORCE_CHECK：强制 NO_CHECK：
                //'re_user_name' => 'jorsh', //收款人用户姓名
                'amount' => $data['money'] * 100, //付款金额单位为分
                'desc'   => empty($data['desc'])? '米豆薯线下返款' : $data['desc'],
                'spbill_create_ip' => request()->ip(),
        );
        $pay_url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        $res = $this->tk_send_data($webdata,$pay_url);
        if(!$res){
            return array('status'=>1, 'msg'=>"微信服务器未响应，请在提现记录中重新点击提现申请" );
        }
        $content = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);
        if(strval($content->return_code) == 'FAIL'){
            return array('status'=>1, 'msg'=>strval($content->return_msg));
        }
        if(strval($content->result_code) == 'FAIL'){
            return array('status'=>1, 'msg'=>strval($content->err_code).':'.strval($content->err_code_des));
        }
        $rdata = array(
            'mch_appid'        => strval($content->mch_appid),
            'mchid'            => strval($content->mchid),
            'device_info'      => strval($content->device_info),
            'nonce_str'        => strval($content->nonce_str),
            'result_code'      => strval($content->result_code),
            'partner_trade_no' => strval($content->partner_trade_no),
            'payment_no'       => strval($content->payment_no),
            'payment_time'     => strval($content->payment_time),
        );
        return $rdata;
    }

    function query_bank($partner_trade_no){
        $url = 'https://api.mch.weixin.qq.com/mmpaysptrans/query_bank';
        $webdata = array(
            'mch_id'     => WxPayConfig::$smchid,
            'nonce_str' => md5(time()),
            'partner_trade_no'=>$partner_trade_no, //商户订单号，需要唯一
        );
        $res = $this->tk_send_data($webdata,$url);
        if(!$res){
            return array('status'=>1, 'msg'=>"微信服务器未响应，请在提现记录中重新点击提现申请" );
        }
        $content = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);
        if(strval($content->return_code) == 'FAIL'){
            return array('status'=>1, 'msg'=>strval($content->return_msg));
        }
        if(strval($content->result_code) == 'FAIL'){
            return array('status'=>1, 'msg'=>strval($content->err_code).':'.strval($content->err_code_des));
        }
        $amount = intval($content->amount) / 100;
        $rdata    =   [
            'result_code'=>strval($content->return_code),
            'err_code'=>strval($content->err_code),
            'err_code_des'=>strval($content->err_code_des),
            'status'=>strval($content->status),
            'reason'=>strval($content->reason),
        ];
        return $rdata;
    }

    function gettransfer($partner_trade_no){
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/gettransferinfo';
        $webdata = array(
            'nonce_str' => md5(time()),
            'partner_trade_no'=>$partner_trade_no, //商户订单号，需要唯一
            'mch_id'     => WxPayConfig::$smchid,   //1507332611
            'appid'=>WxPayConfig::$appid,       //wx6ab3b8d3038ccd2a     
        );
        $res = $this->tk_send_data($webdata,$url);
        if(!$res){
            return array('status'=>1, 'msg'=>"微信服务器未响应，请在提现记录中重新点击提现申请" );
        }
        $content = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);
        if(strval($content->return_code) == 'FAIL'){
            return array('status'=>1, 'msg'=>strval($content->return_msg));
        }
        if(strval($content->result_code) == 'FAIL'){
            return array('status'=>1, 'msg'=>strval($content->err_code).':'.strval($content->err_code_des));
        }
        
        $payment_amount = $content->payment_amount / 100;
        $rdata    =   [
            'partner_trade_no'=>strval($content->partner_trade_no),
            'status'=>strval($content->status),
            'reason'=>strval($content->reason),
            'payment_amount'=>strval($payment_amount),
            'transfer_time'=>tk_money_format($content->transfer_time),
            'payment_time'=>strval($content->payment_time),
        ];
        return $rdata;
    }

    

    function tk_send_data($webdata,$url){
        $wxchat['appid'] = WxPayConfig::$appid;
        $wxchat['mchid'] = WxPayConfig::$smchid;
        $wxchat['api_cert'] = '/plugins/payment/weixin/cert/a_cert.pem';       //apiclient_cert.pem
        $wxchat['api_key'] = '/plugins/payment/weixin/cert/a_key.pem'; //apiclient_key
        $wxchat['api_ca'] = '/plugins/payment/weixin/cert/rootca.pem';
        foreach ($webdata as $k => $v) {
            $tarr[] =$k.'='.$v;
        }
        sort($tarr);
        $sign = implode($tarr, '&');
        $sign .= '&key='.WxPayConfig::$key;
        $webdata['sign']=strtoupper(md5($sign));
        $wget = $this->array2xml($webdata);
        return $this->http_post($url, $wget, $wxchat);
    }
    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    function array2xml($arr, $level = 1) {
    	$s = $level == 1 ? "<xml>" : '';
    	foreach($arr as $tagname => $value) {
    		if (is_numeric($tagname)) {
    			$tagname = $value['TagName'];
    			unset($value['TagName']);
    		}
    		if(!is_array($value)) {
    			$s .= "<{$tagname}>".(!is_numeric($value) ? '<![CDATA[' : '').$value.(!is_numeric($value) ? ']]>' : '')."</{$tagname}>";
    		} else {
    			$s .= "<{$tagname}>" . $this->array2xml($value, $level + 1)."</{$tagname}>";
    		}
    	}
    	$s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
    	return $level == 1 ? $s."</xml>" : $s;
    }
    
    function http_post($url, $param, $wxchat) {
    	$oCurl = curl_init();
    	if (stripos($url, "https://") !== FALSE) {
    		curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
    		curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
    	}
    	if (is_string($param)) {
    		$strPOST = $param;
    	} else {
    		$aPOST = array();
    		foreach ($param as $key => $val) {
    			$aPOST[] = $key . "=" . urlencode($val);
    		}
    		$strPOST = join("&", $aPOST);
    	}
    	curl_setopt($oCurl, CURLOPT_URL, $url);
    	curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($oCurl, CURLOPT_POST, true);
    	curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        curl_setopt($oCurl,CURLOPT_TIMEOUT, 10);
    	if($wxchat){
    		curl_setopt($oCurl,CURLOPT_SSLCERT,dirname(THINK_PATH).$wxchat['api_cert']);
    		curl_setopt($oCurl,CURLOPT_SSLKEY,dirname(THINK_PATH).$wxchat['api_key']);
    		curl_setopt($oCurl,CURLOPT_CAINFO,dirname(THINK_PATH).$wxchat['api_ca']);
    	}
    	$sContent = curl_exec($oCurl);
    	$aStatus = curl_getinfo($oCurl);
     #   dump($aStatus);die;
    	curl_close($oCurl);
    	if (intval($aStatus["http_code"]) == 200) {
    		return $sContent;
    	} else {
    		return false;
    	}
    }
    
     // 微信订单退款原路退回
    public function payment_refund($data){
        if(!empty($data["transaction_id"])){
            $input = new WxPayRefund();
            $input->SetTransaction_id($data["transaction_id"]);
            $input->SetTotal_fee($data["total_fee"]*100);
            $input->SetRefund_fee($data["refund_fee"]*100);
            $input->SetOut_refund_no(WxPayConfig::$mchid.date("YmdHis"));
            $input->SetOp_user_id(WxPayConfig::$mchid);
            return WxPayApi::refund($input);
        }else{
            return false;
        }
    }


}