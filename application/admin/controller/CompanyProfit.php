<?php
/**
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
use app\admin\model\StaffModel;

class CompanyProfit extends Base {

    public function _initialize() {
        parent::_initialize(); 
    }

    public function index(){
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $export = I('export');
        if($company_id = I('get.company_id/d')){
            $map['company_id']    =   ['eq',$company_id];
            $store_list = TK_get_company_store($company_id);
            $this->assign('store_list',$store_list);
            $where['member.parent_id'] =   ['eq',$company_id];
        }
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);

        if($store_id = I('get.store_id/d')){
            $map['store_id']  =   ['eq',$store_id];
            $where['member.parent_id'] =   ['eq',$store_id];
        }
        if($t = I('get.t/d')){
            $map['type']  =   ['eq',1];
        }
        if($key_word = I('get.key_word/s')){
            $serchtype = I('serchtype/d');
            switch ($serchtype) {
                case 2:
                    $where['order_sn'] = $map['order_sn']    =   ['eq',$key_word];
                    break;
                case 3:
                    $where['paid_id'] = $map['paid_id']    =   ['eq',$key_word];
                    break;
                case 4:
                    $where['pay_id'] = $map['pay_id']    =   ['eq',$key_word];
                    break;
                case 5:
                    $where['order_sn'] =  $map['order_sn']    =   ['eq',$key_word];
                    break;
                case 6:
                    $where['phone'] = $map['phone']    =   ['eq',$key_word];
                    break;
                case 7:
                    $where['real_name'] = $map['real_name']    =   ['eq',$key_word];
                    break;
                default:
                    $map['real_name|phone'] = $where['real_name|phone']   =   ['eq',$key_word];
                    break;
            }
        }
        $is_staff = I('get.is_staff',1);
        if($export == 1){
            $p = 0;
            $size = 10000;
        }

        if($is_staff == 1){
            //员工 推广员流水
            $list = M("staff_commission")->alias('log')
                    ->where($map)
                    ->field('log.id,log.create_time,log.info,log.money money,uname,real_name,company.cname as company_name,store.cname as store_name')
                    ->join('__STAFF__ staff','staff.id = staff_id','left')
                    ->join('__COMPANY__ company','staff.company_id = company.cid','left')
                    ->join('__COMPANY__ store','staff.store_id = store.cid','left')
                    ->order("id desc")
                    ->page("$p,$size")
                    ->select();
            $count = M("staff_commission")->alias('log')
                    ->where($map)
                    ->join('__STAFF__ staff','staff.id = staff_id','left')
                    ->join('__COMPANY__ company','staff.company_id = company.cid','left')
                    ->join('__COMPANY__ store','staff.store_id = store.cid','left')->count();

        }else{
            $member_where = $where;
            if(isset($member_where['info'])){
                unset($member_where['info']);
            }
            if(isset($member_where['order_sn'])){
                unset($member_where['order_sn']);
            }
            $member_list = M('company_member')->alias('member')->where($member_where)->field('id,real_name')->cache(true)->select();
            $this->assign('member_list',$member_list);
            if($member_id = I('get.member_id/d')){
                $where['member_id'] =   ['eq',$member_id];
            }

            $list = M("member_commission")->alias('log')
                    ->where($where)
                    ->field('log.id,log.create_time,log.info,log.money money,uname,real_name,company.cname as company_name')
                    ->join('__COMPANY_MEMBER__ member','member.id = member_id','left')
                    ->join('__COMPANY__ company','member.parent_id = company.cid','left')
                    ->order("id desc")
                    ->page("$p,$size")
                    ->select();

            $count = M("member_commission")->alias('log')
                    ->where($where)
                    ->join('__COMPANY_MEMBER__ member','member.id = member_id','left')
                    ->join('__COMPANY__ company','member.parent_id = company.cid','left')
                    ->count();
        }

        if($export == 1){
            $strTable ='<table width="1000" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">真实姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">流水金额</td>';
            if($is_staff == 1){
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">隶属公司</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店</td>';
            }else{
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">隶属上级</td>';
            }
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">备注</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">流水日期</td>';
            $strTable .= '</tr>';
            
            if(is_array($list)){
                foreach ($list as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['real_name'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['money'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'. $val['company_name'] .' </td>';
                    if($is_staff == 1){
                        
                        $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['store_name'].'</td>';
                    }
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">'.$val['info'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['create_time']).'</td>';                
                    $strTable .= '</tr>';
                }
                
            }
            $strTable .='</table>';
            downloadExcel($strTable,'流水管理');
            exit();
        }
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);    
        return $this->fetch('Index');
    }

    function ajax_get_member(){
        if($parent_id = I('get.parent_id')){
            $where['parent_id'] =   ['eq',$parent_id];
            $res['status']  =   1;
            $res['list'] = db("company_member")->field('id,real_name')->cache(true)->where($where)->select();
            $this->ajaxReturn($res);
        }
    }


    function del(){
        /*if($id = I('get.id/d')){
            if(M('member_account_log')->delete($id)){
                $this->success('删除成功！',$this->indexUrl);
            }else{
                $this->error('删除失败！');
            }
        }else{
            $this->error('非法操作');
        }*/
    }



    /*根据公司ID获取该公司下所有用户*/
    function getTop(){
        if($top_id = I('get.top_id/d')){
            $map['top_id'] = ['eq',$top_id];
            if($id = I('get.id/d')){
                $map['id']  =   ['neq',$id];
            } 
            $map['rid'] =   ['eq',0];
            if($list = $this->model->field('id,uname,real_name')->where($map)->select()){
                $data['status'] =   1;
                $data['list']   =   $list;
            }else{
                $data['status'] =   0;
            }
            if(Request::instance()->isAjax()){
                $this->ajaxReturn($data);
            }else{
                return $data;
            }
        }
    }


    /*冻结员工*/

    /*
    冻结红包
    作者：王文凯
    2018年4月16日10:47:28
    */

    function is_lock(){
        if($id = I('get.id/d')){
            $status = I('get.rstatus/d');
            switch ($status) {
                case 0:
                    $save_data['is_lock'] = 1;
                    break;
                case 1:
                    $save_data['is_lock'] = 0;
            }
            $r = $this->model->where("id = {$id}")->save($save_data);
            if($r){
                $data['status'] =   1;
                $data['save_status']    =   $save_data['is_lock'];
                $data['msg']    =   '锁定成功！';
            }else{
                $data['status'] =   1;
                $data['msg']    =   '锁定失败！';
            }
            $this->ajaxReturn($data);
        }
    }



}