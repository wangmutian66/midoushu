<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\applet_app\controller;


use app\common\logic\WechatAppPay;
use think\Request;

class Pay extends MobileBase {
    public function __construct()
    {
        parent::__construct();
    }

    public function index(){
        $paymentPlugin = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        $key = $config_value["key"];


        $appId = "wxe53511823d405295";
        $timeStamp = strval(time());
        $nonceStr = $this->getRandCode();
        $package = "prepay_id=wx".date("YmdHis");
        $signType = "MD5";

        $paySign = md5("appId=$appId&nonceStr=$nonceStr&package=$package&signType=$signType&timeStamp=$timeStamp&key=$key");

        $result = ["timeStamp"=>$timeStamp,"nonceStr"=>$nonceStr,"package"=>$package,"signType"=>$signType,"paySign"=>$paySign];

        echo json_encode($result);
        exit();

    }



    //获取16随机码
    function getRandCode($num=16){
        $array = array(
            'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
            'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
            '1','2','3','4','5','6','7','8','9','0'
        );
        $tmpstr = '';
        $max = count($array);
        for($i=1;$i<=$num;$i++){
            $key = rand(0,$max-1);
            $tmpstr.=$array[$key];
        }
        return $tmpstr;
    }


    /**
     * [微信小程序支付]
     * @author 王牧田
     * @date 2018-11-13
     */
    public function dowxPay(){
        header("Content-type:text/html;charset=utf-8"); //此处进行字符集初始
        // $openId = I('post.openId',"o17eZ5YgQgnwntjsSEJFQU8n6fEQ"); // 用户的openId
        $userid = I('post.user_id'); //用户id
        $openId = db('users')->where(["user_id"=>$userid])->value("openid_xcx");
        $fee  = I('post.fee'); //金额 单位（元）
        $out_trade_no = I('post.out_trade_no'); // 订单单号
        // $out_trade_no='201811231346278807';
        $body = I('post.body',$out_trade_no); // 支付备注
        // $openid = 'o17eZ5Swze1aubSYD1jfOtj8odzw';    
        $wxPay = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置

        $wxPayVal = unserialize($wxPay['config_value']);


        $request = Request::instance();

        $data = array(
            'appid' => 'wxbf199f3c5090e682',  //小程序appid
            'body' => $body,
            'mch_id' => "1507332611",         //微信支付商户号
            'nonce_str' => md5(date('YmdHis') . time() . rand(1000, 9999)),           //随机字符串
            'notify_url' => SITE_URL.'/applet/pay/paysuccess/order_sn/'.$out_trade_no,    //异步回调地址
            'openid' => $openId,        //用户登录时获取的code中含有的值
            'out_trade_no' => $out_trade_no,               //商家订单号
            'spbill_create_ip' => $request->ip(),           //APP和网页支付提交用户端ip
            'total_fee' => $fee * 100,                  //订单总额
            'trade_type' => 'JSAPI'           //交易类型
        );

        $key =  $wxPayVal["key"];

        ksort($data);
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        //签名步骤二：在string后加入KEY
        $string = $buff . "&key=" . $key;
        //签名步骤三：MD5加密

        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $sign = strtoupper($string);
        $data['sign'] = $sign;

        ksort($data);
        //进行拼接数据
        $abc_xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $abc_xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $abc_xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $abc_xml .= "</xml>";

        //统一下单接口prepay_id
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $xml = $this->http_request_curl($url, $abc_xml);     //POST方式请求http
        $info = $this->xmlToArray($xml);
    

        $params = array(
            'appId' => $data['appid'],
            'nonceStr' => $data['nonce_str'],
            'package' => 'prepay_id=' . $info['prepay_id'],
            'signType' => 'MD5',
            'timeStamp' => time()
        );

        $key = $app_info['shopidshop']=  $wxPayVal["key"];
        $info['paySign'] = $this->MakeSign($params, $key);
        $info['timeStamp'] = "" . $params['timeStamp'] . "";
        $info['nonceStr'] = $params['nonceStr'];
        $info['package'] = $params['package'];
        $info = array_merge($info, $app_info);
        //请求成功后进行返回数据信息
        if ($info['return_code'] == 'SUCCESS' || $info['result_code'] == 'SUCCESS') {
            unset($data);
            $data["timeStamp"]=$info["timeStamp"];
            $data["nonceStr"]=$info["nonceStr"];
            $data["package"]=$info["package"];
            $data["paySign"]=$info["paySign"];
            echo json_encode(["data"=>$data,"error"=>0,"message"=>"请求成功"]);
            exit();
            //return $this->result([],$errno, $message, json($info));
        } else {
            echo json_encode(["data"=>[],"error"=>-1,"message"=>"请求失败"]);
            exit();
            //return $this->result([],$errno, $message, json($info));
        }
    }

