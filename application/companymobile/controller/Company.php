<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\companymobile\controller; 
use think\AjaxPage;
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
//use think\Verify;
use think\Db;
use think\Session;
use think\Cookie;
class Company extends Base {

    /*
     * 管理员登陆
     */
    public function login(){
        if(session('?company') && session('company.cid')>0){
            $url = (session('company.from_url')) ? (session('company.from_url')) : U('Companymobile/Index/index');
            $this->error("您已登录",U('Companymobile/Index/index'));
        }
      
        if(IS_POST){
            $condition['mobile'] = I('post.username/s');
            $condition['password'] = I('post.password/s');
            if(!empty($condition['mobile']) && !empty($condition['password'])){
                $condition['password'] = encrypt($condition['password']);

               	$company_info = M('company')->where($condition)->find();

                if(is_array($company_info)){
                    session('company.cid',$company_info['cid']);
                    $save_data = array('last_login'=>time(),'last_ip'=>request()->ip());
                    M('company')->where("cid = {$company_info['cid']}")->save($save_data);
                    session('company.last_login_time',$save_data['last_login']);
                    session('company.last_login_ip',$save_data['last_ip']);

                    if(I('post.remember_psw')){
                        cookie::forever('company.cid', $company_info['cid']);
                        cookie::forever('company.last_login_time', $save_data['last_login_time']);
                        cookie::forever('company.last_login_ip', $save_data['last_ip']);
                    }
                    companyLog('后台登录');
                //  header("location:" . U('Supplier/Index/index'));
                    $this->redirect('Index/index');
                }else{
                    $this->assign('data',['info'=>'手机号或密码不正确']);
                    return $this->fetch('public/print_error');
                }
            }else{
                $this->assign('data',['info'=>'请填写手机号密码']);
                return $this->fetch('public/print_error');
            }
        }else{
            return $this->fetch();
        }
       
    }
    
    /**
     * 退出登陆
     */
    public function logout(){
		session::clear('company');
        cookie::clear('company');
        $this->success("退出成功",U('Companymobile/Company/login'));
    }
    
    /**
     * 验证码获取
     	暂不考虑验证码
     */
    public function vertify()
    {
    	return ;
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        	'reset' => false
        );    
        $Verify = new Verify($config);
        $Verify->entry("supplier_login");
        exit();
    }
   
   
}