<?php
/**
 * 支付宝插件
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 */

//namespace plugins\payment\alipay;
 
use think\Model; 
use think\Request;

/**
 * 支付 逻辑定义
 * Class AlipayPayment
 * @package Home\Payment
 */

class alipay extends Model
{    
    public $tableName = 'plugin'; // 插件表        
    public $alipay_config = array();// 支付宝支付配置参数
    
    /**
     * 析构流函数
     */
    public function  __construct() {           
        parent::__construct();     
        unset($_GET['pay_code']);     // 删除掉 以免被进入签名
        unset($_REQUEST['pay_code']); // 删除掉 以免被进入签名

        $str = 'MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQDig2OB6oIgxdsZK40frnWCvO6hpa9GFBhPdORlSLrq3XRHrfi3EOKTodCASh19pxwD4nyOsP6aceubWo4oNuoPwyy2kEesW8T/N6x7HU+JRbrzyLi1wlvqLr+uGV6QjKyHIaMj5/cnY8kouUt23ulas5m1zTvo+PzexjIoqx5D06614w6T/hMzEgy5zODEV63fDnY6ogIuRMqQnr8OwlS8CzeAFU4hp+Nq6A5FsrtrGxJme1W0O8pXKd+8dLAz7IvwuMzKvdc0pT50dOEmoBIUxrpl6EX78h9ZKGL/Do7FkS1af+f39yAT2Cj1H2B9znAlRAykDl1pA7nL8tyqoBBdAgMBAAECggEAbUQwNjVnvGN1Q3kMxKGlsgFto7CHEmkTKREcM/eXo0BcnU9A0R5mDg1axOf7vedkzkLtDvA2gU4+91wBg0iqNBxUb2S+DljeeLbkjycefPuUKZFD+Pne2OLgOB2ozlXty+ngBqwZP3kVSn3H93mkW8qTdW2hXsrtQJGfFJsnKFQGNrGeiRmIbaM4cjZMj8VRWZH7mTeNs6TrPXDMvjVtLXXKHvuqqdyjvqKK1GHbfhoidpt39aDiG95nR8L5ei4mNEx47+BzQNPNe+/9oF3VZ3WSzuzRkXvGhSnX5V67b2vDYWkeYrreHMLA+8MxvEJGeXY4iXX8AfWCzx1S+LBRiQKBgQD8jCds8eBIckPysYKLELyDG3b7Z8Ov1OneIlvDEkb7WjcE6u8IDgcvPOQB4fY7aQK9O6KwK7qjCw1vL+f3eqBtyJ872FdzfP6RDt7K6Bqjq1NodJqY5bjsSAMJuy965OwIyBsEAGZpzR2/hjmtL9A25YTZek8J/LondqqXqLYMAwKBgQDlnB9BWJbCSikmu/B+LFfuWSD8LrgcAQElQd6vW5yq/+NDPcshDtLIdlNkzwqL5VnvfKZKaMVe117tsG/BFmQqL6hZxDc8Vgyf5FoBck1ZQBRheEt0D33RtYacAzbmBvhEYt91Zw9mxrqwchYYoJK2gYsAkkSaDSjmPHhN5gw0HwKBgFZZdYGCkjUzHJh2qTPzXQFW+q+rIvWTzwLsrINeVHbMudMsoN4YLcyw/STHpfFaTl11boLd8MqelNFXh/DONNxUpF1J81zBeCkQ9IxcH/+mLLaiZ9mvcjMXzDIflnRmoW2/Pb14hYvXXjyGIGJe3spmX64ca7n2d4/Wuy1vug8RAoGAU+qLQcau0rNn9tAZRQPP3zyT4ZbxksqLMKKyUESbLaP320tqQdq5DqqwL9e7cwWPqQdVfzxrZg4wk444Scl1MjXYwXYE+fg5BPbgLkcoHcZdrIHakcBXq508ZSiHl+pUMiowoSfZaSaYdIQ9ryKAfFM8CilrCSJmID9ZeJ7rNuECgYANJ5bY6LoZNVq4BGtIvBcqVrDKqgQc9k3ob2Jxve/dXR1qQGhmju1S7lTMS8EetJFmK9cfwxIYRgwIPzS9FhI7PO3kRJsbgd15w0eVe30OdQGMTModXq1kz8S0Tg4tw6EGw9lgsAxxHm5jz59bVRa1qsM/GEB2LPmxYNtUbTWovA==';

        $str2 = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuc/RmBoqojJTCpi03As92owHHfVW0J985QIhGmKzq6UUD+UVwowOP+TsnJudzITt1lkcwquckXZusgUKwHbsJHJPFrNJ10FEzuJdkWcybDvl9HFdjLmVAdWPuCVpGU6WDwrqk6FfWgPk67hQMqDdGc4jbCKWvZoY0J8T+TVHSgLqJuoKQx0ISqTWLNll8ObQo9ovDiMnS/7tDxahdO59yNfmBAaiF5UUWu6CmnGWfI0Odq0wLA6Im4KvRIUplIOzCFvKVJDf+EDYSjU0synHbpWiBbQ8BnJNf0VJQO46USzkK49MUfqHzwakRvGQrf19QG/nqhOyMsftCENSpPCO1QIDAQAB';
        
        $paymentPlugin = M('Plugin')->where("code='alipay' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化        

        $this->alipay_config['app_id']               = $config_value['app_id'];                 //应用ID,您的APPID。
        $this->alipay_config['merchant_private_key'] = $str;   //商户私钥
        $this->alipay_config['notify_url']           = SITE_URL.U('Payment/notifyUrl',array('pay_code'=>'alipay')); //服务器异步通知页面路径 //必填，不能修改
        $this->alipay_config['return_url']           = SITE_URL.U('Payment/returnUrl',array('pay_code'=>'alipay'));  //页面跳转同步通知页面路径
        $this->alipay_config['charset']              = "UTF-8";   //编码格式
        $this->alipay_config['sign_type']            = "RSA2";      //签名方式
        $this->alipay_config['gatewayUrl']           = "https://openapi.alipay.com/gateway.do";  //支付宝网关
        $this->alipay_config['alipay_public_key']    = $str2;   //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
        
    }    
    
    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $config_value    支付方式信息
     */
    function get_code($order, $config_value)
    {

            require_once("pagepay/service/AlipayTradeService.php");
            require_once("pagepay/buildermodel/AlipayTradePagePayContentBuilder.php");

            $body = $config_value['body'];
            !$body && $body = "米豆薯商品" ;

            $out_trade_no = trim($order['order_sn']);       //商户订单号，商户网站订单系统中唯一订单号，必填
            $subject      = trim($body);                    //订单名称，必填
            $total_amount = trim($order['order_amount']);   //付款金额，必填
            
            if($order['order_ids'] && $order['order_num']){
                $order_num    = $order['order_num'];            //商品数量
                $order_ids    = $order['order_ids'];            //商品ids
                $passback_params = UrlEncode('order_num='.$order_num.';order_ids='.$order_ids);
            }

            //商品描述，可空
            $body = trim($body);

            //构造参数
            $payRequestBuilder = new AlipayTradePagePayContentBuilder();
            $payRequestBuilder->setBody($body);
            $payRequestBuilder->setSubject($subject);
            $payRequestBuilder->setTotalAmount($total_amount);
            $payRequestBuilder->setOutTradeNo($out_trade_no);
            if($order['order_ids'] && $order['order_num']){
                $payRequestBuilder->setPassbackParams($passback_params);
            }

            $aop = new AlipayTradeService($this->alipay_config);

            /**
             * pagePay 电脑网站支付请求
             * @param $builder 业务参数，使用buildmodel中的对象生成。
             * @param $return_url 同步跳转地址，公网可以访问
             * @param $notify_url 异步通知地址，公网可以访问
             * @return $response 支付宝返回的信息
            */

            $response = $aop->pagePay($payRequestBuilder,$this->alipay_config['return_url'],$this->alipay_config['notify_url']);
            //输出表单
            var_dump($response);
    }

    // 提交退款
    public function payment_refund($data){
            require_once("pagepay/service/AlipayTradeService.php");    
            require_once("pagepay/buildermodel/AlipayTradeRefundContentBuilder.php");  

            //商户订单号，商户网站订单系统中唯一订单号
            //$out_trade_no = trim($data['order_sn']);

            //支付宝交易号
            $trade_no = trim($data['transaction_id']);
            //需要退款的金额，该金额不能大于订单金额，必填
            $refund_amount = trim($data['refund_money']);
            //退款的原因说明
            $refund_reason = trim($data['refund_mark']);
            //标识一次退款请求，同一笔交易多次退款需要保证唯一，如需部分退款，则此参数必传
            $out_request_no = trim($data['out_request_no']);

            //构造参数
            $RequestBuilder = new AlipayTradeRefundContentBuilder();
            //$RequestBuilder->setOutTradeNo($out_trade_no);
            $RequestBuilder->setTradeNo($trade_no);
            $RequestBuilder->setRefundAmount($refund_amount);
            $RequestBuilder->setOutRequestNo($out_request_no);
            $RequestBuilder->setRefundReason($refund_reason);
            $aop = new AlipayTradeService($this->alipay_config);
    
            /**
             * alipay.trade.refund (统一收单交易退款接口)
             * @param $builder 业务参数，使用buildmodel中的对象生成。
             * @return $response 支付宝返回的信息
             */
            $response = $aop->Refund($RequestBuilder);
            // 处理订单
            $result = json_decode(json_encode($response),true);
            if($result['msg'] == 'Success'){
                $transaction_id = $result['trade_no'];
                $refund_money   = $result['refund_fee'];
                $data['refund_time'] = $result['gmt_refund_pay'];
                $data['status']      = 3;
                $order = M('order')->where('transaction_id="'.$transaction_id.'"')->find();
                if($order){
                    M('return_goods')->where('order_id ='.$order['order_id'].' AND order_sn = "'.$order['order_sn'].'" AND refund_money="'.$refund_money.'"')->save($data);
                }
            }
      		return $result;
            //var_dump($response);
    }

    // 米豆专区提交退款
    public function payment_red_refund($data){
            require_once("pagepay/service/AlipayTradeService.php");    
            require_once("pagepay/buildermodel/AlipayTradeRefundContentBuilder.php");  

            //商户订单号，商户网站订单系统中唯一订单号
            //$out_trade_no = trim($data['order_sn']);

            //支付宝交易号
            $trade_no = trim($data['transaction_id']);
            //需要退款的金额，该金额不能大于订单金额，必填
            $refund_amount = trim($data['refund_money']);
            //退款的原因说明
            $refund_reason = trim($data['refund_mark']);
            //标识一次退款请求，同一笔交易多次退款需要保证唯一，如需部分退款，则此参数必传
            $out_request_no = trim($data['out_request_no']);

            //构造参数
            $RequestBuilder = new AlipayTradeRefundContentBuilder();
            //$RequestBuilder->setOutTradeNo($out_trade_no);
            $RequestBuilder->setTradeNo($trade_no);
            $RequestBuilder->setRefundAmount($refund_amount);
            $RequestBuilder->setOutRequestNo($out_request_no);
            $RequestBuilder->setRefundReason($refund_reason);
            $aop = new AlipayTradeService($this->alipay_config);
    
            /**
             * alipay.trade.refund (统一收单交易退款接口)
             * @param $builder 业务参数，使用buildmodel中的对象生成。
             * @return $response 支付宝返回的信息
             */
            $response = $aop->Refund($RequestBuilder);
            // 处理订单
            $result = json_decode(json_encode($response),true);
            if($result['msg'] == 'Success'){
                $transaction_id = $result['trade_no'];
                $refund_money   = $result['refund_fee'];
                $data['refund_time']    = $result['gmt_refund_pay'];
              	$data['status']      = 3;
                $order = M('order_red')->where('transaction_id="'.$transaction_id.'"')->find();
                if($order){
                    M('return_red_goods')->where('order_id ='.$order['order_id'].' AND order_sn = "'.$order['order_sn'].'" AND refund_money='.$refund_money)->save($data);
                }
            } else {
                //echo json_encode($response);
            }
            //var_dump($response);
    }

     // 一乡一品提交退款
    public function payment_yxyp_refund($data){
            require_once("pagepay/service/AlipayTradeService.php");    
            require_once("pagepay/buildermodel/AlipayTradeRefundContentBuilder.php");  

            //商户订单号，商户网站订单系统中唯一订单号
            //$out_trade_no = trim($data['order_sn']);

            //支付宝交易号
            $trade_no = trim($data['transaction_id']);
            //需要退款的金额，该金额不能大于订单金额，必填
            $refund_amount = trim($data['refund_money']);
            //退款的原因说明
            $refund_reason = trim($data['refund_mark']);
            //标识一次退款请求，同一笔交易多次退款需要保证唯一，如需部分退款，则此参数必传
            $out_request_no = trim($data['out_request_no']);

            //构造参数
            $RequestBuilder = new AlipayTradeRefundContentBuilder();
            //$RequestBuilder->setOutTradeNo($out_trade_no);
            $RequestBuilder->setTradeNo($trade_no);
            $RequestBuilder->setRefundAmount($refund_amount);
            $RequestBuilder->setOutRequestNo($out_request_no);
            $RequestBuilder->setRefundReason($refund_reason);
            $aop = new AlipayTradeService($this->alipay_config);
    
            /**
             * alipay.trade.refund (统一收单交易退款接口)
             * @param $builder 业务参数，使用buildmodel中的对象生成。
             * @return $response 支付宝返回的信息
             */
            $response = $aop->Refund($RequestBuilder);
            // 处理订单
            $result = json_decode(json_encode($response),true);
            if($result['msg'] == 'Success'){
                $transaction_id = $result['trade_no'];
                $refund_money   = $result['refund_fee'];
                $data['refund_time']    = $result['gmt_refund_pay'];
                $data['status']      = 3;
                $order = M('order_yxyp')->where('transaction_id="'.$transaction_id.'"')->find();
                if($order){
                    M('return_yxyp_goods')->where('order_id ='.$order['order_id'].' AND order_sn = "'.$order['order_sn'].'" AND refund_money='.$refund_money)->save($data);
                }
            } else {
                //echo json_encode($response);
            }
            //var_dump($response);
    }


    /**
     * 服务器点对点响应操作给支付接口方调用
     * 
     */
    function response()
    {   
        require_once("pagepay/service/AlipayTradeService.php");    

        $arr=$_POST;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $alipaySevice->writeLog(var_export($arr,true));
        $result = $alipaySevice->check($arr);
        /* 实际验证过程建议商户添加以下校验。
        1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
        2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
        3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
        4、验证app_id是否为该商户本身。
        */
        if($result) {       //验证成功

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            $order_sn        = $out_trade_no = $out_trade_no = $arr['out_trade_no']; //商户订单号
            $trade_no        = $arr['trade_no'];                                     //支付宝交易号
            $trade_status    = $arr['trade_status'];                                 //交易状态
            $passback_params = urldecode($arr['passback_params']);                //公用回传参数
            $cs_arr = explode(';', $passback_params);
            foreach ($cs_arr as $v) {
                $v2 = explode('=', $v);
                $$v2[0] = $v2[1];
            }

            //用户在线充值
            if (stripos($order_sn, 'recharge') !== false){
                $order_amount = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->value('account');
            }
            else{
                $order_amount = M('order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->sum('order_amount');
            }

            if($order_amount!=$_POST['buyer_pay_amount']) exit("fail"); //验证失败                    

            if($trade_status == 'TRADE_FINISHED') {
                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            } else if ($trade_status == 'TRADE_SUCCESS') {
                if($order_num == 1){
                    update_one_pay_status($order_sn,array('transaction_id'=>$trade_no,'pa')); // 修改订单支付状态    
                } else {
                    update_pay_status($order_sn,array('transaction_id'=>$trade_no)); // 修改订单支付状态  
                }        
                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
            }
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
            echo "success"; //请不要修改或删除
        }else {
            //验证失败
            echo "fail";

        }
    }

    /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2()
    {
        require_once("pagepay/service/AlipayTradeService.php"); 

        $arr=$_GET;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $result = $alipaySevice->check($arr);

        /* 实际验证过程建议商户添加以下校验。
        1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
        2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
        3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
        4、验证app_id是否为该商户本身。
        */
        if($result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代码
            
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表

            $out_trade_no = htmlspecialchars($_GET['out_trade_no']);  //商户订单号
            $trade_no     = htmlspecialchars($_GET['trade_no']);      //支付宝交易号 
            $total_amount = $_GET['total_amount'];              
                                      
            return array('status'=>1,'order_sn'=>$out_trade_no,'trade_no'=>$trade_no,'total_amount'=>$total_amount); //跳转至成功页面                                              
        } else {
            return array('status'=>0,'order_sn'=>$out_trade_no);//跳转至失败页面
        }
    }


        /**
     * 服务器点对点响应操作给支付接口方调用
     * 
     */
    function response_red()
    {   
        require_once("pagepay/service/AlipayTradeService.php");    

        $arr=$_POST;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $alipaySevice->writeLog(var_export($arr,true));
        $result = $alipaySevice->check($arr);
        /* 实际验证过程建议商户添加以下校验。
        1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
        2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
        3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
        4、验证app_id是否为该商户本身。
        */
        if($result) {//验证成功

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            $order_sn        = $out_trade_no = $out_trade_no = $arr['out_trade_no']; //商户订单号
            $trade_no        = $arr['trade_no'];                                     //支付宝交易号
            $trade_status    = $arr['trade_status'];                                 //交易状态
            $passback_params = urldecode($arr['passback_params']);                //公用回传参数
            $cs_arr = explode(';', $passback_params);
            foreach ($cs_arr as $v) {
                $v2 = explode('=', $v);
                $$v2[0] = $v2[1];
            }

            //用户在线充值
            if (stripos($order_sn, 'recharge') !== false){
                $order_amount = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->value('account');
            }
            else{
                $order_amount = M('order_red')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->sum('order_amount');
            }

            if($order_amount!=$_POST['buyer_pay_amount']) exit("fail"); //验证失败                    

            if($trade_status == 'TRADE_FINISHED') {
                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            } else if ($trade_status == 'TRADE_SUCCESS') {
                if($order_num == 1){
                    update_one_pay_status_red($order_sn,array('transaction_id'=>$trade_no)); // 修改订单支付状态    
                } else {
                    update_pay_status_red($order_sn,array('transaction_id'=>$trade_no)); // 修改订单支付状态  
                }        
                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
            }
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
            echo "success"; //请不要修改或删除
        }else {
            //验证失败
            echo "fail";

        }
    }

       /**
     * 服务器点对点响应操作给支付接口方调用
     * 
     */
    function response_yxyp()
    {   
        require_once("pagepay/service/AlipayTradeService.php");    

        $arr=$_POST;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $alipaySevice->writeLog(var_export($arr,true));
        $result = $alipaySevice->check($arr);
        /* 实际验证过程建议商户添加以下校验。
        1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
        2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
        3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
        4、验证app_id是否为该商户本身。
        */
        if($result) {//验证成功

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            $order_sn        = $out_trade_no = $out_trade_no = $arr['out_trade_no']; //商户订单号
            $trade_no        = $arr['trade_no'];                                     //支付宝交易号
            $trade_status    = $arr['trade_status'];                                 //交易状态
            $passback_params = urldecode($arr['passback_params']);                //公用回传参数
            $cs_arr = explode(';', $passback_params);
            foreach ($cs_arr as $v) {
                $v2 = explode('=', $v);
                $$v2[0] = $v2[1];
            }

            //用户在线充值
            if (stripos($order_sn, 'recharge') !== false){
                $order_amount = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->value('account');
            }
            else{
                $order_amount = M('order_yxyp')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->sum('order_amount');
            }

            if($order_amount!=$_POST['buyer_pay_amount']) exit("fail"); //验证失败                    

            if($trade_status == 'TRADE_FINISHED') {
                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            } else if ($trade_status == 'TRADE_SUCCESS') {
                if($order_num == 1){
                    update_one_pay_status_yxyp($order_sn,array('transaction_id'=>$trade_no)); // 修改订单支付状态    
                } else {
                    update_pay_status_yxyp($order_sn,array('transaction_id'=>$trade_no)); // 修改订单支付状态  
                }        
                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
            }
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
            echo "success"; //请不要修改或删除
        }else {
            //验证失败
            echo "fail";

        }
    }

    /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2_red()
    {
        require_once("pagepay/service/AlipayTradeService.php"); 

        $arr=$_GET;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $result = $alipaySevice->check($arr);

        /* 实际验证过程建议商户添加以下校验。
        1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
        2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
        3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
        4、验证app_id是否为该商户本身。
        */
        if($result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代码
            
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表

            $out_trade_no = htmlspecialchars($_GET['out_trade_no']);  //商户订单号
            $trade_no     = htmlspecialchars($_GET['trade_no']);      //支付宝交易号 
            $total_amount = $_GET['total_amount'];              
                                      
            return array('status'=>1,'order_sn'=>$out_trade_no,'trade_no'=>$trade_no,'total_amount'=>$total_amount); //跳转至成功页面                                              
        } else {
            return array('status'=>0,'order_sn'=>$out_trade_no);//跳转至失败页面
        }
    }

     /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2_yxyp()
    {
        require_once("pagepay/service/AlipayTradeService.php"); 

        $arr=$_GET;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $result = $alipaySevice->check($arr);

        /* 实际验证过程建议商户添加以下校验。
        1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
        2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
        3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
        4、验证app_id是否为该商户本身。
        */
        if($result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代码
            
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表

            $out_trade_no = htmlspecialchars($_GET['out_trade_no']);  //商户订单号
            $trade_no     = htmlspecialchars($_GET['trade_no']);      //支付宝交易号 
            $total_amount = $_GET['total_amount'];              
                                      
            return array('status'=>1,'order_sn'=>$out_trade_no,'trade_no'=>$trade_no,'total_amount'=>$total_amount); //跳转至成功页面                                              
        } else {
            return array('status'=>0,'order_sn'=>$out_trade_no);//跳转至失败页面
        }
    }

    // 批量申请提现转账回调
    function transfer_response(){

    }

    // 退款原路回调
    public function  refund_respose(){

    }

 
}