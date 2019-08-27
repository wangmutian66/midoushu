<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\admin\controller;
use app\admin\logic\SuppliersLogic;
use app\admin\logic\OrderLogic;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use Symfony\Component\Yaml\Tests\DumperTest;
use think\AjaxPage;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
use think\Loader;
use app\admin\controller\Character;

class Suppliers extends Base {

    public  $order_status;
    public  $pay_status;
    public  $shipping_status;
    public  $refuse_status;
    public  $js_status;

    //   var $is_red;
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        C('TOKEN_ON',false); // 关闭表单令牌验证
        $this->order_status    = C('ORDER_STATUS');
        $this->pay_status      = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        $this->refuse_status   = C('REFUSE_STATUS');
        $this->js_status       = C('JS_STATUS');

        // 订单 支付 发货状态
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);
        $this->assign('refuse_status',$this->refuse_status);
        $this->assign('js_status',$this->js_status);
    }

    public function index(){
        return $this->fetch();
    }

    /**
     * 供货商申请列表
     */
    public function ajaxindex(){
        // 搜索条件
        $condition = array();
        $conditions = array();
        I('suppliers_phone') ? $condition['suppliers_phone'] = I('suppliers_phone') : false;
        I('suppliers_name')  ? $condition['suppliers_name']  = I('suppliers_name')  : false;
        // if( I('search_key') ){
        //     $conditions['suppliers_phone'] = I('search_key');
        //     $condition2['suppliers_name'] = I('search_key');
        // }
        //2018-09-26 李鑫 修改模糊查询
        if( I('search_key') ){
            $conditions['suppliers_phone'] = I('search_key');
        }
        $searchkey=I('search_key');
        if($searchkey){
            $condition['suppliers_phone'] = ['like',"%$searchkey%"];
            $condition2['suppliers_name'] = ['like',"%$searchkey%"];
        }
        //修改结束
        $order    = array();
        $order_by = $_REQUEST['order_by'];
        $sort     = $_REQUEST['sort'];
        $order['order_by'] = $order_by ? $order_by : 'suppliers_id';
        $order['sort']     = $sort     ? $sort     : 'desc';
        $sort_order        = $order['order_by'].' '.$order['sort'];
               
        $model = M('suppliers');
        $count = $model->where('is_check','<','3')->where($condition)->count();
        $Page  = new AjaxPage($count,10);
        //  搜索条件下 分页赋值
        foreach($conditions as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        $supplierList = $model->where('is_check','<','3')->where($condition)->whereOr($condition2)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();

        $show = $Page->show();
        $this->assign('supplierList',$supplierList);
        $this->assign('level',M('suppliers_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }


    /**
     * 供应商列表
     */
    public function supplierList()
    {
        return $this->fetch();
    }

    public function ajaxsupplierList()
    {
        // 搜索条件
        $condition = array();
        $conditions = array();
        I('suppliers_phone') ? $condition['suppliers_phone'] = I('suppliers_phone') : false;
        I('suppliers_name')  ? $condition['suppliers_name']  = I('suppliers_name')  : false;

        // if( I('search_key') ){
        //     $condition['suppliers_phone'] = I('search_key');
        //     $condition2['suppliers_name'] = I('search_key');
        // }
         //2018-09-26 李鑫 修改模糊查询
        if( I('search_key') ){
            $conditions['suppliers_phone'] = I('search_key');
        }
        $searchkey=I('search_key');
        if($searchkey){
            $condition['suppliers_phone'] = ['like',"%$searchkey%"];
            $condition2['suppliers_name'] = ['like',"%$searchkey%"];
        }
        //修改结束
        $order    = array();
        $order_by = $_REQUEST['order_by'];
        $sort     = $_REQUEST['sort'];
        $order['order_by'] = $order_by ? $order_by : 'suppliers_id';
        $order['sort']     = $sort     ? $sort     : 'desc';
        $sort_order        = $order['order_by'].' '.$order['sort'];
               
        $model = M('suppliers');
        $count = $model->where('is_check','=','3')->where($condition)->count();
        $Page  = new AjaxPage($count,10);
        //  搜索条件下 分页赋值
        foreach($conditions as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        $supplier_obj = new SuppliersLogic();
        $supplierList = $model->where('is_check','=','3')->where($condition)->whereOr($condition2)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();
        foreach ($supplierList as $k => $val) {
            $supplierList[$k]['salemoney'] = $supplier_obj->getSalemoney($val['suppliers_id']);
        }

        $show = $Page->show();
        $this->assign('supplierList',$supplierList);
        $this->assign('level',M('suppliers_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    
    //添加供货商
    public function add_suppliers(){
        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();

    	if(IS_POST){
    		$data = I('post.');
            $buchong_img = serialize(I('buchong/a')); // 补充资质
            $data['buchong']  = $buchong_img;

            $chat_group_id = M('chat_group')->where(['is_default'=>'1'])->value('id');
            $data['chat_group_id'] = $chat_group_id;

			$supplier_obj = new SuppliersLogic();
			$res = $supplier_obj->addSupplier($data);
			if($res['status'] == 1){
                adminLog('添加供货商');
				$this->success('添加成功',U('Suppliers/index'));exit;
			}else{
				$this->error('添加失败,'.$res['msg'],U('Suppliers/index'));
			}
    	}

        $admin = M('admin')->field('admin_id,user_name')->select();
        $this->assign('admin', $admin);
        $levelList = M('suppliers_level')->order('level_id')->select();
        $this->assign('levelList', $levelList);

        $this->assign('province',$province);
        $this->assign('city',$city);
    	return $this->fetch();
    }

    /**
     * 供货商详细信息查看
     */
    public function detail(){
        $supplierid = I('get.id');
        $supplier   = D('suppliers')->where(array('suppliers_id'=>$supplierid))->find();
        if(!$supplier)
            exit($this->error('供货商不存在'));

        $supplier['buchongImages'] = unserialize($supplier['buchong']); // 晒单图片

        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取城市
        $city =  M('region')->where(array('parent_id'=>$supplier['province_id'],'level'=>2))->select();

        if(IS_POST){
            //  供货商信息编辑
            $suppliers_password  = I('post.suppliers_password');
            $suppliers_password2 = I('post.suppliers_password2');
            if($suppliers_password != '' && $suppliers_password != $suppliers_password2){
                exit($this->error('两次输入密码不同'));
            }
            if($suppliers_password == '' && $suppliers_password2 == ''){
                unset($_POST['suppliers_password']);
            }else{
                $_POST['suppliers_password'] = encrypt($_POST['suppliers_password']);
            }          
            
            if(!empty($_POST['suppliers_phone']))
            {   $suppliers_phone = trim($_POST['suppliers_phone']);
                $c = M('suppliers')->where("suppliers_id != $supplierid and suppliers_phone = '$suppliers_phone'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }    

            $buchong_img = serialize(I('buchong/a')); // 补充资质
            $_POST['buchong']  = $buchong_img;        
            
            $row = M('suppliers')->where(array('suppliers_id'=>$supplierid))->save($_POST);
            adminLog('修改供货商信息');
            if($row)
                exit($this->success('修改成功'));

            exit($this->error('未作内容修改或修改失败'));
        }

        $group = M('chat_group')->field('id,name')->select();
        $this->assign('group',$group);
        $admin = M('admin')->field('admin_id,user_name')->select();
        $this->assign('admin', $admin);
        $levelList = M('suppliers_level')->order('level_id')->select();
        $this->assign('levelList', $levelList);
 
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('supplier',$supplier);
        return $this->fetch();
    }



    public function detail_zizhi(){
        $supplierid = I('get.id');
        $supplier   = D('suppliers')->where(array('suppliers_id'=>$supplierid))->find();
        if(!$supplier)
            exit($this->error('供货商不存在'));

        $supplier['buchongImages'] = unserialize($supplier['buchong']); // 补充资质
        
        if(IS_POST){
            header("Content-type: text/html; charset=utf-8"); 
            $files = array($supplier['zhizhao'], $supplier['organization_code_electronic']);
            print_r($files);
        }
        $this->assign('supplier',$supplier);
        return $this->fetch();
    }

    /**
    *图片打包下载
    *wuchaoqun
    *2018.11.06
    */
    public function imagedown(){
        $supplierid = I('get.id');
        $supplier   = D('suppliers')->where(array('suppliers_id'=>$supplierid))->find();
        if(!$supplier)
            exit($this->error('供货商不存在'));
        $supplier['buchongImages'] = unserialize($supplier['buchong']); // 补充资质
        $imgs = array();
        $imgs['zhizhao'] = $supplier['zhizhao'];
        $imgs['bank_licence_electronic'] = $supplier['bank_licence_electronic'];
        $imgs['organization_code_electronic'] = $supplier['bank_licence_electronic'];
        $imgs['general_taxpayer'] = $supplier['general_taxpayer'];
        $imgs['fr_idcard_front'] = $supplier['fr_idcard_front'];
        $imgs['operator_idcard_front'] = $supplier['operator_idcard_front'];
        $imgs['related_product_certification'] = $supplier['related_product_certification'];
        $imgs['customs_declaration'] = $supplier['customs_declaration'];
        $imgs['customs_quarantine_certificate'] = $supplier['customs_quarantine_certificate'];
        $imgs['food_production_license'] = $supplier['food_production_license'];
        $imgs['food_circulation_permit'] = $supplier['food_circulation_permit'];
        $imgs['trademark_registration'] = $supplier['trademark_registration'];
        $imgs['health_food_hygiene_license'] = $supplier['health_food_hygiene_license'];
        $imgs['food_business_license'] = $supplier['food_business_license'];
        $imgs['liquor_circulation_registration'] = $supplier['liquor_circulation_registration'];
        $imgs['enterprise_credit_information_img'] = $supplier['enterprise_credit_information_img'];
        $imgs['formal_authorization'] = $supplier['formal_authorization'];
        $imgs['enterprise_certificate'] = $supplier['enterprise_certificate'];
        $imgs['national_certification_certificate'] = $supplier['national_certification_certificate'];
        $imgs['food_circulation_permit'] = $supplier['food_circulation_permit'];
        $imgs['food_circulation_permit'] = $supplier['food_circulation_permit'];
        $imgs['food_circulation_permit'] = $supplier['food_circulation_permit'];
        if(count($supplier['buchongImages']) > 1){
            foreach($supplier['buchongImages'] as $t=>$j){
                $imgs['buchongImages'.$t] = $j;
            }
        }else{
            $imgs['buchongImages'] = ROOT_PATH.$supplier['buchongImages'];  
        }
        array_pop($imgs);
        $html = "<!DOCTYPE html><html><head><title></title></head><body><form id='form21' action='/imgdown.php' method='post'>";
        foreach ($imgs as $key => $value) {
            if($value != ''){
                if(file_exists(ROOT_PATH.$v)){
                   $html .= "<input type='text' name='{$key}' value='{$value}'>";
                }
            }
        }
        $html .="<input type='submit'><script>var form = document.getElementById('form21');form.submit();</script>";
        $html .= "</form></body></html>";
        echo $html;
        //打包下载实例操作过程
    }



    public function export_suppliers(){
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">供货商ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">供应商名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供应商等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供应商联系人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供应商电话</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所在地区</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供应商描述</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">营业执照号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">组织机构代码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">法人身份证号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运营者身份证号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商户类型</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">审核状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">审核意见</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">冻结供货商</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请日期</td>';
        $strTable .= '</tr>';
        $count = M('suppliers')->count();
        $p = ceil($count/5000);
        for($i=0;$i<$p;$i++){
            $start = $i*5000;
            $end = ($i+1)*5000;
            $suppliersList = M('suppliers')->order('suppliers_id')->limit($start.','.$end)->select();
            if(is_array($suppliersList)){
                foreach($suppliersList as $k=>$val){
                    $suppliers_level = M('suppliers_level')->field('level_id,level_name')->find($val['suppliers_level']);
                    $address = getTotalAddress($val['province_id'],$val['city_id']);
                    if($val['suppliers_type'] == 1) $suppliers_type = '一般企业'; else $suppliers_type = '食品企业'; 
                    if($val['is_check'] == 3) $is_check = '审核通过'; else if($val['is_check'] == 2) $is_check = '审核未通过'; else if($val['is_check'] == 1) $is_check = '审核中'; else $suppliers_type = '未审核'; 
                    if($val['status'] == 1) $status = '冻结'; else $suppliers_type = '正常';
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['suppliers_id'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['suppliers_name'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$suppliers_level['level_name'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['suppliers_contacts'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['suppliers_phone'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$address.'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['suppliers_desc'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'."'".$val['business_licence_number'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'."'".$val['organization_code'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'."'".$val['id_card'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'."'".$val['operator_id_card_no'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$suppliers_type.' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_check.' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['supplier_remark'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$status.' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['add_time']).'</td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .='</table>';
        adminLog('导出供货商表格');
        downloadExcel($strTable,'suppliers_'.$i);
        exit();
    }

    /**
     * 删除供货商
     */
    public function delete(){
        $supplierid = I('get.id');
        $row = M('suppliers')->where(array('suppliers_id'=>$supplierid))->delete();
        if($row){
            adminLog('删除供货商');
            $this->success('成功删除供货商');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 删除供货商
     */
    public function ajax_delete(){
        $supplierid = I('id');
        if($supplierid){
            $row = M('suppliers')->where(array('suppliers_id'=>$supplierid))->delete();
            if($row !== false){
                adminLog('删除供货商');
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            }else{
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        }else{
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }


    /**
     * 供货商商品订单 
     */
    
    public function suppliers_order(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end   = date('Y/m/d',strtotime('+1 days'));  
        $this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }

    /**
     * 供货商商品订单列表 
     */
    
    public function ajax_suppliers_order(){
        $orderLogic = new OrderLogic();       
        $timegap    = I('timegap');
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
        }else{
            //@new 新后台UI参数
            $begin = strtotime(I('add_time_begin'));
            $end   = strtotime(I('add_time_end'));
        }
        
        // 搜索条件
        $condition = array();
        $keyType   = I("keytype");
        $keywords  = I('keywords','','trim');
        
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;

        if($begin && $end){
            $condition['add_time'] = array('between',"$begin,$end");
        }
        $condition['order_prom_type'] = array('lt',5);
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        
        I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;
        I('pay_status1')  != '' ? $condition['pay_status']   = I('pay_status1')  : false;
        I('pay_status')   != '' ? $condition['pay_status']   = I('pay_status')   : false;
        I('pay_code')     != '' ? $condition['pay_code']     = I('pay_code')     : false;
        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;
        I('user_id') ? $condition['user_id'] = trim(I('user_id')) : false;
        I('suppliers_id') ? $condition['suppliers_id'] = trim(I('suppliers_id')) : false;   // 供货商ID
        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('order')->where($condition)->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }


    /**
     * 订单操作
     * @param $id
     */
    public function order_action(){     
        $orderLogic = new OrderLogic();
        $action = I('get.type');
        $order_id = I('get.order_id');
        if($action && $order_id){
            if($action !=='pay'){
                $convert_action= C('CONVERT_ACTION')["$action"];
                $res = $orderLogic->orderActionLog($order_id,$convert_action,I('note'));
            }
             $a = $orderLogic->orderProcessHandle($order_id,$action,array('note'=>I('note'),'admin_id'=>0));
             if($res !== false && $a !== false){
                 if ($action == 'remove') {
                     exit(json_encode(array('status' => 1, 'msg' => '操作成功', 'data' => array('url' => U('admin/order/index')))));
                 }
                exit(json_encode(array('status' => 1,'msg' => '操作成功')));
             }else{
                 if ($action == 'remove') {
                     exit(json_encode(array('status' => 0, 'msg' => '操作失败', 'data' => array('url' => U('admin/order/index')))));
                 }
                exit(json_encode(array('status' => 0,'msg' => '操作失败')));
             }
        }else{
            $this->error('参数错误',U('Admin/Order/detail',array('order_id'=>$order_id)));
        }
    }

    /**
     * 供货商账户资金记录
     */
    public function suppliers_account_log(){
        $suppliers_id = I('get.id');
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
     * 供货商账户资金调节
     */
    public function suppliers_account_edit(){
        $suppliers_id = I('suppliers_id'); // 供货商ID
        if(!$suppliers_id > 0) $this->ajaxReturn(['status'=>0,'msg'=>"参数有误"]); // 判断有效参数
        // 供货商信息
        $suppliers = M('suppliers')->field('suppliers_id,suppliers_money,frozen_money,pay_points,status')->where('suppliers_id',$suppliers_id)->find(); 
        // 提交数据
        if(IS_POST){
            $desc = I('post.desc');
            if(!$desc)
                $this->ajaxReturn(['status'=>0,'msg'=>"请填写操作说明"]);
            //加减用户资金
            $m_op_type = I('post.money_act_type'); // 金额变更方式
            $suppliers_money = I('post.suppliers_money/f'); // 变更数额
            $suppliers_money =  $m_op_type ? $suppliers_money : 0-$suppliers_money; // 判断加减
            //加减用户积分
            /*$p_op_type = I('post.point_act_type'); // 积分变更方式
            $pay_points = I('post.pay_points/d');  // 积分变更金额
            $pay_points =  $p_op_type ? $pay_points : 0-$pay_points;  // 判断加减*/
            $pay_points = 0;
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type'); // 冻结资金变更方式
            $revision_frozen_money = I('post.frozen_money/f');  // 冻结资金变更金额
            if( $revision_frozen_money != 0){    //有加减冻结资金的时候
                $frozen_money =  $f_op_type ? $revision_frozen_money : 0-$revision_frozen_money; // 判断冻结加减
                $frozen_money = $suppliers['frozen_money']+$frozen_money;    //计算用户被冻结的资金
                if($f_op_type==1 and $revision_frozen_money > $suppliers['suppliers_money'])
                {
                    $this->ajaxReturn(['status'=>0,'msg'=>"用户剩余资金不足！！"]);
                }
                if($f_op_type==0 and $revision_frozen_money > $suppliers['frozen_money'])
                {
                    $this->ajaxReturn(['status'=>0,'msg'=>"冻结的资金不足！！"]);
                }
                $suppliers_money = $f_op_type ? 0-$revision_frozen_money : $revision_frozen_money ;    //计算用户剩余资金
                M('suppliers')->where('suppliers_id',$suppliers_id)->update(['frozen_money' => $frozen_money]);
            }
            if(suppliers_accountLog($suppliers_id,$suppliers_money,$pay_points,$desc,0))
            {
                adminLog('供货商账户资金调节');
                $this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U("Admin/Suppliers/suppliers_account_log",array('id'=>$suppliers_id))]);
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败"]);
            }
            exit;
        }
        $this->assign('suppliers_id',$suppliers_id);
        $this->assign('suppliers',$suppliers);
        return $this->fetch();
    }
    
    //  充值
    public function recharge(){
    	$timegap = urldecode(I('timegap'));
    	$nickname = I('nickname');
    	$map = array();
    	if($timegap){
    		$gap = explode(',', $timegap);
    		$begin = $gap[0];
    		$end = $gap[1];
    		$map['ctime'] = array('between',array(strtotime($begin),strtotime($end)));
    	}
    	if($nickname){
    		$map['nickname'] = array('like',"%$nickname%");
    	}  	
    	$count = M('recharge')->where($map)->count();
    	$page = new Page($count);
    	$lists  = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow.','.$page->listRows)->select();
    	$this->assign('page',$page->show());
        $this->assign('pager',$page);
    	$this->assign('lists',$lists);
    	return $this->fetch();
    }


    //供货商等级
    public function level(){
    	$act = I('get.act','add');
    	$this->assign('act',$act);
    	$level_id = I('get.level_id');
    	if($level_id){
    		$level_info = D('suppliers_level')->where('level_id='.$level_id)->find();
    		$this->assign('info',$level_info);
    	}
    	return $this->fetch();
    }
    
    public function levelList(){
    	$Ad =  M('suppliers_level');
        $p = $this->request->param('p');
    	$res = $Ad->order('level_id')->page($p.',10')->select();
    	if($res){
    		foreach ($res as $val){
    			$list[] = $val;
    		}
    	}
    	$this->assign('list',$list);
    	$count = $Ad->count();
    	$Page = new Page($count,10);
    	$show = $Page->show();
    	$this->assign('page',$show);
    	return $this->fetch();
    }

    /**
     * 供货商等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $suppliersLevelValidate = Loader::validate('SuppliersLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$suppliersLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $suppliersLevelValidate->getError()];
            } else {
                $r = D('suppliers_level')->add($data);
                if ($r !== false) {
                    adminLog('添加供货商等级');
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $suppliersLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$suppliersLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $suppliersLevelValidate->getError()];
            } else {
                $r = D('suppliers_level')->where('level_id=' . $data['level_id'])->save($data);
                if ($r !== false) {
                    adminLog('编辑供货商等级');
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $suppliersLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('suppliers_level')->where('level_id=' . $data['level_id'])->delete();
            if ($r !== false) {
                adminLog('删除供货商等级');
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索供应商名
     */
    public function search_suppliers()
    {
        $search_key = trim(I('search_key'));        
        $list = M('suppliers')->where(" suppliers_phone like '%$search_key%' ")->select();        
        foreach($list as $key => $val)
        {
            echo "<option value='{$val['suppliers_id']}'>{$val['suppliers_phone']}</option>";
        }             
        exit;
    }


    // 结算
    public function settlement(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end   = date('Y/m/d',strtotime('+1 days'));    
        $this->assign('timegap',$begin.'-'.$end);

        $js_time_start = tpCache('settlement.supplier_jstime_start');
        $js_time_end   = tpCache('settlement.supplier_jstime_end');
        $this->assign('js_time_start',$js_time_start);
        $this->assign('js_time_end',$js_time_end);

        $suppliersList = M("suppliers")->where('')->select();
        foreach ($suppliersList as $k => $val) {
            $str = "";
            switch ($val['is_check']) {
                case '0':
                    $str = "(未审核)";
                    break;
                case '1':
                    $str = "(审核中)";
                    break;
                case '2':
                    $str = "(审核未通过)";
                    break;
                case '3':
                    if($val['status'] == 0)
                        $str = "(已冻结)";
                    if($val['status'] == 1)
                        $str = "(营业)";
                    else
                        $str = "(审核通过)";
                    break;
                default:
                    $str = "(未审核)";
                    break;
            }
            $val['suppliers_name'] = $val['suppliers_name'].$str;
            $suppliersList[$k] = $val;
        }
		$character_obj = new Character();
		$suppliersList = $character_obj->groupByInitials($suppliersList, 'suppliers_name');#按首字母排序 by 刘姝含 2018/10/17 星期三
        $this->assign('suppliersList', $suppliersList);

        return $this->fetch();
    }

    public function ajaxsettlement(){


        $this->js_status = C('JS_STATUS');
        $this->assign('js_status',$this->js_status);

        //搜索条件
        $order_sn       = I('order_sn');
        $timegap        = I('timegap');
        $add_time_begin = I('add_time_begin');
        $add_time_end   = I('add_time_end');
        $status         = I('status');
        $where          = array();//搜索条件
        //2018-09-26 李鑫  修改供货商申请结算按订单号模糊搜素
        if($order_sn){
            $where['order_sn'] = array('like',"%$order_sn%");
        }
        //--修改结束
        if($status > -1){
            $where['status'] = $status;
        } 
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        if($add_time_begin && $add_time_end){
            //@new 新后台UI参数
            $begin = strtotime($add_time_begin);
            $end   = strtotime($add_time_end);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        I('suppliers_id') ? $where['suppliers_id']  = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $where['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09

        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('order_settlement')->where($where)->count();
        $Page  = new AjaxPage($count,20);
        $show  = $Page->show();
        //获取订单列表
        $orderList = M('order_settlement')->where($where)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();

        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();

    }

    // 结算操作
    public function do_js(){

        //搜索条件
        $order_sn     = I('order_sn');
        $timegap      = I('timegap');
        $order_status = I('order_status');
        $order_ids    = I('order_ids');
        $suppliers_id = I('suppliers_id');
        $where        = array();//搜索条件
        $where['status'] = 1;

        if($consignee){
            $where['consignee'] = ['like','%'.$consignee.'%'];
        }
        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        if($order_status){
            $where['order_status'] = $order_status;
        }
        if($suppliers_id){
            $where['suppliers_id'] = $suppliers_id; // 供货商ID
        }
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }
        if($order_ids){
            $where['order_id'] = ['in', $order_ids];
        }else{
            $this->error('必须选择订单ID才能进行结算,否则不允许全部结算操作');
        }

        I('suppliers_id') ? $where['suppliers_id']  = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $where['suppliers_id'] = trim(I('suppliers_id2')) : false;  // 供货商ID liyi 2018.07.09
      //  dump($where);die;
        $list = M('order_settlement')->field("*,FROM_UNIXTIME(add_time,'%Y-%m-%d') as create_time")->where($where)->order('rec_id')->select();

        if(!$list) $this->error('暂无可结算订单');

        foreach ($list as $k => $val) {
            $where2['order_id']     = $val['order_id'];
            $where2['order_sn']     = $val['order_sn'];
            $where2['suppliers_id'] = $val['suppliers_id'];
            $order_info = M('order')->where($where2)->find();
            if($order_info){
                $suppliers_money = $order_info['shipping_price'] + $order_info['tk_cost_price'];
                $desc            = '订单：'.$order_info['order_sn'].'结算';
                if(suppliers_accountLog($val['suppliers_id'],$suppliers_money,0,$desc,0)){
                    $data2['js_status'] = $data['status'] = 2;
                    $data['js_time']   = time();
                    M('order_settlement')->where('rec_id ='.$val['rec_id'])->update($data);
                    M('order')->where('order_id ='.$val['order_id'])->update($data2);
                }
                adminLog('结算订单');
            } else {
                $this->error('订单信息有误！');
            }
        }
        $this->success('结算成功！');
    }


    public function export_settlement(){

        //搜索条件
        $order_sn       = I('order_sn');
        $timegap        = I('timegap');
        $add_time_begin = I('add_time_begin');
        $add_time_end   = I('add_time_end');
        $status         = I('status');
        $where          = array();//搜索条件

        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        if($status > -1){
            $where['status'] = $status;
        } 
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        if($add_time_begin && $add_time_end){
            //@new 新后台UI参数
            $begin = strtotime($add_time_begin);
            $end   = strtotime($add_time_end);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        $rec_ids = I('rec_ids');
        if($rec_ids){
            $where['rec_id'] = ['in',$rec_ids];
        }

        I('suppliers_id') ? $where['suppliers_id']  = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $where['suppliers_id'] = trim(I('suppliers_id2')) : false;  // 供货商ID liyi 2018.07.09

        $sort_order = I('order_by','DESC').' '.I('sort');
        //获取订单列表
        $orderList = M('order_settlement')->where($where)->order($order_str)->select();
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">成本金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">结算状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">结算时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供货商</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            foreach($orderList as $k=>$val){
                if(empty($val['js_time'])) $val['js_time'] = '暂未结算'; else  $val['js_time'] = date('Y-m-d H:i:s',$val['js_time']);
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$this->js_status[$val['status']].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['add_time']).' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['js_time'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.get_suppliers_name($val['suppliers_id']).'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($order_list);
        adminLog('导出数据');
        downloadExcel($strTable,'saleTop');
        exit();

    }


    // 米豆结算
    public function settlementred(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end   = date('Y/m/d',strtotime('+1 days'));    
        $this->assign('timegap',$begin.'-'.$end);

        $js_time_start = tpCache('settlement.supplier_jstime_start');
        $js_time_end   = tpCache('settlement.supplier_jstime_end');
        $this->assign('js_time_start',$js_time_start);
        $this->assign('js_time_end',$js_time_end);

        $suppliersList = M("suppliers")->where('')->select();
        foreach ($suppliersList as $k => $val) {
            $str = "";
            switch ($val['is_check']) {
                case '0':
                    $str = "(未审核)";
                    break;
                case '1':
                    $str = "(审核中)";
                    break;
                case '2':
                    $str = "(审核未通过)";
                    break;
                case '3':
                    if($val['status'] == 0)
                        $str = "(已冻结)";
                    if($val['status'] == 1)
                        $str = "(营业)";
                    else
                        $str = "(审核通过)";
                    break;
                default:
                    $str = "(未审核)";
                    break;
            }
            $val['suppliers_name'] = $val['suppliers_name'].$str;
            $suppliersList[$k] = $val;
        }
		$character_obj = new Character();
		$suppliersList = $character_obj->groupByInitials($suppliersList, 'suppliers_name');#按首字母排序 by 刘姝含 2018/10/17 星期三
        $this->assign('suppliersList', $suppliersList);

        return $this->fetch();
    }

    public function ajaxsettlementred(){


        $this->js_status = C('JS_STATUS');
        $this->assign('js_status',$this->js_status);

        //搜索条件
        $order_sn       = I('order_sn');
        $timegap        = I('timegap');
        $add_time_begin = I('add_time_begin');
        $add_time_end   = I('add_time_end');
        $status         = I('status');
        $where          = array();//搜索条件

        //2018-09-26 李鑫  修改供货商申请结算按订单号模糊搜素
        if($order_sn){
            $where['order_sn'] = array('like',"%$order_sn%");
        }
        //--修改结束
        if($status > -1){
            $where['status'] = $status;
        } 
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        if($add_time_begin && $add_time_end){
            //@new 新后台UI参数
            $begin = strtotime($add_time_begin);
            $end   = strtotime($add_time_end);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        I('suppliers_id') ? $where['suppliers_id']  = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $where['suppliers_id'] = trim(I('suppliers_id2')) : false;  // 供货商ID liyi 2018.07.09

        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('order_red_settlement')->where($where)->count();
        $Page  = new AjaxPage($count,20);
        $show  = $Page->show();
        //获取订单列表
        $orderList = M('order_red_settlement')->where($where)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();
        foreach ($orderList as $k=>$row){
            $orderList[$k]["is_store"]=M('order_red')->where(["order_id"=>$row["order_id"]])->value("is_store");
        }
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();

    }


    // 结算操作
    public function do_js_red(){
        //搜索条件
        $order_sn     = I('order_sn');
        $timegap      = I('timegap');
        $order_status = I('order_status');
        $order_ids    = I('order_ids');
        $where        = array();//搜索条件
        $where['status'] = 1;

        if($consignee){
            $where['consignee'] = ['like','%'.$consignee.'%'];
        }
        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        if($order_status){
            $where['order_status'] = $order_status;
        }
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }
        if($order_ids){
            $where['order_id'] = ['in', $order_ids];
        }

        I('suppliers_id') ? $where['suppliers_id']  = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $where['suppliers_id'] = trim(I('suppliers_id2')) : false;  // 供货商ID liyi 2018.07.09

        $list = M('order_red_settlement')->field("*,FROM_UNIXTIME(add_time,'%Y-%m-%d') as create_time")->where($where)->order('rec_id')->select();

        if(!$list) $this->error('暂无可结算订单');

        foreach ($list as $k => $val) {
            $where2['order_id']     = $val['order_id'];
            $where2['order_sn']     = $val['order_sn'];
            $where2['suppliers_id'] = $val['suppliers_id'];
        
            $order_info = M('order_red')->where($where2)->find();
            if($order_info){
                $suppliers_money = $order_info['shipping_price'] + $order_info['tk_cost_price'];
                $desc            = '米豆订单：'.$order_info['order_sn'].'结算';

                if(suppliers_accountLog($val['suppliers_id'],$suppliers_money,0,$desc,0)){
                    $data2['js_status'] = $data['status'] = 2;
                    $data['js_time']   = time();
                    M('order_red_settlement')->where('rec_id ='.$val['rec_id'])->update($data);
                    M('order_red')->where('order_id ='.$val['order_id'])->update($data2);
                }

                adminLog('米豆区结算订单');
            } else {
                $this->error('订单信息有误！');
            } 
        }
        $this->success('结算成功！');
    }


    public function export_settlementred(){

        //搜索条件
        $order_sn       = I('order_sn');
        $timegap        = I('timegap');
        $add_time_begin = I('add_time_begin');
        $add_time_end   = I('add_time_end');
        $order_status   = I('order_status');
        $where          = array();//搜索条件

        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        if($order_status){
            $where['order_status'] = $order_status;
        }
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        if($add_time_begin && $add_time_end){
            //@new 新后台UI参数
            $begin = strtotime($add_time_begin);
            $end   = strtotime($add_time_end);
            $where['add_time'] = ['between',[$begin, $end]];
        }

        $rec_ids = I('rec_ids');
        if($rec_ids){
            $where['rec_id'] = ['in',$rec_ids];
        }

        I('suppliers_id') ? $where['suppliers_id']  = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $where['suppliers_id'] = trim(I('suppliers_id2')) : false;  // 供货商ID liyi 2018.07.09

        $sort_order = I('order_by','DESC').' '.I('sort');
        //获取订单列表
        $orderList = M('order_red_settlement')->where($where)->order($order_str)->select();
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">成本金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">结算状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">结算时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供货商</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            foreach($orderList as $k=>$val){
                if(empty($val['js_time'])) $val['js_time'] = '暂未结算'; else  $val['js_time'] = date('Y-m-d H:i:s',$val['js_time']);
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$this->js_status[$val['status']].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['add_time']).' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['js_time'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.get_suppliers_name($val['suppliers_id']).'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($order_list);
        adminLog('米豆区结算订单');
        downloadExcel($strTable,'saleTop');
        exit();

    }


    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信-供货商
     */
    public function sendMessage()
    {
        $suppliers_id_array = I('get.suppliers_id_array');
        $suppliers = array();
        if (!empty($suppliers_id_array)) {
            $suppliers = M('suppliers')->field('suppliers_id,suppliers_name')->where(array('suppliers_id' => array('IN', $suppliers_id_array)))->select();
        }
        $this->assign('suppliers',$suppliers);
        return $this->fetch();
    }

    /**
     * 发送系统消息-供货商
     * @author dyr
     * @time  2016/09/01
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');        //回调方法
        $text      = I('post.text');        //内容
        $type      = I('post.type', 0);     //个体or全体
        $admin_id  = session('admin_id');   // 管理员id
        $suppliers = I('post.suppliers/a'); //个体id
        $message = array(
            'admin_id'  => $admin_id,
            'message'   => $text,
            'category'  => 0,
            'send_time' => time(),
            'object'    => 'suppliers'
        );

        if ($type == 1) {
            //全体用户系统消息
            //还有所有供货商
            $message['type'] = 1;
            M('Message')->add($message);
        } else {
            //个体消息
            $message['type'] = 0;
            if (!empty($suppliers)) {
                $create_message_id = M('Message')->add($message);
                foreach ($suppliers as $key) {
                    M('suppliers_message')->add(array('suppliers_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $suppliers_id_array = I('get.suppliers_id_array');
        $suppliers = array();
        if (!empty($suppliers_id_array)) {
            $suppliers_where = array(
                'suppliers_id' => array('IN', $suppliers_id_array),
                'suppliers_email' => array('neq', '')
            );
            $suppliers = M('suppliers')->field('suppliers_id,suppliers_name,suppliers_email')->where($suppliers_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('suppliers', $suppliers);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $suppliers = I('post.suppliers/a');
        $suppliers_email= I('post.suppliers_email');
        if (!empty($suppliers)) {
            $suppliers_id_array = implode(',', $suppliers);
            $suppliers = M('suppliers')->field('suppliers_email')->where(array('suppliers_id' => array('IN', $suppliers_id_array)))->select();
            $to = array();
            foreach ($suppliers as $user) {
                if (check_email($user['suppliers_email'])) {
                    $to[] = $user['suppliers_email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if($suppliers_email){
            $res = send_email($suppliers_email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
    	$this->get_withdrawals_list();
        return $this->fetch();
    }
    
    public function get_withdrawals_list($status=''){
    	$suppliers_id = I('suppliers_id/d');  // 供货商ID
    	$realname     = I('realname');        // 提款账号真实姓名
    	$bank_card    = I('bank_card');       // 银行账号或支付宝账号
    	$create_time  = I('create_time');     // 申请时间
        $start_time   = I('start_time');
        $end_time     = I('end_time');

    	$create_time = str_replace("+"," ",$create_time);
    	$create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
    	$create_time3 = explode(' - ',$create_time2);
    	$this->assign('start_time',$create_time3[0]); 
    	$this->assign('end_time',$create_time3[1]);

    	if($create_time){
            $where['w.create_time'] =  array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));            
        }
        
        if($start_time && $end_time){
            $where['w.create_time'] =  array(array('gt', strtotime($start_time)), array('lt', strtotime($end_time)));
        }
    	$status = empty($status) ? I('status') : $status;  // 状态
    	if(empty($status) || $status === '0'){
    		$where['w.status'] =  array('lt',1);    
    	}
    	if($status === '0' || $status > 0) {
    		$where['w.status'] = $status;
    	}
        //2018-09-27 李鑫修改用户id模糊搜索
    	$suppliers_id && $where['u.suppliers_id'] = array('like','%'.$suppliers_id.'%');
    	$realname && $where['w.realname'] = array('like','%'.$realname.'%');
    	$bank_card && $where['w.bank_card'] = array('like','%'.$bank_card.'%');
    	$export = I('export');
    	if($export == 1){
    		$strTable ='<table width="500" border="1">';
    		$strTable .= '<tr>';
    		$strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请供货商</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
    		$strTable .= '</tr>';
    		$remittanceList = Db::name('suppliers_withdrawals')->alias('w')->field('w.*,u.suppliers_name')->join('__SUPPLIERS__ u', 'u.suppliers_id = w.suppliers_id', 'INNER')->where($where)->order("w.id desc")->select();
    		if(is_array($remittanceList)){
    			foreach($remittanceList as $k=>$val){
    				$strTable .= '<tr>';
    				$strTable .= '<td style="text-align:center;font-size:12px;">'.$val['suppliers_name'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['money'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['bank_name'].'</td>';
    				$strTable .= '<td style="vnd.ms-excel.numberformat:@">'.$val['bank_card'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['realname'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['create_time']).'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['remark'].'</td>';
    				$strTable .= '</tr>';
    			}
    		}
    		$strTable .='</table>';
    		unset($remittanceList);
    		downloadExcel($strTable,'remittance');
    		exit();
    	}
    	$count = Db::name('suppliers_withdrawals')->alias('w')->join('__SUPPLIERS__ u', 'u.suppliers_id = w.suppliers_id', 'INNER')->where($where)->count();
    	$Page  = new Page($count,20);
    	$list = Db::name('suppliers_withdrawals')->alias('w')->field('w.*,u.suppliers_name')->join('__SUPPLIERS__ u', 'u.suppliers_id = w.suppliers_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
    	$this->assign('create_time',$create_time2);
    	$show  = $Page->show();
    	$this->assign('show',$show);
    	$this->assign('list',$list);
    	$this->assign('pager',$Page);
    	C('TOKEN_ON',false);
    }
    
    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $model = M("suppliers_withdrawals");
        $model->where('id ='.$_GET['id'])->delete();
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public  function editWithdrawals(){        
       $id = I('id');
       $model = M("suppliers_withdrawals");
       $withdrawals = $model->find($id);
       $suppliers = M('suppliers')->where("suppliers_id = {$withdrawals[suppliers_id]}")->find();     
       if($suppliers['suppliers_name'])        
           $withdrawals['suppliers_name'] = $suppliers['suppliers_name'];
       elseif($suppliers['suppliers_phone'])        
           $withdrawals['suppliers_name'] = $suppliers['suppliers_phone'];            
       $this->assign('suppliers',$suppliers);
       $this->assign('data',$withdrawals);

       if(IS_POST){
            $data['remark'] = I('remark');
            $r    = M('suppliers_withdrawals')->where('id ='.$id)->update($data);
            if($r) $this->success('修改成功！');
       }

       return $this->fetch();
    }  

    /**
     *  处理供货商提现申请
     */
    public function withdrawals_update(){
    	$id     = I('id/a');
        $data['status']=$status = I('status');
    	$data['remark'] = I('remark');
        if($status == 1) $data['check_time'] = time();
        if($status != 1) $data['refuse_time'] = time();
        $lists = M('suppliers_withdrawals')->where('id in ('.implode(',', $id).')')->select();
        $r     = M('suppliers_withdrawals')->where('id in ('.implode(',', $id).')')->update($data);

    	if($r){
            if($status == 3){
                foreach ($lists as $k => $val) {
                    $suppliers = M('suppliers')->where('suppliers_id ='.$val['suppliers_id'])->find();
                    $suppliers_id = $val['suppliers_id'];
                    $money        = $val['money']+$val['taxfee'];
                    suppliers_accountLog($suppliers_id, $money, 0,'管理员拒绝供货商提现申请');
                    $up_data['frozen_money'] = -1*$money+$suppliers['frozen_money'];
                    M('suppliers')->where('suppliers_id ='.$suppliers_id)->update($up_data);                    
                }
            }
            adminLog('处理供货商提现申请');
    		$this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"),'JSON');
    	}else{
    		$this->ajaxReturn(array('status'=>0,'msg'=>"操作失败"),'JSON');
    	}  	
    }


    // 用户申请提现
    public function transfer(){
    	$id = I('selected/a');
    	if(empty($id))$this->error('请至少选择一条记录');
    	$atype = I('atype');
    	if(is_array($id)){
    		$withdrawals = M('suppliers_withdrawals')->where('id in ('.implode(',', $id).')')->select();
    	}else{
    		$withdrawals = M('suppliers_withdrawals')->where(array('id'=>$id))->select();
    	}
    	$alipay['batch_num'] = 0;
    	$alipay['batch_fee'] = 0;
    	foreach($withdrawals as $val){
    		$suppliers = M('suppliers')->where(array('suppliers_id'=>$val['suppliers_id']))->find();
            $total = $val['money']+$val['taxfee']; // 总

    		if($suppliers['frozen_money'] < $total)
            {
                //$data = array('status'=>-2,'remark'=>'账户冻结金额不足');
                //M('suppliers_withdrawals')->where(array('id'=>$val['id']))->save($data);
                $this->error('账户冻结金额不足');
                exit();
            }else{
    			$rdata = array('type'=>4,'money'=>$val['money'],'log_type_id'=>$val['id'],'suppliers_id'=>$val['suppliers_id']);
    			if($atype == 'online'){
			        header("Content-type: text/html; charset=utf-8");
                    exit("暂不支持此功能");
    			}else{
    				//suppliers_accountLog($val['suppliers_id'], ($val['money'] * -1), 0,"管理员处理供货商提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
    				$up_data['frozen_money'] = -1*$total+$suppliers['frozen_money'];
                    M('suppliers')->where('suppliers_id ='.$val['suppliers_id'])->update($up_data);
                    $r = M('suppliers_withdrawals')->where(array('id'=>$val['id']))->save(array('status'=>2,'pay_time'=>time()));
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
        adminLog('操作用户申请提现');
    	$this->success("操作成功!",U('remittance'),3);
    }
    
    /**
     *  转账汇款记录
     */
    public function remittance(){
    	$status = I('status',1);
    	$this->assign('status',$status);
    	$this->get_withdrawals_list($status);
        return $this->fetch();
    }

    /**
    *供货商供货列表
    */
    public function supplyaudit(){
        $js_time_start = tpCache('settlement.supplier_jstime_start');
        $js_time_end   = tpCache('settlement.supplier_jstime_end');
        $this->assign('js_time_start',$js_time_start);
        $this->assign('js_time_end',$js_time_end);

        $suppliersList = M("suppliers")->where('')->select();
        foreach ($suppliersList as $k => $val) {
            $str = "";
            switch ($val['is_check']) {
                case '0':
                    $str = "(未审核)";
                    break;
                case '1':
                    $str = "(审核中)";
                    break;
                case '2':
                    $str = "(审核未通过)";
                    break;
                case '3':
                    if($val['status'] == 0)
                        $str = "(已冻结)";
                    if($val['status'] == 1)
                        $str = "(营业)";
                    else
                        $str = "(审核通过)";
                    break;
                default:
                    $str = "(未审核)";
                    break;
            }
            $val['suppliers_name'] = $val['suppliers_name'].$str;
            $suppliersList[$k] = $val;
        }
        $character_obj = new Character();
        $suppliersList = $character_obj->groupByInitials($suppliersList, 'suppliers_name');#按首字母排序 by 刘姝含 2018/10/17 星期三
        $this->assign('suppliersList', $suppliersList);
        $company = M('company')->where(["parent_id"=>0])->select();
        $this->assign('company',$company);
        return $this->fetch();
    }

    /**
     * 供货商供货申请列表
     */
    public function ajaxsupplyaudit(){
        // 搜索条件
        $goods_name = trim(I('goods_name'));
        $company = I('company');
        $store_id = I('store_id');
        $suppliers_id2 = I('suppliers_id2');
        $begin = strtotime(I('add_time_begin'));
        $end   = strtotime(I('add_time_end'));
        if($goods_name){
            $condition['goods_name'] = ['like',"%$goods_name%"];
        }
        if($begin&&$end){
            $condition['sgs.create_time'] = ['between',[$begin, $end]];
        }
        if($company&&!$store_id){
            $condition['cid'] = $company;
        }
        if($store_id){
            $condition['store_id'] = $store_id;
        }
        if($suppliers_id2){
            $condition['supplier_id'] = $suppliers_id2;
        }
        $model = M('store_goods_supplices sgs');
        $count = $model->join('__GOODS_RED__ gr', 'gr.goods_id = sgs.goods_id', 'INNER')->join('company b','sgs.store_id=b.cid','LEFT')->where($condition)->count();
        $Page  = new AjaxPage($count,15);
        //  搜索条件下 分页赋值
        // foreach($conditions as $key=>$val) {
        //     $Page->parameter[$key]   =   urlencode($val);
        // }
        $supplierList = $model->field('sgs.*,gr.suppliers_id,b.cid')->join('__GOODS_RED__ gr', 'gr.goods_id = sgs.goods_id', 'INNER')->join('company b','sgs.store_id=b.cid','LEFT')->where($condition)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        // dump($supplierList);die();
        $show = $Page->show();
        $this->assign('supplierList',$supplierList);
        $this->assign('level',M('suppliers_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    /**
    *供货商供货列表supplygonghuo
    */
    public function supplygonghuo(){
        
        return $this->fetch();
    }

     /**
    *实体店铺货审核supplyapply
    * @author wuchaoqun
     * @date 2018-11-05
    */
    public function supplyapply(){
        return $this->fetch();
    }

    /**
    *实体店铺主推铺货supplyshop
    * @author wuchaoqun
     * @date 2018-11-05
    */
    public function supplytop(){
        
        return $this->fetch();
    
    }
    /**
     * 实体店铺货审核列表ajaxsupplyapply
     * * @author wuchaoqun
     * @date 2018-11-05
     */
    public function ajaxsupplyapply(){

        // 搜索条件
        $key_word = trim(I('key_word'));
        if($key_word){
            $condition['cname'] = ['like',"%$key_word%"];
        }
        $condition['sexamine'] = '1';//提交审核的审核铺货
        //$condition['zexamine'] = '0';//主推铺货为未审核
        $model = M('company');
        $count = $model->where($condition)->count();
        $Page  = new AjaxPage($count,15);
        //  搜索条件下 分页赋值
        // foreach($conditions as $key=>$val) {
        //     $Page->parameter[$key]   =   urlencode($val);
        // }
        $supplierList = $model->where($condition)->order('cid desc')->limit($Page->firstRow.','.$Page->listRows)->select();

        $show = $Page->show();
        $this->assign('supplierList',$supplierList);
        $this->assign('level',M('suppliers_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }


    /**
     * 实体店铺审核列表ajaxsupplytop
     * * @author wuchaoqun
     * @date 2018-11-05
     */
    public function ajaxsupplytop(){

         // 搜索条件
         $key_word = trim(I('key_word'));
         if($key_word){
             $condition['cname'] = ['like',"%$key_word%"];
         }
        $condition['sexamine'] = '2';//审核铺货通过
        $condition['zexamine'] = '1';//提交审核的主推铺货
        $model = M('company');
        $count = $model->where($condition)->count();
        $Page  = new AjaxPage($count,15);
        //  搜索条件下 分页赋值
        // foreach($conditions as $key=>$val) {
        //     $Page->parameter[$key]   =   urlencode($val);
        // }
        $supplierList = $model->where($condition)->order('cid desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $show = $Page->show();
        $this->assign('supplierList',$supplierList);
        $this->assign('level',M('suppliers_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    /**
     * 实体店审核弹窗
     * * @author wuchaoqun
     * @date 2018-11-05
     */
    public function is_check_company(){
        $t = I('get.t');
        if($t == 1){
            $field = 'sexamine';
        }else{
            $field = 'zexamine';
        }
        $this->assign('field',$field);
        return $this->fetch();
    }


    /**
     * 供货商供货申请列表
     */
    public function ajaxsupplygonghuo(){
        // 搜索条件
   
        //2018-09-26 李鑫 修改模糊查询
        // if( I('search_key') ){
        //     $conditions['suppliers_phone'] = I('search_key');
        // }
        // $searchkey=I('search_key');
        // if($searchkey){
        //     $condition['suppliers_phone'] = ['like',"%$searchkey%"];
        // }
    
  
//        $condition['gonghuo_examine'] = '1';
        $store_id = db('supply_goods')->where(["is_com"=>"0"])->group("store_id")->column("store_id");

        $condition=[];
        $condition["cid"] = ["in",$store_id];
        $model = M('company');
        $count = $model->where($condition)->count();
        $Page  = new AjaxPage($count,15);
        //  搜索条件下 分页赋值
        // foreach($conditions as $key=>$val) {
        //     $Page->parameter[$key]   =   urlencode($val);
        // }
        $supplierList = $model->where($condition)->order('cid desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        // dump($supplierList);die();
        $show = $Page->show();
        $this->assign('supplierList',$supplierList);
        $this->assign('level',M('suppliers_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }


     public function is_checkgonghuo(){
        $cid = I('get.cid');
        $goodid = I('get.goodid');


        $stockinit = db('company')->where(["cid"=>$cid])->find();
        $this->assign('goodid',$goodid);
        $this->assign('stockinit',$stockinit);
        return $this->fetch();
    }

    /**
     * 供货商详细信息查看
     */
    public function gonghuodetail(){
        $cid = I('get.id');
        //  获取省份
        $company = M('company')->where(array('cid'=>$cid))->find();
        $this->assign('company',$company);
        $goods = M('supply_goods sgs')
                ->join('__GOODS_RED__ gr', 'gr.goods_id = sgs.goods_id', 'INNER')
                ->where(array('store_id'=>$cid,'sgs.is_com'=>"0"))->select();

        $this->assign('id',$cid);
        $this->assign('goods',$goods);
        // dump($goods);die();
        return $this->fetch();
    }

    /**
     * [供货商供货管理 - 表格导出]
     * @author 王牧田
     * @date 2018-11-10
     */
    public function downExcelGoods(){
        $cid = I('get.id');

        $goods = M('supply_goods sgs')
            ->join('__GOODS_RED__ gr', 'gr.goods_id = sgs.goods_id', 'left')
            ->join('__SUPPLIERS__ s', 's.suppliers_id = gr.suppliers_id', 'left')
            ->where(array('store_id'=>$cid,'sgs.is_com'=>"0"))
            ->field("gr.goods_name,sgs.stock,s.suppliers_name,s.suppliers_phone")
            ->select();

        $str="<table width=\"1000\" border=\"1\">";
        $str.="<tr><td>商品名称</td><td>数量</td><td>供货商</td><td>供货电话</td></tr>";
        foreach ($goods as $g){
            $str.="<tr><td>{$g["goods_name"]}</td><td>{$g["stock"]}</td><td>{$g["suppliers_name"]}</td><td>{$g["suppliers_phone"]}</td></tr>";
        }
        $str.="</table>";

        downloadExcel($str,'供货商供货管理');
        exit();
        dump($goods);
        exit();
    }


    /**
     * [审核通过]
     * @author 王牧田
     * @date 2018-10-17
     */
    public function auditing(){
        $table    = I('table'); // 表名
        $id_name  = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field    = I('field'); // 修改哪个字段
        $value    = I('value'); // 修改字段值
        M($table)->where("$id_name = $id_value")->save(array($field=>$value)); // 根据条件保存修改的数据

        $stockinit = M($table)->where("$id_name = $id_value")->find();

        $where=[];
        $data = [];
        $where['store_id'] = $data['store_id'] = $stockinit['store_id'];
        $where['goods_id'] = $data['goods_id'] = $stockinit['goods_id'];
        $where['item_id'] = $data['item_id'] = $stockinit['item_id'];
        $data['supplier_id'] = $stockinit['supplier_id'];
        $data['stock'] = $stockinit['stock'];
        $data['is_examine'] = 1;
        $data['create_time'] = time();
        $storestock = M('store_goods_stock')->where($where)->find();
        //检查正常使用的实体店库存是否存在
        if(empty($storestock)){
            //如果不存在就去添加
            $a = db('store_goods_stock')->insert($data);
        }else{
            //如果存在就在原来的库存上做累加
            db('store_goods_stock')->where($where)->setInc('stock',$data['stock']);
        }

    }


    public function is_check(){
        $id = I('get.id');
        $stockinit = db('store_goods_supplices')->where(["id"=>$id])->find();
        $this->assign('stockinit',$stockinit);
        return $this->fetch();
    }

    public function dois_check(){
        $is_check = I('post.is_check');
        $examine_reason = I('post.examine_reason');
        $id = I('post.id');

        db('store_goods_supplices')->where(["id"=>$id])->save(["is_examine"=>$is_check,'examine_reason'=>$examine_reason]);

        if($is_check == 1){

        }

        echo "<script>parent.call_back(1)</script>";
        echo "<script>parent.window.location.reload()</script>";
        exit();
    }  

    public function dois_checkgonghuo(){
        $is_check = I('post.is_check');
        $gonghuo_reason = I('post.gonghuo_reason');
        $supplyGoodid = I('post.id');
        $cid = I('post.cid');

        $supplyGoodid = explode("-",$supplyGoodid);
        unset($supplyGoodid[count($supplyGoodid)-1]);

        $id = db('company')->where(["cid"=>$cid])->save(["gonghuo_examine"=>$is_check,'gonghuo_reason'=>$gonghuo_reason]);
        if($is_check == 3){
            db('supply_goods')->where(["id"=>["in",$supplyGoodid]])->save(["is_com"=>"2","reason"=>$gonghuo_reason]);
        }else{
            db('supply_goods')->where(["id"=>["in",$supplyGoodid]])->save(["is_com"=>"1"]);
        }


//         echo "<script>parent.call_back(1)</script>";
        //echo "<script>window.history.go(-2);</script>";
        echo "<script>parent.location.reload();</script>";
        exit();
    }

     /**
     * [铺货审核]
     * @author wuchaoqun
     * @date 2018-11-05
     */
    public function dois_check_company(){
        $field = I('post.field');
        $is_check = I('post.is_check');
        $reason = I('post.reason');
        $cid = I('post.cid');
        db('company')->where(["cid"=>$cid])->save([$field=>$is_check,'reason'=>$reason]);
        echo "<script>parent.call_back(1)</script>";
        echo "<script>parent.window.location.reload()</script>";
        exit();
    }

    /**
     * [查看物流]
     * @author 王牧田
     * @date 2018-11-08
     */
    public function showwuliu(){

        $id = I('get.id');
        $supplices = db('store_goods_supplices')->where(["id"=>$id])->field("logistics_id,Logistics_single_number")->find();
        $code = explode("_",$supplices["logistics_id"]);
        $supplices["logistics_id"] = $code[0];
        
        $shipping_code = $supplices["logistics_id"];
        $invoice_no = $supplices["Logistics_single_number"];
        //$shipping_code = 'zhongtong';
        //$invoice_no = '285413535650';
         //参数设置
//        $post_data = array();
//        $post_data["customer"] = 'DF32DE3F16EDCCC68C701048A18A8AA8';
//        $key= 'MGUIHCmb6562' ;
//        $post_data["param"] = '{"com":"'.$shipping_code.'","num":"'.$invoice_no.'"}';
//
//        $url='http://poll.kuaidi100.com/poll/query.do';
//        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
//        $post_data["sign"] = strtoupper($post_data["sign"]);
//        $o="";
//        foreach ($post_data as $k=>$v)
//        {
//            $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
//        }
//        $post_data=substr($o,0,-1);
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_HEADER, 0);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($ch, CURLOPT_URL,$url);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
//        $result = curl_exec($ch);
//        $data = str_replace("\"",'"',$result );
//        $data = json_decode($data,true);
//
//        $wuliudata = array();
//        if($data['result'] !== false){
//            $wuliudata = $data['data'];
//        }


        $wuliudata = file_get_contents("https://www.midoushu.com/auto/kuaidi/doquery/num/".$invoice_no);
        $wuliudata = json_decode($wuliudata,true);

        $this->assign('invoice_no',$invoice_no);
        $this->assign('wuliudata',$wuliudata['log']);
        return $this->fetch();
    }

    /**
     * [确认收货]
     * @author 王牧田
     * @date 2018-11-08
     */
    public function confirmgood(){
        $id = I('post.id');
        db('store_goods_supplices')->where(["id"=>$id])->save(["is_confirm"=>"1"]);
        $stockinit = db('store_goods_supplices')->where(["id"=>$id])->find();
        $where=[];
        $data = [];
        $where['store_id'] = $data['store_id'] = $stockinit['store_id'];
        $where['goods_id'] = $data['goods_id'] = $stockinit['goods_id'];
        $where['item_id'] = $data['item_id'] = $stockinit['item_id'];
        $data['supplier_id'] = $stockinit['supplier_id'];
        $data['stock'] = $stockinit['stock'];
        $data['is_examine'] = 1;
        $data['create_time'] = time();
        $storestock = M('store_goods_stock')->where($where)->find();
        //检查正常使用的实体店库存是否存在
        if(empty($storestock)){
            //如果不存在就去添加
            $a = db('store_goods_stock')->insert($data);
        }else{
            //如果存在就在原来的库存上做累加
            db('store_goods_stock')->where($where)->setInc('stock',$data['stock']);
        }

        return json(["code"=>200,"msg"=>"操作成功"]);
    }


    /**
     * [获取实体店供货明细view]
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function stock_goods_list(){
        $company = M('company')->where(["parent_id"=>0])->select();
        $this->assign('urlname','ajaxStockgoodsList');
        $this->assign('company',$company);
        return $this->fetch();
    }


    public function getstore(){
        $cid = I('post.cid');
        $storedata = M('company')->where(["parent_id"=>$cid])->select();
        return json($storedata);
    }

    /**
     * [获取实体店供货明细ajax]
     * @author 王牧田
     * @date 2018-11-20
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ajaxStockgoodsList(){

        //ajax请求
        $company = I('post.company',0);   //子公司id
        $storeid = I('post.store',0);     //实体店id
        $key_word = I('post.key_word','');  //关键词


        if($company != 0){
            $condition['b.parent_id'] = $company;
        }
        if($storeid != 0){
            $condition['b.cid'] = $storeid;
        }

        if($key_word!=""){
            $condition['g.goods_name'] = ['like',"%".$key_word."%"];
        }

        $count = Db::name('store_goods_stock')->alias('a')
            ->where($condition)
            ->join('company b','a.store_id=b.cid','LEFT')
            ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
            ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
            ->count();

        $Page  = new AjaxPage($count,10);
        $show = $Page->show();

        $goods_store = Db::name('store_goods_stock')->alias('a')
            ->where($condition)
            ->field('a.*,b.cname,c.key_name,g.goods_name,g.suppliers_id')
            ->join('company b','a.store_id=b.cid','LEFT')
            ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
            ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
            ->limit("$Page->firstRow,$Page->listRows")
            ->order("a.id desc")
            ->select();
        $this->assign('StockgoodsList',$goods_store);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }



    /**
     * [获取实体店供货明细view]
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function stock_goods_log(){
        $company = M('company')->where(["parent_id"=>0])->select();
        $this->assign('urlname','ajaxStockgoodsLog');
        $this->assign('company',$company);
        return $this->fetch("stock_goods_list");
    }

    /**
     * [获取实体店供货明细ajax]
     * @author 王牧田
     * @date 2018-11-20
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ajaxStockgoodsLog(){

        //ajax请求
        $company = I('post.company',0);   //子公司id
        $storeid = I('post.store',0);     //实体店id
        $key_word = I('post.key_word','');  //关键词


        if($company != 0){
            $condition['b.parent_id'] = $company;
        }
        if($storeid != 0){
            $condition['b.cid'] = $storeid;
        }

        if($key_word!=""){
            $condition['g.goods_name'] = ['like',"%".$key_word."%"];
        }

        $count = Db::name('store_goods_supplices')->alias('a')
            ->where($condition)
            ->join('company b','a.store_id=b.cid','LEFT')
            ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
            ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
            ->count();

        $Page  = new AjaxPage($count,10);
        $show = $Page->show();

        $goods_store = Db::name('store_goods_supplices')->alias('a')
            ->where($condition)
            ->field('a.*,b.cname,c.key_name,g.goods_name,g.suppliers_id')
            ->join('company b','a.store_id=b.cid','LEFT')
            ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
            ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
            ->limit("$Page->firstRow,$Page->listRows")
            ->order("a.id desc")
            ->select();
        $this->assign('StockgoodsList',$goods_store);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch("ajaxStockgoodsList");
    }



    /**
     *
     * @author 王牧田
     * @time  2018/11/22
     */

    public function fahuo(){
        $id = I('id');
        $info = Db::name('store_goods_supplices')->where(['id'=>$id])->find();
        $plugin = Db::name('plugin')->where(['status'=>1,'type'=>'shipping'])->select();
        $this->assign("info",$info);
        $this->assign("plugin",$plugin);
        return $this->fetch();
    }


    /**
     *
     * @author 王牧田
     * @time  2018/11/23
     */

    public function is_fahuo(){
        $id = I('id');
        $info = Db::name('store_goods_supplices')->where(['id'=>$id])->find();
        $plugin = Db::name('plugin')->where(['status'=>1,'type'=>'shipping'])->select();
        $this->assign("info",$info);
        $this->assign("plugin",$plugin);
        return $this->fetch();
    }

    /**
     *
     * @author 王牧田
     * @time  2018/11/23
     */
    public function dois_fahuo()
    {
        $call_back = I('call_back');      //回调方法
        $id = I('post.id');
        $logistics_id = I('post.logistics_id');
        $Logistics_single_number  = I('post.Logistics_single_number');
        $result = M('store_goods_supplices')->where(['id'=>$id])->save(['logistics_id'   => $logistics_id,'Logistics_single_number'=> $Logistics_single_number]);
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }
}