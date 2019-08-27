<?php
/**
 */
namespace app\applet_app\controller;
use app\common\logic\CartLogic;
use app\common\logic\UsersLogic;
use think\Controller;
use think\Session;

class ReturnMobileBase extends Controller {
    public $session_id;
    public $weixin_config;
    public $cateTrre = array();
    
    /*
     * 初始化操作
     */
    public function _initialize() {

        # session('user'); //不用这个在忘记密码不能获取session('validate_code');

        // Session::start();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        $this->session_id = session_id(); // 当前的 session_id
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用
    
        // $this->public_assign();
    }
    
   

}