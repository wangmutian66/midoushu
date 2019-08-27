<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\staff\controller; 
use think\Controller;
//use think\Config;
use think\Cache;
use think\Page;
use think\Db;
use app\common\logic\UsersLogic;
class Team extends Base {

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
        $staff_info = db('staff')->cache("staff_{$this->staff_id}")->find($this->staff_id);
        if($staff_info['store_id']){
            $level_list = db('company_level')->field('id,lv_name')->cache(true)->where('id','exp',"in (select distinct(company_level) from ".PREFIX."staff where store_id = {$staff_info['store_id']})")->select();
            $this->assign('level_list',$level_list);
        }
        
        $p = I('p/d',1);
        $page_last = 5;
        if($company_level_id = I('get.company_level/d')){
            $where['company_level'] =   ['eq',$company_level_id];
        }
        if($real_name = I('get.real_name/s')){
            $where['real_name'] =   ['like',"%{$real_name}%"];
        }
        $where['parent_id'] =   ['eq',$this->staff_id];
        $list = DB::name('staff')->where($where)
                                ->field("staff.*,lv.lv_name,(select user_id from ".PREFIX."users u where u.staff_id = staff.id limit 1) next_user")
                                ->alias('staff')
                                ->order('last_login desc,id desc')
                                ->join('company_level lv','lv.id = staff.company_level','left')
                                ->page("{$p},{$page_last}")
                                ->select();

        $count = DB::name('staff')->where($where)->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();        
        $this->assign('page',$show);
        $this->assign('list', $list);
        return $this->fetch();
    }
    #查看下级推广员
    public function next_promote(){
        $id = I('get.id/d');
        $p = I('p/d',1);
        $page_last = 5;
        #先查询这个员工或者推广员本身的信息
        #$id_staff_info = db('staff')->cache("staff_{$id}")->find($id);
        $where['parent_id']    =  ['eq',$id];
        $list   =   db('staff')->where($where)
                            ->page("{$p},{$page_last}")
                            ->alias('staff')
                            ->field("staff.*,(select user_id from ".PREFIX."users u where u.staff_id = {$id} limit 1) next_user")
                            ->select();
        $count = DB::name('staff')->where($where)->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list', $list);
        return $this->fetch();
    }
    #查看发展的会员
    public function next_user(){
        $id = I('get.id/d');
        $p = I('p/d',1);
        $page_last = 5;

        #先查询这个员工或者推广员本身的信息
        $phone = db('staff')->where(['id'=>$id])->value("phone");
        $ids = db('staff')->where(['phone'=>$phone])->column("id");
        $where['staff_id']    =  ['in',$ids];
        $list   =  db('users')->field('user_id,mobile,head_pic,nickname')->where($where)->page("{$p},{$page_last}")->select();
        $count = db('users')->where($where)->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list', $list);
        return $this->fetch();
    }


    #查看推广员详细信息
    function view_staff(){
        $id = I('get.id/d');
        $staff_r = db('staff')->cache("staff_{$id}")
                            ->alias('staff')
                            ->field('staff.*,lv.lv_name')
                            ->join('company_level lv','lv.id = company_level','left')
                            ->find($id);
        $this->assign('staff_r',$staff_r);
        return $this->fetch();
    }

}