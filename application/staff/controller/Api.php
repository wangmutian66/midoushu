<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\staff\controller;
//use app\common\logic\UsersLogic;
use think\Db;
use think\Session;
use think\Verify;
use think\Cookie;

class Api extends Base {
    public  $send_scene;
    
    public function _initialize() {
        parent::_initialize();
    }
    
    
    
    /**
     * 检测手机号是否已经存在
     */
    public function issetMobile()
    {
      $where['phone'] = ['eq',I("phone",'0')]; 
      $where['id'] = ['neq',session('staff.id')]; 

      $staff = M('staff')->where($where)->find();
      if($staff)
          echo 1;
      else 
          echo 0; 
    }

   
    
}