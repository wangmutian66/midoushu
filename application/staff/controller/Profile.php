<?php
/**
用户中心
 */
namespace app\staff\controller; 
use think\Controller;
use think\Cache;
//use think\Page;
use think\Db;
use app\common\logic\UsersLogic;
class Profile extends Base {

    function _initialize() 
    {
        parent::_initialize();
    }
    public function index(){
        
        if (IS_POST) {
            $userLogic = new UsersLogic();
            
            $_POST['update_time']   =   NOW_TIME;
            $phone = I('post.phone');
            $code =I('post.mobile_code/d');
            if (!empty($phone)) {
                $c = M('staff')->where(['phone' => $phone, 'id' => ['<>', $this->staff_id]])->count();
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
            $r = M('staff')->cache("staff_{$this->staff_id}")->where('id','eq',$this->staff_id)->save($_POST);
            if($r){
                $this->success('编辑成功！');
            }else{
                $this->error('编辑失败！');
            }
        }else{
            $staff_info = cache::get("staff_{$this->staff_id}");
        //    dump($staff_info);die;
            #完善资料仅允许编辑一次
            if($staff_info['id_code']){ 
                if(empty(cache("staff_{$this->staff_id}")['phone'])){
                    $this->error('请先设置手机号码',U('/Staff/Profile/set_phone'));
                }
                return $this->fetch('perfect_view');    
            }else{
                $this->assign('crumbs','完善资料');
                return $this->fetch('Index');      
            }
        }
    }
   
    function tk_upfile($file_name,$dir='staff_head_pic'){
    //    $file = request()->file($file_name);
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
        $staff_id = I('get.staff_id');
        $staff = db('staff')->where(["id"=>$staff_id])->find();
        $this->assign('staff_info1',$staff);
        return $this->fetch();    
    }

    #解绑手机
    function unset_phone(){
        if (IS_POST) {
            $code =I('post.mobile_code/d');
            $staff_info = cache::get("staff_{$this->staff_id}");
            $phone = $staff_info['phone'];
            $c = M('staff')->where(['phone' => $phone, 'id' => ['<>', $this->staff_id]])->count();
            $c && $this->error("手机已被使用");
            if (!$code)
                $this->error('请输入验证码');
            $userLogic = new UsersLogic();
            $check_code = $userLogic->check_validate_code($code, $phone, 'phone', session_id(), 5);
          
            if ($check_code['status'] != 1)
                $this->error($check_code['msg']);
            M('staff')->where('id','eq',$this->staff_id)->setField('phone','');
            cache::rm("staff_{$this->staff_id}");
            $this->redirect('/Staff/Profile/set_phone/');
            
        }else{
            if(empty(cache("staff_{$this->staff_id}")['phone'])){
                $this->redirect('/Staff/Profile/set_phone/');
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
                $c = M('staff')->where(['phone' => $phone])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $userLogic = new UsersLogic();
                $check_code = $userLogic->check_validate_code($code, $phone, 'phone', session_id(), $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);

                if(M('staff')->where('id','eq',$this->staff_id)->setField('phone',$phone)){
                    cache::rm("staff_{$this->staff_id}");
                    $this->success('绑定成功！',U('/Staff/Profile'));
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
        $r = M('staff')->where('id','eq',$this->staff_id)->setField('head_pic',$pic);
        if($r){
            Cache::rm("staff_{$this->staff_id}");
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
            $staff_info = db('staff')->find($this->staff_id);
            if($staff_info['money'] < $money){
                $this->error('您的可用余额不足');
            }
            $staff_data['money']    =   ['exp'," money - {$money}"];
            $staff_data['frozen']    =   ['exp'," frozen + {$money}"];
            $staff_r = M('staff')->cache("staff_{$this->staff_id}")->where("id = {$this->staff_id}")->save($staff_data);
            if(!$staff_r){
                $this->error('扣款失败！请联系管理员');
            }
            $_POST['create_time']    =   NOW_TIME;
            $_POST['staff_id']  =   $this->staff_id;
            $r = M('StaffWithdrawals')->save($_POST);
            if($r){
                $this->success('申请成功，请等待管理员审核',U('/Staff/Deposit/index'));
            }else{
                $this->error('申请失败，请联系系统管理员');
            }
        }else{
            $staff_info = M('staff')->find($this->staff_id);
            if($staff_info['money'] <= 0){
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