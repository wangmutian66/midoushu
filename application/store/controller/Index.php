<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\store\controller; 
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

        /*查询登录子公司的信息*/
   /*     $company_info = M('company')->cache(true)->find($this->cid);
        $this->assign('company_info',$company_info);*/
        #查询站内消息
        $msg_count = db('company_msg')->where("company_id = {$this->store_id} and status = 0")->count();
        $this->assign('msg_count',$msg_count);
        return $this->fetch('Index');
    }
   

    function welCome(){
        $where ['parent_id']= ['eq',$this->store_id];
        if($key_word = I('get.key_word/s')){
            $where['cname'] = ['like',"%{$key_word}%"] ;
        }
        $count = M('Company')->where($where)->count();
        $pager = new Page($count,12);
        $list = M('Company')->where($where)->order('cid desc')->limit($pager->firstRow.','.$pager->listRows)->select();

        $this->assign('list',$list);
        $this->assign('pager',$pager);

        return $this->fetch();   
    }
   
}