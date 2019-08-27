<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\staff\controller; 
use think\Controller;
//use think\Config;
use think\Cache;
use think\Page;
use think\Db;
#use app\common\logic\UsersLogic;
#代付
class Paid extends Base {

#	var $staff_id;
	/**
     * 析构函数
     */
    function _initialize() 
    {
    #	$this->staff_id = Session('staff.id');
        parent::_initialize();
    } 

    function test(){
      //  action('Home/Test/index',);
    #    $a = \think\Loader::controller('Home/Test');
        /*$project = \think\Loader::controller('Mobile/MobileBase');
        $project->tktest();*/
    //    $test->index();
    }

    public function index(){
        $p = I('p/d',1);
        $page_last = 4;
        $phone = db('staff')->where(['id'=>$this->staff_id])->value("phone");
        $staff_id = db('staff')->where(['phone'=>$phone])->column('id');

        $where["paid.staff_id"]  =   ['in',$staff_id];
        $pay_status = I('get.pay_status/d');
        if($pay_status != 999){
            $where["paid.pay_status"]   =   ['eq',0];
        }
        $list = DB::name('staff_paid')->where($where)
                                ->field("paid.*,user.mobile")
                                ->alias('paid')
                                ->order('id desc')
                                ->join('users user','user.user_id = paid.user_id')
                                ->page("{$p},{$page_last}")
                                ->select();
       # dump($list);die;
        $count = DB::name('staff_paid')->where($where)->alias('paid')->join('users user','user.user_id = paid.user_id')->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();        
        $this->assign('page',$show);
        $this->assign('list', $list);
        return $this->fetch('index');
    }


    public function Log(){
        $p = I('p/d',1);
        $page_last = 4;
        $phone = db('staff')->where(['id'=>$this->staff_id])->value("phone");
        $staff_id = db('staff')->where(['phone'=>$phone])->column('id');
        $where["a.staff_id"]  =   ['in',$staff_id];
        $pay_status = I('get.pay_status/d',2);
        if($pay_status == 2){
            $where["a.pay_status"]   =   ['eq',1];
        }else{
            $where["a.pay_status"]   =   ['eq',0];
        }
        $list = DB::name('staff_mypays')->where($where)
                                ->field("a.*,user.mobile")
                                ->alias('a')
                                ->order('id desc')
                             //   ->join('staff staff','staff.id = a.staff_id','left')
                                ->join('users user','user.user_id = a.user_id')
                                ->page("{$p},{$page_last}")
                                ->select();
        $count = DB::name('staff_mypays')->where($where)->alias('a')->join('users user','user.user_id = a.user_id')->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();        
        $this->assign('page',$show);
        $this->assign('list', $list);
        return $this->fetch('log');
    }


    public function Pay(){
        $id = I('id/d');
        $phone = db('staff')->where(['id'=>$this->staff_id])->value("phone");
        $staff_id = db('staff')->where(['phone'=>$phone])->column('id');
        $where['p.staff_id']  =   ['eq',$staff_id];
        $where['pay_status']    =   ['eq',0];
        $r = db('staff_paid')->alias('p')->field('p.*,u.mobile')
                            ->where($where)
                            ->field('p.*,u.mobile,store.cname store_name,store.is_payment store_alipay_status,store.siyao store_private_key,store.gongyao store_public_key,store.alipay_id store_app_id,company.cname company_name,company.is_payment company_alipay_status,company.siyao company_private_key,company.gongyao company_public_key,company.alipay_id company_app_id,staff.real_name staff_name,staff.store_id,staff.company_id')
                            ->join('users u',"u.user_id = p.user_id")
                            ->join('staff staff','staff.id = p.staff_id')
                            ->join('company store','store.cid = staff.store_id')
                            ->join('company company','company.cid = staff.company_id')
                            ->find($id);
        #,store.is_weixin,store.w_appid,store.w_mchid,store.w_miyao,store.w_appsecret,company.is_weixin c_is_weixin,company.w_appid c_w_appid,company.w_mchid c_w_mchid,company.w_miyao c_w_miyao,company.w_appsecret c_w_appsecret
        if($r){
            $transfer_log_data['is_alipay'] = 0;
            if($r['company_alipay_status'] == 1){
                $transfer_log_data['store_id']  =   $r['company_id'];
                $transfer_log_data['store_name']  =   $r['company_name'];
                $transfer_log_data['alipay_app_id']  =   $r['company_app_id'];
                $transfer_log_data['alipay_public']  =   $r['company_public_key'];
                $transfer_log_data['alipay_private']  =   $r['company_private_key'];
                $transfer_log_data['is_alipay'] = 1;
            }
            if($r['store_alipay_status'] == 1){
                $transfer_log_data['store_id']  =   $r['store_id'];
                $transfer_log_data['store_name']  =   $r['store_name'];
                $transfer_log_data['alipay_app_id']  =   $r['store_app_id'];
                $transfer_log_data['alipay_public']  =   $r['store_public_key'];
                $transfer_log_data['alipay_private']  =   $r['store_private_key'];
                $transfer_log_data['is_alipay'] = 1;
            }
            if($transfer_log_data['is_alipay'] == 1){
                if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $this->error('请使用外部浏览器支付该订单，公众号中无法支持该笔交易');
                    exit();
                }
                $transfer_log_data['staff_id']  =   $r['staff_id'];
                $transfer_log_data['staff_name']  =   $r['staff_name'];
                $transfer_log_data['paid_sn']  =   $r['paid_sn'];
                $transfer_log_data['create_time']  =   NOW_TIME;
                $transfer_log_data['paid_id']  =   $id;
                if(!db('transfer_log')->where("paid_sn = '{$r['paid_sn']}'")->find()){
                    db('transfer_log')->insert($transfer_log_data);
                }
                $paymentList[0]['code'] =   'alipayMobile';
                $paymentList[0]['name'] =   '手机网站支付宝';
                $paymentList[0]['icon'] =   'logo.gif';
                $paymentList[0]['type'] =   'payment';
            }else{
                $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and scene = 1")->select();                //微信浏览器
                if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
                }
            }
        //    dump($paymentList);
            $paymentList = convert_arr_key($paymentList, 'code');
            $this->assign('paymentList', $paymentList);
            
            $this->assign('item',$r);
            return $this->fetch('pay');
        }else{
            $this->error('数据不存在或无权限支付');
        }
        
    }
    #https://www.midoushu.com/Staff/paid/pay_status/paid_sn/staff_paid_15299829954088
    function pay_status(){
        $paid_sn = I('paid_sn');
        $where['paid_sn']   =   ['eq',$paid_sn];
        $where['staff_id']  =   ['eq',$this->staff_id];
        $order = M("staff_paid")->where($where)->find();
   #     echo M("staff_paid")->getlastsql();
        $this->assign('order',$order);
        return $this->fetch('pay_status');
    }

    

}