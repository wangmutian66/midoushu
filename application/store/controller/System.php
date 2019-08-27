<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\store\controller; 
#use think\AjaxPage;
use think\Controller;
#use think\Url;
use think\Config;
use think\Page;
//use think\Verify;
use think\Db;
use think\Session;
use think\Cookie;
#use think\Cookie;
use app\common\logic\UsersLogic;
class System extends Base {

    /*
     * 管理员登陆
     */
    public function login(){
        if(session('?store') && session('store.cid')>0){
            $this->error("您已登录",U('/Store/Index/index'));
        }
        return $this->fetch();
    }
    function dologin(){
        session('power',null);
        if(\think\Request::instance()->isPost()){
            $condition['mobile'] = I('post.username/s');
            $condition['password'] = I('post.password/s');
            $condition['parent_id'] = ['neq','0'];
            $store_id  =   I('post.store_id');
            $store_id && $condition['cid']  =   $store_id;
            $config = include APP_PATH.'store/conf/config.php';
            $rolemobile = $config['ROLE_MOBILE'];

            if(!empty($condition['mobile']) && !empty($condition['password'])){
                $condition['password'] = encrypt($condition['password']);

                $r = M('company')->where($condition)->find();

                $role['user_name'] = $condition['mobile'];
                $role['password'] = $condition['password'];
                $admin_info = M('store_admin sa')->join(PREFIX.'store_role sr', 'sa.role_id=sr.role_id','INNER')->field("sa.*,sr.act_list")->where($role)->find();

                //总账号
                if(is_array($r)){

                    $company = M('company')->where($condition)->select();
                    if(count($company) > 1){
                        $data['status'] =   2;
                        $data['info'] =   $company;
                        echo json_encode($data);
                        die;
                    }

                    if(empty($r['parent_id'])){
                        $msg['status']  =   0;
                        $msg['info']    =   '该账号为子公司账号，请在子公司管理平台登录';
                        $this->ajaxReturn($msg);
                    }
                    if($r['is_lock'] == 1){
                        $msg['status']  =   0;
                        $msg['info']    =   '您的账户已经被冻结，请联系平台管理员';
                        $this->ajaxReturn($msg);
                    }
                    session('store.company_id',$r['parent_id']);
                    session('store.cid',$r['cid']);
                    $save_data = array('last_login'=>time(),'last_ip'=>request()->ip());
                    M('company')->where("cid = {$r['cid']}")->save($save_data);
                    session('store.last_login_time',$save_data['last_login']);
                    session('store.last_login_ip',$save_data['last_ip']);

                    session('act_list','all'); //总账号所有权限开启
                    if($rolemobile == $condition['mobile']){
                        session('power','1');
                    }
                    if(I('post.remember_psw')){
                        cookie::forever('store.cid', $r['cid']);
                        cookie::forever('store.last_login_time', $save_data['last_login_time']);
                        cookie::forever('store.last_login_ip', $save_data['last_ip']);
                    }
                    storeLog('后台登录');
                    $msg['status']  =   1;
                    session('role_id',null);
                }else if(!empty($admin_info)){ //此处是子账号
                    session('role_id',$admin_info["admin_id"]);
                    session('act_list',$admin_info['act_list']); //记录权限
                    session('store.cid',$admin_info['store_id']); //实体店id
                    session('store.last_login_time',time());
                    session('store.last_login_ip',request()->ip());
                    $msg['status']  =   1;
                }else{
                    $msg['status']  =   0;
                    $msg['info']    =   '用户名或密码不正确';
                }
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '用户名或密码不正确';
            }
            $this->ajaxReturn($msg);
        }
    }
    /**
     * 退出登陆
     */
    public function logout(){
		session::clear('store');
        cookie::clear('store');
        $this->success("退出成功",U('/Store/System/login'));
    }

    #修改密码
    function Setpsw(){
        return $this->fetch();
    }
    #修改密码
    function doSetpsw(){
        $password = I('post.oldpsw/s');
        $where['password']  =   ['eq',encrypt($password)];
        $where['cid']   =   ['eq',$this->store_id];
        $newpsw = encrypt(I('post.newpsw/s'));
        if(session("role_id")){ //子账号修改密码
            $storewhere['password'] = ['eq',encrypt($password)];
            $storewhere['store_id'] = ['eq',$this->store_id];
            $storewhere['admin_id'] =  session("role_id");
            $result = db('store_admin')->where($storewhere)->save(['password'=>$newpsw]);
            if($result){
                $msg['status']  =   1;
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '新密码不能与原密码相同';
            }
        }else if(db('company')->where($where)->find()){ //总账号
            if(db('company')->where("cid = {$this->store_id}")->setField('password',$newpsw)){
                $msg['status']  =   1;
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '新密码不能与原密码相同';
            }
        }else {
            $msg['status']  =   0;
            $msg['info']    ='原密码不正确';
        }
        $this->ajaxReturn($msg);
    }

