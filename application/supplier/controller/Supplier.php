<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\supplier\controller; 
use app\common\logic\MessageLogic;
use app\common\logic\SuppliersLogic;
use think\Page;
use think\Verify;
use think\Db;
use think\Session;
class Supplier extends Base {

    var $suppliers_id;

    function _initialize() 
    {   
        $this->suppliers_id = Session('suppliers.suppliers_id');
        parent::_initialize();
    } 

    public function index(){
        return $this->fetch();
    }

    /*
     * 管理员登陆
     */
    public function login(){
        if(session('?suppliers') && session('suppliers.suppliers_id')>0){
             $this->error("您已登录",U('Supplier/Index/index'));
        }
        if(IS_POST){
            $verify = new Verify();
            if (!$verify->check(I('post.vertify'), "suppliers_login")) {
                exit(json_encode(array('status'=>0,'msg'=>'验证码错误')));
            }

            $user_name = I('post.username/s');
            $user = M('users')->field('user_id')->where('mobile = "'.$user_name.'"')->find();
            if(!$user) exit(json_encode(array('status'=>0,'msg'=>'用户名不存在')));

            $condition['user_id']             = $user['user_id'];

            $condition2['suppliers_password'] = I('post.suppliers_password/s');
            
            if(!empty($condition['user_id']) && !empty($condition2['suppliers_password'])){
                $condition2['suppliers_password'] = encrypt($condition2['suppliers_password']);
                
                $suppliers_info = M('suppliers')->where($condition)->find();

                if(!$suppliers_info)
                    exit(json_encode(array('status'=>0,'msg'=>'供货商信息不存在')));

                if($suppliers_info['suppliers_password'] != $condition2['suppliers_password'])
                    exit(json_encode(array('status'=>0,'msg'=>'供货商密码不正确 ')));

                if($suppliers_info['is_check'] < 3)
                    exit(json_encode(array('status'=>0,'msg'=>'供货商申请暂未通过审核，请联系客服人员')));
                //exit(json_encode(array('status'=>0,'msg'=>$suppliers_info['status'])));
                if($suppliers_info['status'] == 1)
                    exit(json_encode(array('status'=>0,'msg'=>'账户已被冻结，请联系客服人员')));

                if($suppliers_info){
                    session('suppliers.suppliers_id',$suppliers_info['suppliers_id']);
                    M('suppliers')->where("suppliers_id = ".$suppliers_info['suppliers_id'])->save(array('last_login'=>time(),'last_ip'=>  request()->ip()));
                    session('suppliers.last_login_time',$suppliers_info['last_login']);
                    session('suppliers.last_login_ip',$suppliers_info['last_ip']);
                    adminLog('供货商登录');
                    $url = session('from_url') ? session('from_url') : U('Supplier/Index/index');
                    exit(json_encode(array('status'=>1,'url'=>$url)));
                }else{
                    exit(json_encode(array('status'=>0,'msg'=>'账号密码不正确')));
                }
            }else{
                exit(json_encode(array('status'=>0,'msg'=>'请填写账号密码')));
            }
        }
        
       return $this->fetch();
    }

    /**
     * 退出登陆
     */
    public function logout(){
        session_unset();
        session_destroy();
        session::clear();
        $this->success("退出成功",U('Supplier/Supplier/login'));
    }

    
    /**
     * 验证码获取
     */
    public function vertify()
    {
        $config = array(
            'codeSet'  => '0123456789',
            'fontSize' => 40,
            'length'   => 4,
            'useCurve' => false,
            'useNoise' => false,
            'reset' => false
        );    
        $Verify = new Verify($config);
        $Verify->entry("suppliers_login");
        exit();
    }


