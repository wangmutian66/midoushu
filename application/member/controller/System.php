<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\member\controller; 
use think\Session;
use think\Cookie;
class System extends Base {

    /*
     * 管理员登陆
     */
    public function login(){

        if(session('?member') && session('member.id')>0){
            $this->error("您已登录",U('/Member/Index/index'));
        }
      
        if(IS_POST){
            $condition['phone'] = I('post.username/s');
            $condition['psw'] = I('post.password/s');
            $store_id  =   I('post.store_id');
            $store_id && $condition['member.parent_id']  =   $store_id;
            if(!empty($condition['phone']) && !empty($condition['psw'])){
                $condition['psw'] = encrypt($condition['psw']);
             #  	$info = M('company_member')->where($condition)->find();
                $info = M('company_member')->field('company.cname company_name,id,phone,company.cid store_id')
                                    ->alias('member')
                                    ->join('company company','company.cid = member.parent_id')
                                    ->where($condition)
                                    ->select();

            #    echo M('staff')->getlastsql();die;
                if($info){
//                    if(count($info) > 1){
//                        $data['status'] =   2;
//                        $data['info'] =   $info;
//                        echo json_encode($data);
//                        die;
//                    }
                    $info  = $info[0];
                    if($info['is_lock'] == 1){
                        $msg['status']  =   0;
                        $msg['info']    =   '您的账户已经被冻结，请联系平台管理员';
                        $this->ajaxReturn($msg);
                    }
                    session('member.id',$info['id']);
                    $save_data = array('last_login'=>NOW_TIME,'last_ip'=>request()->ip());
                    M('company_member')->where("id = {$info['id']}")->save($save_data);
                    session('member.last_login',$save_data['last_login']);
                    session('member.last_ip',$save_data['last_ip']);

                    if(I('post.remember_psw')){
                        cookie::forever('member.id', $info['id']);
                        cookie::forever('member.last_login', $save_data['last_login']);
                        cookie::forever('member.last_ip', $save_data['last_ip']);
                    }
                    memberLog('后台登录');
                    $data['status'] =   1;
                }else{
                    $data['status'] =   0;
                    $data['info']   =   '用户名或密码不正确';
                }
            }else{
                $data['status'] =   0;
                $data['info']   =   '请填写用户名密码';
            }
            $this->ajaxReturn($data);
        }else{
            return $this->fetch();
        }
       
    }
    
    /**
     * 退出登陆
     */
    public function logout(){
		session::delete('member');
        cookie::delete('member');
        $this->success("退出成功",U('/Member/System/login'));
    }
    

    /*
    切换用户
    2018年11月5日09:58:40
    作者：王文凯
    */
    public function switch_user(){
        $store_id = I('post.store_id/d');
        $mobile = I('post.mobile/s');
        $where['parent_id']  =   ['eq',$store_id];
        $where['phone']    =   ['eq',$mobile];
        $where['id']    =   ['neq',$this->member_id];
        $r = db('company_member')->where($where)->find();
        if(!$r){
            $res['status']  =   0;
            $res['info']    =   '账户不存在，请稍后再试';
            $this->ajaxReturn($res);
        }
        if($r['is_lock'] == 1){
            $res['status']  =   0;
            $res['info']    =   '该账户已经被冻结，请联系系统管理员！';
            $this->ajaxReturn($res);
        }
        session('member.id',$r['id']);
        cookie::forever('member.id', $r['id']);
        $res['status']  =   1;
        $res['info']    =   '切换成功！';
        $this->ajaxReturn($res);
    }

    /**
     * 登录忘记密码
     * @author 张洪凯
     * @date 2018-11-12
     */
    public function forgetPwd(){
        if($this->request->isPost()){
            $session_id =  session_id();
            $mobile = I('post.mobile');
            $mobile_code = I('post.mobile_code');
            //判断是不是推广员账号
            $member = M('company_member')->where(['phone'=>$mobile])->find();
            if(empty($member)){
                $this->error("该账号不是成员账号！");
            }

            $sms_log = M('sms_log')->where(['mobile'=>$mobile,'session_id'=>$session_id,'scene'=>6])->order("id desc")->find();

            //判断验证码是否过期
            if(($mobile_code != $sms_log['code']) || ((time() - $sms_log['add_time']) > tpCache('sms.sms_time_out'))){
                $this->error("验证失败,验证码有误");
            }else{
                session("user.mobile",$mobile);
                session("member.id",$member['id']);
                $this->success("验证成功",url('Member/System/resetPwd'));
            }
        }else{
            return $this->fetch();
        }
    }