    #修改基本信息
    function Modify(){
        return $this->fetch();
    }

    function doModify(){
        $data['strore_content'] = $_POST["strore_content"];
        $data['contact'] = I("post.contact/s");
        $msg['status']  =   1;
        if(empty($data['contact'])){
            $msg['status']  =   0;
            $msg['info']    =   '联系人不能为空';
        }
        $data['cname'] = I("post.cname/s");
        if(empty($data['cname'])){
            $msg['status']  =   0;
            $msg['info']    =   '公司名称不能为空';
        }
        if($msg['status'] != 0){
            $data['litpic'] = I('post.litpic/s');
         
            $r = db('company')->where('cid','eq',$this->store_id)->update($data);
         #   echo db('company')->getlastsql();die;
            if($r){
                \think\Cache::rm("store_{$this->store_id}");
                $msg['status']  =   1;
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '数据未修改，无需更新';
            } 
        }

        $this->ajaxReturn($msg);

    }


    function setMobile(){
        return $this->fetch();
    }

    function doSetMobile(){
        $store_info = cache("store_{$this->store_id}");
        $code1 =I('post.code1/d');
        $code2 =I('post.code2/d');
        $newmobile = I('post.newmobile');
        $msg['status']  =   1;
        if($newmobile <= 0){
            $msg['status'] =   0;
            $msg['info']   =   '请输入新的手机号码';
        }
        if($code1 <= 0 || $code2 <= 0 ){
            $msg['status'] =   0;
            $msg['info']   =   '验证码不能为空';
        }
        if($msg['status'] != 1){
            $this->ajaxReturn($msg);
        }
        $userLogic = new UsersLogic();
        $check_code = $userLogic->check_validate_code($code1, $store_info['mobile'], 'phone', session_id(), $scene);
        if ($check_code['status'] != 1){
            $msg['status'] =   0;
            $mobile_hide = mobile_hide($store_info['mobile']);
            $msg['info']   =   "手机号：{$mobile_hide} ".$check_code['msg'];
            $this->ajaxReturn($msg);
        } 
        $check_code2 = $userLogic->check_validate_code($code2, $newmobile, 'phone', session_id(), $scene);
        if ($check_code['status'] != 1){
            $msg['status'] =   0;
            $msg['info']   =   "手机号：{$newmobile} ".$check_code2['msg'];
            $this->ajaxReturn($msg);
        }
        if($msg['status'] != 0){
            $r = db("company")->where('cid','eq',$this->store_id)->setField('mobile',$newmobile);
            \think\Cache::rm("store_{$this->store_id}");
            if(!$r){
                $msg['status'] =   0;
                $msg['info']   =   "更新信息失败！请联系系统管理员";
            }
        }
        $this->ajaxReturn($msg);
        
    }
   

    function Sms(){
        $where ['company_id']= ['eq',$this->store_id];
        if($key_word = I('get.key_word/s')) $where['cname'] = ['like',"%{$key_word}%"] ;
        $count = M('company_msg')->where($where)->count();
        $pager = new Page($count,15);
        $list = M('company_msg')->where($where)->order('status asc,id desc')->limit($pager->firstRow.','.$pager->listRows)->select();
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch();
    }

    function setMsgStatus(){
        $id = I('get.id/d');
        $where['company_id']    =   ['eq',$this->store_id];
        $where['id'] = ['eq',$id];
        db('company_msg')->where($where)->setField('status',1);
        $this->ajaxReturn(['status'=>1]);
    }

