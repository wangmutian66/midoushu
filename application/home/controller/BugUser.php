<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 *
 */ 
namespace app\home\controller; 
use think\Controller;
use think\Model;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
use think\Cache;
use think\Lang;



class BugUser extends Controller {
    
    public function index(){ 
        $where['parent_sn'] =   ['exp','is null'];
        $where['is_store']  =   ['eq',0];
        $where['pay_status']    =   ['>=',1];
        $where['a.order_id']  =   ['<',3936];
        $list = db::name('order_red a')->field('a.*,b.log_id')->where($where)
        ->join('account_log b','b.order_sn = a.order_sn','left')
        ->order('a.order_id desc')
        ->cache(true)
        ->paginate(100);
        $array_list = $list->toArray()['data'];
    
        foreach ($array_list as $key => $value) {
        //    $array_list[$key]['pay_status'] =   $pay_array[$value['pay_status']];
            $r    =   db::name('order_red')->where('parent_sn',$value['order_sn'])->cache("order_sn_bug_list_{$value['order_sn']}")->select();
            if($r){
                $array_list[$key]['son_order']  =   $r;

            }else{
                unset($array_list[$key]);
            }
        }
        $this->assign('array_list',$array_list);
        $this->assign('list', $list);
        return $this->fetch();
    }  
    

    function getAccountLog(){
        $user_id = I('get.user_id/d');
        $where['is_red']    =   ['eq',1];
        $where['desc']  =   ['eq','米豆专区商品兑换'];
        $list = db('account_log')->where($where)->order('log_id desc')->select();

        $this->assign('list', $list);
        return $this->fetch('getAccountLog');
    }
    

    
}