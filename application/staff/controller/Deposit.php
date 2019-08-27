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
use app\common\logic\UsersLogic;
class Deposit extends Base {
	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
   } 

    public function index(){
        $staff_info = db('staff')->cache('public_staff_'.$this->staff_id)->find($this->staff_id);
        $this->assign('staff_info',$staff_info);

        $start_time = I('start_time',date('Y-m-d',strtotime('-1 month')));   
        $end_time = I('end_time',date('Y-m-d',strtotime('+1 day')));   
        $this->assign('start_time',$start_time); 
        $this->assign('end_time',$end_time);
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        $where['create_time']   =   ['between',[$start_time,$end_time]];
        $where['staff_id']  =   ['eq',$this->staff_id];
        $p = I('p/d',1);
        $page_last = 5;
        $list = DB::name('staff_withdrawals')->where($where)->order('id desc')->page("{$p},{$page_last}")->select();
        $count = DB::name('staff_withdrawals')->where($where)->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();
        // $this->assign('pager',$Page);
        $this->assign('page',$show);
        $this->assign('list', $list);
        $this->assign('crumbs','提现记录');
        return $this->fetch();
    }

    #申请提现
    public function withdraw(){
        $staff_info = cache("public_staff_{$this->staff_id}");

        if(empty($staff_info['phone'])){
            $this->error('请先绑定手机号码',U('/Staff/Profile/set_phone'));
        }
        $phone = $staff_info['phone'];
        $money = db('staff_balance')->where(['phone'=>$phone])->value("balance");

        if($money <= 0){
            $this->error('您的可用余额为0，不可提现');
        }
        if($staff_info['present_money'] >= $money){
            $this->error('您的可用余额低于最低提现额度，不可提现');
        }
        if($staff_info['present_time_start'] != 0 && $staff_info['present_time_end'] !=0 && $staff_info['present_time_end'] >= $staff_info['present_time_start'] ){
            if(!($staff_info['present_time_start'] <= date('d') && $staff_info['present_time_end'] >= date('d'))){
                $this->error("提现日期不符合要求,请在每月 {$staff_info['present_time_start']}日 - {$staff_info['present_time_end']}日 提现");
            }
        }



        $balance = db('staff_balance')->where(["phone"=>$staff_info['phone']])->value("balance");

        $this->assign('money',$balance);
        $this->assign('bank_list',tk_bank_list());
        $config = tpCache('shop_info');
        $staff_info['service_charge']=$config['poundage'];
        $this->assign('staff_info',$staff_info);
        return $this->fetch();
    }


    function doWithdraw(){
        $staff_info = cache("public_staff_{$this->staff_id}");
        $tmoney = db('staff_balance')->where(["phone"=>$staff_info['phone']])->value('balance');
        $code =I('post.mobile_code/d');
        $money = I('post.money/f');
        $data['status'] = 1;
        if($money < 0){
            $data['status'] =   0;
            $data['info']   =   '请输入提现金额<br>';
        }
        if($money < $staff_info['present_money']){
            $data['status'] =   0;
            $data['info']   =   '提现金额小于最低限额<br>';
        }
        if(empty($code)){
            $data['status'] =   0;
            $data['info']   .=   '验证码不能为空<br>';
        }

        $config = tpCache('shop_info');
        $service_fee = $config['poundage'];    // 推广员提现手续费
        //$service_fee = $staff_info['service_charge'];    // 推广员提现手续费
        $taxfee      = bcdiv(bcmul($money,$service_fee,9),100,9); // 手续费
        $total       = bcadd($money,$taxfee,9);          // 总

        if($tmoney < $total){
            $data['status'] =   0;
            $data['info']   .=   '您的可用余额不足<br>';
        }

        $userLogic = new UsersLogic();
        $check_code = $userLogic->check_validate_code($code, $staff_info['phone'], 'phone', session_id(), 6);
        if ($check_code['status'] != 1){
            $data['status'] =   0;
            $data['info']   .=   $check_code['msg'];
        }

        if($data['status'] == 1){
            
//            $staff_data['money']  = ['exp'," money - {$total}"];
//            $staff_data['frozen'] = ['exp'," frozen + {$total}"];
//            $staff_r = M('staff')->where("id = {$this->staff_id}")->save($staff_data);

            $member_data['balance']    =   ['exp'," balance - {$total}"];
            $member_data['frozen']    =   ['exp'," frozen + {$total}"];
            $staff_r = db('staff_balance')->where(["phone"=>$staff_info['phone']])->save($member_data);

            if($staff_r){
                $_POST['create_time'] = NOW_TIME;
                $_POST['staff_id']    = $this->staff_id;
                $_POST['money']       = $money;
                $_POST['taxfee']      = $taxfee;
                $r = M('StaffWithdrawals')->save($_POST);
                if($r){
                    staff_accountLog($this->staff_id, (-1 * $total),"管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    cache::rm("public_staff_{$this->staff_id}");
                    $data['status'] =   1;
                    $data['info']   ='申请成功，请等待管理员审核';
                }else{
                    $data['status'] =   0;
                    $data['info']   =   '申请失败，请重试';
                }
            }else{
                $data['status'] =   0;
                $data['info']   =   '申请失败，请重试';
            }
        }
        $this->ajaxReturn($data);
    }
    
    function view(){
        $id = I('get.id/d');
        $r = db('staff_withdrawals')->where('staff_id','eq',$this->staff_id)->find($id);
        if($r){
            $this->assign('r',$r);
            return $this->fetch();    
        }else{
            $this->error('数据不存在！');
        }        
    }


}