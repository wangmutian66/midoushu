<?php
/*
	飞鹅打印机
*/
namespace Feie;

use Feie\HttpClient;
use think\Db;

class FeieService
{
	protected $FeieConstant	=	[];

	protected $orderInfo = [];
    protected $storeInfo = [];
	public $times;				//打印次数

	public function __construct(){

		$this->FeieConstant['IP']	=	'api.feieyun.cn';		//接口IP或域名
		$this->FeieConstant['PORT']	=	80;						//接口IP端口
		$this->FeieConstant['PATH']	=	'/Api/Open/';			//接口路径
		$this->FeieConstant['USER'] = '200544784@qq.com';
		$this->FeieConstant['UKEY'] = 'K5AYtfubpI42pynT';
		$this->FeieConstant['SIG'] = sha1($this->FeieConstant['USER'].$this->FeieConstant['UKEY'].NOW_TIME);		//公共参数，请求公钥
		$this->times = 1;

	}

	function setOrder($order){
		if($order['staff_id']){
			$staff_info = db::name('staff')->alias('staff')
							->field('staff.real_name staff_name,store.cname store_name,staff.printer_sn')
							->join('company store',"store.cid = staff.store_id",'left')
							->where('id',$order['staff_id'])
							->cache("feie_staff_{$order['staff_id']}")
							->find();
			$this->FeieConstant['printer_sn']	=	$staff_info['printer_sn'];
			if($staff_info['printer_sn']){
				$user_info	=	db::name('users')->where('user_id',$order['user_id'])->field('nickname,mobile')->find();
				$this->orderInfo['store_name']	=	$staff_info['store_name'];
				$this->orderInfo['staff_name']	=	$staff_info['staff_name'];
				$this->orderInfo['paid_sn']	=	$order['paid_sn'];
				if($order['pay_time']){
					$this->orderInfo['pay_time']	=	date("Y-m-d H:i:s",$order['pay_time']);
				}else{
					$this->orderInfo['pay_time']	=	date("Y-m-d H:i:s",$order['create_time']);
				}
				$this->orderInfo['remark']	=	$order['remark'];
				$this->orderInfo['pay_name']	=	$order['pay_name'];
				$this->orderInfo['user_nickname']	=	$user_info['nickname'];
				$this->orderInfo['user_mobile']	=	hide_mobile($user_info['mobile']);
				$this->orderInfo['user_id']	=	'18706'.$order['user_id'];
				$this->orderInfo['pay_money']	=	$order['money'];
			}
			
		}
	}

	public function getOrderInfoHtml(){
		$orderInfoHtml = "<CB>{$this->orderInfo['store_name']}</CB><BR>";
		$orderInfoHtml .= '--------------------------------<BR>';
		$orderInfoHtml .= "收款人：   {$this->orderInfo['staff_name']}<BR>";
		$orderInfoHtml .= "订单编号： {$this->orderInfo['paid_sn']}<BR>";
		$orderInfoHtml .= "支付方式： {$this->orderInfo['pay_name']}<BR>";
		$orderInfoHtml .= "用户昵称： {$this->orderInfo['user_nickname']}<BR>";
		$orderInfoHtml .= "用户手机： {$this->orderInfo['user_mobile']}<BR>";
		$orderInfoHtml .= "用户ID：   {$this->orderInfo['user_id']}<BR>";
		$orderInfoHtml .= "备注：{$this->orderInfo['remark']}<BR>";
		$orderInfoHtml .= '--------------------------------<BR>';
		$orderInfoHtml .= "支付金额：   {$this->orderInfo['pay_money']}<BR>";
		$orderInfoHtml .= "支付时间：   {$this->orderInfo['pay_time']}<BR><BR><BR><BR>";
		return $orderInfoHtml ;
	}
	/*
	 *  方法1
		拼凑订单内容时可参考如下格式
		根据打印纸张的宽度，自行调整内容的格式，可参考下面的样例格式

	*/
	public function wp_print(){
		
		if(!$this->FeieConstant['printer_sn']){
			$msg['status']	=	-10001;
			$msg['info']	=	'Printer_sn is empty !';
			return $msg;
		}
	
		$content = array(			
			'user'=>$this->FeieConstant['USER'],
			'stime'=>NOW_TIME,
			'sig'=>$this->FeieConstant['SIG'],
			'apiname'=>'Open_printMsg',
			'sn'=>$this->FeieConstant['printer_sn'],
			'content'=>$this->getOrderInfoHtml(),
		    'times'=>$this->times				
		);
		$client = new HttpClient($this->FeieConstant['IP'],$this->FeieConstant['PORT']);
		if(!$client->post($this->FeieConstant['PATH'],$content)){
			$msg['status']	=	-20000;
			$msg['info']	=	'Feie error!';
			return $msg;
		}
		else{
			//服务器返回的JSON字符串，建议要当做日志记录起来
			return $client->getContent();
		}
	}

    //实体店订单详情

