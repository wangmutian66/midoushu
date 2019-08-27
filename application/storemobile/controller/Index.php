<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\storemobile\controller; 
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
class Index extends Base {

	#var $cid;
	/**
     * 析构函数
     */
    function _initialize() 
    {
    #	$this->cid = Session('company.cid');
        parent::_initialize();
   } 

    public function index(){
    	//查询上次登录时间
    	$map['cid']	=	['eq',$this->store_id];
    	$map['log_info']	=	['eq','后台登录'];
    	$company_log = M('CompanyLog')->where($map)->cache('company_log',300)->order('id desc')->limit(2)->select();
     
        if(count($company_log)==2){
            $last_login['tims'] =  $company_log[1]['log_time'];
            $last_login['ip'] =  $company_log[1]['log_ip'];
        }
    	$this->assign('last_login',$last_login);

        return $this->fetch('Index');
     
       
    }
  
    
}