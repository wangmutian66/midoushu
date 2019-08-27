<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tpshop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 *
 */ 
namespace app\applet_app\controller; 
use app\common\logic\JssdkLogic;
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
use think\Cache;
use think\Lang;
#class Test extends Controller {

class Test extends MobileBase {
    
    public function index(){ 
      $user_mobile = session('user.mobile');
      if($user_mobile){
            $staff_info = M('staff')->field('invite_code,id')->where("phone = {$user_mobile}")->find();
       }
       $staff_id =  session('staff.id');
       if($staff_id){
            $staff_info = M('staff')->field('invite_code,id')->find($staff_id);
       }
       $this->assign('staff_info',$staff_info);

       return $this->fetch('t');die;   
	   $mid = 'hello'.date('H:i:s');
       //echo "测试分布式数据库$mid";
       //echo "<br/>";
       //echo $_GET['aaa'];       
       //  M('config')->master()->where("id",1)->value('value');
       //echo M('config')->cache(true)->where("id",1)->value('value');
       //echo M('config')->cache(false)->where("id",1)->value('name');
       echo $config = M('config')->cache(false)->where("id",1)->value('value');
        // $config = DB::name('config')->cache(true)->query("select * from __PREFIX__config where id = :id",['id'=>2]);
         print_r($config);
       /*
       //DB::name('member')->insert(['mid'=>$mid,'name'=>'hello5']);
       $member = DB::name('member')->master()->where('mid',$mid)->select();
	   echo "<br/>";
       print_r($member);
       $member = DB::name('member')->where('mid',$mid)->select();
	   echo "<br/>";
       print_r($member);
	*/   
//	   echo "<br/>";
//	   echo DB::name('member')->master()->where('mid','111')->value('name');
//	   echo "<br/>";
//	   echo DB::name('member')->where('mid','111')->value('name');
         echo C('cache.type');
    }  

    public function ajaxGetWxConfig(){
      $askUrl = I('askUrl');//分享URL
      $weixin_config = M('wx_user')->find(); //获取微信配置
      $jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
      $signPackage = $jssdk->GetSignPackage(urldecode($askUrl));
      if($signPackage){
        $this->ajaxReturn($signPackage,'JSON');
      }else{
            echo 2;die;
        return false;
      }
    }
    
    public function redis(){
        Cache::clear();
        $cache = ['type'=>'redis','host'=>'192.168.0.201'];        
        Cache::set('cache',$cache);
        $cache = Cache::get('cache');
        print_r($cache);         
        S('aaa','ccccccccccccccccccccccc');
        echo S('aaa');
    }
    
    public function table(){
        $t = Db::query("show tables like '%tp_goods_2017%'");
        print_r($t);
    }
    
        public function t(){
                
         //echo $queue = \think\Cache::get('queue');
         //\think\Cache::inc('queue',1);
         //\think\Cache::dec('queue');
        $res = DB::name('config')->cache(true)->find();
        print_r($res);
              DB::name('config')->update(['id'=>1,'name'=>'http://www.tp-shop.cn11111']);
        $res = DB::name('config')->cache(true)->find();
        print_r($res);
        
        
    }
    // 多语言测试
    public function lang(){
        header("Content-type: text/html; charset=utf-8");
        // 设置允许的语言
        //Lang::setAllowLangList(['zh-cn','en-us']);
        //echo $_GET['lang'];
        echo Lang::get('hello_TPshop');
        echo "<br/>";
        echo Lang::get('where');
        //{$Think.lang.where}
        //return $this->fetch();
    }
}