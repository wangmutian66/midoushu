<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\storemobile\controller; 
use think\Controller;
use think\Config;
use think\Page;
use think\Db;
use think\Cache;
use app\common\logic\UsersLogic;
/*
    成员管理
*/
class CapitalManage extends Base {

	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
        $store_info = db('company')->find($this->store_id);
        $this->assign('store_info',$store_info);
   } 

    public function index(){
        //搜索关键词
		$where['store_id']	=	['eq',$this->store_id];
        $p = I('p/d',1);
        $page_last = 10;
        if($key_word = I('get.key_word/s')) $where['partner_trade_no'] = ['like',"%{$key_word}%"] ;
        $list = DB::name('store_withdraw_log')->where($where)->order('id desc')->page("{$p},{$page_last}")->select();
        $count = DB::name('store_withdraw_log')->where($where)->count();
        $Page = new Page($count,$page_last);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list', $list);
        return $this->fetch('index');
    }
    public function withdrawals(){        
        return $this->fetch('withdrawals');   
    }

 

    function dowithdrawals(){
        $store_info = db('company')->find($this->store_id);
        $money = I('post.money/f');
        $test = I('get.test/d',0);
        if($money < 0){
            $res['status'] =   0;
            $res['info']   =   '请输入提现金额<br>';
            $this->ajaxReturn($res);
        }
        if($money > $store_info['store_money']){
            $res['status'] =   0;
            $res['info']   =   '您的可用余额不足<br>';
            $this->ajaxReturn($res);
        }
        $presentation_mode = I('presentation_mode/d',1);
        if($presentation_mode == 1){
            //企业付款到零钱
            $code =I('post.dxyzm/d');
            $scene = I('post.scene/d');
            if(empty($code)){
                $res['status'] =   0;
                $res['info']   =   '验证码不能为空';
                $this->ajaxReturn($res);
            }
            $userLogic = new UsersLogic();
            $check_code = $userLogic->check_validate_code($code, $store_info['receivable_mobile'], 'phone', session_id(), $scene);
            if ($check_code['status'] != 1){
                $res['status'] =   0;
                $res['info']   =   $check_code['msg'];
                $this->ajaxReturn($res);
            }
            $store_data['store_money']  =   ['exp'," store_money - {$money}"];
            $store_data['v']    =   ['exp','v +1'];
            $store_where['cid'] =   $this->store_id;
            $store_where['v']   =   ['eq',$store_info['v']];
            $store_r = M('company')->where($store_where)->save($store_data);
            if($store_r){
                $res = $this->pay_lq($money,$store_info);
                if($res['status'] == -1){
                    $store_data['store_money']  =   ['exp'," store_money + {$money}"];
                    $store_where['v']   =   ['eq',$store_info['v']+1];
                    M('company')->where($store_where)->save($store_data);
                }
            }
        }else{
            $res = $this->pay_yhk($money,$store_info);    
        }
        
        $this->ajaxReturn($res);
    }
    

    function pay_lq($money,$store_info,$partner_trade_no=''){

        $open_id = db('users u')->join('oauth_users ou','ou.user_id = u.user_id')->where('mobile',$store_info['receivable_mobile'])->getField('ou.openid');

        if(empty($open_id)){
            $res['status'] =   -1;
            $res['info']   =  "请使用手机号码 {$store_info['receivable_mobile']} 在本公众号内注册，否则无法打款";
            return $res;
        }
        if(!$partner_trade_no){
            $partner_trade_no = $this->get_store_withdraw_no();
            $log_id = $this->create_order($money,$store_info,$partner_trade_no);
        }else{
            $log_id = db('store_withdraw_log')->where('partner_trade_no',$partner_trade_no)->cache(true)->getField('id');
        }
        include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
        $weixin_obj = new \weixin();
        $weixinpay['openid']    =   $open_id;
        $weixinpay['money']     =   $money;
        $weixinpay['partner_trade_no']  =   $partner_trade_no;
        $r = $weixin_obj->tk_transfer_lq($weixinpay);
        if($r['status'] == 1){
            $res['status']  =   0;
            $res['info']    =   '系统繁忙，请在提现记录中重新申请';
            return $res;
        }else{
            if($r['result_code'] == 'SUCCESS'){
                $save_data['result_code']   =   $r['result_code'];
                $save_data['payment_no']   =   $r['payment_no'];
                $save_data['payment_time']   =   $r['payment_time'];
                M('store_withdraw_log')->where('id',$log_id)->save($save_data);
                $res['status'] = 1;
                $res['info']   =   '提现成功！请在微信零钱中查询资金';
            }else{
                $res['status'] =   0;
                $res['info']   =   '系统繁忙，请在提现记录中重新申请';
            }
        }
    
        return $res;
    }

    function rewithdrawals(){
        $id = I('get.id/d');
        $r = db('store_withdraw_log')->find($id);

        $store_info = db('company')->find($this->store_id);
        if(!$r){
            $res['status']  =   0;
            $res['info']    =   '记录不存在';
            $this->ajaxReturn($res);
        }
        if($r['is_card'] == 1){
            $res = $this->pay_yhk($r['txje'],$store_info,$r['partner_trade_no']);
        }else{
            $res = $this->pay_lq($r['txje'],$store_info,$r['partner_trade_no']);
        }
        $this->ajaxReturn($res);
        
    }

    function create_order($money,$store_info,$partner_trade_no,$is_card=0){
        $insert_data['store_id']  =   $this->store_id;
        $insert_data['store_name']    =   $store_info['cname'];
        $insert_data['txje']  =  $money;
        $insert_data['create_time']   =   NOW_TIME;
        $insert_data['nonce_str'] =  md5(time().rand(1000,9999));
        $insert_data['partner_trade_no'] = $partner_trade_no;
        $insert_data['is_card']   =   $is_card;
        if($is_card == 1){
            $insert_data['enc_true_name']   =   $store_info['enc_true_name'];
            $insert_data['enc_bank_no'] =    $store_info['enc_bank_no'];
            $insert_data['bank_code']   =   $store_info['bank_code'];
            $insert_data['cmms_amt']    =   $this->calculation_fee($money);
        }else{
            $insert_data['receivable_mobile']     =   $store_info['receivable_mobile'];
            $insert_data['cmms_amt']    =   0;
        }
        return Db::name('store_withdraw_log')->insertGetId($insert_data);
    }

    function pay_yhk($money,$store_info,$partner_trade_no=''){
        if($store_info['enc_true_name'] == ''){
            $res['status']  =   0;
            $res['info']    =   '收款方用户名不存在';
            return $res;
        }
        if($store_info['enc_bank_no'] == ''){
            $res['status']  =   0;
            $res['info']    =   '收款方银行卡号不存在';
            return $res;
        }
        if($store_info['bank_code'] == ''){
            $res['status']  =   0;
            $res['info']    =   '收款方开户行不存在';
            return $res;
        }
        if(!$partner_trade_no){
            #计算手续费           
            $store_data['store_money']  =   ['exp'," store_money - {$money}"];
            $store_data['v']    =   ['exp','v +1'];
            $store_where['cid'] =   $this->store_id;
            $store_where['v']   =   ['eq',$store_info['v']];
            $store_r = M('company')->where($store_where)->save($store_data);
            $partner_trade_no = $this->get_store_withdraw_no();
            $log_id = $this->create_order($money,$store_info,$partner_trade_no,1);
        }else{
            $log_id = db('store_withdraw_log')->where('partner_trade_no',$partner_trade_no)->getField('id');
        }
     
        $weixinpay['bank_code'] =   $store_info['bank_code'];       //收款方开户行
        $weixinpay['enc_bank_no'] =   $store_info['enc_bank_no'];       //收款方银行卡号
        $weixinpay['enc_true_name'] =   $store_info['enc_true_name'];       //收款方用户名
        $weixinpay['money']    =     $money;
        include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
        $weixin_obj = new \weixin();
       
        $r = $weixin_obj->tk_transfer_yhk($weixinpay);
        if($r['status'] == 1){
            $res['status']  =   0;
            $res['info']    =    "微信系统提示：{$r['msg']}  请在提现记录中重新申请";
            $this->ajaxReturn($res);
        }else{
            if($r['result_code'] == 'SUCCESS'){
                $save_data['cmms_amt']   =   $r['cmms_amt'] / 100;
                $save_data['partner_trade_no']  =   $r['partner_trade_no'];
                $save_data['payment_no']   =   $r['payment_no'];
                $save_data['result_code']   =   $r['result_code'];
                M('store_withdraw_log')->where('id',$log_id)->save($save_data);
                cache::rm("store_{$this->store_id}");
                $res['status'] = 1;
                $res['info']   =   '微信侧受理成功！请等待银行处理该申请';
            }else{
                $res['status'] =   0;
                $res['info']   =   '系统繁忙，请在提现记录中重新申请';
            }
            $this->ajaxReturn($res);
        }
    }

    function get_bank_status(){
        $id = I('get.id/d');
        $where['id']    =   ['eq',$id];
        $where['store_id']  =   ['eq',$this->store_id];
        $result = db('store_withdraw_log')->cache(true)->where($where)->find();
        
        if($result['partner_trade_no'] && $result['is_card'] == 1){
            include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
            $weixin_obj = new \weixin();
            $r = $weixin_obj->query_bank($result['partner_trade_no']);
            if($r['status'] == 1){
                $res['system_status'] = 0;
                $res['info']  =   $res['msg'];
                $this->ajaxReturn($res);
            }
            $save_data['status']    =   $r['status'];
            $save_data['reason']    =   $r['reason'];
            M('store_withdraw_log')->where('partner_trade_no',$result['partner_trade_no'])->save($save_data);
           
            $r['system_status'] =   1;
            $r['status']    =   query_bank_status($r['status']);
            $this->ajaxReturn($r);
        }
    }

    



    function calculation_fee($money){
        $fee = bcmul($money,0.001,9);
        if($fee < 1){
            $fee = 1;
        }elseif($fee > 25){
            $fee = 25;
        }
        return $fee;
    }

    /*获取商户订单号*/
    function get_store_withdraw_no(){
        $str_no = time(). rand(1000,9999);
        $r = M('store_withdraw_log')->where('partner_trade_no',$str_no)->find();
        if($r){
            return $this->get_store_withdraw_no();
        }else{
            return $str_no;
        }
    }
}