<?php
namespace app\admin\controller;
use think\Page;

/**
 * Created by PhpStorm.
 * User: 王牧田
 * Date: 2018/9/28
 * Time: 10:07
 */
class WithdrawLog extends Base {

    public function index(){
		$from = I('from/s');
        $export = I('export',0);
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $key_word = I('get.key_word') ? trim(I('key_word')) : '';
        $where = [];
        /*查询所有实体店*/
        if($company_id){
            $store_cidlist = M('company')->where('parent_id','eq',$company_id)->column('cid');
            $store_lists = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_lists);

            $where['store_id'] = ['in',$store_cidlist];
        }
        if($store_id){
            $where['store_id']  =   ['eq',$store_id];
        }

        if($key_word){
            $where['receivable_mobile|partner_trade_no'] = ['like',"%".$key_word."%"];
        }
		$start_time = I('start_time');  // 开始时间
        if(I('start_time')){
           $begin    = urldecode($start_time);
           $end_time = I('end_time');   // 结束时间
           $end      = urldecode($end_time);
		   $starttime = strtotime($begin);
		   $endtime = strtotime($end) + 86400;
		   $where['create_time'] = ['between',"{$starttime},{$endtime}"];
        }else{
           $begin = date('Y-m-d', strtotime("-1 month")); 
          #  $begin = date('Y-m-d', strtotime("-3 day")); 
          # $end   = date('Y-m-d', strtotime("+3 day"));  // 1 天后
            $end   = date('Y-m-d', NOW_TIME);;
        }
		
        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);
        $company_parent_list = db('company')->field('cid,cname,parent_id')->cache(true)->select_key('cid');
        if($export == 1){
            //下载表格
            $store_withdraw_log =  db('store_withdraw_log')->where($where)->whereTime('create_time','-2 month')->order("id desc")->select();

            $html = "<table border=1>";
            $html.= '<tr><td>ID</td><td>实体店名称</td>';
            $html.= '<td>子公司名称</td>';
            $html.= "<td>提现金额</td>";
            $html.= "<td>手续费</td>";
            $html.= "<td>提现手机号</td>";
/*            $html.= "<td>提现账号</td>";*/
            $html.= "<td>业务结果</td>";
            $html.= "<td>提现时间</td>";
            $html.= "<td>商户订单号</td>";
            $html.= "<td>微信订单号</td>";
            $html.= "<td align='right'>微信支付成功时间</td>";
            $html.= "<td>收款人姓名</td>";
            $html.= "<td>收款人卡号</td>";
            $html.= "<td>收款人银行</td>";
            $html.= "<td>银行受理状态</td>";
            $html.= "<td>失败原因</td>";
            $bank_list = $this->bank_list();
            // dump($store_withdraw_log);die();
            foreach ($store_withdraw_log as $k=>$row){
                $company_name =   $company_parent_list[$company_parent_list[$row['store_id']]['parent_id']]['cname'];
                $html.= "<tr>";
                $html.= "<td>{$row['id']}</td>";
                $html.= "<td>{$row['store_name']}</td>";
                $html.= "<td>{$company_name}</td>";
                $html.= "<td>".tk_money_format($row['txje'])."</td>";
                $html.= "<td>".tk_money_format($row['cmms_amt'])."</td>";
                $html.= "<td>{$row['receivable_mobile']}</td>";
                $html.= "<td>{$row['result_code']}</td><td>";
                $html.= $row['create_time'] ? date("Y-m-d H:i:s",$row['create_time']) : '';
                $html.= "</td><td>&nbsp;{$row['partner_trade_no']}</td>";
                $html.= "<td>&nbsp;{$row['payment_no']}</td><td>";
                $html.= $row['payment_time'] ? date("Y-m-d H:i:s",strtotime($row['payment_time'])) : '';
                $html.= "</td><td>{$row['enc_true_name']}</td>";
                $html.= "<td>&nbsp;{$row['enc_bank_no']}</td>";
                $html.= "<td>".$bank_list[$row['bank_code']]."</td>";
                $html.= "<td>".query_bank_status($row['status'])." </td>";
                $html.= "<td>{$row['reason']} </td>";
                $html.= "</tr>";
            }
            $html.="</table>";

            downloadExcel($html,'提现记录表');
            exit();
        }

        $p  = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
        $company_list = get_company_list();
        $store_withdraw_log = db('store_withdraw_log')->where($where)->page($p,$size)->order('id desc')->select();
    //    dump($store_withdraw_log);die;
        foreach ($store_withdraw_log as $k=>$row){
            $store_withdraw_log[$k]["cname"] =   $company_parent_list[$company_parent_list[$row['store_id']]['parent_id']]['cname'];
			if(!empty($row['store_name'])) {
				$sName = mb_substr($row['store_name'], 0, 1);
				$sNameLen = mb_strlen($row['store_name']) - 1;
				for($i=1; $i<$sNameLen; $i++) {
					$sName .= '*';
				}
				$sName .= mb_substr($row['store_name'], -1);
				$store_withdraw_log[$k]['sName'] = $sName;
			}
			if(!empty($row['enc_bank_no'])) {
				$bank_no = substr($row['enc_bank_no'], 0, 4);
				$noLeng = strlen($row['enc_bank_no']) - 9;
				for($i=4; $i<$noLeng; $i++) {
					$bank_no .= '*';
				}
				$bank_no .= mb_substr($row['enc_bank_no'], -4);
				$store_withdraw_log[$k]['bank_no'] = $bank_no;
			}
			if(!empty($row['receivable_mobile'])) {
				$mobile = substr($row['receivable_mobile'], 0, 3);
				$mobile .= '****';
				$mobile .= mb_substr($row['receivable_mobile'], -3);
				$store_withdraw_log[$k]['mobile'] = $mobile;
			}
			if(!empty($row['partner_trade_no'])) {
				$tradeNo = substr($row['partner_trade_no'], 0, 4);
				$tradeNo .= '******';
				$tradeNo .= mb_substr($row['partner_trade_no'], -4);
				$store_withdraw_log[$k]['tradeNo'] = $tradeNo;
			}
        }
        $store_withdraw_log_count =  db('store_withdraw_log')->where($where)->count();
        $Page = new Page($store_withdraw_log_count,$size);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('store_withdraw_log',$store_withdraw_log);
        $this->assign('page',$show);
        $this->assign('company_list',$company_list);
		$tmp = ($from == 'qrcode')? 'qrcode' : '';
        return $this->fetch($tmp);
    }
	
	public function too_index() {
		$this->index();
	}
    function get_bank_status(){
        $id = I('get.id/d');
        $where['id']    =   ['eq',$id];
        $result = db('store_withdraw_log')->cache(true)->where($where)->find();
        
        if($result['partner_trade_no'] && $result['is_card'] == 1){
            include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
            $weixin_obj = new \weixin();
            $r = $weixin_obj->query_bank($result['partner_trade_no']);
            if($r['status'] == 1){
                $res['system_status'] = 0;
                $res['info']  =   $r['msg'];
                $this->ajaxReturn($res);
            }
            $save_data['status']    =   $r['status'];
            $save_data['reason']    =   $r['reason'];
            M('store_withdraw_log')->where('partner_trade_no',$result['partner_trade_no'])->save($save_data);
           
            $r['system_status'] =   1;
            $r['status']    =   query_bank_status($r['status']);
            $this->ajaxReturn($r);
        }
    }
    /*查询零钱转账状态*/
    function gettransfer(){
        $id = I('get.id/d');
        $where['id']    =   ['eq',$id];
        $result = db('store_withdraw_log')->cache(true)->where($where)->find();
        if($result['partner_trade_no']){
            include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
            $weixin_obj = new \weixin();
            $r = $weixin_obj->gettransfer($result['partner_trade_no']);
            if($r['status'] == 1){
                $res['system_status'] = 0;
                $res['info']  =   $r['msg'];
                $this->ajaxReturn($res);
            }
            $r['status']    =   query_change_status($r['status']);
            $this->ajaxReturn($r);
        }
    }



    function bank_list(){
        $bank_list['1002']  =   '工商银行';
        $bank_list['1005']  =   '农业银行';
        $bank_list['1026']  =   '中国银行';
        $bank_list['1003']  =   '建设银行';
        $bank_list['1001']  =   '招商银行';
        $bank_list['1066']  =   '邮储银行';
        $bank_list['1020']  =   '交通银行';
        $bank_list['1004']  =   '浦发银行';
        $bank_list['1006']  =   '民生银行';
        $bank_list['1009']  =   '兴业银行';
        $bank_list['1010']  =   '平安银行';
        $bank_list['1021']  =   '中信银行';
        $bank_list['1025']  =   '华夏银行';
        $bank_list['1027']  =   '广发银行';
        $bank_list['1022']  =   '光大银行';
        $bank_list['1032']  =   '北京银行';
        $bank_list['1056']  =   '宁波银行';
        return $bank_list;
    }


}