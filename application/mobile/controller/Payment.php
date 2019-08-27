<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\mobile\controller;
use think\Request;
use think\Db;
use think\Session;
class Payment extends MobileBase {
    
    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code
 
    /**
     * 析构流函数
     */
    public function  __construct() {   
        parent::__construct();      
    #    dump(session('staff.id'));die;
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        if(!empty($pay_radio)) 
        {                         
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        }
        else                //第三方支付商返回
        {
            //$_GET = I('get.');            
            //file_put_contents('./a.html',$_GET,FILE_APPEND);    
            $this->pay_code = I('get.pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }  

        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];   
		$xml = file_get_contents('php://input'); 
        if(empty($this->pay_code)){
            header("Location: https://www.midoushu.com/Mobile");
            exit();
            //'pay_code 不能为空'
        }
        //          
        // 导入具体的支付类文件      
        if($this->pay_code == 'alipay'){
            $this->pay_code = 'alipayMobile';
        }
        include_once  "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php";                 
        $code = '\\'.$this->pay_code; // \alipay

        $this->payment = new $code();
    }
   
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
                $order[0] = M('Order')->where(['order_id' => $order_id])->find();
                if($order[0]['pay_status'] == 1){
                    $this->error('此订单，已完成支付!',U('/mobile/Order/order_list'));
                }
                M('order')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
            } else {
                $order    = M('Order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();
                M('order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
            }

            if(empty($order) || $order[0]['order_status'] > 1){
                    $this->error('非法操作！',U("Mobile/Index/index"));
            }

            //tpshop 订单支付提交
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
                $order_new['order_amount'] = M('Order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->sum('order_amount');
            }

            $order_new['order_num'] = $order_num;
            $order_new['order_ids'] = $oids;

            //微信JS支付
           if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
                $code_str = $this->payment->getJSAPI($order_new);
                exit($code_str);
           }elseif($this->pay_code == 'weixinH5') {
//               new \alipayMobile()
                $code_str = $this->payment->get_code($order_new, $config_value);
                if ($code_str['status'] != 1) {
                   $this->error($code_str['msg']);
                }
                header("Location:".$code_str['result']);
                exit;
                # $this->assign('deeplink', $code_str['result']);
            }else{
           	    $code_str = $this->payment->get_code($order_new,$config_value);
           }

            $this->assign('code_str', $code_str); 
            $this->assign('order_id', $order_id); 
            return $this->fetch('payment');  // 分跳转 和不 跳转
    }


