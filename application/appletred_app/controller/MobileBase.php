<?php
/**
 */
namespace app\appletred_app\controller;
use app\common\logic\RedCartLogic;
use app\common\logic\UsersLogic;
use app\common\logic\RedUsersLogic;
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