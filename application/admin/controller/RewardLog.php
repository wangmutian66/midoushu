<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\admin\controller; 
use think\Page;
use think\Db;
use think\Request;
use app\admin\model\StaffModel;

class RewardLog extends Base {

    public function _initialize() {
        parent::_initialize();   
    }

    public function index(){
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $export = I('export');
        if($company_id = I('get.company_id/d')){
            $map['company_id']    =   ['eq',$company_id];
            $store_list = TK_get_company_store($company_id);
            $this->assign('store_list',$store_list);
        }
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);

        if($store_id = I('get.store_id/d')){
            $map['store_id']  =   ['eq',$store_id];
        }
        if($key_word = I('get.key_word/s')) {
            $map['staff.uname'] = ['like', "%{$key_word}%"];
        }
        $start_time = I('start_time');  // 开始时间
        if(I('start_time')){
            $begin    = urldecode($start_time);
            $end_time = I('end_time');   // 结束时间
            $end      = urldecode($end_time);
            $starttime = strtotime($begin);
            $endtime = strtotime($end);
            $map['log.create_time'] = ['between',"{$starttime},{$endtime}"];
        }else{
            $begin = date('Y-m-d', strtotime("-1 month"));
            $end   = date('Y-m-d', strtotime("+1 days"));
        }
        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);

        //员工奖励流水
        $list = M("staff_reward")->alias('log')
                ->where($map)
                ->field('log.id,log.create_time,log.info,log.money money,uname,real_name,company.cname as company_name,store.cname as store_name')
                ->join('__STAFF__ staff','staff.id = staff_id','left')
                ->join('__COMPANY__ company','staff.company_id = company.cid','left')
                ->join('__COMPANY__ store','staff.store_id = store.cid','left')
                ->order("id desc")
                ->page("$p,$size")
                ->select();
        $count = M("staff_reward")->alias('log')
                ->where($map)
                ->join('__STAFF__ staff','staff.id = staff_id','left')
                ->join('__COMPANY__ company','staff.company_id = company.cid','left')
                ->join('__COMPANY__ store','staff.store_id = store.cid','left')->count();

        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);    
        return $this->fetch();
    }


}