    /**
     * 修改密码
     * @author 张洪凯
     * @date 2018-11-12
     * @return mixed
     */
    public function resetPwd(){
        if($this->request->isPost()) {
            $password = input('post.password');
            $memberid = session("member.id");

            $phone = M('company_member')->where('id' , $memberid)->value("phone");
            $member = M('company_member')->where('phone' , $phone)->save(['psw'=>encrypt($password)]);
            $member = session('member');
            //if(empty($member)){
                session('member',null);
                cookie('member', null);
            //}
            $this->success("密码设置成功！",url('Member/System/login'));
        }else{
            return $this->fetch();
        }
    }




    /**
     * 实体店列表
     * @author 王牧田
     * @date 2018-12-05
     */
    public function store(){
//        $staff_info = cache("public_staff_{$this->staff_id}");
//        $staff_list = db('staff')
//            ->alias('staff')
//            ->field('staff.id,staff.phone,tkpsw,real_name,store.cname store_name,store.cid store_id,staff.money')
//            ->cache("staff_list_{$staff_info['phone']}")
//            ->join('company store','store.cid = staff.store_id','left')
//            ->where('staff.phone',$staff_info['phone'])
//            ->select();


        $member_info = cache("member_{$this->member_id}");
        $member_list = db('company_member')
            ->alias('member')
            ->field('member.phone,psw,real_name,store.cname store_name,company.cid company_id,company.cname as company_name,store.cid store_id,member.money,member.id')
            ->join('company store','store.cid = member.parent_id','left')
            ->join('company company','company.cid=store.parent_id','left')
            ->where('member.phone',$member_info['phone'])

            ->select();


        $this->assign('member_list',$member_list);
        return $this->fetch();
    }



    /**
     * 转至当前用户总余额
     * @author 王牧田
     * @date 2018-12-05
     */
    public function transferbalance(){

        $member_info = cache("member_{$this->member_id}");
        $member_id = I('post.member_id');
        $member = db('company_member')->where('id',$member_id)->find();

        //增加总余额数量
        if(empty($member_info['phone'])){
            $this->error('请先绑定手机号码',U('/Member/Profile/set_phone'));
        }

        $staffbalance = db('member_balance')->where(['phone'=>$member_info['phone']])->find();

        if(empty($staffbalance)){
            //如果不存在就去添加
            $member_balance['phone'] = $member_info['phone'];
            $member_balance['balance'] = $member['money'];
            db('member_balance')->add($member_balance);
        }else{
            //如果存在将追加的原来余额
            db('member_balance')->where(['phone'=>$member_info['phone']])->setInc('balance',$member['money']);
        }
        //减少当前用户在实体店的余额
        db('company_member')->where('id',$member_id)->save(['money'=>0]);



        //添加记录
        $addlog['time'] = time();
        $addlog['money'] = $member['money'];
        $addlog['ip'] = request()->ip();
        $addlog['member_id'] = $member_id;
        $addlog['store_id'] = $member['parent_id'];
        db('member_balance_log')->add($addlog);

        $result = array(
            'code' => 200,
            'message' => "success",
            'data' => []
        );
        return json($result);

    }



    /**
     * [一键全部转至当前余额]
     * @author 王牧田
     * @date 2018-12-11
     */
    public function transferbalanceall(){
        $member = db('company_member')->where('id',$this->member_id)->find();
        $phone = $member['phone'];
        $sumMoney = db('company_member')->where(['phone'=>$phone])->sum('money');

        //加到全部余额
        $staffbalance = db('member_balance')->where(['phone'=>$phone])->find();
        if(empty($staffbalance)){
            //如果不存在就去添加
            $member_balance['phone'] = $phone;
            $member_balance['balance'] = $sumMoney;
            db('member_balance')->add($member_balance);
        }else{
            //如果存在将追加的原来余额
            db('member_balance')->where(['phone'=>$phone])->setInc('balance',$sumMoney);
        }

        //记录日志
        $memberSelect = db('company_member')->where(['phone'=>$phone])->select();
        $data = [];
        foreach ($memberSelect as $row){
            if($row['money'] != 0){
                $data[]=[
                    'time' => time(),
                    'money' => $row['money'],
                    'ip' => request()->ip(),
                    'member_id' => $row['id'],
                    'store_id' => $row['parent_id'],
                ];
            }

        }
        db('member_balance_log')->insertAll($data);

        //清空余额
        db('company_member')->where(['phone'=>$phone])->save(['money'=>0]);


        $result = array(
            'code' => 200,
            'message' => "success",
            'data' => []
        );
        return json($result);


    }


    /**
     * [转至余额记录]
     * @author 王牧田
     * @date 2018-12-07
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function store_log(){
        $member_id = I('post.member_id');
        $store_id = I('post.store_id');
        $balanceLog = db('member_balance_log')->where(['member_id'=>$member_id,'store_id'=>$store_id])->select();
        $this->assign('balanceLog',$balanceLog);
        return $this->fetch();
    }



   
}