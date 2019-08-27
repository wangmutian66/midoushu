<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\supplier\controller; 
use app\supplier\logic\SuppliersLogic;
use think\Page;
use think\Verify;
use think\Db;
use think\Loader;
class withdrawal extends Base {

    var $suppliers_id;

    function _initialize() 
    {   
        parent::_initialize();
        $this->suppliers_id = Session('suppliers.suppliers_id');

        $start_time = I('start_time');  // 开始时间
        if(I('start_time')){
           $begin    = urldecode($start_time);
           $end_time = I('end_time');   // 结束时间
           $end      = urldecode($end_time);
        }else{
           $begin = date('Y-m-d', strtotime("-3 month")); //30天前  ···明明是 3 个月前
           $end   = date('Y-m-d', strtotime('+1 days'));  // 1 天后
        }
        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);
        $this->begin = strtotime($begin);
        $this->end   = strtotime($end)+86399;  // 比 24 小时 少 1 s
    } 

    /**
     * 申请提现记录
     */
    public function index(){

        $supplierid = $this->suppliers_id;
        $supplier   = D('suppliers')->where(array('suppliers_id'=>$supplierid))->find();
        $this->assign('supplier',$supplier);

        $m_stsrt = date('Y-m-01', time());
        $m_end   = date('Y-m-t', time());

        $m_stsrt_time = strtotime($m_stsrt);
        $m_end_time   = strtotime($m_end);

        $logic     = new SuppliersLogic();
        $salemoney = $logic->getSalemoney($supplierid,$m_stsrt_time,$m_end_time);

        $this->assign('salemoney',$salemoney);
        $this->assign('service_fee',$supplier['service_fee']);
        $this->assign('distribut_min',tpCache('basic.min'));

        if(IS_POST)
        {
            if(!$this->verifyHandle('withdrawals')){
                $this->ajaxReturn(['status'=>0,'msg'=>'图像验证码错误']);
            };
            $data = I('post.');
            $data['suppliers_id'] = $this->suppliers_id;                      
            $data['create_time']  = time();                
            $distribut_min = tpCache('basic.min');     // 最少提现额度
            $service_fee   = $supplier['service_fee']; // 供货商提现手续费
            $data['taxfee'] = $data['money']*$service_fee/100; // 手续费
            $total = $data['money']+$data['taxfee']; // 总

            if($data['money'] < $distribut_min)
            {
                $this->ajaxReturn(['status'=>0,'msg'=>'每次最少提现额度'.$distribut_min]);
                exit;
            }
            if($total > $supplier['suppliers_money'])
            {
                //$this->ajaxReturn(['status'=>0,'msg'=>"你最多可提现".$supplier['suppliers_money']."账户余额."]);
                $this->ajaxReturn(['status'=>0,'msg'=>"抱歉，您的余额不足"]);
                exit;
            }
            if(encrypt($data['suppliers_paypwd']) != $supplier['suppliers_paypwd']){
                $this->ajaxReturn(['status'=>0,'msg'=>"支付密码错误"]);
            }
            
            if(M('suppliers_withdrawals')->add($data)){
                suppliers_accountLog($supplierid, (-1 * $total), 0, '供货商提现申请');
                $up_data['frozen_money'] = $supplier['frozen_money']+$total;
                M('suppliers')->where('suppliers_id ='.$this->suppliers_id)->update($up_data);
                $this->ajaxReturn(array('status'=>1,'msg'=>"已提交申请"));
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>'提交失败,联系客服!']);
            }
        }

        return $this->fetch();
    }


    public function withdrawals_log()
    {
        // 搜索
        $create_time  = I('create_time');  // 申请时间
        $create_time  = str_replace("+"," ",$create_time);

        $today  = strtotime(date("Y-m-d"),time()) + 24*3600;
        $end_time = date("Y-m-d",$today);

        $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.$end_time;
        $create_time3 = explode(' - ',$create_time2);
        $this->assign('start_time',$create_time3[0]); 

        $this->assign('end_time',$create_time3[1]);
        $where['create_time'] = array(array('gt', strtotime($create_time3[0])), array('lt', (strtotime($create_time3[1])+24*3600)));

        /*$status = empty($status) ? I('status') : $status;  // 状态
        if(empty($status) || $status === '0'){
            $where['status'] =  array('lt',1);    
        }
        if($status === '0' || $status > 0) {
            $where['status'] = array('eq',$status);
        }*/

        $where['suppliers_id'] = array('eq',$this->suppliers_id);
        if($status){
            $where['status'] = array('eq',$status);
        }

        $count = M('suppliers_withdrawals')->where($where)->count();
        $Page = new Page($count, 15);
        $withdrawals_log = M('suppliers_withdrawals')->where($where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $show = $Page->show_sup();
        
        $this->assign('create_time',$create_time2);
        $this->assign('page', $show);
        $this->assign('lists', $withdrawals_log);
        return $this->fetch();
    }


    /**
     * 供货商账户资金记录
     */
    public function suppliers_account_log(){
        $suppliers_id = $this->suppliers_id;
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('suppliers_account_log')->where(array('suppliers_id'=>$suppliers_id))->count();
        $page = new Page($count);
        $lists  = M('suppliers_account_log')->where(array('suppliers_id'=>$suppliers_id))->order('change_time desc')->limit($page->firstRow.','.$page->listRows)->select();

        $this->assign('suppliers_id',$suppliers_id);
        $this->assign('page',$page->show());
        $this->assign('lists',$lists);
        return $this->fetch();
    }


    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        $result = $verify->check(I('post.verify_code'), $id ? $id : 'withdrawals');
        if (!$result) {
            return false;
        }else{
            return true;
        }
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'withdrawals';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }




   
}