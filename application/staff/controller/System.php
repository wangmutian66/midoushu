<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\staff\controller; 
use think\AjaxPage;
use think\Controller;
use think\Request;
use think\Url;
use think\Config;
use think\Page;
//use think\Verify;
use think\Db;
use think\Session;
use think\Cookie;
class System extends Base {

    /*
     * 管理员登陆
     */
    public function login(){
        if(IS_POST){ 
            $condition['phone'] = I('post.username/s');
            $condition['tkpsw'] = I('post.password/s');
            $store_id  =   I('post.store_id');
            $store_id && $condition['store_id']  =   $store_id;
            //$condition['is_lock']  = 0;
            if(!empty($condition['phone']) && !empty($condition['tkpsw'])){
                $condition['tkpsw'] = encrypt($condition['tkpsw']);
               	$info = M('staff')->field('company.cname company_name,store.cname store_name,id,phone,store.cid store_id,staff.is_lock')
                                    ->alias('staff')
                                    ->join('company company','company.cid = company_id')
                                    ->join('company store','store.cid = store_id')
                                    ->where($condition)
                                    ->select();

                if($info){
//                    if(count($info) > 1){
//                        $data['status'] =   2;
//                        $data['info'] =   $info;
//                        echo json_encode($data);
//                        die;
//                    }

                    $info  = current($info);
                   
                    if($info['is_lock'] == 1){
                        $msg['status']  =   0;
                        $msg['info']    =   '您的账户已经被冻结，请联系平台管理员';
                        $this->ajaxReturn($msg);
                    }
                    session('staff.id',$info['id']);
                    $save_data = array('last_login'=>NOW_TIME,'last_ip'=>request()->ip());

                    M('staff')->where("id = {$info['id']}")->save($save_data);
                    session('staff.last_login',$save_data['last_login']);
                    session('staff.last_ip',$save_data['last_ip']);
                    if(I('post.remember_psw')){
                        cookie::forever('staff.id', $info['id']);
                        cookie::forever('staff.last_login', $save_data['last_login']);
                        cookie::forever('staff.last_ip', $save_data['last_ip']);
                    }
                    staffLog('后台登录');
                    $data['status'] = 1;
                }else{
                    $data['status'] = 0;
                    $data['info']   = '用户名或密码不正确';
                }
            }else{
                $data['status'] = 0;
                $data['info']   = '请填写用户名密码';
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
        session('staff',null);
        cookie('staff', null);
        $this->success("退出成功",U('Staff/System/login'));
    }
    /*
    切换用户
    2018年11月5日09:58:40
    作者：王文凯
    */
    public function switch_user(){
        $store_id = I('post.store_id/d');
        $mobile = I('post.mobile/s');
        $where['store_id']  =   ['eq',$store_id];
        $where['phone']    =   ['eq',$mobile];
        $where['id']    =   ['neq',$this->staff_id];
        $r = db('staff')->where($where)->find();
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
        session('staff.id',$r['id']);
        cookie::forever('staff.id', $r['id']);
        $res['status']  =   1;
        $res['info']    =   '切换成功！';
        $this->ajaxReturn($res);
    }

    /**
     * 登录忘记密码
     * @author 王牧田
     * @date 2018-09-21
     */
    public function forgetPwd(){

        if($this->request->isPost()){
            $session_id =  session_id();
            $mobile = I('post.mobile');
            $mobile_code = I('post.mobile_code');
            //判断是不是推广员账号
            $staff = M('staff')->where(['phone'=>$mobile])->find();
            if(empty($staff)){
                $this->error("该账号不是推广员账号！");
            }

            $sms_log = M('sms_log')->where(['mobile'=>$mobile,'session_id'=>$session_id,'scene'=>6])->order("id desc")->find();

            //判断验证码是否过期
            if(($mobile_code != $sms_log['code']) || ((time() - $sms_log['add_time']) > 120)){
                $this->error("验证失败,验证码有误");
            }else{
                session("user.mobile",$mobile);
                session("staff.id",$staff['id']);
                $this->success("验证成功",url('Staff/System/resetPwd'));
            }
        }else{
            return $this->fetch();
        }
    }

    /**
     * 修改密码
     * @author 王牧田
     * @date 2018-09-25
     * @return mixed
     */
    public function resetPwd(){
        if($this->request->isPost()) {
            $password = input('post.password');
            $staffid = session("staff.id");

            $phone = M('staff')->where('id' , $staffid)->value("phone");
            $staff = M('staff')->where('phone' , $phone)->save(['tkpsw'=>encrypt($password)]);
            $staff = session('staff');
            if(empty($staff)){
                session('staff',null);
                cookie('staff', null);
            }
            $this->success("密码设置成功！",url('Staff/System/login'));
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
        $staff_info = cache("public_staff_{$this->staff_id}");
        $staff_list = db('staff')
            ->alias('staff')
            ->field('staff.id,staff.phone,tkpsw,real_name,store.cname store_name,store.cid store_id,staff.money')
           // ->cache("staff_list_{$staff_info['phone']}")
            ->join('company store','store.cid = staff.store_id','left')
            ->where('staff.phone',$staff_info['phone'])
            ->select();
        $this->assign('staff_list',$staff_list);
        return $this->fetch();
    }



    /**
     * 转至当前用户总余额
     * @author 王牧田
     * @date 2018-12-05
     */
    public function transferbalance(){

        $staff_info = cache("public_staff_{$this->staff_id}");
        $staff_id = I('post.staff_id');
        $staff = db('staff')->where('id',$staff_id)->find();

        //增加总余额数量
        if(empty($staff_info['phone'])){
            $this->error('请先绑定手机号码',U('/Staff/Profile/set_phone'));
        }

        $staffbalance = db('staff_balance')->where(['phone'=>$staff_info['phone']])->find();

        if(empty($staffbalance)){
            //如果不存在就去添加
            $staff_balance['phone'] = $staff_info['phone'];
            $staff_balance['balance'] = $staff['money'];
            db('staff_balance')->add($staff_balance);
        }else{
            //如果存在将追加的原来余额
            db('staff_balance')->where(['phone'=>$staff_info['phone']])->setInc('balance',$staff['money']);
        }
        //减少当前用户在实体店的余额
        db('staff')->where('id',$staff_id)->save(['money'=>0]);
        \think\cache::rm("staff_list_{$staff_info['phone']}");
        \think\cache::rm("public_staff_{$this->staff_id}");
        //添加记录
        $addlog['time'] = time();
        $addlog['money'] = $staff['money'];
        $addlog['ip'] = request()->ip();
        $addlog['staff_id'] = $staff_id;
        $addlog['store_id'] = $staff['store_id'];
        db('staff_balance_log')->add($addlog);

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
        $staff = db('staff')->where('id',$this->staff_id)->find();
        $phone = $staff['phone'];
        $sumMoney = db('staff')->where(['phone'=>$phone])->sum('money');

        //加到全部余额
        $staffbalance = db('staff_balance')->where(['phone'=>$phone])->find();
        if(empty($staffbalance)){
            //如果不存在就去添加
            $staff_balance['phone'] = $phone;
            $staff_balance['balance'] = $sumMoney;
            db('staff_balance')->add($staff_balance);
        }else{
            //如果存在将追加的原来余额
            db('staff_balance')->where(['phone'=>$phone])->setInc('balance',$sumMoney);
        }

        //记录日志
        $staffSelect = db('staff')->where(['phone'=>$phone])->select();
        $data = [];
        foreach ($staffSelect as $row){
            if($row['money'] != 0) {
                $data[] = [
                    'time' => time(),
                    'money' => $row['money'],
                    'ip' => request()->ip(),
                    'staff_id' => $row['id'],
                    'store_id' => $row['store_id'],
                ];
            }
        }
        db('staff_balance_log')->insertAll($data);

        //清空余额
        db('staff')->where(['phone'=>$phone])->save(['money'=>0]);


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
        $staff_id = I('post.staff_id');
        $store_id = I('post.store_id');

        $balanceLog = db('staff_balance_log')->where(['staff_id'=>$staff_id,'store_id'=>$store_id])->select();
        $this->assign('balanceLog',$balanceLog);
        return $this->fetch();
    }





}