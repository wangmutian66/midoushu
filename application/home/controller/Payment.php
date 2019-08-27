<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\home\controller; 
use think\Request;
use think\Db;

class Payment extends Base {
    
    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code
 
    /**
     * 析构流函数
     */
    public function  __construct() {   
        parent::__construct();           
        
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        if(!empty($pay_radio)) 
        {                         
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        }
        else // 第三方 支付商返回
        {            
            //file_put_contents('./a.html',$_GET,FILE_APPEND);    
            $this->pay_code = I('get.pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }                        
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];      
        $xml = file_get_contents('php://input');
        if(empty($this->pay_code)){
            header("Content-type:text/html;charset=utf-8");
            exit('pay_code 不能为空');
        }
        // 导入具体的支付类文件                
        include_once  "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php"; // D:\wamp\www\svn_tpshop\www\plugins\payment\alipay\alipayPayment.class.php                       
        $code = '\\'.$this->pay_code; // \alipay

        $this->payment = new $code();
    }
    //get_code
    /**
     * tpshop 提交支付方式
     */
    public function getCode(){        
            //C('TOKEN_ON',false); // 关闭 TOKEN_ON
            header("Content-type:text/html;charset=utf-8"); 
            $order_num = I('order_num/d');   // 订单数量           
            $order_id  = I('order_id/d');    // 订单id
            $order_sn  = I('order_sn');      // 订单号

            session('order_id',$order_id); // 最近支付的一笔订单 id
            session('order_sn',$order_sn); // 最近支付的一笔订单 id
            if(!session('user')) $this->error('请先登录',U('User/login'));

            // 修改订单的支付方式
            $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");

            if($order_num == 1){
                $order[0] = Db::name('Order')->where(['order_id' => $order_id])->find();

                if($order[0]['pay_status'] == 1){
                    $this->error('此订单，已完成支付!');
                }

                M('order')->where("order_id",$order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
            } else {
                $order    = Db::name('Order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();
                M('order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
            }

            if(empty($order) || $order[0]['order_status'] > 1){
                    $this->error('非法操作！',U("Home/Index/index"));
            }

            // tpshop 订单支付提交
            $pay_radio    = $_REQUEST['pay_radio'];
            $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
            // 获取订单id集合
            $oids = '';
            $i = 1;
            foreach ($order as $k => $val) {
                if($i != 1) $oids .= ',';
                $oids .= $val['order_id'];
                $i++;
            }

            $payBody = getPayBody($oids);
            $config_value['body'] = $payBody;

            if($order_num == 1){
                $order_new = $order[0];
            } else {
                $order_new['order_sn'] = $order[0]['order_sn'];
                $order_new['order_id'] = $order[0]['order_id'];
                $order_new['order_amount'] = Db::name('Order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->sum('order_amount');
            }

            $order_new['order_num'] = $order_num;
            $order_new['order_ids'] = $oids;
           
            //微信JS支付
           if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
               $code_str = $this->payment->getJSAPI($order_new,$config_value);
               exit($code_str);
           }else{
           	    $code_str = $this->payment->get_code($order_new,$config_value);
           }
           $this->assign('code_str', $code_str); 
           $this->assign('order_id', $order_id);           
           return $this->fetch('payment');  // 分跳转 和不 跳转 
    }

    public function getPay(){

    	//C('TOKEN_ON',false); // 关闭 TOKEN_ON
    	header("Content-type:text/html;charset=utf-8"); 
    	$order_id = I('order_id/d');  // 订单id
        session('order_id',$order_id); // 最近支付的一笔订单 id
    	// 修改充值订单的支付方式
    	$payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
    	
    	M('recharge')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
    	$order = M('recharge')->where("order_id", $order_id)->find();
    	if($order['pay_status'] == 1){
    		$this->error('此订单，已完成支付!');
    	}
    	$pay_radio = $_REQUEST['pay_radio'];
    	$config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $config_value['body'] = "会员充值";
        $order['order_amount'] = $order['account'];

    	//微信JS支付
    	if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
    		$code_str = $this->payment->getJSAPI($order,$config_value);
    		exit($code_str);
    	}else{
            $code_str = $this->payment->get_code($order,$config_value);
       }
    	$this->assign('code_str', $code_str);
    	$this->assign('order_id', $order_id);
    	return $this->fetch('recharge'); //分跳转 和不 跳转
    }
    
    // 服务器点对点 // http://www.tp-shop.cn/index.php/Home/Payment/notifyUrl        
    public function notifyUrl(){
        $this->payment->response();
        exit();
    }

    // 页面跳转 // http://www.tp-shop.cn/index.php/Home/Payment/returnUrl        
    public function returnUrl(){
        $result = $this->payment->respond2();
        
        if(stripos($result['order_sn'],'recharge') !== false)
        {
            $order = M('recharge')->where("order_sn", $result['order_sn'])->find();
            $this->assign('order', $order);
            if($result['status'] == 1)
                return $this->fetch('recharge_success');   
            else
                return $this->fetch('recharge_error');   
            exit();            
        }

        if(session('order_id')){
            $order = M('order')->where("order_sn", $result['order_sn'])->find();
            //2018-9-25 王牧田修改  订单提交支付后直接确认
            M('order')->where("order_id", $order['order_id'])->save(['order_status'=>1]);
            if(empty($order)) // order_sn 找不到 根据 order_id 去找
            {
                $order_id = session('order_id'); // 最近支付的一笔订单 id        
                $order = M('order')->where("order_id", $order_id)->find();
            }    
            $this->assign('order', $order);
        } 

        if(session('order_sn')){
            $order = M('order')->where('order_sn ="'.$result['order_sn'].'" OR parent_sn ="'.$result['order_sn'].'"')->find();
            //2018-9-25 王牧田修改  订单提交支付后直接确认
            M('order')->where("order_id", $order['order_id'])->save(['order_status'=>1]);
            if(empty($order)) // order_sn 找不到 根据 order_id 去找
            {
                $order_sn = session('order_sn'); // 最近支付的一笔订单 id        
                $order = M('order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->find();
            }       
            $this->assign('order', $order);
        } 
                
        if($result['status'] == 1){
            $this->assign('total_amount', $result['total_amount']);
            return $this->fetch('success'); 
        } else {
            return $this->fetch('error');
        }  

    }  

    public function refundBack(){
    	$this->payment->refund_respose();
    	exit();
    }
    
    public function transferBack(){
    	$this->payment->transfer_response();
    	exit();
    }
}
