<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\member\controller; 
use think\Db;
use think\Page;
use app\common\logic\UsersLogic;
/*use think\Controller;
//use think\Config;
use think\Cache;


*/
class Deposit extends Base {

	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
   } 

    public function index(){
        $start_time = I('start_time',date('Y-m-d',strtotime('-1 month')));   
        $end_time = I('end_time',date('Y-m-d',strtotime('+1 day')));   
        $this->assign('start_time',$start_time); 
        $this->assign('end_time',$end_time);
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        $where['create_time']   =   ['between',[$start_time,$end_time]];
        $where['member_id']  =   ['eq',$this->member_id];
        $p = I('p/d',1);
        $page_last = 5;
        $list = DB::name('member_withdrawals')->where($where)->order('id desc')->page("{$p},{$page_last}")->select();
        $count = DB::name('member_withdrawals')->where($where)->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();
    //    $this->assign('pager',$Page);
        $this->assign('page',$show);
        $this->assign('list', $list);
        $this->assign('crumbs','提现记录');
        return $this->fetch('index');
    }

    #申请提现
    public function withdraw(){
        $member_info = cache("member_{$this->member_id}");

        $tmoney = db('member_balance')->where(["phone"=>$member_info['phone']])->value('balance');

        if(empty($member_info['phone'])){
            $this->error('请先绑定手机号码',U('/Member/Profile/set_phone'));
        }
        if($tmoney <= 0){
            $this->error('您的可用余额为0，不可提现');
        }
        if($member_info['present_money'] >= $tmoney){
            $this->error('您的可用余额低于最低提现额度，不可提现');
        }
        if($member_info['present_time_start'] != 0 && $member_info['present_time_end'] !=0 && $member_info['present_time_end'] >= $member_info['present_time_start'] ){
            if(!($member_info['present_time_start'] <= date('d') && $member_info['present_time_end'] >= date('d'))){
                $this->error("提现日期不符合要求,请在每月 {$member_info['present_time_start']}日 - {$member_info['present_time_end']}日 提现");
            }
        }
        $this->assign('money',$tmoney);
        $this->assign('bank_list',tk_bank_list());

        return $this->fetch();
    }
    function doWithdraw(){
        $member_info = cache("member_{$this->member_id}");
        $tmoney = db('member_balance')->where(["phone"=>$member_info['phone']])->value('balance');
        $code =I('post.mobile_code/d');
        $money = I('post.money/f');
        $scene = I('post.scene/d');
        $data['status'] = 1;
        if($money < 0){
            $data['status'] =   0;
            $data['info']   =   '您的可用余额不足<br>';
        }
        if(empty($code)){
            $data['status'] =   0;
            $data['info']   .=   '验证码不能为空<br>';
        }

        $config = tpCache('shop_info');
        $member_info['service_charge'] = $config['poundage'];

        $service_charge = bcmul($money ,bcdiv($member_info['service_charge'],100,9),9);
        $money = bcadd($money,$service_charge,9);

        if($tmoney < $money){
            $data['status'] =   0;
            $data['info']   .=   '您的可用余额不足<br>';
        }

        $userLogic = new UsersLogic();
        $check_code = $userLogic->check_validate_code($code, $member_info['phone'], 'phone', session_id(), $scene);
        if ($check_code['status'] != 1){
            $data['status'] =   0;
            $data['info']   .=   $check_code['msg'];
        }


        if($data['status'] == 1){
            #计算手续费
//            $member_data['money']    =   ['exp'," money - {$money}"];
//            $member_data['frozen']    =   ['exp'," frozen + {$money}"];
            //$member_r = M('company_member')->cache("member_{$this->member_id}")->where("id = {$this->member_id}")->save($member_data);

            $member_data['balance']    =   ['exp'," balance - {$money}"];
            $member_data['frozen']    =   ['exp'," frozen + {$money}"];
            $member_r = db('member_balance')->where(["phone"=>$member_info['phone']])->save($member_data);
            if($member_r){
                $_POST['create_time']    =   NOW_TIME;
                $_POST['member_id']  =   $this->member_id;
                $_POST['taxfee']    =   $service_charge;
                $r = M('member_withdrawals')->save($_POST);
                if($r){
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
        $r = db('member_withdrawals')->where('member_id','eq',$this->member_id)->find($id);
        if($r){
            $this->assign('r',$r);
            return $this->fetch();    
        }else{
            $this->error('数据不存在！');
        }        
    }



}