    function setStore($count){
        if($count['store_id']){
            $staff_info = db::name('company')
                ->where('cid',$count['store_id'])
                ->find();
            $this->FeieConstant['printer_sn']	=	$staff_info['printer_sn'];
            if($count['store_name']){
                $this->storeInfo['repurchase']	=	$count['repurchase'];
                $this->storeInfo['cname']	=	$count['store_name'];
                $this->storeInfo['staff_mypays']	=	$count['staff_mypays'];
                if (empty($order['staff_mypays_store_money'])){
                    $this->storeInfo['staff_mypays_store_money']	=	'暂无数据';
                }else{
                    $this->storeInfo['staff_mypays_store_money']	=	$count['staff_mypays_store_money'];
                }
                if (empty($order['staff_mypays_money'])){
                    $this->storeInfo['staff_mypays_money']	=	'暂无数据';
                }else{
                    $this->storeInfo['staff_mypays_money']	=	$count['staff_mypays_money'];
                }
                $this->storeInfo['repurchase_person']	=	$count['repurchase_person'];
                $this->storeInfo['new_user']	=	$count['new_user'];
                $this->storeInfo['time']	=	$count['time'];
            }
        }
    }

    public function getStoreInfoHtml(){
        $orderInfoHtml = "<CB>{$this->storeInfo['cname']}</CB><BR>";
        $orderInfoHtml .= '--------------------------------<BR>';
        $orderInfoHtml .= "今日换购订单数量：   {$this->storeInfo['repurchase']}<BR>";
        $orderInfoHtml .= "今日订单总数： {$this->storeInfo['staff_mypays']}<BR>";
        $orderInfoHtml .= "今日实体店结余： {$this->storeInfo['staff_mypays_store_money']}<BR>";
        $orderInfoHtml .= "今日订单总额： {$this->storeInfo['staff_mypays_money']}<BR>";
        $orderInfoHtml .= "今日线下换购人数： {$this->storeInfo['repurchase_person']}<BR>";
        $orderInfoHtml .= "今日新用户：   {$this->storeInfo['new_user']}<BR>";
        $orderInfoHtml .= '--------------------------------<BR>';
        $orderInfoHtml .= "时间：   {$this->storeInfo['time']}<BR><BR><BR><BR>";
        return $orderInfoHtml ;
    }
    /*
     *  方法1
        拼凑订单内容时可参考如下格式
        根据打印纸张的宽度，自行调整内容的格式，可参考下面的样例格式

    */
    public function wp_storeprint(){

        if(!$this->FeieConstant['printer_sn']){
            $msg['status']	=	-10001;
            $msg['info']	=	'Printer_sn is empty !';
            return $msg;
        }

        $content = array(
            'user'=>$this->FeieConstant['USER'],
            'stime'=>NOW_TIME,
            'sig'=>$this->FeieConstant['SIG'],
            'apiname'=>'Open_printMsg',
            'sn'=>$this->FeieConstant['printer_sn'],
            'content'=>$this->getStoreInfoHtml(),
            'times'=>$this->times
        );

        $client = new HttpClient($this->FeieConstant['IP'],$this->FeieConstant['PORT']);
        if(!$client->post($this->FeieConstant['PATH'],$content)){
            $msg['status']	=	-20000;
            $msg['info']	=	'Feie error!';
            return $msg;
        }
        else{
            //服务器返回的JSON字符串，建议要当做日志记录起来
            return $client->getContent();
        }
    }

	/*
	 *  方法2
		根据订单索引,去查询订单是否打印成功,订单索引由方法1返回
	*/
	function queryOrderState($index){
			$msgInfo = array(
				'user'=>USER,
				'stime'=>STIME,
				'sig'=>SIG,	 
				'apiname'=>'Open_queryOrderState',
				
				'orderid'=>$index
			);
		
		$client = new HttpClient(IP,PORT);
		if(!$client->post(PATH,$msgInfo)){
			echo 'error';
		}
		else{
			$result = $client->getContent();
			echo $result;
		}
		
	}




	/*
	 *  方法3
		查询指定打印机某天的订单详情
	*/
	function queryOrderInfoByDate($printer_sn,$date){
			$msgInfo = array(
				'user'=>USER,
				'stime'=>STIME,
				'sig'=>SIG,			
				'apiname'=>'Open_queryOrderInfoByDate',
				
		        'sn'=>$printer_sn,
				'date'=>$date
			);
		
		$client = new HttpClient(IP,PORT);
		if(!$client->post(PATH,$msgInfo)){ 
			echo 'error';
		}
		else{
			$result = $client->getContent();
			echo $result;
		}
		
	}



	/*
	 *  方法4
		查询打印机的状态
	*/
	function queryPrinterStatus($printer_sn){
			
		    $msgInfo = array(
		    	'user'=>USER,
				'stime'=>STIME,
				'sig'=>SIG,	
				'apiname'=>'Open_queryPrinterStatus',
				
		        'sn'=>$printer_sn
			);
		
		$client = new HttpClient(IP,PORT);
		if(!$client->post(PATH,$msgInfo)){
			echo 'error';
		}
		else{
			$result = $client->getContent();
			echo $result;
		}
	}

}