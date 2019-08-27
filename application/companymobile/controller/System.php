<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\companymobile\controller;
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
        if(session('?company') && session('company.cid')>0){
            $url = (session('company.from_url')) ? (session('company.from_url')) : U('companymobile/Index/index');
            $this->error("您已登录",U('companymobile/Index/index'));
        }

        return $this->fetch();
    }
    function dologin(){
        if(\think\Request::instance()->isPost()){
            $condition['mobile'] = I('post.username/s');
            $condition['password'] = I('post.password/s');
            $condition['parent_id'] = ['eq','0'];
            $store_id  =   I('post.store_id');
            $store_id && $condition['cid']  =   $store_id;
            if(!empty($condition['mobile']) && !empty($condition['password'])){
                $condition['password'] = encrypt($condition['password']);

                $r = M('company')->where($condition)->find();
                if(is_array($r)){

                    $company = M('company')->where($condition)->select();

                    if(count($company) > 1){
                        $data['status'] =   2;
                        $data['info'] =   $company;
                        echo json_encode($data);
                        die;
                    }

                    if($r['parent_id']){
                        $msg['status']  =   0;
                        $msg['info']    =   '该账号为实体店账号，请在实体店管理平台登录';
                        $this->ajaxReturn($msg);
                    }
                    if($r['is_lock'] == 1){
                        $msg['status']  =   0;
                        $msg['info']    =   '您的账户已经被冻结，请联系平台管理员';
                        $this->ajaxReturn($msg);
                    }
                    session('company.cid',$r['cid']);
                    $save_data = array('last_login'=>time(),'last_ip'=>request()->ip());
                    M('company')->where("cid = {$r['cid']}")->save($save_data);
                    session('company.last_login_time',$save_data['last_login']);
                    session('company.last_login_ip',$save_data['last_ip']);

                    if(I('post.remember_psw')){
                        cookie::forever('company.cid', $r['cid']);
                        cookie::forever('company.last_login_time', $save_data['last_login_time']);
                        cookie::forever('company.last_login_ip', $save_data['last_ip']);
                    }
                    companyLog('后台登录');
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
		session::clear('company');
        cookie::clear('company');
        $this->success("退出成功",U('companymobile/System/login'));
    }

    #修改密码
    function Setpsw(){
        return $this->fetch();
    }
    #修改密码
    function doSetpsw(){
        $password = I('post.oldpsw/s');
        $where['password']  =   ['eq',encrypt($password)];
        $where['cid']   =   ['eq',$this->company_id];
        if(db('company')->where($where)->find()){
            $newpsw = encrypt(I('post.newpsw/s'));
            if(db('company')->where("cid = {$this->company_id}")->setField('password',$newpsw)){
                $msg['status']  =   1;
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '新密码不能与原密码相同';
            }
        }else{
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
        if ($_FILES['litpic']['tmp_name']) {
                $file = $this->request->file('litpic');
                $image_upload_limit_size = config('image_upload_limit_size');
                $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
                $dir = 'public/upload/company/'.date('Y').'/';
                if (!($_exists = file_exists($dir))){
                    $isMk = mkdir($dir);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);

                if($info){
                    $data['litpic'] = '/'.$dir.$parentDir.'/'.$info->getFilename();
                }else{
                    $this->error($file->getError());//上传错误提示错误信息
                }
        }  
        // dump($data);die();
        
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
         
         
            $r = db('company')->where('cid','eq',$this->company_id)->update($data);
         #   echo db('company')->getlastsql();die;
            if($r){
                \think\Cache::rm("company_{$this->company_id}");
                $msg['status']  =   1;
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '数据未修改，无需更新';
            } 
        }

        if ($msg['status']=='1') {
            $this->success('更新成功!');
        }else{
            $this->error($msg['info']);
        }

    }


    function setMobile(){
        return $this->fetch();
    }

    function doSetMobile(){
        $company_info = cache("company_{$this->company_id}");
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
        $check_code = $userLogic->check_validate_code($code1, $company_info['mobile'], 'phone', session_id(), $scene);
        if ($check_code['status'] != 1){
            $msg['status'] =   0;
            $mobile_hide = mobile_hide($company_info['mobile']);
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
            $r = db("company")->where('cid','eq',$this->company_id)->setField('mobile',$newmobile);
            \think\Cache::rm("company_{$this->company_id}");
            if(!$r){
                $msg['status'] =   0;
                $msg['info']   =   "更新信息失败！请联系系统管理员";
            }
        }
        $this->ajaxReturn($msg);
        
    }
   

    function Sms(){
        $where ['company_id']= ['eq',$this->company_id];
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
        $where['company_id']    =   ['eq',$this->company_id];
        $where['id'] = ['eq',$id];
        db('company_msg')->where($where)->setField('status',1);
        $this->ajaxReturn(['status'=>1]);
    }

    function View(){
        $id = I('get.id/d');
        $where['company_id']    =   ['eq',$this->company_id];
        $item = db('company_msg')->where($where)->find($id);
        $where['id'] = ['eq',$id];
        db('company_msg')->where($where)->setField('status',1);
        $this->assign('item',$item);
        return $this->fetch();
    }
}