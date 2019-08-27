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
use think\Config;
use think\Page;
use think\Db;
use think\Cache;
use app\common\logic\UsersLogic;
use Withdraw\StoreWithdraw;
/*
    成员管理
*/
class OutstandingFunds extends Base {

	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
    } 

    public function index(){
        //搜索关键词
        

        $p = I('p/d',1);
        $page_last = 10;
        $where['store_id']  =   ['eq',$this->store_id];
        $list = DB::name('settlement_subtotal')->where($where)->order('id desc')->page("{$p},{$page_last}")->select();
     
        $count = DB::name('settlement_subtotal')->where($where)->count();
        $Page = new Page($count,$page_last);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list', $list);

        $js_where['settlement_status'] =    ['eq',0];
        $js_where['store_id']   =   ['eq',$this->store_id];
        $js_money = db('store_settlement')->where($js_where)->sum('settlement_amount');
        $this->assign('js_money',$js_money);

        $outstanding_funds_where['settlement_status'] =    ['eq',1];
        $outstanding_funds_where['store_id']   =   ['eq',$this->store_id];
        $outstanding_funds = db('store_settlement')->where($outstanding_funds_where)->sum('settlement_amount');
        $this->assign('outstanding_funds',$outstanding_funds);

        return $this->fetch('index');
        
    }
    
    public function settlement_list(){
        $id = I('get.id/d');
        $ids = DB::name('settlement_subtotal')->where('id',$id)->getField('settlement_ids');
        $where['id'] =  ['in',$ids];
        $where['store_id']  =   ['eq',$this->store_id];
        $key_word = I('param.key_word/s');
        $key_word && $where['paid_sn']   =   ['like',"%{$key_word}%"];

        if($settlement_status = I('param.settlement_status/d')){
            if($settlement_status == 3){
                $where['settlement_status'] =   ['eq',0];
            }else{
                $where['settlement_status'] =   ['eq',$settlement_status];
            }
        }

        $p = I('p/d',1);
        $page_last = 10;
        $list = DB::name('store_settlement')->where($where)->order('id desc')->page("{$p},{$page_last}")->select();
        $count = DB::name('store_settlement')->where($where)->count();
        $Page = new Page($count,$page_last);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list', $list);

        
        
        return $this->fetch('settlement_list');
    }


}