    function View(){
        $id = I('get.id/d');
        $where['company_id']    =   ['eq',$this->store_id];
        $item = db('company_msg')->where($where)->find($id);
        $where['id'] = ['eq',$id];
        db('company_msg')->where($where)->setField('status',1);
        $this->assign('item',$item);
        return $this->fetch();
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
            $mobile_code = I('post.code');
            //判断是不是子公司账号
            $company = M('company')->where(['mobile'=>$mobile])->find();
            if(empty($company)){
                $this->error("该账号不是实体店账号！");
            }

            $sms_log = M('sms_log')->where(['mobile'=>$mobile,'session_id'=>$session_id,'scene'=>6])->order("id desc")->find();

            //判断验证码是否过期
            if(($mobile_code != $sms_log['code']) || ((time() - $sms_log['add_time']) > tpCache("sms.sms_time_out"))){
                $this->error("验证失败,验证码有误");
            }else{
                session("store.mobile",$mobile);
                session("store.cid",$company['cid']);
                //$this->success("验证成功",url('Company/System/resetPwd'));
                $this->redirect(url('Store/System/resetPwd'));
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
            $companyid = session("company.cid");

            $result = M('company')->where('cid',$companyid )->save(['password'=>encrypt($password)]);

            if($result){
                session('store',null);
                cookie('store', null);
            }
            $this->success("密码设置成功！",url('Store/System/login'));
        }else{
            return $this->fetch();
        }
    }
    /**
     * 实体店后台总统计
     * @author 吴宇凡
     * @date 2018-12-24
     * @return mixed
     */
    public function statistics(){
        $today = strtotime(date("Y-m-d")); // 今天 00:00
         // and (a.create_time>$today or a.create_time=$today)
        $store_id = $this->store_id;
        $count['staff_mypays']  =  M('staff_mypays')->alias('a')
                                ->join('staff staff','staff.id = a.staff_id')
                                ->join('users user','user.user_id = a.user_id')
                                ->join('company store','store.cid = staff.store_id')
                                ->where("staff.store_id=$store_id and (a.create_time>$today or a.create_time=$today) and pay_status=1")
                                ->count();//扫码订单
        $count['staff_mypays_money']  =  M('staff_mypays')->alias('a')
                                ->join('staff staff','staff.id = a.staff_id')
                                ->join('users user','user.user_id = a.user_id')
                                ->join('company store','store.cid = staff.store_id')
                                ->where("staff.store_id=$store_id and (a.create_time>$today or a.create_time=$today) and pay_status=1")
                                ->sum("a.money");//扫码订单金额
        $count['repurchase'] = M('order_red')->alias('order')
                                ->where("is_store=1 and store_id=$store_id and (add_time>$today or add_time=$today) and pay_status=1")->count();
        $count['staff_mypays_store_money']  =  M('staff_mypays')->alias('a')
                                ->join('staff staff','staff.id = a.staff_id')
                                ->join('users user','user.user_id = a.user_id')
                                ->join('company store','store.cid = staff.store_id')
                                ->where("staff.store_id=$store_id and (a.create_time>$today or a.create_time=$today) and pay_status=1")
                                ->sum("a.store_money");//扫码订单金额
        $count['repurchase_person'] = M('order_red')->alias('order')
                                      ->where("is_store=1 and store_id=$store_id and (add_time>$today or add_time=$today) and pay_status=1")
                                      ->group("user_id")
                                      ->count();
        $count['new_user'] = M("users")->alias('u')->field('reg_time')
                             ->join("staff s","s.id=u.staff_id")
                             ->where("(reg_time>$today or reg_time=$today) and s.store_id=$store_id")
                             ->count();
        $this->assign('count',$count);
        return $this->fetch();
    }

    public function storeorder(){
        $today = strtotime(date("Y-m-d")); // 今天 00:00
        $store_id = $this->store_id;
        $count['staff_mypays']  =  M('staff_mypays')->alias('a')
            ->join('staff staff','staff.id = a.staff_id')
            ->join('users user','user.user_id = a.user_id')
            ->join('company store','store.cid = staff.store_id')
            ->where("staff.store_id=$store_id and (a.create_time>$today or a.create_time=$today) and pay_status=1")
            ->count();//扫码订单
        $count['staff_mypays_money']  =  M('staff_mypays')->alias('a')
            ->join('staff staff','staff.id = a.staff_id')
            ->join('users user','user.user_id = a.user_id')
            ->join('company store','store.cid = staff.store_id')
            ->where("staff.store_id=$store_id and (a.create_time>$today or a.create_time=$today) and pay_status=1")
            ->sum("a.money");//扫码订单金额
        $count['repurchase'] = M('order_red')->alias('order')
            ->where("is_store=1 and store_id=$store_id and (add_time>$today or add_time=$today) and pay_status=1")->count();
        $count['staff_mypays_store_money']  =  M('staff_mypays')->alias('a')
            ->join('staff staff','staff.id = a.staff_id')
            ->join('users user','user.user_id = a.user_id')
            ->join('company store','store.cid = staff.store_id')
            ->where("staff.store_id=$store_id and (a.create_time>$today or a.create_time=$today) and pay_status=1")
            ->sum("a.store_money");//扫码订单金额
        $count['repurchase_person'] = M('order_red')->alias('order')
            ->where("is_store=1 and store_id=$store_id and (add_time>$today or add_time=$today) and pay_status=1")
            ->group("user_id")
            ->count();
        $count['new_user'] = M("users")->alias('u')->field('reg_time')
            ->join("staff s","s.id=u.staff_id")
            ->where("(reg_time>$today or reg_time=$today) and s.store_id=$store_id")
            ->count();
        $count['store_name'] = M("company")
            ->where("cid",$store_id)
            ->getField('cname');
        $count['store_id'] =$store_id;
        $count['time'] =  date("Y-m-d H:i:s");
        $feieProject = new \Feie\FeieService();
        $feieProject->setStore($count);
        $feieProject->wp_storeprint();
    }
}