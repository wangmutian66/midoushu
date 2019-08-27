<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\staff\controller; 
use think\AjaxPage;
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
class Index extends Base {

	var $staff_id;
	/**
     * 析构函数
     */
    function _initialize() 
    {
    	$this->staff_id = Session('staff.id');
        parent::_initialize();
   } 

    public function index(){
    	//查询上次登录时间
        $staff_info = cache("public_staff_{$this->staff_id}");
        $staff_list = db('staff')
                        ->alias('staff')
                        ->field('staff.phone,tkpsw,real_name,store.cname store_name,store.cid store_id')
                        ->cache("staff_list_{$staff_info['phone']}")
                        ->join('company store','store.cid = staff.store_id','left')
                        ->where('staff.phone',$staff_info['phone'])
                        ->select();

        //$wherephone = [];

        $zmoney = db('staff')->where(["phone"=>$staff_info['phone']])->sum('money');

        $tmoney = db('staff_balance')->where(["phone"=>$staff_info['phone']])->sum('balance');
        $cumulative_money = db('staff')->where(["phone"=>$staff_info['phone']])->sum('cumulative_money');


        $this->assign('cumulative_money',$cumulative_money); //累积总雇佣金
        $this->assign('tmoney',$tmoney);                     //体现总额
        $this->assign('zmoney',$zmoney);                     //实体店总额
        $this->assign('staff_list',$staff_list);

        return $this->fetch();
    }
   

    public function tgm(){
        $staff_info = cache("public_staff_{$this->staff_id}");
        $staff_list = db('staff')
            ->alias('staff')
            ->field('staff.phone,tkpsw,real_name,store.cname store_name,store.cid store_id,staff.head_pic,staff.id')
            ->join('company store','store.cid = staff.store_id','left')
            ->where('staff.phone',$staff_info['phone'])
            ->select();

        $this->assign('staff_list',$staff_list);
        return $this->fetch();

    }


}