<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\mobile\controller; 
use think\Page;
use think\Db;
#use think\Request;



#https://www.midoushu.com/customer_service/Chat/login

class Chat extends MobileBase { 
    var $users;
    var $fromid;
    var $toid;
    var $fromip;
    /*
     * 初始化操作
     */
    public function index(){    
        return $this->fetch('index');
    }
    

    public function jump(){    
        return $this->fetch('jump');
    }
}