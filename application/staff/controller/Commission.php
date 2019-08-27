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
use think\Cache;
use think\Page;
use think\Db;
#use app\common\logic\UsersLogic;
#佣金
class Commission extends Base {
	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
    } 

    public function index(){

        $phone = db('staff')->where(['id'=>$this->staff_id])->value('phone');

        $staff_id = db('staff')->where(['phone'=>$phone])->column('id');

        $where['staff_id']  =   ['in',$staff_id];
//        $where['staff_id']  =   ['in',$this->staff_id];

        $p = I('p/d',1);
        $page_last = 4;
        $list = DB::name('staff_commission')->where($where)
                                ->field("comission.*")
                                ->alias('comission')
                                ->order('id desc')
                                ->page("{$p},{$page_last}")
                                ->select();


        #老张加
        foreach($list as $k=>$v){
            $order_sn = $v['order_sn'];
            if(strpos("midou".$order_sn,"mypays_") > 0){
                $tablename = 'staff_mypays';
            }elseif(strpos("midou".$order_sn,"staff_paid_") > 0){
                $tablename = 'staff_paid';
            }else{
                $tablename = '';
            }
            if($tablename != ''){
                $rst_store = DB::name($tablename)
                    ->alias('mypays')
                    ->field('company.cname')
                    ->join('staff staff','staff.id=mypays.staff_id','left')
                    ->join('company company','staff.store_id=company.cid','left')
                    ->where(['mypays.paid_sn'=>$order_sn])
                    ->find();
                if($rst_store){
                    $order_from = $rst_store['cname'];
                }
            }else{
                $order_from = "线上";
            }
            $list[$k]['order_from'] = $order_from;
        }
        #老张加

        $count = DB::name('staff_commission')->where($where)->alias('commission')->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();        
        $this->assign('page',$show);
        $this->assign('list', $list);
        $this->assign('crumbs','佣金记录');
        return $this->fetch('index');
    }


    

}