    // 供货商信息
    public function detail()
    {
        $supplierid = $this->suppliers_id;
        $supplier   = D('suppliers')->where(array('suppliers_id'=>$supplierid))->find();
        if(!$supplier)
            exit($this->error('供货商不存在'));

        $supplier['buchongImages'] = unserialize($supplier['buchong']); // 晒单图片

        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取城市
        $city =  M('region')->where(array('parent_id'=>$supplier['province_id'],'level'=>2))->select();

        if(IS_POST){
            //  供货商信息编辑
            $suppliers_password  = I('post.suppliers_password');
            $suppliers_password2 = I('post.suppliers_password2');
            if($suppliers_password != '' && $suppliers_password != $suppliers_password2){
                exit($this->error('两次输入密码不同'));
            }
            if($suppliers_password == '' && $suppliers_password2 == ''){
                unset($_POST['suppliers_password']);
            }else{
                $_POST['suppliers_password'] = encrypt($_POST['suppliers_password']);
            }          
            
            if(!empty($_POST['suppliers_phone']))
            {   $suppliers_phone = trim($_POST['suppliers_phone']);
                $c = M('suppliers')->where("suppliers_id != $supplierid and suppliers_phone = '$suppliers_phone'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }

            $buchong_img = serialize(I('buchong/a')); // 补充资质
            $_POST['buchong']  = $buchong_img;                   
            
            $row = M('suppliers')->where(array('suppliers_id'=>$supplierid))->save($_POST);
            if($row){
                $data2['is_check'] = 0;
                M('suppliers')->where(array('suppliers_id'=>$supplierid))->save($data2);
                exit($this->success('修改成功'));
            }

            exit($this->error('未作内容修改或修改失败'));
        }

        $levelList = M('suppliers_level')->order('level_id')->select();
        $this->assign('levelList', $levelList);
 
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('supplier',$supplier);
        return $this->fetch();
    }

    public function postaddress(){

        $supplierid = $this->suppliers_id;
        $supplier   = D('suppliers')->where(array('suppliers_id'=>$supplierid))->field('post_consignee,post_mobile,post_province,post_city,post_district,post_address')->find();

        $province = M('region')->where(array('parent_id'=>0))->select();
        $city =  M('region')->where(array('parent_id'=>$supplier['post_province']))->select();
        $area =  M('region')->where(array('parent_id'=>$supplier['post_city']))->select();
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);

        $this->assign('config',$supplier);

        if(IS_POST){
            $row = M('suppliers')->where(array('suppliers_id'=>$supplierid))->save($_POST);
            if($row){
                exit($this->success('修改成功'));
            }
            exit($this->error('未作内容修改或修改失败'));
        }

        return $this->fetch();
    }

    /*
     * 密码修改
     */
    public function password(){
        if(IS_POST){
            //检查是否第三方登录用户
            $SuppliersLogic = new SuppliersLogic();
            $data = $SuppliersLogic->password($this->suppliers_id,I('post.old_password'),I('post.new_password'),I('post.confirm_password')); // 获取用户信息
            if($data['status'] == -1) $this->error($data['msg']);
            if($data['status'] == 1) $this->success('修改成功！');
        }               
        return $this->fetch();
    }

    // 支付密码 
    public function paypwd()
    {
        $supplierid = $this->suppliers_id;
        $supplier   = D('suppliers')->where(array('suppliers_id'=>$supplierid))->find();
        $user       = M('users')->where('user_id', $suppliers['user_id'])->find();
        if($supplier['suppliers_phone'] == '' || $supplier['suppliers_phone_validated'] == 0)
            $this->error('请先绑定手机', U('Supplier/detail',['action'=>'mobile']));
        $step = I('step', 1);
        $config = tpCache('sms');
        $sms_time_out = $config['sms_time_out'];
      
        if ($step > 1) {
            
            $mobile     = I('post.mobile');
            $code       = I('post.code');
            $session_id = I('unique_id', session_id());
            $SuppliersLogic = new SuppliersLogic();
            $res = $SuppliersLogic->check_validate_code($code, $mobile, 'phone', $session_id, 5);
            if (!$res && $res['status'] != 1) $this->error($res['msg']);
        }
        if (IS_POST && $step == 3) {
            $SuppliersLogic = new SuppliersLogic();
            $data = I('post.');
            $data = $SuppliersLogic->paypwd($supplierid, I('new_password'), I('confirm_password'));
            if ($data['status'] == -1)
                $this->error($data['msg']);
            $this->redirect(U('Supplier/Supplier/paypwd', array('step' => 3)));
            exit;
        }
        $this->assign('step', $step);

        $this->assign('supplier', $supplier);
        return $this->fetch();
    }

    //忘记密码
    public function forget_pwd()
    {
        if ($this->suppliers_id > 0) {
            header("Location: " . U('Supplier/supplier/Index'));
        }
        return $this->fetch();
    }
    
    public function set_pwd(){
        if($this->suppliers_id > 0){
            $this->redirect('Supplier/supplier/Index');
        }
        $config = tpCache('sms');
        $sms_time_out = $config['sms_time_out'];
            
        $mobile     = I('post.mobile');
        $code       = I('post.code');
        $scene      = I('post.scene', 6);
        $session_id = I('unique_id', session_id());
        $SuppliersLogic = new SuppliersLogic();
        $res = $SuppliersLogic->check_validate_code($code, $mobile, 'phone', $session_id, $scene);
        if (!$res && $res['status'] != 1) $this->error($res['msg']);
        
        if(IS_POST){
            $password = I('post.password');
            $password2 = I('post.confirm_password');
            if($password2 != $password){
                $this->error('两次密码不一致',U('Supplier/supplier/forget_pwd'));
            }
//            if($password == $user['password']){
//                $this->error('不可与会员登录密码一样',U('Supplier/supplier/forget_pwd'));
//            }

            if (!$res && $res['status'] != 1) 
            {
                $this->error($res['msg'],U('Supplier/supplier/forget_pwd'));
            }else{

                $suppliers = M('suppliers')->where("suppliers_phone", '=', $mobile)->find();

                if(empty($suppliers)){
                    $this->error('供货商手机号不存在');
                }
                $suppliers = M('suppliers')->where("suppliers_id", $suppliers['suppliers_id'])->save(array('suppliers_password'=>encrypt($password)));

                session('validate_code',null);
                $this->redirect('Supplier/supplier/finished');                
            }  
        }
        return $this->fetch();
    }
    
    public function finished(){
        if($this->suppliers_id > 0){
            $this->redirect('Supplier/supplier/Index');
        }
        return $this->fetch();
    }   

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $supplierid = $this->suppliers_id;
        $supplier   = D('suppliers')->where(array('suppliers_id'=>$supplierid))->find();
        if($supplier['suppliers_phone_validated'] != 1) $supplier['suppliers_phone'] = '';
        $config = tpCache('sms');
        $sms_time_out = $config['sms_time_out'];
        $step = I('get.step', 1);
        if($supplier['suppliers_phone_validated'] < 1 && I('get.step') != 3)$step = 2;
        if (IS_POST && $step == 2) {
            $mobile     = I('post.mobile');
            $code       = I('post.code');
            $scene      = I('post.scene', 6);
            $session_id = I('unique_id', session_id());

            $SuppliersLogic = new SuppliersLogic();
            $res = $SuppliersLogic->check_validate_code($code, $mobile, 'phone', $session_id, $scene);

            if (!$res && $res['status'] != 1) $this->error($res['msg']);

            //检查原手机是否正确
            if ($supplier['suppliers_phone_validated'] == 1 && $mobile != $supplier['suppliers_phone'])
                $this->error('原手机号码错误');
            //验证手机和验证码
            if ($res['status'] != 1)$this->error($res['msg']);
        }

        if (IS_POST && $step == 3) {
            $mobile     = I('post.mobile');
            $code       = I('post.code');
            $scene      = I('post.scene', 6);
            $session_id = I('unique_id', session_id());

            $SuppliersLogic = new SuppliersLogic();
            $res = $SuppliersLogic->check_validate_code($code, $mobile, 'phone', $session_id, $scene);

            if (!$res || $res['status'] != 1) $this->error($res['msg']);

            //验证手机和验证码
            if ($res['status'] == 1) {
                //验证有效期
                
                if (!$SuppliersLogic->update_email_mobile($mobile, $supplierid, 2)) $this->error('手机已存在');
                $this->success('绑定成功', U('Supplier/Index/welcome'));
                exit;
            } else {
                $this->error($res['msg']);
            }
        }
        $this->assign('time', $sms_time_out);
        $this->assign('step', $step);
        $this->assign('supplier', $supplier);
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        $type = I('type' )? I('type') : 0;
        $where['deleted']         = 0;
        $where['sm.suppliers_id'] = $this->suppliers_id;
        $where['sm.category']     = 0;
        $where['m.object']        = "suppliers";

        $message_model = new MessageLogic();
        $message_model->checkPublicMessage_sup(); // 更新全体系统信息

        $count = Db::name('suppliers_message')
            ->alias('sm')
            ->field('sm.rec_id,sm.suppliers_id,sm.category,sm.message_id,sm.status,m.send_time,m.type,m.message')
            ->join('__MESSAGE__ m','sm.message_id = m.message_id','LEFT')
            ->where($where)
            ->count();

        $Page = new Page($count,20);
        $suppliers_message = Db::name('suppliers_message')
            ->alias('sm')
            ->field('sm.rec_id,sm.suppliers_id,sm.category,sm.message_id,sm.status,m.send_time,m.type,m.message')
            ->join('__MESSAGE__ m','sm.message_id = m.message_id','LEFT')
            ->where($where)
            ->limit($Page->firstRow,$Page->listRows)
            ->select();

        $this->assign('page',$Page->show_sup());
        $this->assign('messages', $suppliers_message);
        return $this->fetch();
    }

    public function message_notice_detail()
    {
        $message_id = I('id');
        if(!$message_id) $this->error('信息错误');

        $where['sm.message_id'] = $message_id;
        $info = Db::name('suppliers_message')
            ->alias('sm')
            ->field('sm.rec_id,sm.suppliers_id,sm.category,sm.status,m.send_time,m.type,m.message')
            ->join('__MESSAGE__ m','sm.message_id = m.message_id','LEFT')
            ->where($where)
            ->find();

        if(!$info) $this->error('信息错误');
        $data['status'] = 1;
        M('suppliers_message')->where('message_id='.$message_id)->save($data);

        $this->assign('message', $info);
        return $this->fetch();
    }

    /**
     * ajax用户消息通知请求
     */
    public function del_message_notice()
    {
        $type   = I('type');
        $msg_id = I('post.msg_id','');
        empty($msg_id) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        $msg_id = rtrim($msg_id,",");
        $message_model = new MessageLogic();
        $res = $message_model->delSuppliersMessage($type,$msg_id);
        $this->ajaxReturn($res);
    }

}