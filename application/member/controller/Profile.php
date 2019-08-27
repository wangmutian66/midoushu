<?php
/**
用户中心
 */
namespace app\member\controller; 
use think\Controller;
use think\Cache;
//use think\Page;
use think\Db;
use app\common\logic\UsersLogic;
class Profile extends Base {

    public function index(){
        if (IS_POST) {
            $userLogic = new UsersLogic();
            
            $_POST['update_time']   =   NOW_TIME;
            $phone = I('post.phone');
            $code =I('post.mobile_code/d');
            if (!empty($phone)) {
                $c = M('company_member')->where(['phone' => $phone, 'id' => ['<>', $this->member_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $phone, 'phone', session_id(), $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if ($_FILES['code_img']['tmp_name']) {
                $res = $this->tk_upfile('code_img','card');
                if($res['status'] == 1){
                    $_POST['code_img']  =   $res['info'];
                }else{
                    $this->error($res['info']);
                }
            }
            if ($_FILES['code_img2']['tmp_name']) {
                $res = $this->tk_upfile('code_img2','card');
                if($res['status'] == 1){
                    $_POST['code_img2']  =   $res['info'];
                }else{
                    $this->error($res['info']);
                }
            }
            #头像上传
            if ($_FILES['head_pic']['tmp_name']) {
                $res = $this->tk_upfile('head_pic');
                if($res['status'] == 1){
                    $_POST['head_pic']  =   $res['info'];
                }else{
                    $this->error($res['info']);
                }
            }

            $r = M('company_member')->cache("member_{$this->member_id}")->where('id','eq',$this->member_id)->save($_POST);
            if($r){
                $this->success('编辑成功！');
            }else{
                $this->error('编辑失败！');
            }
        }else{
            $member_info = cache::get("member_{$this->member_id}");
            #完善资料仅允许编辑一次
            if($member_info['id_code']){ 
                if(empty(cache("member_{$this->member_id}")['phone'])){
                    $this->error('请先设置手机号码',U('/Member/Profile/set_phone'));
                }
                return $this->fetch('perfect_view');    
            }else{
                $this->assign('crumbs','完善资料');
                return $this->fetch('Index');      
            }
        }
    }
    
    function tk_upfile($file_name,$dir='staff_head_pic'){  
        $save_name = get_rand_str();
        $image_upload_limit_size = config('image_upload_limit_size');
        $ext = ['jpg','png','gif','jpeg'];
        $dir = "public/upload/{$dir}/".date('Ymd').'/';
        if (!($_exists = file_exists($dir))){
            $isMk = mkdir($dir, 0777, true);
        }
        $image = \think\Image::open(request()->file($file_name));
        $save_file_name = $dir.$save_name.'.'.$image->type();
        if(in_array($image->type(), $ext)){
            $image->thumb(500, 400)->save($save_file_name);
            $res['status']  =   1;
            $res['info']    =   '/' . $save_file_name;
        }else{
            $res['status']  =   0;
            $res['info']    =   '文件格式不匹配，请重新上传';
        }
        return $res;
    }
    #显示二维码
    function qrcode(){
        return $this->fetch();    
    }

    #解绑手机
    function unset_phone(){
        if (IS_POST) {
            $code =I('post.mobile_code/d');
        //    $scene = I('scene');
            $member_info = cache::get("member_{$this->member_id}");
            $phone = $member_info['phone'];
            $c = M('company_member')->where(['phone' => $phone, 'id' => ['<>', $this->member_id]])->count();
            $c && $this->error("手机已被使用");
            if (!$code)
                $this->error('请输入验证码');
            $userLogic = new UsersLogic();
            $check_code = $userLogic->check_validate_code($code, $phone, 'phone', session_id(), $scene);
          
            if ($check_code['status'] != 1)
                $this->error($check_code['msg']);
            M('company_member')->cache("member_{$this->member_id}")->where('id','eq',$this->member_id)->setField('phone','');
            $this->redirect('/Member/Profile/set_phone/');
            
        }else{
            if(empty(cache("member_{$this->member_id}")['phone'])){
                $this->redirect('/Member/Profile/set_phone/');
            }
            return $this->fetch();    
        }
    }
    #绑定手机
    function set_phone(){
        if (IS_POST) {
            $code =I('post.mobile_code/d');
            $phone = I('post.phone');
            if ($phone){
                $c = M('company_member')->where(['phone' => $phone])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $userLogic = new UsersLogic();
                $check_code = $userLogic->check_validate_code($code, $phone, 'phone', session_id(), $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);

                if(M('company_member')->cache("member_{$this->member_id}")->where('id','eq',$this->member_id)->setField('phone',$phone)){
                    $this->success('绑定成功！',U('/Member/Profile'));
                }else{
                    $this->error("绑定失败!");
                }
            }
        }else{
            return $this->fetch();    
        }
    }
    /*更改头像*/
    function set_pic(){
        if ($_FILES['head_pic']['tmp_name']) {
            $file = $this->request->file('head_pic');
            $image_upload_limit_size = config('image_upload_limit_size');
            $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
            $dir = 'public/upload/staff_head_pic/';
            if (!($_exists = file_exists($dir))){
                $isMk = mkdir($dir);
            }
            $parentDir = date('Ymd');
            $info = $file->validate($validate)->move($dir, true);
            if($info){
                $pic = '/'.$dir.$parentDir.'/'.$info->getFilename();
            }else{
                $this->error($file->getError());//上传错误提示错误信息
            }
        }
        $r = M('company_member')->cache("member_{$this->member_id}")->where('id','eq',$this->member_id)->setField('head_pic',$pic);
        if($r){
            $this->success("操作成功");
        }else{
            $this->error('操作失败！');
        }
    }
    #申请提现
    public function withdraw(){
        if(IS_POST){
            $money = I('post.money');
            $pay_password = I('post.pay_password');
            #支付密码暂无
            $member_info = db('company_member')->find($this->member_id);
            if($member_info['money'] < $money){
                $this->error('您的可用余额不足');
            }
            $member_data['money']    =   ['exp'," money - {$money}"];
            $member_data['frozen']    =   ['exp'," frozen + {$money}"];
            $member_r = M('company_member')->cache("member_{$this->member_id}")->where("id = {$this->member_id}")->save($member_data);
            if(!$member_r){
                $this->error('扣款失败！请联系管理员');
            }
            $_POST['create_time']    =   NOW_TIME;
            $_POST['member_id']  =   $this->member_id;
            $r = M('member_withdrawals')->save($_POST);
            if($r){
                $this->success('申请成功，请等待管理员审核',U('/Member/Deposit/index'));
            }else{
                $this->error('申请失败，请联系系统管理员');
            }
        }else{
            $member_info = M('company_member')->find($this->member_id);
            if($member_info['money'] <= 0){
                $this->error('您的可用余额为0，不可提现');
            }
            $this->assign('bank_list',tk_bank_list());
            return $this->fetch();
        }
        
    }

    function view(){
        $id = I('get.id/d');
    }


    

}