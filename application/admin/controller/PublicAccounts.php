<?php
namespace app\admin\controller;
use think\Page;

/**
 * Created by PhpStorm.
 * User: 王文凯
 * Date: 2018年12月12日13:37:37
 */
class PublicAccounts extends Base {

    public function index(){
		$from = I('from/s');
        $export = I('export',0);
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $key_word = I('get.key_word') ? trim(I('key_word')) : '';
        $where = [];
        /*查询所有实体店*/
        if($company_id){
            $store_lists = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_lists);

            $where['company_id'] = ['in',$company_id];
        }
        if($store_id){
            $where['store_id']  =   ['eq',$store_id];
        }
        $settlement_status = I('param.settlement_status/d');
        if($settlement_status){
            $settlement_status == 3 && $settlement_status = 0;
            $where['settlement_status'] =   ['eq',$settlement_status];
        }
        if($key_word){
            $where['paid_sn'] = ['like',"%".$key_word."%"];
        }
		$start_time = I('start_time');  // 开始时间
        if(I('start_time')){
           $begin    = urldecode($start_time);
           $end_time = I('end_time');   // 结束时间
           $end      = urldecode($end_time);
		   
        }else{
           $begin = date('Y-m-d', strtotime("-1 month")); 
           $end   = date('Y-m-d', NOW_TIME);;
        }

        $starttime = strtotime($begin);
        $endtime = strtotime($end) + 86400;
        $where['settlement.create_time'] = ['between',"{$starttime},{$endtime}"];
		
        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);
        
        if($export == 1){
            $p  = 0;
            $size = 10000;
        }else{
            $p  = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
            $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
        }
        
        $list = db('store_settlement settlement')
                ->field('settlement.*,store.cname store_name,company.cname company_name,admin.user_name settlement_admin_name')
                ->join('company company','company.cid = settlement.company_id','left')
                ->join('company store','store.cid = settlement.store_id','left')
                ->join('admin admin','admin.admin_id = settlement_admin_id','left')
                ->where($where)->page($p,$size)->order('id desc')->select();
        
        if($export == 1){
            $this->export($list);
        }
        $count =  db('store_settlement settlement')->where($where)->count();
        $Page = new Page($count,$size);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('list',$list);
        $this->assign('page',$show);
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        return $this->fetch('index');
    }

	/*结算订单*/
    function settlement_list(){
        $ids = I('param.ids');
        $set_status = I('param.set_status/d',1);
        if($ids){
            if($set_status == 1){
                $list_where['settlement_status'] =   ['eq',0];
                $msg_info    =   '未查找到需要结算的数据！';
                $success_info = '结算成功!';
            }elseif($set_status == 2){
                $list_where['settlement_status'] =   ['eq',1];
                $msg_info    =   '未查找到需要转账的数据！';
                $success_info    =   '转账成功！';
            }
            $list = db('store_settlement settlement')->where($list_where)->where('id','in',$ids)->select();
            if($list){
                foreach ($list as $key => $value) {
                    $updata_sql[] = ['id'=>$value['id'],
                                        'settlement_status'=>$set_status,
                                        'settlement_time'=>NOW_TIME,
                                        'settlement_admin_id'=>session('admin_id')
                                    ];
                    if($set_status == 2){
                        $store_list[$value['store_id']]['ids'][]   =   $value['id'];   
                        $store_list[$value['store_id']]['money']    += $value['settlement_amount'];
                    }
                }
                if($store_list){
                    foreach ($store_list as $key => $value) {
                        $insert_data[]  =['store_id'=>$key,
                                        'settlement_count'=>count($store_list[$key]['ids']),
                                        'create_time'=>NOW_TIME,
                                        'settlement_ids'=>implode(',',$value['ids']),
                                        'subtotal_money'=>$value['money'],
                                        ];
                    }
                    model('SettlementSubtotal')->saveAll($insert_data);
                }
                model('storeSettlement')->saveAll($updata_sql);
                $res['status']  =   1;
                $res['info']    =   $success_info;
            }else{
                $res['status']  =   0;
                $res['info']    =   $msg_info;
            }
        }else{
            $res['status']  =   0;
            $res['info']    =   '请输入结算ID！';
        }
        $this->ajaxReturn($res);
    }

    function export($list){
   
        //下载表格
        
        $html = "<table border=1>";
        $html.= '<tr><td>ID</td><td>实体店名称</td>';
        $html.= '<td>子公司名称</td>';
        $html.= "<td>订单号码</td>";
        $html.= "<td>创建时间</td>";
        $html.= "<td>结算状态</td>";
        $html.= "<td>金额</td>";
        $html.= "<td>结算/转账时间</td>";
        $html.= "<td>最终操作人</td></tr>";
        
        foreach ($list as $k=>$row){
            $html.= "<tr>";
            $html.= "<td>{$row['id']}</td>";
            $html.= "<td>{$row['store_name']}</td>";
            $html.= "<td>{$row['company_name']}</td>";
            $html.= "<td>{$row['paid_sn']}</td>";
            $html.= "<td>".date("Y-m-d H:i:s",$row['create_time'])."</td>";
            if($row['settlement_status'] == 0){
                $settlement_status = '待结算';
            }elseif($row['settlement_status'] == 1){
                $settlement_status = '待转账';
            }elseif($row['settlement_status'] == 2){
                $settlement_status = '已完成';
            }
            $html.= "<td>{$settlement_status}</td>";
            $html.= "<td>{$row['settlement_amount']}</td><td>";
            $html.= $row['settlement_time'] ? date("Y-m-d H:i:s",$row['settlement_time']) : '';
            $html.= "</td><td>{$row['settlement_admin_name']}</td>";
            $html.= "</tr>";
        }
        $html.="</table>";

        downloadExcel($html,'对公账户数据导出');
        exit();
    
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