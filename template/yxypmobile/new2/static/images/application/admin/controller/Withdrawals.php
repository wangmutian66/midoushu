<?php
/**
 * tpshop
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
use app\admin\model\CompanyWithdrawalModel;

class Withdrawals extends Base {

    var $table_name;
    var $model;
    var $pk;
    var $indexUrl;
    public function _initialize() {
        parent::_initialize();   
        $this->table_name = 'company_withdrawals';
        $this->pk ='id';
        $this->indexUrl = U('Admin/Withdrawals/Index');
    }

    public function index(){
        $this->get_withdrawals_list();
        return $this->fetch('index');
    }

    public function get_withdrawals_list($status=''){
        $user_id = I('user_id/d');  
        $realname = I('realname');            // 提款账号真实姓名
        $bank_card = I('bank_card');          // 银行账号或支付宝账号
        $create_time = I('create_time');      // 申请时间

        $is_staff = I('get.is_staff/d',1);
        $create_time = str_replace("+"," ",$create_time);
        $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);
        $this->assign('start_time',$create_time3[0]); 
        $this->assign('end_time',$create_time3[1]);
        $where['w.create_time'] =   ['between',strtotime($create_time3[0]).",".strtotime($create_time3[1])];
        $status = empty($status) ? I('status') : $status;  // 状态
        if(empty($status) || $status === '0'){
            $where['w.status'] =  array('lt',1);    
        }
        if($status === '0' || $status > 0) {
            $where['w.status'] = ['eq',$status];
        }
        if($user_id) {
            if($is_staff == 1){
                $where['w.staff_id'] = ['eq',$user_id];
            }else{
                $where['w.member_id'] = ['eq',$user_id];
            }
        }
        $realname && $where['w.real_name'] = array('like','%'.$realname.'%');
        $bank_card && $where['w.bank_card'] = array('like','%'.$bank_card.'%');
      //  dump($where);die;
        $export = I('export');
        if($export == 1){
            if($is_staff == 1){
                $strTable ='<table width="1000" border="1">';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请员工</td>';

                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属子公司</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属实体店</td>';

                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现金额</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手续费</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提款账号姓名</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
                $strTable .= '</tr>';
                $remittanceList = Db::name('staff_withdrawals')->alias('w')->field('w.*,staff.real_name name,lv.service_charge,store.cname store_name,company.cname company_name')
                ->join('staff staff', 'staff.id = w.staff_id', 'INNER')
                ->join('company_level lv','lv.id = staff.company_level','left')
                ->join('company store','store.cid = staff.store_id','left')
                ->join('company company','company.cid = staff.company_id','left')
                ->where($where)
                ->order("w.id desc")
                ->select();
            }else{
                $strTable ='<table width="1000" border="1">';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请成员</td>';

                $strTable .= '<td style="text-align:center;font-size:12px;">隶属上级</td>';

                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现金额</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手续费</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
                $strTable .= '</tr>';
                $remittanceList = Db::name('member_withdrawals')->alias('w')
                                    ->field('w.*,u.real_name name,lv.service_charge,company.cname company_name')
                                    ->join('company_member u', 'u.id = w.member_id','left')
                                    ->join('company_level lv','lv.id = u.company_level','left')
                                    ->join('__COMPANY__ company','company.cid = u.parent_id','left')
                                    ->where($where)
                                    ->order("w.id desc")
                                    ->select();

            }
            if(is_array($remittanceList)){
                foreach($remittanceList as $k=>$val){
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['name'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['company_name'].'</td>';
                    if($is_staff == 1){
                        $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['store_name'].'</td>';
                    }
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['money'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['money'] * $val['service_charge']).' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['bank_name'].'</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">'.$val['bank_card'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['real_name'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['create_time']).'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['remark'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.check_withdrawal($val['status']).'</td>';
                    
                    $strTable .= '</tr>';
                }
            }
            $strTable .='</table>';
            unset($remittanceList);
            downloadExcel($strTable,'remittance');
            exit();
        }
        
        if($is_staff == 1){
            $count = db('staff_withdrawals')->alias('w')->join('__STAFF__ u', 'u.id = w.staff_id')->where($where)->count();
            $Page  = new Page($count,20);
            $list = M('staff_withdrawals')->alias('w')->field('w.*,u.real_name name,c.cname store_name,company.cname company_name,lv.service_charge')
                                            ->join('__STAFF__ u', 'u.id = w.staff_id')
                                            ->join('__COMPANY__ c','c.cid = u.store_id','left')
                                            ->join('__COMPANY__ company','company.cid = u.company_id','left')
                                            ->join('company_level lv','lv.id = u.company_level','left')
                                            ->where($where)->order("w.id desc")
                                            ->limit($Page->firstRow.','.$Page->listRows)
                                            ->select();
       #     echo M('staff_withdrawals')->getlastsql();
        }else{
            $count = db('member_withdrawals')->alias('w')->join('company_member u', 'u.id = w.member_id')->where($where)->count();
            $Page  = new Page($count,20);
            $list = M('member_withdrawals')->alias('w')->field('w.*,u.real_name name,company.cname company_name,company.cid company_id,lv.service_charge')
                                            ->join('company_member u', 'u.id = w.member_id','left')
                                            ->join('company company','company.cid = u.parent_id','left')
                                            ->join('company_level lv','lv.id = u.company_level','left')
                                            ->where($where)
                                            ->order("w.id desc")
                                            ->limit($Page->firstRow.','.$Page->listRows)
                                            ->select();
        }
    //    dump($where);die;
        foreach ($list as $key => $value) {
            $list[$key]['service_charge']   =   $value['money'] * $value['service_charge'];
        }
        $this->assign('create_time',$create_time2);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        C('TOKEN_ON',false);
    }

    /*汇款记录*/
    public function remittance(){
        $status = I('status',1);
        $this->assign('status',$status);
        $this->get_withdrawals_list($status);
        return $this->fetch();
    }


 

    /**
     * 修改编辑 申请提现
     */
    public  function modify(){        
       $id = I('id');
       $is_staff = I('get.is_staff/d',1);
       if($is_staff == 2){
            $item = M("member_withdrawals")->alias('w')
                            ->alias('w')->field('w.*,u.real_name,company.cname company_name,company.cid company_id,u.uname,lv.service_charge,lv.present_money,lv.present_time_start,lv.present_time_end,lv_name')
                            ->join('company_member u', 'u.id = w.member_id','left')
                            ->join('company_level lv','lv.id = u.company_level','left')
                            ->join('company company','company.cid = u.parent_id','left')
                            ->find($id);
            $item['final_money']    =  $item['money'] -  ($item['money']  *  $item['service_charge']);
            $this->assign('item',$item);
            return $this->fetch('Form_s');
       }else{
            $item = M("staff_withdrawals")
                           ->alias('w')
                           ->field('w.*,store.cname store_name,company.cname company_name,staff.uname,staff.real_name staff_name,lv.service_charge,lv.present_money,lv.present_time_start,lv.present_time_end,lv_name')
                           ->join('__STAFF__ staff','staff.id = w.staff_id')
                           ->join('company_level lv','lv.id = staff.company_level','left')
                           ->join('__COMPANY__ store','store.cid = staff.store_id','left')
                           ->join('__COMPANY__ company','company.cid = staff.company_id','left')
                           ->find($id);
            $item['final_money']    =  $item['money'] -  ($item['money']  *  $item['service_charge']);
            $this->assign('item',$item);
            return $this->fetch('Form');
       }
      
       
    }  

    function doModify(){
        if($id = I('post.id/d')){
            $remark = I('post.remark/s');
            $is_staff = I('is_staff/d',1);
            if($is_staff == 1){
                $table_name = 'staff_withdrawals';
            }else{
                $table_name = 'member_withdrawals';
            }
            if(M($table_name)->where('id','eq',$id)->setField('remark',$remark)){
                $this->success('修改成功');
            }else{
                $this->error('修改失败!');
            }
        }else{
            $this->error('参数错误');
        }
    }

    /**
     *  处理人员提现申请
     */
    public function withdrawals_update(){
        $id = I('id/a');
        $data['status']= $status = I('status');
        $data['remark'] = I('remark');
        if($status == 1) $data['check_time'] = time();
        if($status != 1) $data['refuse_time'] = time();
//        dump($status);die;
        if($status < 0){
            $log_list = M('staff_withdrawals')->where('id','in',$id)->select();
            foreach ($log_list as $key => $value) {
                $staff_ids[] = $value['staff_id'];
            }
            $staff_list_array = M('staff')->where('id','in',$staff_ids)->select();
            foreach ($staff_list_array as $key => $value) {
                $staff_list[$value['id']]   =   $value;
            }
            foreach ($log_list as $key => $value) {
                if($staff_list[$value['staff_id']]['frozen'] < $value['money']){
                    $error_msg .= "员工：{$value['real_name']} 提款记录ID：{$value['id']} 失败，可用冻结余额不足<br>";
                    continue;
                }
                $save_ids[] =   $value['id'];
                $staff_money_list[] = ['id'=>$value['staff_id'],
                                        'frozen'=>['exp',"frozen - {$value['money']}"],
                                        'money'=>['exp',"money + {$value['money']}"]
                                    ];
            }
            model('staff')->saveAll($staff_money_list);
            $id = $save_ids;
        }
        if($error_msg){
            $data['status'] =   0;
            $data['msg']    =   $error_msg;
            $this->ajaxReturn($data);exit;
        }
        
        
        $r = M('staff_withdrawals')->where('id','in',$id)->update($data);
        if($r){
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"));
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>"操作失败"));
        }   
    }

    /**
     *  处理人员提现申请
     */
    public function withdrawals_update_member(){
        $id = I('id/a');
        $data['status']= $status = I('status');
        $data['remark'] = I('remark');
        if($status == 1) $data['check_time'] = NOW_TIME;
        if($status != 1) $data['refuse_time'] = NOW_TIME;
        if($status < 0){
            $log_list = M('member_withdrawals')->where('id','in',$id)->select();
            foreach ($log_list as $key => $value) {
                $member_ids[] = $value['member_id'];
            }
            $member_list_array = M('company_member')->where('id','in',$member_ids)->select();
            foreach ($member_list_array as $key => $value) {
                $member_list[$value['id']]   =   $value;
            }
            foreach ($log_list as $key => $value) {
                if($member_list[$value['member_id']]['frozen'] < $value['money']){
                    $error_msg .= "员工：{$value['real_name']} 提款记录ID：{$value['id']} 失败，可用冻结余额不足<br>";
                    continue;
                }
                $save_ids[] =   $value['id'];
                $member_money_list[] = ['id'=>$value['staff_id'],
                                        'frozen'=>['exp',"frozen - {$value['money']}"],
                                        'money'=>['exp',"money + {$value['money']}"]
                                    ];
            }
            model('company_member')->saveAll($member_money_list);
            $id = $save_ids;
        }
        if($error_msg){
            $data['status'] =   0;
            $data['msg']    =   $error_msg;
            $this->ajaxReturn($data);exit;
        }
        
        
        $r = M('member_withdrawals')->where('id','in',$id)->update($data);
        if($r){
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"));
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>"操作失败"));
        }   
    }

    // 用户申请提现
    public function transfer(){
        $id = I('selected/a');
        if(empty($id))$this->error('请至少选择一条记录');
        $atype = I('atype');
        $log_list = M('staff_withdrawals')->where('id','in',$id)->select();
      
        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach($log_list as $val){
            $staff_info = M('staff')->where('id','eq',$val['staff_id'])->find();
            if($staff_info['frozen'] < $val['money'])
            {
                $data = array('status'=>-2,'remark'=>'账户冻结余额不足');
                M('staff_withdrawals')->where(array('id'=>$val['id']))->save($data);
                $this->error('账户余额不足');
            }else{
                $rdata = array('type'=>4,'money'=>$val['money'],'log_type_id'=>$val['id'],'staff_id'=>$val['staff_id']);
                if($atype == 'online'){
                    #在线转账
                     header("Content-type: text/html; charset=utf-8");
                     exit("未完待续");
                }else{
                    $update_data = array(
                        'frozen' => ['exp','frozen +'.($val['money'] * -1)],
                    );
                    $update = M('staff')->where('id','eq',$val['staff_id'])->update($update_data);
                    staff_accountLog($val['staff_id'], ($val['money'] * -1),"管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('staff_withdrawals')->where(array('id'=>$val['id']))->save(['status'=>2,'pay_time'=>NOW_TIME]);
                    expenseLog($rdata);//支出记录日志
                }
            }
        }

        if($alipay['batch_num']>0){
            //支付宝在线批量付款
            include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!",U('remittance'),3);
    }


    // 用户申请提现
    public function transfer_member(){
        $id = I('selected/a');
        if(empty($id))$this->error('请至少选择一条记录');
        $atype = I('atype');
        $log_list = M('member_withdrawals')->where('id','in',$id)->select();
      
        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach($log_list as $val){
      #      dump($val);die;
            $member_info = M('company_member')->where('id','eq',$val['member_id'])->find();
            if($member_info['frozen'] < $val['money'])
            {
                $data = array('status'=>-2,'remark'=>'账户冻结余额不足');
                M('member_withdrawals')->where("id = {$val['id']}")->update($data);
                $this->error('账户冻结余额不足');
            }else{
                $rdata = array('type'=>4,'money'=>$val['money'],'log_type_id'=>$val['id'],'member_id'=>$val['member_id']);
                if($atype == 'online'){
                    #在线转账
                     header("Content-type: text/html; charset=utf-8");
                     exit("未完待续");
                }else{
                    $update_data = array(
                        'frozen' => ['exp','frozen +'.($val['money'] * -1)],
                    );
                    $update = M('company_member')->where("id = {$val['member_id']}")->update($update_data);
                    member_accountLog($val['member_id'], ($val['money'] * -1),"管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('member_withdrawals')->where("id = {$val['id']}")->save(['status'=>2,'pay_time'=>NOW_TIME]);
                    expenseLog($rdata);//支出记录日志
                }
            }
        }

        if($alipay['batch_num']>0){
            //支付宝在线批量付款
            include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!",U('/Admin/Withdrawals/remittance/is_staff/2'),3);
    }
    
 
    function del(){
        if($id = I('get.del_id/d')){
            $is_staff = I('get.is_staff/d',1);
            if($is_staff == 1){
                if(M('staff_withdrawals')->delete($id)){
                    $res  =   1;
                }else{
                    $res    =   '删除失败！';
                }
            }elseif($is_staff == 2){
                if(M('member_withdrawals')->delete($id)){
                    $res  =   1;
                }else{
                    $res  = '删除失败！';
                }
            } 
        }else{
            $res = '非法操作！';
        }
        $this->ajaxReturn($res);
    }




}