    /**
     * [支付成功回到这里]
     * @author 王牧田
     * @date 2018-11-13
     */
    public function paysuccess(){
        $order_sn = I('get.order_sn');
        update_pay_status($order_sn);
    }


    function http_request_curl($url, $rawData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawData);
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER,
            array(
                'Content-Type: text'
            )
        );
        $data = curl_exec($ch);
        curl_close($ch);
        return ($data);


    }


    //将XMl转化为数组
    function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring), true);
        return $val;
    }

    //进行拼接sign
    function MakeSign($params, $KEY)
    {
        //签名步骤一：按字典序排序数组参数
        ksort($params);
        $buff1 = '';
        foreach ($params as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff1 .= $k . "=" . $v . "&";
            }
        }
        $buff1 = trim($buff1, "&");
        //签名步骤二：在string后加入KEY
        $string = $buff1 . "&key=" . $KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);

        return $result;
    }



    ////-----------------------

    //获取支付必备参数prepay_id 以及进行支付返回paysign

    public function doPagegetprepayid()
    {   //进行查询数据库获得支付参数，

        header("Content-type:text/html;charset=utf-8"); //此处进行字符集初始化，
        global $_GPC, $_W;
        $order_id = $_GPC['orderid'];

        function http_request_curl($url, $rawData)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawData);
            curl_setopt(
                $ch, CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: text'
                )
            );
            $data = curl_exec($ch);
            curl_close($ch);
            return ($data);
        }

        $wxPay = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置

        $wxPay['config_value'] = "a:5:{s:5:\"appid\";s:18:\"wx6ab3b8d3038ccd2a\";s:5:\"mchid\";s:10:\"1507332611\";s:3:\"key\";s:32:\"1645353midoushu867123chuangshaas\";s:9:\"appsecret\";s:32:\"03724e46aebea05072c05f248c1a2367\";s:6:\"smchid\";s:0:\"\";}";
        $wxPayVal = unserialize($wxPay['config_value']);
        $order_sn = "201810221207542089";
        $request = Request::instance();
        $data = array(
            'appid' => 'wxbf199f3c5090e682',  //小程序appid
            'body' => '腾讯-游戏',
            'mch_id' => "1507332611",         //微信支付商户号
            'nonce_str' => md5(date('YmdHis') . time() . rand(1000, 9999)),           //随机字符串
            'notify_url' => SITE_URL.'/applet/pay/paysuccess/order_sn/'.$order_sn,    //异步回调地址
            'openid' => 'o17eZ5cRwH0SR8lbsOiTM90ZfJZ8',        //用户登录时获取的code中含有的值
            'out_trade_no' => $order_sn,               //商家订单号
            'spbill_create_ip' => $request->ip(),           //APP和网页支付提交用户端ip
            'total_fee' => 1,                  //订单总额
            'trade_type' => 'JSAPI'           //交易类型
        );

        $key =  $wxPayVal["key"];
        //制作签名
        //签名步骤一：按字典序排序参数
        ksort($data);
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        //签名步骤二：在string后加入KEY
        $string = $buff . "&key=" . $key;
        //签名步骤三：MD5加密
        
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $sign = strtoupper($string);
        $data['sign'] = $sign;

        ksort($data);
        //进行拼接数据
        $abc_xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $abc_xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $abc_xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $abc_xml .= "</xml>";

        //统一下单接口prepay_id
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $xml = http_request_curl($url, $abc_xml);     //POST方式请求http
        //将XMl转化为数组
        function xmlToArray($xml)
        {
            //禁止引用外部xml实体
            libxml_disable_entity_loader(true);
            $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
            $val = json_decode(json_encode($xmlstring), true);
            return $val;
        }


        $info = xmlToArray($xml);


        //进行拼接sign
        function MakeSign($params, $KEY)
        {
            //签名步骤一：按字典序排序数组参数
            ksort($params);
            $buff1 = '';
            foreach ($params as $k => $v) {
                if ($k != "sign" && $v != "" && !is_array($v)) {
                    $buff1 .= $k . "=" . $v . "&";
                }
            }
            $buff1 = trim($buff1, "&");
            //签名步骤二：在string后加入KEY
            $string = $buff1 . "&key=" . $KEY;
            //签名步骤三：MD5加密
            $string = md5($string);
            //签名步骤四：所有字符转为大写
            $result = strtoupper($string);

            return $result;
        }

        $params = array(
            'appId' => $data['appid'],
            'nonceStr' => $data['nonce_str'],
            'package' => 'Sign=WXPay&prepay_id=' . $info['prepay_id'],
            'signType' => 'MD5',
            'timeStamp' => time()
        );

        //$key = 'z4hgl4cnf5ac2wl3msiek5p0x3aiy2yc'; //商户秘钥
        $key = $app_info['shopidshop']=  $wxPayVal["key"];
        $info['paySign'] = MakeSign($params, $key);
        $info['timeStamp'] = "" . $params['timeStamp'] . "";
        $info['nonceStr'] = $params['nonceStr'];
        $info['package'] = $params['package'];
        $info = array_merge($info, $app_info);

        //请求成功后进行返回数据信息
        if ($info['return_code'] == 'SUCCESS' || $info['result_code'] == 'SUCCESS') {

            $errno = 0;
            $message = '请求成功';
            echo json_encode($info);
            exit();
            //return $this->result([],$errno, $message, json($info));
        } else {
            $errno = -1;
            $message = '请求失败';
            return $this->result([],$errno, $message, json($info));
        }

        function array2xml($data,$tag = '')
        {
            $xml = '';

            foreach($data as $key => $value)
            {
                if(is_numeric($key))
                {
                    if(is_array($value))
                    {
                        $xml .= "<$tag>";
                        $xml .= array2xml($value);
                        $xml .="</$tag>";
                    }
                    else
                    {
                        $xml .= "<$tag>$value</$tag>";
                    }
                }
                else
                {
                    if(is_array($value))
                    {
                        $keys = array_keys($value);
                        if(is_numeric($keys[0]))
                        {
                            $xml .=array2xml($value,$key);
                        }
                        else
                        {
                            $xml .= "<$key>";
                            $xml .=array2xml($value);
                            $xml .= "</$key>";
                        }

                    }
                    else
                    {
                        $xml .= "<$key>$value</$key>";
                    }
                }
            }
            return $xml;
        }
    }


    public function getPay(){
        header("Content-type:text/html;charset=utf-8"); //此处进行字符集初始
        // $openId = I('post.openId',"o17eZ5YgQgnwntjsSEJFQU8n6fEQ"); // 用户的openId

        $userid = I('post.user_id'); //用户id
        $account = I('post.account'); //金额
        $openId = db('users')->where(["user_id"=>$userid])->find();
        $datas['account'] = $account;
        $datas['user_id'] = $userid;
        $datas['pay_name'] = '微信支付';
        $datas['pay_code'] = 'weixin';
        $datas['nickname'] = $openId['nickname'];
        $datas['order_sn'] = 'recharge'.get_rand_str(10,0,1);
        $datas['ctime'] = time();
        $fee  = $account; //金额 单位（元）
        $out_trade_no = $datas['order_sn']; // 订单单号
        $body = "充值金额：".$fee; // 支付备注

        $wxPay = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置

        $wxPayVal = unserialize($wxPay['config_value']);


        $request = Request::instance();

        $data = array(
            'appid' => 'wxbf199f3c5090e682',  //小程序appid
            'body' => $body,
            'mch_id' => "1507332611",         //微信支付商户号
            'nonce_str' => md5(date('YmdHis') . time() . rand(1000, 9999)),           //随机字符串
            'notify_url' => SITE_URL.'/applet/pay/paysuccess/order_sn/'.$out_trade_no,    //异步回调地址
            'openid' => $openId['openid_xcx'],        //用户登录时获取的code中含有的值
            'out_trade_no' => $out_trade_no,               //商家订单号
            'spbill_create_ip' => $request->ip(),           //APP和网页支付提交用户端ip
            'total_fee' => $fee * 100,                  //订单总额
            'trade_type' => 'JSAPI'           //交易类型
        );
        // dump(data);die();
        $key =  $wxPayVal["key"];

        ksort($data);
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        //签名步骤二：在string后加入KEY
        $string = $buff . "&key=" . $key;
        //签名步骤三：MD5加密

        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $sign = strtoupper($string);
        $data['sign'] = $sign;

        ksort($data);
        //进行拼接数据
        $abc_xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $abc_xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $abc_xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $abc_xml .= "</xml>";

        //统一下单接口prepay_id
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $xml = $this->http_request_curl($url, $abc_xml);     //POST方式请求http
        $info = $this->xmlToArray($xml);
        // dump($abc_xml);
        // echo "<br>";
        // dump($xml);
        // die();

        $params = array(
            'appId' => $data['appid'],
            'nonceStr' => $data['nonce_str'],
            'package' => 'prepay_id=' . $info['prepay_id'],
            'signType' => 'MD5',
            'timeStamp' => time()
        );

        $key = $app_info['shopidshop']=  $wxPayVal["key"];
        $info['paySign'] = $this->MakeSign($params, $key);
        $info['timeStamp'] = "" . $params['timeStamp'] . "";
        $info['nonceStr'] = $params['nonceStr'];
        $info['package'] = $params['package'];
        $info = array_merge($info, $app_info);
        //请求成功后进行返回数据信息
        if ($info['return_code'] == 'SUCCESS' || $info['result_code'] == 'SUCCESS') {
            $order_id = M('recharge')->add($datas);

            unset($data);
            $data["timeStamp"]=$info["timeStamp"];
            $data["nonceStr"]=$info["nonceStr"];
            $data["package"]=$info["package"];
            $data["paySign"]=$info["paySign"];
            echo json_encode(["data"=>$data,"error"=>0,"message"=>"请求成功"]);
            exit();
            //return $this->result([],$errno, $message, json($info));
        } else {
            echo json_encode(["data"=>[],"error"=>-1,"message"=>"请求失败"]);
            exit();
            //return $this->result([],$errno, $message, json($info));
        }
    }


    /**
     * [APP微信支付]
     * @author 王牧田
     * @date 2019-02-*22
     * @return bool|string
     */
    public function weixinpay(){
        //商户号该产品权限未开通,请前往商户平台>产品中心检查后重试
        //SELECT * FROM `tp_plugin` WHERE  `type` = 'payment'  AND `code` = 'weixinH5' LIMIT 1
        $plugin = db('plugin')->where(["type"=>'payment',"code"=>"weixinH5"])->find();
        $config_value = unserialize($plugin['config_value']);
        //支付后未发送订单支付变化需要重写回调地址

        $config["appid"] = $config_value['appid'];
        $config["mch_id"] = $config_value['mchid']; //微信商户号需要开通app支付权限
        $config["key"] = $config_value['key'];

        $order_sn = I('post.order_sn',time());
        $total_fee = I('post.total_fee',1);
        $notify_url = SITE_URL.'/applet_app/pay/paysuccess/order_sn/'.$order_sn;
        $config["notify_url"] = $notify_url;
        $config = [
            "appid" => "wx99a6905fbfdf09c5",
            "mch_id" => "1489110852",
            "notify_url" => "http://".$_SERVER["HTTP_HOST"]."/api/Payment/callback/type/wechatPay/order_type/",
            "key" => "c36d00c2dd3534b99d8efd3dbabad680",
        ];
        $wechatAppPay = new WechatAppPay($config);

        $params = [
            "body" => "米豆薯-微信支付",
            "out_trade_no" => $order_sn,
            "total_fee" => $total_fee * 100,
            "trade_type" => "APP",
        ];
        $result = $wechatAppPay->unifiedOrder($params);
        if ($order_sn == "" || $total_fee == ""){
            return json_encode(["code"=>"201","msg"=>"支付金额和订单号不能为空"]);
        }
        if ($result['return_code'] == 'FAIL') {
            return json_encode(["code"=>"201","msg"=>$result["return_msg"]]);
        }

        //创建APP端预支付参数
        $data = @$wechatAppPay->getAppPayParams($result['prepay_id']);
        if (!$data['partnerid']) {
            $data = @$wechatAppPay->getAppPayParams($result['prepay_id']);
            if (!$data['partnerid']) {
                return false;
            }
        }
        $data['order_sns'] = $order_sn;
        return json_encode(["code"=>"200","msg"=>"操作成功","data"=>json_encode($data,JSON_UNESCAPED_UNICODE)]);


    }

    /**
     * [app支付宝支付]
     * @author 王牧田
     * @date 2019-02-22
     */
    public function aliPay(){
        require_once PLUGIN_PATH.'/payment/alipay/aop/AopClient.php';
        $order_sn = I('post.order_sn/s',time());
        $total_amount = I('post.total_fee/f',1);
        $privatekey = 'MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQDig2OB6oIgxdsZK40frnWCvO6hpa9GFBhPdORlSLrq3XRHrfi3EOKTodCASh19pxwD4nyOsP6aceubWo4oNuoPwyy2kEesW8T/N6x7HU+JRbrzyLi1wlvqLr+uGV6QjKyHIaMj5/cnY8kouUt23ulas5m1zTvo+PzexjIoqx5D06614w6T/hMzEgy5zODEV63fDnY6ogIuRMqQnr8OwlS8CzeAFU4hp+Nq6A5FsrtrGxJme1W0O8pXKd+8dLAz7IvwuMzKvdc0pT50dOEmoBIUxrpl6EX78h9ZKGL/Do7FkS1af+f39yAT2Cj1H2B9znAlRAykDl1pA7nL8tyqoBBdAgMBAAECggEAbUQwNjVnvGN1Q3kMxKGlsgFto7CHEmkTKREcM/eXo0BcnU9A0R5mDg1axOf7vedkzkLtDvA2gU4+91wBg0iqNBxUb2S+DljeeLbkjycefPuUKZFD+Pne2OLgOB2ozlXty+ngBqwZP3kVSn3H93mkW8qTdW2hXsrtQJGfFJsnKFQGNrGeiRmIbaM4cjZMj8VRWZH7mTeNs6TrPXDMvjVtLXXKHvuqqdyjvqKK1GHbfhoidpt39aDiG95nR8L5ei4mNEx47+BzQNPNe+/9oF3VZ3WSzuzRkXvGhSnX5V67b2vDYWkeYrreHMLA+8MxvEJGeXY4iXX8AfWCzx1S+LBRiQKBgQD8jCds8eBIckPysYKLELyDG3b7Z8Ov1OneIlvDEkb7WjcE6u8IDgcvPOQB4fY7aQK9O6KwK7qjCw1vL+f3eqBtyJ872FdzfP6RDt7K6Bqjq1NodJqY5bjsSAMJuy965OwIyBsEAGZpzR2/hjmtL9A25YTZek8J/LondqqXqLYMAwKBgQDlnB9BWJbCSikmu/B+LFfuWSD8LrgcAQElQd6vW5yq/+NDPcshDtLIdlNkzwqL5VnvfKZKaMVe117tsG/BFmQqL6hZxDc8Vgyf5FoBck1ZQBRheEt0D33RtYacAzbmBvhEYt91Zw9mxrqwchYYoJK2gYsAkkSaDSjmPHhN5gw0HwKBgFZZdYGCkjUzHJh2qTPzXQFW+q+rIvWTzwLsrINeVHbMudMsoN4YLcyw/STHpfFaTl11boLd8MqelNFXh/DONNxUpF1J81zBeCkQ9IxcH/+mLLaiZ9mvcjMXzDIflnRmoW2/Pb14hYvXXjyGIGJe3spmX64ca7n2d4/Wuy1vug8RAoGAU+qLQcau0rNn9tAZRQPP3zyT4ZbxksqLMKKyUESbLaP320tqQdq5DqqwL9e7cwWPqQdVfzxrZg4wk444Scl1MjXYwXYE+fg5BPbgLkcoHcZdrIHakcBXq508ZSiHl+pUMiowoSfZaSaYdIQ9ryKAfFM8CilrCSJmID9ZeJ7rNuECgYANJ5bY6LoZNVq4BGtIvBcqVrDKqgQc9k3ob2Jxve/dXR1qQGhmju1S7lTMS8EetJFmK9cfwxIYRgwIPzS9FhI7PO3kRJsbgd15w0eVe30OdQGMTModXq1kz8S0Tg4tw6EGw9lgsAxxHm5jz59bVRa1qsM/GEB2LPmxYNtUbTWovA==';
        $plugin = db('plugin')->where(["type"=>'payment',"code"=>"alipay"])->find();
        $config_value = unserialize($plugin['config_value']);

        if ($order_sn == "" || $total_amount == ""){
            return json_encode(["code"=>"201","msg"=>"支付金额和订单号不能为空"]);
        }
        if ($privatekey == '') {
            return json_encode(["code"=>"201","msg"=>"支付私钥不能为空"]);
        }

        $content = array(
            'body' => '米豆薯-支付宝支付',
            'subject' => '支付宝支付', /*商品的标题/交易标题/订单标题/订单关键字等*/
            'out_trade_no' => $order_sn, /*商户网站唯一订单号*/
            'timeout_express' => '30m', //该笔订单允许的最晚付款时间
            'total_amount' => floatval($total_amount), //订单总金额(必须定义成浮点型)
            'product_code' => 'QUICK_MSECURITY_PAY', //销售产品码，商家和支付宝签约的产品码，为固定值QUICK_MSECURITY_PAY
        );
        /*$content是biz_content的值,将之转化成字符串*/
        $con = json_encode($content);
        //公共参数
        $param = [];
        $Client = new \AopClient(); //实例化支付宝sdk里面的AopClient类,下单时需要的操作,都在这个类里面
        $param['app_id'] = $config_value["app_id"]; //支付宝分配给开发者的应用ID $config_value['app_id']
        $param['method'] = 'alipay.trade.app.pay'; //接口名称
        $param['charset'] = 'utf-8'; //请求使用的编码格式
        $param['sign_type'] = 'RSA2'; //商户生成签名字符串所使用的签名算法类型
        $param['timestamp'] = date("Y-m-d H:i:s"); //发送请求的时间
        $param['version'] = "1.0"; //调用的接口版本，固定为：1.0
        //支付后未发送订单支付变化需要重写回调地址
        $param['notify_url'] = SITE_URL.'/applet_app/pay/paysuccess/order_sn/'.$order_sn; //支付宝服务器主动通知地址
        $param['biz_content'] = $con; //业务请求参数的集合,长度不限,json格式
        /*生成签名*/
        $paramStr = $Client->getSignContent($param);

        $sign = $Client->alonersaSign($paramStr, $privatekey, 'RSA2');
        /*生成最终的请求字符串*/
        $paramResult['sign'] = $sign;
        $paramResult['schemeStr'] = $paramStr;

        return json_encode(["code"=>"200","msg"=>"操作成功","data"=>json_encode($paramResult,JSON_UNESCAPED_UNICODE)]);

    }
       
}