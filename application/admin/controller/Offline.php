<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 *  子公司管理
 */
namespace app\admin\controller;

use think\Config;
use think\Page;
use think\Verify;
use think\Db;
use think\Cache;

class Offline extends Base
{

    public $table_name;
    public $pk;
    public $indexUrl;

    public function _initialize()
    {
        parent::_initialize();
        $this->indexUrl = U('Admin/Company/index');
        $this->catUrl = U('Admin/Company/category');
    }

   
    

    /*线下付款流水明细*/
    function running_water()
    {
		$from = I('from/s');
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        /*子公司列表*/
        $company_list = get_company_list();
        $this->assign('company_list', $company_list);
        
        $table_name = 'staff_mypays';
        if ($company_id = I('get.company_id/d')) {
            $where['a.company_id'] = ['eq', $company_id];
            $store_list = db('company')->where("parent_id = {$company_id}")->select();
            $this->assign('store_list', $store_list);
        }

        if ($store_id = I('get.store_id/d')) {
            $where['a.store_id'] = ['eq', $store_id];
        };
        if ($is_pay = I('get.is_pay/d')) {
            if ($is_pay == 2) {
                $where['a.pay_status'] = ['eq', 0];
            } elseif ($is_pay == 1) {
                $where['a.pay_status'] = ['eq', 1];
            }
        }

        if (I('add_time_begin')) {
            $begin = strtotime(str_replace('+',' ',I('add_time_begin')));
            $end   = strtotime(str_replace('+',' ',I('add_time_end')));
        }
        if($begin && $end){
            $where['a.create_time'] = array('between',"$begin,$end");
        }
		$key_word_type = I('get.key_word_type/d',1);
        if ($key_word = I('get.key_word/s')) {
			if($key_word_type == 1) {
				$where['a.paid_sn'] = ['like', "%{$key_word}%"];
			} else if($key_word_type == 2) {
				$where['a.user_id'] = $key_word;
			} else {
				$where['a.staff_id'] = $key_word;
			}
        }

        $export = I('get.export/d');
        if ($export == 1) {
            $p = 0;
            $size = 10000;
            //$time = date("Y-m-d H:i:s", strtotime("-7 day"));
        } else {
            //$time = date("Y-m-d H:i:s", strtotime("-30 day"));
        }
        // $where['a.id']    =   ['gt',2699];
        $list = M($table_name)->where($where)->alias('a')
            ->field('a.*,staff.real_name staff_name,tgy.real_name tgy_name,store.cname store_name,company.cname company_name,u.nickname')
            ->join('staff staff', 'staff.id = a.staff_id','left')
            ->join('staff tgy','tgy.id = a.tgy_id ','left')
            ->join('company store', 'store.cid = a.store_id','left')
            ->join('company company', 'company.cid = a.company_id','left')
            ->join('users u','u.user_id = a.user_id','left')
            ->order("id desc")->page("$p,$size")->select();
        // echo M($table_name)->getlastsql();
        // die;

        if ($export == 1) {
            $this->company_export($list);
        }
        $count = M($table_name)->where($where)->alias('a')->count();
        $pager = new Page($count, $size);
        $this->assign('list', $list);
        $this->assign('pager', $pager);
        $this->assign('add_time_begin', str_replace('+',' ',I('add_time_begin')));
        $this->assign('add_time_end', str_replace('+',' ',I('add_time_end')));
		return $this->fetch('offline_detail');
    }

    function company_export($list){
        $strTable = '<table width="1000" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">订单ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">员工ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">员工姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;">用户ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;">用户昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单编码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推广员金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推广员</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">员工</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">子公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推荐子公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">剩余</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">赠送米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店结余</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">公司结余</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属实体店</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属子公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">下单时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否支付</td>';
        $strTable .= '</tr>';

        if (is_array($list)) {
            foreach ($list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['staff_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['staff_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['paid_sn'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['tgy_money'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['tgy_name'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['staff_money'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['store_money_sum'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['company_money'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['referee_company_money'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['surplus'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['return_midou'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['store_money'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['dby_money'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['store_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['company_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . date("Y-m-d H:i:s", $val['create_time']) . '</td>';
                $pay_status = ($val['pay_status'] == 1) ? ('是') : ('否');
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $pay_status . '</td>';
                
                $strTable .= '</tr>';
            }
        }
        
        $strTable .= '</table>';
        downloadExcel($strTable, '新版冗余线下流水明细');
        adminLog('导出线下流水明细');
        exit();
    }

    



}