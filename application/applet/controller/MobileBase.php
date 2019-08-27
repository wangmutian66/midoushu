<?php
/**
 */
namespace app\applet\controller;
use app\common\logic\CartLogic;
use app\common\logic\UsersLogic;
use think\Controller;
use think\Session;

class MobileBase extends Controller {
    public $session_id;
    public $weixin_config;
    public $cateTrre = array();
    
    /*
     * 初始化操作
     */
    public function _initialize() {

      
    }
   


}