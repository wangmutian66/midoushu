<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\common\logic;

use think\Model;
use think\Page;
use think\db;

/**
 * 分类逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class SuppliersLogic extends Model
{
    protected $suppliers_id = 0;

    /*
     * 获取最近一笔订单
     */
    public function get_last_order($suppliers_id){
        $last_order = M('order')->where("suppliers_id", $suppliers_id)->order('order_id DESC')->find();
        return $last_order;
    }

    /**
     * 获取供货商销售额
     * @param $Supplier
     * @return num
     */  
    
    public function getSalemoney($sid,$begin="",$end="")
    {
        $order_where['o.suppliers_id'] = $sid;
        $order_where['o.pay_status']   = 1;
        if( $begin ) $order_where['o.add_time'] = ['egt',$begin]; 
        if( $end )   $order_where['o.add_time'] = ['elt',$end]; 

        $list = Db::name('order')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.order_amount) as amount')
            ->join('suppliers u','o.suppliers_id=u.suppliers_id','LEFT')
            ->where($order_where)
            ->group('o.suppliers_id')
            ->find();   //以用户ID分组查询

        if($list['amount'])
            $salemoney = $list['amount'];
        else 
            $salemoney = 0;

        return $salemoney;
    }

    /**
     * 获取账户资金记录
     * @param $user_id|用户id
     * @param int $account_type|收入：1,支出:2 所有：0
     * @return mixed
     */
    public function get_account_log($suppliers_id,$account_type = 0){
        $account_log_where = ['suppliers_id'=>$suppliers_id];
        if($account_type == 1){
            $account_log_where['suppliers_money|pay_points'] = ['gt',0];
        }
        if($account_type == 2){
            $account_log_where['suppliers_money|pay_points'] = ['lt',0];
        }
        $count = M('suppliers_account_log')->where($account_log_where)->count();
        $Page = new Page($count,16);
        $account_log = M('suppliers_account_log')->where($account_log_where)
            ->order('change_time desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$account_log,
            'show'      =>$Page->show_sup()
        ];
        return $return;
    }

    /**
     * 提现记录
     * @author lxl 2017-4-26
     * @param $user_id
     * @param int $withdrawals_status 提现状态 0:申请中 1:申请成功 2:申请失败
     * @return mixed
     */
    public function get_withdrawals_log($suppliers_id,$withdrawals_status=''){
        $withdrawals_log_where = ['suppliers_id'=>$suppliers_id];
        if($withdrawals_status){
            $withdrawals_log_where['status']=$withdrawals_status;
        }
        $count = M('suppliers_withdrawals')->where($withdrawals_log_where)->count();
        $Page = new Page($count, 15);
        $withdrawals_log = M('suppliers_withdrawals')->where($withdrawals_log_where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$withdrawals_log,
            'show'      =>$Page->show_cxpc()
        ];
        return $return;
    }


    /**
     * 修改密码
     * @param $suppliers_id  供货商id
     * @param $old_password  旧密码
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     * @param bool|true $is_update
     * @return array
     */
    public function password($suppliers_id,$old_password,$new_password,$confirm_password,$is_update=true){
        $suppliers = M('suppliers')->where('suppliers_id', $suppliers_id)->find();

        $user      = M('users')->where('user_id', $suppliers['user_id'])->find();

        if(strlen($new_password) < 6)
            return array('status'=>-1,'msg'=>'密码不能低于6位字符','result'=>'');
        if($new_password != $confirm_password)
            return array('status'=>-1,'msg'=>'两次密码输入不一致','result'=>'');

        if($new_password == $user['password'])
            return array('status'=>-1,'msg'=>'不可与会员登录密码一样','result'=>'');

        //验证原密码
        if($is_update && ($suppliers['suppliers_password'] != '' && encrypt($old_password) != $suppliers['suppliers_password']))
            return array('status'=>-1,'msg'=>'密码验证失败','result'=>'');
        $row = M('suppliers')->where("suppliers_id", $suppliers_id)->save(array('suppliers_password'=>encrypt($new_password)));
        if(!$row)
            return array('status'=>-1,'msg'=>'修改失败','result'=>'');
        return array('status'=>1,'msg'=>'修改成功','result'=>'');
    }

    
    /**
     * 设置支付密码
     * @param $suppliers_id  供货商id
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     */
    public function paypwd($suppliers_id,$new_password,$confirm_password){
        if(strlen($new_password) < 6)
            return array('status'=>-1,'msg'=>'密码不能低于6位字符','result'=>'');
        if($new_password != $confirm_password)
            return array('status'=>-1,'msg'=>'两次密码输入不一致','result'=>'');
        $row = M('suppliers')->where("suppliers_id",$suppliers_id)->update(array('suppliers_paypwd'=>encrypt($new_password)));
        if(!$row){
            return array('status'=>-1,'msg'=>'修改失败','result'=>'');
        }
        $url = session('payPriorUrl') ? session('payPriorUrl'): U('Suppliers/detail');
        session('payPriorUrl',null);
    	return array('status'=>1,'msg'=>'修改成功','url'=>$url);
    }
    
    /**
     * 账户明细
     */
    public function account($suppliers_id, $type='all'){
    	if($type == 'all'){
    		$count = M('suppliers_account_log')->where("suppliers_money!=0 and user_id=" . $user_id)->count();
    		$page = new Page($count, 16);
    		$account_log = M('suppliers_account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("suppliers_money!=0 and suppliers_id=" . $suppliers_id)
                ->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
    	}else{
    		$where = $type=='plus' ? " and user_money>0 " : " and user_money<0 ";
    		$count = M('suppliers_account_log')->where("suppliers_id=" . $suppliers_id.$where)->count();
    		$page = new Page($count, 16);
    		$account_log = Db::name('suppliers_account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("suppliers_id=" . $suppliers_id.$where)
                ->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
    	}
    	$result['suppliers_account_log'] = $account_log;
    	$result['page'] = $page;
    	return $result;
    }


     /**
     * 检查短信/邮件验证码验证码
     * @param unknown $code
     * @param unknown $sender
     * @param unknown $session_id
     * @return multitype:number string
     */
    public function check_validate_code($code, $sender, $type ='email', $session_id=0 ,$scene = -1){
        
        $timeOut = time();
        $inValid = true;  //验证码失效

        //短信发送否开启
        //-1:用户没有发送短信
        //空:发送验证码关闭
        $sms_status = checkEnableSendSms($scene);

        //邮件证码是否开启
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
        
        if($type == 'email'){            
            if(!$reg_smtp_enable){//发生邮件功能关闭
                $validate_code = session('validate_code');
                $validate_code['sender'] = $sender;
                $validate_code['is_check'] = 1;//标示验证通过
                session('validate_code',$validate_code);
                return array('status'=>1,'msg'=>'邮件验证码功能关闭, 无需校验验证码');
            }            
            if(!$code)return array('status'=>-1,'msg'=>'请输入邮件验证码');                
            //邮件
            $data = session('validate_code');
            $timeOut = $data['time'];
            if($data['code'] != $code || $data['sender']!=$sender){
                $inValid = false;
            }  
        }else{
            if($scene == -1){
                return array('status'=>-1,'msg'=>'参数错误, 请传递合理的scene参数');
            }elseif($scene == 6){
                $suppliers = M('suppliers')->where("suppliers_email", $sender)->whereOr('suppliers_phone', $sender)->find();
                if ($suppliers) {
                    session('find_password', array('suppliers_id' => $suppliers['suppliers_id'], 'suppliers_name' => $suppliers['suppliers_name'],
                        'suppliers_email' => $suppliers['suppliers_email'], 'suppliers_phone' => $suppliers['suppliers_phone'], 'type' => $field));
                } else {
                    echo "用户名不存在，请检查";
                    array('status'=>-1,'msg'=>'用户账号不存在');
                }
            }elseif($sms_status['status'] == 0){
                $data['sender'] = $sender;
                $data['is_check'] = 1; //标示验证通过
                session('validate_code',$data);
                return array('status'=>1,'msg'=>'短信验证码功能关闭, 无需校验验证码');
            } 
            
            if(!$code)return array('status'=>-1,'msg'=>'请输入短信验证码');
            //短信
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 180;
            $data = M('sms_log')->where(array('mobile'=>$sender,'session_id'=>$session_id , 'status'=>1))->order('id DESC')->find();
            //file_put_contents('./test.log', json_encode(['mobile'=>$sender,'session_id'=>$session_id, 'data' => $data]));
            if(is_array($data) && $data['code'] == $code){
                $data['sender'] = $sender;
                $timeOut = $data['add_time']+ $sms_time_out;
            }else{
                $inValid = false;
            }           
        }
        
       if(empty($data)){
           $res = array('status'=>-1,'msg'=>'请先获取验证码');
       }elseif($timeOut < time()){
           $res = array('status'=>-1,'msg'=>'验证码已超时失效');
       }elseif(!$inValid)
       {
           $res = array('status'=>-1,'msg'=>'验证失败,验证码有误');
       }else{
            $data['is_check'] = 1; //标示验证通过
            session('validate_code',$data);
            $res = array('status'=>1,'msg'=>'验证成功');
        }
        return $res;
    }

    /**
     * 邮箱或手机绑定
     * @param $email_mobile  邮箱或者手机
     * @param int $type  1 为更新邮箱模式  2 手机
     * @param int $user_id  用户id
     * @return bool
     */
    public function update_email_mobile($email_mobile,$suppliers_id,$type=1){
        //检查是否存在邮件
        if($type == 1)
            $field = 'suppliers_email';
        if($type == 2)
            $field = 'suppliers_phone';
        $condition['suppliers_id'] = array('neq',$suppliers_id);
        $condition[$field] = $email_mobile;

        $is_exist = M('suppliers')->where($condition)->find();
        if($is_exist)
            return false;
        unset($condition[$field]);
        $condition['suppliers_id'] = $suppliers_id;
        $validate = $field.'_validated';
        M('suppliers')->where($condition)->save(array($field=>$email_mobile,$validate=>1));
        return true;
    }
    
}