<?php
/**
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

class alipayMobile extends Model
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
        
        $paymentPlugin = M('Plugin')->where("code='alipayMobile' and  type = 'payment' ")->find(); // 找到支付插件的配置
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
        require_once("wappay/service/AlipayTradeService.php");    
        require_once("wappay/buildermodel/AlipayTradeWapPayContentBuilder.php");

        $body = $config_value['body'];
        !$body && $body = "米豆薯商品" ;    

        $out_trade_no = trim($order['order_sn']);       //商户订单号，商户网站订单系统中唯一订单号，必填
        $subject      = trim($body);                    //订单名称，必填
        $total_amount = trim($order['order_amount']);   //付款金额，必填
        $order_num    = $order['order_num'];            //商品数量
        $order_ids    = $order['order_ids'];            //商品ids
        $passback_params = UrlEncode('order_num='.$order_num.';order_ids='.$order_ids);
        $timeout_express = "1m";
        
        $payRequestBuilder = new AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setTimeExpress($timeout_express);
        $payRequestBuilder->setPassbackParams($passback_params);
        
        $payResponse = new AlipayTradeService($this->alipay_config);
    #    dump($payRequestBuilder);die;
        $result=$payResponse->wapPay($payRequestBuilder,$this->alipay_config['return_url'],$this->alipay_config['notify_url']);

        return ;

}

    /**
     * 代付 生成支付代码
     TK  2018年6月4日10:19:14
     * @param   array   $order      订单信息
     * @param   array   $config_value    支付方式信息
     */
    function get_paid_code($paid_order, $config_value)
    {     
         $r = M('transfer_log')->alias('log')
                                ->field('log.*')
                                ->where("paid_sn = '{$paid_order['paid_sn']}'")
                                ->find();
        if(is_array($r)){
            if($r['is_alipay'] == 1){
                $this->alipay_config['app_id']  =  $r['alipay_app_id'];
                $this->alipay_config['merchant_private_key']  = $r['alipay_private'];
                $this->alipay_config['alipay_public_key'] = $r['alipay_public'];    
            }
        }
    
        require_once("wappay/service/AlipayTradeService.php");    
        require_once("wappay/buildermodel/AlipayTradeWapPayContentBuilder.php");
    #    dump($paid_order);die;
        $out_trade_no = $paid_order['paid_sn'];       //商户订单号，商户网站订单系统中唯一订单号，必填
        $subject      = ($r['is_alipay']== 1) ? ("米豆薯 - {$r['store_name']} 扫码支付订单") : '米豆薯扫码支付订单';                    //订单名称，必填
        $total_amount = $paid_order['money'];   //付款金额，必填
        $order_num    = 1;            //商品数量
        $order_ids    = $paid_order['id'];            //商品ids
        $passback_params = UrlEncode('order_num='.$order_num.';order_ids='.$order_ids);
        $timeout_express = "1m";
        
        $payRequestBuilder = new AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($subject);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setTimeExpress($timeout_express);
        $payRequestBuilder->setPassbackParams($passback_params);
        
        $payResponse = new AlipayTradeService($this->alipay_config);
        $result=$payResponse->wapPay($payRequestBuilder,$this->alipay_config['return_url'],$this->alipay_config['notify_url']);
        return ;

    }

    function get_paysd_code($paid_order, $config_value)
    {         
        $r = M('transfer_log')->alias('log')
                                ->field('log.*')
                                ->where("paid_sn = '{$paid_order['paid_sn']}'")
                                # ->join('company c','c.cid = log.store_id')
                                ->find();
       /* echo M('transfer_log')->getlastsql();
        dump($r );die;*/
        if(is_array($r)){
            if($r['is_alipay'] == 1){
                $this->alipay_config['app_id']               = $r['alipay_app_id'];                 //应用ID,您的APPID。
                $this->alipay_config['merchant_private_key'] = $r['alipay_private'];   //商户私钥
                $this->alipay_config['notify_url']           = SITE_URL.U('Payment/notifyUrl',array('pay_code'=>'alipay')); //服务器异步通知页面路径 //必填，不能修改
                $this->alipay_config['return_url']           = SITE_URL.U('Payment/returnUrl',array('pay_code'=>'alipay'));  //页面跳转同步通知页面路径
                $this->alipay_config['charset']              = "UTF-8";   //编码格式
                $this->alipay_config['sign_type']            = "RSA2";      //签名方式
                $this->alipay_config['gatewayUrl']           = "https://openapi.alipay.com/gateway.do";  //支付宝网关
                $this->alipay_config['alipay_public_key']    = $r['alipay_public'];   //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。 
            }
        }


        require_once("wappay/service/AlipayTradeService.php");    
        require_once("wappay/buildermodel/AlipayTradeWapPayContentBuilder.php");

        $body = $config_value['body'];
        !$body && $body = "米豆薯商品" ;  

        $out_trade_no = $paid_order['paid_sn'];       //商户订单号，商户网站订单系统中唯一订单号，必填
        $subject      = ($r['is_alipay']== 1) ? ("米豆薯 - {$r['store_name']} 扫码支付订单") : '米豆薯扫码支付订单';                    //订单名称，必填
        $total_amount = $paid_order['money'];   //付款金额，必填
        $order_num    = 1;                      //商品数量
        $order_ids    = $paid_order['id'];      //商品ids
        $passback_params = UrlEncode('order_num='.$order_num.';order_ids='.$order_ids);
        $timeout_express = "1m";
        
        $payRequestBuilder = new AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($subject);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setTimeExpress($timeout_express);
        $payRequestBuilder->setPassbackParams($passback_params);
        $payResponse = new AlipayTradeService($this->alipay_config);
        $result=$payResponse->wapPay($payRequestBuilder,$this->alipay_config['return_url'],$this->alipay_config['notify_url']);
        return ;

    }
    
    /**
     * 服务器点对点响应操作给支付接口方调用
     * 
     */
    function response()
    {                
        require_once("wappay/service/AlipayTradeService.php");
      /*  $a = db('test')->select();
        foreach ($a as $key => $value) {
            $_POST[$value['a']]    =   $value['b'];
        }*/
        $arr=$_POST;
        $order_sn        = $out_trade_no = $out_trade_no = $arr['out_trade_no']; //商户订单号
        if(stripos($out_trade_no,'staff_paid')!==false || stripos($out_trade_no,'mypays')!==false){
            //员工代付      或扫码支付
            $r =    db('transfer_log')->where("paid_sn = '{$order_sn}'")->find();
            if(is_array($r)){
                if($r['is_alipay'] == 1){
                    $this->alipay_config['app_id']  =  $r['alipay_app_id'];
                    $this->alipay_config['merchant_private_key']  = $r['alipay_private'];
                    $this->alipay_config['alipay_public_key'] = $r['alipay_public'];     
                }  
            }
        }
        
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
    //    $alipaySevice->writeLog(var_export($this->alipay_config,true));
        $alipaySevice->writeLog(var_export($arr,true));
        $result = $alipaySevice->check($arr);
        if($result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            /* $list[] =   ['name'=>'1111111111111111','email'=>'2222222222'];
            foreach ($_REQUEST as $key => $value) {
                $list[] =   ['name'=>$key,'email'=>$value];
            }*/
            # db('test')->insertAll($list);
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            //
            
            $trade_no        = $arr['trade_no'];                                     //支付宝交易号
            $trade_status    = $arr['trade_status'];                                 //交易状态
            $passback_params = urldecode($arr['passback_params']);                //公用回传参数
            $cs_arr = explode(';', $passback_params);
            foreach ($cs_arr as $v) {
                $v2 = explode('=', $v);
                $$v2[0] = $v2[1];
            }
            if(stripos($out_trade_no,'staff_paid')!==false){
                //员工代付
                $order_amount   =   M('staff_paid')->where(['paid_sn' => $order_sn, 'pay_status' => 0])->value('money');
            }elseif(stripos($out_trade_no,'mypays')!==false){
                //员工代付
                $order_amount   =   M('staff_mypays')->where(['paid_sn' => $order_sn, 'pay_status' => 0])->value('money');
            }elseif (stripos($order_sn, 'recharge') !== false){
            //用户在线充值
                $order_amount = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->value('account');
            }
            else{
                $order_amount = M('order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->sum('order_amount');
            }

            $buyer_pay_amount = I('post.buyer_pay_amount');
            if(bccomp($order_amount,$buyer_pay_amount)  != 0){
                exit("fail");
            }
            #db('tests')->insert(['name'=>'33333333333333333']);

            if($trade_status == 'TRADE_FINISHED') {
               # db('tests')->insert(['name'=>'5555555555555555555555']);
                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            } else if ($trade_status == 'TRADE_SUCCESS') {

                if($order_num == 1){
                    # db('tests')->insert(['name'=>'77777777777777777777']);
                    update_one_pay_status($order_sn,array('transaction_id'=>$trade_no)); // 修改订单支付状态    
                } else {
                    # db('tests')->insert(['name'=>'4444444444444444444']);
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
        require_once("wappay/service/AlipayTradeService.php"); 
        $arr=$_GET;
        $order_sn        = $out_trade_no = $out_trade_no = $arr['out_trade_no']; //商户订单号
        if(stripos($out_trade_no,'staff_paid')!==false || stripos($out_trade_no,'mypays')!==false){
            //员工代付      或扫码支付
            $r =    db('transfer_log')->where("paid_sn = '{$order_sn}'")->find();
            if(is_array($r)){
                if($r['is_alipay'] == 1){
                    $this->alipay_config['app_id']  =  $r['alipay_app_id'];
                    $this->alipay_config['merchant_private_key']  = $r['alipay_private'];
                    $this->alipay_config['alipay_public_key'] = $r['alipay_public'];     
                }  
            }
        }
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $result = $alipaySevice->check($arr);

        if($result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代码
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表

            $out_trade_no = htmlspecialchars($_GET['out_trade_no']);  //商户订单号
            $trade_no     = htmlspecialchars($_GET['trade_no']);      //支付宝交易号 
            $total_amount = $_GET['total_amount'];              
                                      
            return array('status'=>1,'order_sn'=>$out_trade_no,'trade_no'=>$trade_no,'total_amount'=>$total_amount); //跳转至成功页面                                              
        }
        else {
            return array('status'=>0,'order_sn'=>$out_trade_no);//跳转至失败页面
        }
    }


    /**
     * 服务器点对点响应操作给支付接口方调用
     * 
     */
    function response_red()
    {                
        require_once("wappay/service/AlipayTradeService.php");    

        $arr=$_POST;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $alipaySevice->writeLog(var_export($arr,true));
        $result = $alipaySevice->check($arr);

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
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2_red()
    {
        require_once("wappay/service/AlipayTradeService.php"); 

        $arr=$_GET;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $result = $alipaySevice->check($arr);

        if($result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代码
            
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表

            $out_trade_no = htmlspecialchars($_GET['out_trade_no']);  //商户订单号
            $trade_no     = htmlspecialchars($_GET['trade_no']);      //支付宝交易号 
            $total_amount = $_GET['total_amount'];              
                                      
            return array('status'=>1,'order_sn'=>$out_trade_no,'trade_no'=>$trade_no,'total_amount'=>$total_amount); //跳转至成功页面                                              
        }
        else {
            return array('status'=>0,'order_sn'=>$out_trade_no);//跳转至失败页面
        }
    }

 /**
     * 服务器点对点响应操作给支付接口方调用
     * 
     */
    function response_yxyp()
    {                
        require_once("wappay/service/AlipayTradeService.php");    

        $arr=$_POST;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $alipaySevice->writeLog(var_export($arr,true));
        $result = $alipaySevice->check($arr);

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
    function respond2_yxyp()
    {
        require_once("wappay/service/AlipayTradeService.php"); 

        $arr=$_GET;
        $alipaySevice = new AlipayTradeService($this->alipay_config); 
        $result = $alipaySevice->check($arr);

        if($result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代码
            
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表

            $out_trade_no = htmlspecialchars($_GET['out_trade_no']);  //商户订单号
            $trade_no     = htmlspecialchars($_GET['trade_no']);      //支付宝交易号 
            $total_amount = $_GET['total_amount'];              
                                      
            return array('status'=>1,'order_sn'=>$out_trade_no,'trade_no'=>$trade_no,'total_amount'=>$total_amount); //跳转至成功页面                                              
        }
        else {
            return array('status'=>0,'order_sn'=>$out_trade_no);//跳转至失败页面
        }
    }

    
}