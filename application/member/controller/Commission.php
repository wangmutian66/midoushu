<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\member\controller; 
use think\Controller;
//use think\Config;
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

        $phone = db('company_member')->where(['id'=>$this->member_id])->value('phone');
        $member_id = db('company_member')->where(['phone'=>$phone])->column('id');
        $where['member_id']  =   ['in',$member_id];


        //$where['member_id']  =   ['eq',$this->member_id];
        $where['money'] =   ['neq',0];
        $p = I('p/d',1);
        $page_last = 4;
        $list = DB::name('member_commission')->where($where)
                                ->field("create_time,money,order_sn,order_id,member_id,pay_id")
                                ->alias('comission')
                                ->order('id desc')
                                ->page("{$p},{$page_last}")
                                ->select();

        foreach ($list as $key => $value) {
            // dump($value['pay_id']);
            if ($value['pay_id'] == '0') {
                if (strstr($value['order_sn'],'midou')) {
                     $list[$key]['is_red']='1';
                }else{
                     $list[$key]['is_red']='0';
                }
            }else{
                    $list[$key]['store_name']= $this->mypays($value['pay_id']);
            }
               
          
        }

        $count = DB::name('member_commission')->where($where)->alias('commission')->count();
        $Page = new Page($count,$page_last);
        $Page->rollPage = 2;
        $show = $Page->show();        
        $this->assign('page',$show);
        $this->assign('list', $list);
        // dump($list);die();
        $this->assign('crumbs','佣金记录');
        return $this->fetch('index');
    }

    function mypays($id){
         $where = array(
                'sm.id' => $id,
            );
        $list = M('staff_mypays')
            ->alias('sm')
            ->join('staff s', 's.id = sm.staff_id')
            ->join('company c', 'c.cid = s.store_id')
            ->field('c.cname')
            ->where($where)
            ->find();
        return $list['cname'];
    }
   
    

}