    /**
     * tpshop 提交支付方式
     */
    public function mypays_pay(){     
            //C('TOKEN_ON',false); // 关闭 TOKEN_ON
            header("Content-type:text/html;charset=utf-8");            
            $pays_id = I('pays_id/d',0);
            if(!session('user')) $this->error('请先登录',U('User/login'));   

            $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name"); 
            if($id >= 0){
                $where['id']    =   ['eq',$pays_id];
                $pay_order = M('staff_mypays')->where($where)->find();
                if($order['pay_status'] == 1){
                    $res['status']  =   0;
                    $res['info']    =   '此订单，已完成支付!';
                    $this->error($res['info'] );die;
                    $this->ajaxReturn($res);
                }
                M('staff_mypays')->where($where)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
            } else {
                $res['status']  =   0;
                $res['info']    =   '非法操作';
                $this->error($res['info']);die;
                $this->ajaxReturn($res);
            }
            //tpshop 订单支付提交
            $pay_radio    = $_REQUEST['pay_radio'];
            $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
            //微信JS支付
           if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
                $paid_str = $this->payment->TKgetJSAPI($pay_order);
                exit($paid_str);
           } elseif ($this->pay_code == 'weixinH5') {
                #  echo 2;die;
                //微信H5支付            这个直接用代付的代码就行，没什么差别
                $paid_str = $this->payment->get_paid_code($pay_order, $config_value);
                if ($paid_str['status'] != 1) {
                    $this->error($paid_str['msg']);
                }
                /* header("Location:{$paid_str['result']}");
                exit();*/
                $this->assign('staff_id',$pay_order['staff_id']);
                $this->assign('deeplink', $paid_str['result']);
            } else{
                $paid_str = $this->payment->get_paysd_code($pay_order,$config_value);
            }
            $this->assign('paid_str', $paid_str); 
            $this->assign('id', $pays_id); 
            return $this->fetch('pays_payment');  // 分跳转 和不 跳转
    }

    /**
     * tpshop 提交支付方式
     */
    public function paid_pay(){     
        #     dump(session('staff'));die;
            //C('TOKEN_ON',false); // 关闭 TOKEN_ON
#            header("Content-type:text/html;charset=utf-8");            
            $paid_id = I('post.paid_id/d',0);
            $staff_id = session('staff.id');
            if(!$staff_id) {
                $res['status']  =   0;
                $res['info']    =   '请先登录';
                $res['url'] =   U('/Staff/System/login');
                $this->error($res['info']);die;
            #    $this->ajaxReturn($res);
            }   
            $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name"); 
            if($id >= 0){
                $where['staff_id']  =   ['eq',$staff_id];
                $where['id']    =   ['eq',$paid_id];
                $paid_order = M('staff_paid')->where($where)->find();
              #  echo M('staff_paid')->getlastsql();die;
                if($order['pay_status'] == 1){
                    $res['status']  =   0;
                    $res['info']    =   '此订单，已完成支付!';
                    $this->error($res['info']);die;
                #    $this->ajaxReturn($res);
                }
                M('staff_paid')->where($where)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
            }else {
                $res['status']  =   0;
                $res['info']    =   '非法操作';
                $this->erro($res['info']);die;
             #   $this->ajaxReturn($res);
            }
            //tpshop 订单支付提交
            $pay_radio    = $_REQUEST['pay_radio'];
            $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
            //微信JS支付
           if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
               $code_str = $this->payment->TKgetJSAPI($paid_order);
               exit($code_str);
           }elseif($this->pay_code == 'weixinH5') {
                //微信H5支付   手机版网站支付  不能在微信里面调用，坑爹
                $paid_str = $this->payment->get_paid_code($paid_order, $config_value);
                if ($paid_str['status'] != 1) {
                    $this->error($paid_str['msg']);
                }
                $this->assign('deeplink', $paid_str['result']);
                $this->assign('staff_id',$paid_order['staff_id']);
            }else{
                $paid_str = $this->payment->get_paid_code($paid_order,$config_value);
           }

            $this->assign('paid_str',$paid_str); 
            $this->assign('paid_id', $paid_id); 
            return $this->fetch('paid_payment');  // 分跳转 和不 跳转
    }

    public function getPay(){
    	//手机端在线充值
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON 
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); //订单id
        $user = session('user');
        $data['account'] = I('account');
        if($order_id>0){
        	M('recharge')->where(array('order_id'=>$order_id,'user_id'=>$user['user_id']))->save($data);
        }else{
        	$data['user_id'] = $user['user_id'];
        	$data['nickname'] = $user['nickname'];
        	$data['order_sn'] = 'recharge'.get_rand_str(10,0,1);
        	$data['ctime'] = time();
        	$order_id = M('recharge')->add($data);
        }
        if($order_id){
        	$order = M('recharge')->where("order_id", $order_id)->find();
        	if(is_array($order) && $order['pay_status']==0){
        		$order['order_amount'] = $order['account'];
        		$pay_radio = $_REQUEST['pay_radio'];
        		$config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        		$payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
        		M('recharge')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
        		//微信JS支付
        		if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
        			$code_str = $this->payment->getJSAPI($order);
        			exit($code_str);
        		}elseif($this->pay_code == 'weixinH5') {
                    $config_value['body']   =   '手机端在线充值';
                    //微信H5支付   手机版网站支付  不能在微信里面调用，坑爹
                    $code_str = $this->payment->get_code($order, $config_value);
                    if ($code_str['status'] != 1) {
                        $this->error($code_str['msg']);
                    }
                    header("Location:{$code_str['result']}");
                    exit();
                  #  $this->assign('deeplink', $paid_str['result']);
                }else{
        			$code_str = $this->payment->get_code($order,$config_value);
        		}
        	}else{
        		$this->error('此充值订单，已完成支付!');
        	}
        }else{
        	$this->error('提交失败,参数有误!');
        }
        $this->assign('code_str', $code_str); 
        $this->assign('order_id', $order_id); 
    	return $this->fetch('recharge'); //分跳转 和不 跳转
    }

 //   public function notifyUrl


    // 服务器点对点 // http://www.tp-shop.cn/index.php/Home/Payment/notifyUrl        
    public function notifyUrl(){
        $this->payment->response();            
        exit();
    }

    // 页面跳转 // http://www.midoushu.com/index.php/Home/Payment/returnUrl        
    public function returnUrl(){
        # dump($_GET);die;
        $result = $this->payment->respond2(); 
        # dump($result);
        if(stripos($result['order_sn'],'mypays') !== false)
        {
            $staff_paid = M('staff_mypays')->where("paid_sn", $result['order_sn'])->find();
            $this->assign('staff_paid', $staff_paid);
            if($result['status'] == 1)
                return $this->fetch('pays_success');
            else
                return $this->fetch('pays_error');
            exit();
        }


        if(stripos($result['order_sn'],'staff_paid') !== false)
        {
            $staff_paid = M('staff_paid')->where("paid_sn", $result['order_sn'])->find();
        #    dump($staff_paid);die;
            $this->assign('staff_paid', $staff_paid);
            if($result['status'] == 1)
                return $this->fetch('staff_paid_success');
            else
                return $this->fetch('staff_paid_error');
            exit();
        }

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



}
