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
class CapitalManage extends Base {

	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
        $store_info = db('company')->find($this->store_id);
        $this->assign('store_info',$store_info);
   } 

    public function index(){

        //搜索关键词
		$where['store_id']	=	['eq',$this->store_id];
        $p = I('p/d',1);
        $page_last = 10;
        $list = DB::name('store_withdraw_log')->where($where)->order('id desc')->page("{$p},{$page_last}")->select();
        $count = DB::name('store_withdraw_log')->where($where)->count();
        $Page = new Page($count,$page_last);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list', $list);
        return $this->fetch('index');
    }
    public function withdrawals(){   
        return $this->fetch('withdrawals');   
    }


    function dowithdrawals(){
        $money = I('post.money/f');                                                       //支付金额
        $presentation_mode = I('presentation_mode/d',1);    //支付方式，比较坑啊 自己控制不住的坑自己
        $store_info = db('company')->find($this->store_id);
        $StoreWithdrawProject = new StoreWithdraw();
        $StoreWithdrawProject->setPayMoney($money);
        $StoreWithdrawProject->setStoreInfo($store_info);
        if($presentation_mode == 2){    //如果选择支付到银行卡
            $StoreWithdrawProject->setIsCard(1);
        }
        $res = $StoreWithdrawProject->payCheck();
        if($res['status'] == 1){
            if($StoreWithdrawProject->withdrawDeduction()){
                $res = $StoreWithdrawProject->withdrawPay();
            }else{
                $res['status']  =   0;
                $res['info']    =   '实体店扣款失败！';
            }
        }
        $this->ajaxReturn($res);
    }

    function rewithdrawals(){
        $id = I('get.id/d');
        $r = db('store_withdraw_log')->find($id);
        $StoreWithdrawProject = new StoreWithdraw();
        $StoreWithdrawProject->setPayMoney($r['txje']);
        $StoreWithdrawProject->setStoreInfo(db('company')->find($r['store_id']));
        $StoreWithdrawProject->setPartnerTradeNo($r['partner_trade_no']);
        $StoreWithdrawProject->setIsCard($r['is_card']);
        $res = $StoreWithdrawProject->payCheck();
        if($res['status'] == 1){
            $res = $StoreWithdrawProject->withdrawPay();
        }
        $this->ajaxReturn($res);
    }

    /*查询订单状态*/
    /*function get_bank_status(){
        $id = I('get.id/d');
        $where['id']    =   ['eq',$id];
        $where['store_id']  =   ['eq',$this->store_id];
        $result = db('store_withdraw_log')->cache(true)->where($where)->find();
        $StoreWithdrawProject = new StoreWithdraw();
        $res = $StoreWithdrawProject->get_weixin_status(['id'=>$id,'store_id'=>$this->store_id]);
        $this->ajaxReturn($res);
    }*/

}