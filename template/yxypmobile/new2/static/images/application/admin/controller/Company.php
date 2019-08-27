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
use app\admin\logic\CompanyLogic;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use think\Loader;
use think\Cache;
use app\admin\model\CompanyModel;

class Company extends Base {

    public $table_name;
    public $pk;
    public $indexUrl;
    public function _initialize() {
        parent::_initialize();   
        $this->table_name = 'Company';
        $this->pk ='cid';
        $this->indexUrl = U('Admin/Company/Index');
    }
    
    // 子公司
    public function index(){
        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $map['parent_id'] = 0;
        if($company_id = I('get.company_id/d')){
            $map['parent_id'] = ['eq',$company_id];
            $company_info = db('company')->cache("company_{$company_id}")->find($company_id);
            $this->assign('company_info',$company_info);
        }
        $list  = M('company')->where($map)->order("{$this->pk} desc")->page("$p,$size")->select();
        $count = M('company')->where($map)->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);        
        return $this->fetch('index');
    }

    // 添加子公司
    public function add(){
        $this->assign('acts','doAdd');
        $this->assign('pk',$this->pk);
        $is_hide = 0;   //利润比是否隐藏
        if($company_id = I('company_id/d')){
            $company_info = db('company')->cache("company_{$company_id}")->find($company_id);
            $this->assign('company_info',$company_info);
        }else{
            $is_hide = 1;
        }
        
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        $this->assign('is_hide',$is_hide);
        return $this->fetch('form');
    }
    
    public function doAdd(){
        $new_model = new CompanyModel($_POST);
        $_POST['password'] = encrypt($_POST['password']);
        $_POST['level']    = 1;
        $verify_r = M('company')->where("mobile = '{$_POST['mobile']}'")->find();
        if($verify_r){
            $res['status']  =   0;
            $res['info']    =   '手机号码重复，请重新填写';
            $this->ajaxReturn($res);
        }
    //    $company_obj       = new CompanyLogic();
        if($new_model->allowField(true)->save($_POST)){
         //   $insert_id =    M('company')->getLastInsID();
        //    $company_obj->refresh_cat($insert_id);
            cache::rm('company_list');
            $res['status']  =   1;
            $res['info']    =   '新增数据成功';
            $this->ajaxReturn($res);
          #  $this->success('新增数据成功！',$this->indexUrl);
        }else{
            $res['status']  =   0;
            $res['info']    =   '新增失败';
            $this->ajaxReturn($res);
        }

    }

    // 修改实体店
    function modify(){
        if($id = I('get.id/d')){
            $item = M('company')->find($id);
            $company_list = get_company_list();
            $this->assign('company_list',$company_list);
            $this->assign('acts','doModify');
            $this->assign('pk',$this->pk);
            $this->assign('item',$item);
            if($item['parent_id'] == 0){
                $is_hide = 1;
            }else{
                $is_hide = 0;
            }
            $this->assign('is_hide',$is_hide);
            return $this->fetch('form');
        }else{
            $this->error('参数错误!');
        }
        
    }

    function doModify(){
        $new_model = new CompanyModel();
        $password  = I('post.password');
        if($password){
            $_POST['password'] = encrypt($_POST['password']);
        }else{
            unset($_POST['password']);
        }
        $cid = I('post.cid');
        $verify_r = M('company')->where("mobile = '{$_POST['mobile']}' and cid != $cid")->find();
        if($verify_r){
            $res['status']  =   0;
            $res['info']    =   '手机号码重复，请重新填写';
            $this->ajaxReturn($res);
        }
        // 过滤post数组中的非数据表字段数据
        if($new_model->allowField(true)->save($_POST,[$this->pk => $cid])){
        //    $company_obj->refresh_cat($_POST[$this->pk]);
            cache::rm('company_list');
            cache::rm("company_store_list_{$cid}");
            $res['status']  =   1;
            $res['info']    =   '更新数据成功！';
            $this->ajaxReturn($res);
        }else{
            $res['status']  =   0;
            $res['info']    =   '数据无改动，请重新修改';
            $this->ajaxReturn($res);
        }
    }

    


    // 子公司，实体店删除
    function del(){
        if($id = I('post.id/d')){
            if(M('company')->delete($id)){
                $this->success('删除成功！',$this->indexUrl);
            }else{
                $this->error('删除失败！');
            }
        }else{
            $this->error('非法操作');
        }
    }


    


    /*获取某公司下方所有实体店*/
    function ajax_get_store(){
        $company_id = I('get.company_id');
        $store_list = TK_get_company_store($company_id);
        if($store_list){
            $data['status']    =   1;
            $data['list']   =   $store_list;
        }else{
            $data['status'] =   0;
            $data['list']   =   '';
        }
        $this->ajaxReturn($data);
    }

    function ajax_get_level(){
        $company_id = I('get.company_id');
        $store_id = I('get.store_id');
        $is_staff = I('get.is_staff/s');
        if($is_staff == 'no'){
            $where['is_staff']  =   ['eq',0];
        }elseif($is_staff == 'yes'){
            $where['is_staff']  =   ['eq',1];
        }
        if($store_id) {
            $where['c_parent_id'] = ['eq',$store_id];
            $level_list = M('CompanyLevel')->where($where)->cache(true)->select();
        }elseif($company_id){
            $where['c_parent_id'] = ['eq',$company_id];
            $level_list = M('CompanyLevel')->where($where)->cache(true)->select();
        }else{
            $level_list = M('CompanyLevel')->where($where)->cache(true)->select();
        }
        if($level_list){
            $data['status']    =   1;
            $data['list']   =   $level_list;
        }else{
            $data['status'] =   0;
            $data['list']   =   '';
        }
        $this->ajaxReturn($data);
    }

    // 子公司 实体店 成员
    public function company_member(){
        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $map = array();
      
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $level_id = I('level_id') ? I('level_id') : '';

        if($company_id) $map['a.parent_id'] = $company_id;
        if($store_id) $map['a.parent_id'] = $store_id;
        if($level_id) $map["company_level"] = $level_id;

        #初始化搜索条件
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id != 0){
            $store_list = M('company')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
        }

        #初始化搜索条件结束
        $key_word = I('key_word') ? I('key_word/s') : '';
        $list = M('company_member')
              ->alias('a')
              ->field('a.*,l.lv_name,c.cname company_name')
              ->join('company_level l','a.company_level = l.id','left')
              ->join('company c','a.parent_id = c.cid')
              ->where($map)
              ->where(function($query) use ($key_word){
                if ($key_word) {
                    $query->where('a.phone',$key_word)->whereOr('a.real_name','like',"%{$key_word}%");
                }
              })
              ->page("$p,$size")
              ->select();
        $count = M('company_member')->alias('a')->where($map)->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);  
        return $this->fetch();
    }


    // 添加成员
    public function company_member_add(){
        if($company_id = I('company_id/d')){
            $map['cid']      = ['eq',$company_id];
            $company_level = M('company_level')->where("c_parent_id = {$company_id} AND is_staff = 0")->select();
            $item['parent_id'] = $company_id;
            $item['cname']  =   M('company')->cache("company_{$company_id}")->find($company_id)['cname'];
        }
        if($store_id = I('store_id/d')){
            $map['cid']      = ['eq',$store_id];
            $company_level = M('company_level')->where("c_parent_id = {$store_id} AND is_staff = 0")->select();
            $item['parent_id'] = $store_id;
            $item['cname']  =   M('company')->cache("company_{$store_id}")->find($store_id)['cname'];
        }
        $this->assign('member',$item);
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        

        if(IS_POST){
            $data          = I('post.');
        #    $data['parent_id_path'] = $company['parent_id_path'];
            $data['psw'] = encrypt($data['psw']);
            $CompanyLogic  = new CompanyLogic();
            $res           = $CompanyLogic->addCompany_member($data);
            if($res['status'] == 1){
                $msg['status']    =   1;
            }else{
                $msg['status']    =   0;
                $msg['info']   =   $res['msg'];
            }
            $this->ajaxReturn($msg);
        }
        $this->assign('company_level', $company_level);     //等级列表，下拉用的
        return $this->fetch('company_member_form');
    }


    // 修改成员
    public function company_member_edit(){
        $id   = I('get.id');
        $company_id = I('get.company_id/d',0);
        $member = M('company_member')->alias('m')->field('m.*,profit,c.cname')
                            ->join('company c','c.cid = m.parent_id')
                            ->join('company_level lv','lv.id = m.company_level')
                            ->where("m.id = {$id}")
                            ->find();
    #    dump($member );die;
        if(!$member)
            exit($this->error('成员不存在'));

        $company_level = M('company_level')->where("c_parent_id = {$member['parent_id']} AND is_staff = 0")->select();


        if(IS_POST){
            $data     = I('post.');
            $company_obj = new CompanyLogic();
            $res         = $company_obj->updateCompany_member($data);
            if($res['status'] == 1){;
                
                $msg['status']    =   1;
            }else{
                $msg['status']    =   0;
                $msg['info']   =   $res['msg'];
            }
            $this->ajaxReturn($msg);
        }
        $this->assign('pk','id');
        $this->assign('member', $member);
        $this->assign('company_level', $company_level);     //等级列表，下拉用的
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        return $this->fetch('company_member_form');
    }
  
    function company_member_del(){
        if($id = I('post.id/d')){
            if(M('company_member')->delete($id)){
                $this->success('删除成功！',U('/Admin/Company/company_member',['company_id'=>I('company_id'),'store_id'=>I('store_id')]));
            }else{
                $this->error('删除失败！');
            }
        }else{
            $this->error('非法操作');
        }
    }


    function tree(){
        return $this->fetch('tree');
    }

    function ajax_tree_json(){
        /*$tree_data = S('level_tree_all_data');
        if($tree_data){
            $this->ajaxReturn($tree_data);
        }*/
        //查询出所有子公司
        $data['name']   =   '米豆薯';
        $data['children'] = M('company')->field('cname name,cid')->where('parent_id',0)->select();
        #查询出所有的实体店
        $store_list = M('company')->field('cname name,cid,parent_id_path,parent_id')->select();
        #查询出所有的成员
        $company_member_list = M('CompanyMember')->field('parent_id_path,parent_id,id cmid,real_name name')->select();
        
        #查询出所有员工
        $staff_list = M('staff')->field('id staff_id,real_name name,uname,parent_id,company_id,store_id,type t')->select();
        foreach ($data['children'] as $key => $value) {
            $data['children'][$key][0]['name']  =   '实体店';
            $data['children'][$key][1]['name']  =   '成员';
        }
        foreach ($data['children'] as $key => $value) {
            $data['children'][$key]['children'][0]['name']  =   '实体店';
            #添加实体店
            foreach ($store_list as $k => $v) {
                if($value['cid'] == $v['parent_id']){
                    #在节点添加实体店之前，先将员工和成员加入实体店
                    $v['children'][0]['name']   =   '员工';
                    $v['children'][1]['name']   =   '成员';
                    foreach ($staff_list as $k1 => $v1) {                        
                        if($v1['store_id'] == $v['cid'] && $v1['t'] == 0){
                            #添加员工之前将推广员添加到员工下方
                            foreach ($staff_list as $k2 => $v2) {
                                if($v2['parent_id'] == $v1['staff_id']){
                                    $v1['children'][] =   $v2;
                                }
                            }
                            #添加员工
                            $v1['name']  =   ($v1['name']) ? ($v1['name']) : ($v1['uname']);
                            $v['children'][0]['children'][]   =   $v1;
                            unset($staff_list[$k1]);
                        } 
                    }
                    #将实体店的成员加进去
                    foreach ($company_member_list as $k1 => $v1) {
                        if($v['cid'] == $v1['parent_id']){
                            $v['children'][1]['children'][]   =   $v1;
                            unset($company_member_list[$k1]);
                        }
                    }
                    /*判断该节点是否有有下级，没有删除，不然太乱*/
                    $data['children'][$key]['children'][0]['children'][] = $v;
                    unset($staff_list[$k]);


                }
            }
            #实体店结束
            #添加子公司成员
            $data['children'][$key]['children'][1]['name']  =   '成员';
            foreach ($company_member_list as $k => $v) {
                if($value['cid'] == $v['parent_id']){
                    $data['children'][$key]['children'][1]['children'][] = $v;
                    unset($company_member_list[$k]);
                }
            }
            #添加子公司成员结束
            
        }
        $this->ajaxReturn($data); 
    }

    function tree_unset(&$array){
        if($array){
            if(empty($array['children'])){
                unset($array);
            }
        }
    }

    #发送站内消息
    function sendSms(){
        if(\think\Request::instance()->isPost()){
            $ids = I('post.ids');
            $text = I('post.text');
            if(empty($ids) || empty($text)){
                $msg['status'] = 0;
                $msg['info']    =   '发送的消息不能为空';
            }else{
                $id_array = explode(',',$ids);
                foreach ($id_array as $key => $value) {
                    if(empty($value)){
                        continue;
                    }
                    $list[] =   ['company_id'=>$value,'message'=>$text,'admin_id'=>session('admin_id'),'create_time'=>NOW_TIME,'status'=>0,'info'=>'系统通知'];
                }
                $r = db('company_msg')->insertAll($list);
                if($r){
                    $msg['status'] = 1;
                }else{
                    $msg['status'] = 0;
                    $msg['info']    =   '发送失败！';
                }
            }
            $this->ajaxReturn($msg);
        }else{
            return $this->fetch();
        }
    }

    /*企业收款记录*/
    function transfer_log(){
      #  $_GET['is_pay'] =   1;
        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);

        $tz = I('get.tz/d');
        if($tz == 2){
            $where['paid_sn'] = ['like',"mypays_%"];
        }else{
             $where['paid_sn'] = ['like',"staff_paid%"];
        }


        if($company_id = I('get.company_id/d')){
            $where['store_id'] = ['eq',$company_id];
            $store_list = db('company')->where("parent_id = {$company_id}")->select();
            $this->assign('store_list',$store_list);
        }

        if($store_id = I('get.store_id/d')){
            $where['store_id'] = ['eq',$store_id];
        }
        ;
        
        $where['pay_status']    =   ['eq',1];
           
        if($key_word = I('get.key_word/s')){
            $where['paid_sn']   =   ['like',"%{$key_word}%"];
        }


        $list  = M('transfer_log')->where($where)->order("id desc")->page("$p,$size")->select();
        $count = M('transfer_log')->where($where)->count();
        foreach ($list as $key => $value) {
            if(strstr($value['paid_sn'],'staff_paid_')){
                $list[$key]['money']  =   db('staff_paid')->where("paid_sn = '{$value['paid_sn']}'")->getField('money');
            }else{
                $list[$key]['money']  =   db('staff_mypays')->where("paid_sn = '{$value['paid_sn']}'")->getField('money');
            }
        }
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);        
        return $this->fetch('transfer_log');
    }

    function view_transfer_log(){
        $id = I('get.id/d');
        $where['id']    =   ['eq',$id];
        $paid_sn = I('get.paid_sn/s');
        if($paid_sn){
            $where['paid_sn']   =   ['eq',$paid_sn];
            unset($where['id']);
        }
        $item = db('transfer_log')->where($where)->find();
        if(strstr($item['paid_sn'],'staff_paid_')){
            $item['r']  =   db('staff_paid')->where("paid_sn = '{$item['paid_sn']}'")->find();
        }else{
            $item['r']  =   db('staff_mypays')->where("paid_sn = '{$item['paid_sn']}'")->find();
        }

        $this->assign('item',$item);
        return $this->fetch('view_transfer_log');
    }


    /*线下付款流水明细*/
    function offline_detail(){
        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        $export = I('export');
        if($export == 1){
            $p = 0;
            $size = 1000;
        }
        $tz = I('get.tz/d');
        if($tz == 2){
            //扫码自定义
            $table_name = 'staff_mypays';
        }else{
            $table_name = 'staff_paid';
        }
        if($company_id = I('get.company_id/d')){
            $where['staff.store_id'] = ['eq',$company_id];
            $store_list = db('company')->where("parent_id = {$company_id}")->select();
            $this->assign('store_list',$store_list);
        }

        if($store_id = I('get.store_id/d')){
            $where['a.store_id'] = ['eq',$store_id];
        }
        ;
        if($is_pay = I('get.is_pay/d')){
            if($is_pay == 2){
                $where['a.pay_status']    =   ['eq',0];
            }elseif($is_pay == 1){
                $where['a.pay_status']    =   ['eq',1];
            }
        }
        if($key_word = I('get.key_word/s')){
            $where['a.paid_sn']   =   ['like',"%{$key_word}%"];
        }

        $list  = M($table_name)->where($where)->alias('a')
                            ->field('a.*,staff.store_id,staff.company_id,store.cname store_name,company.cname company_name,staff.real_name staff_name,tl.paid_sn is_store_collection')
                            ->join('staff staff','staff.id = a.staff_id')
                            ->join('company store','store.cid = staff.store_id')
                            ->join('company company','company.cid = staff.company_id')
                            ->join('transfer_log tl','tl.paid_sn = a.paid_sn','left')
                            ->order("id desc")->page("$p,$size")->select();
        foreach ($list as $key => $value) {
            if($value['pay_status'] == 1){
                //如果已经支付
                if($table_name == 'staff_mypays'){
                    $map['pay_id']  =   ['eq',$value['id']];
                }else{
                    $map['paid_id']    =   ['eq',$value['id']];
                }
                #推广员
                $tgy_map = $map;
                $tgy_map['is_tj']   =   ['eq',1];
                $list[$key]['tgy_name']=   db('users u')->where("user_id = {$value['user_id']}")->join('staff staff','staff.id = u.staff_id')->getField('staff.real_name');
                $list[$key]['tgy_money']    =      db('staff_commission')->where($tgy_map)->sum('money');
                #员工
                $staff_map = $map;
                $staff_map['is_tj']   =   ['eq',0];
                $list[$key]['staff_money']    =      db('staff_commission')->where($staff_map)->sum('money');
                #实体店
                if($value['store_id']){
                    $store_map = $map;
                    $store_map['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$value['store_id']})"];
                    $list[$key]['store_money']    =   db('member_commission')->where($store_map)->sum('money');     //实体店成员的钱
                }
                #子公司
                if($value['company_id']){
                    $company_map = $map;
                    $company_map['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$value['company_id']})"];
                    $list[$key]['company_money']    =   db('member_commission')->where($company_map)->sum('money');     //子公司成员的钱
                }
                $syjg =   bcsub($value['money'],$list[$key]['tgy_money'],9);
                $syjg =   bcsub($syjg,$list[$key]['staff_money'],9);
                $syjg =   bcsub($syjg,$list[$key]['store_money'],9);
                $list[$key]['syjg'] =   bcsub($syjg,$list[$key]['company_money'],2);
            }
        }
        if($export == 1){
            $strTable ='<table width="1000" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">员工姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单编码</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推广员</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">员工</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">子公司</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">剩余</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">隶属公司</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">下单时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否支付</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">企业收款</td>';
            $strTable .= '</tr>';
            
            if(is_array($list)){
                foreach ($list as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['staff_id'].':'.$val['staff_name'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['paid_sn'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'. $val['money'] .' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tgy_money'] . ':'.$val['tgy_name'] .'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['staff_money'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['store_money'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['company_money'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['syjg'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['store_name'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['create_time'].'</td>';
                    $pay_status = ($val['pay_status'] == 1) ? ('是') : ('否');
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$pay_status.'</td>';
                    $str = $val['is_store_collection'] ? ('是') :('否');
                    $strTable .= '<td style="text-align:center;font-size:12px;">'. $str .'</td>';
                    $strTable .= '</tr>';
                }
                
            }
            $strTable .='</table>';
            downloadExcel($strTable,'线下流水明细');
            exit();
        }
        $count = M($table_name)->where($where)->alias('a')
                            ->field('a.*,staff.store_id,staff.company_id,store.cname store_name,company.cname company_name,staff.real_name staff_name')
                            ->join('staff staff','staff.id = a.staff_id')
                            ->join('company store','store.cid = staff.store_id')
                            ->join('company company','company.cid = staff.company_id')->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);        
        return $this->fetch('offline_detail');
    }

    function get_staff_list(){

        $paid_sn = I('get.paid_sn');
        $id = I('get.id/d');
        if(stripos($paid_sn,'staff_paid') !== false){
            $where['paid_id']   =   ['eq',$id];
        }else{
            $where['pay_id']   =   ['eq',$id];
        }
        $where['is_tj'] =   ['eq',0];
        $list = db('staff_commission a')->field('a.*,staff.real_name')->join('staff staff','staff.id = a.staff_id','left')->where($where)->select();
        $this->assign('list',$list);
        return $this->fetch('tongji/staff_list');
    }

    /*获取某笔订单谁吃返利了 成员*/
    function get_store_list(){
        $paid_sn = I('get.paid_sn');
        $id = I('get.id/d');
        $store_where['a.id']   =   ['eq',$id];
        if(stripos($paid_sn,'staff_paid') !== false){
            $table_name = 'staff_paid';
            $where['paid_id']   =   ['eq',$id];
        }else{
            $table_name = 'staff_mypays';
            $where['pay_id']   =   ['eq',$id];
        }

        $store_id = db($table_name)->alias('a')->where($store_where)
                    ->join('staff staff','staff.id = a.staff_id')
                    ->getField('store_id');
        if($store_id){
         #   $where['a.paid_sn']   =   ['eq',$paid_sn];
            $where['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$store_id})"];
            $list   =   db('member_commission a')->field('a.*,member.real_name')
                            ->join('company_member member','member.id = a.member_id','left')
                            ->where($where)
                            ->select();
        }
        
        $this->assign('list',$list);
        return $this->fetch('tongji/member_list');
    }
    /*获取某笔订单谁吃返利了 成员*/
    function get_company_list(){
         $paid_sn = I('get.paid_sn');
        $id = I('get.id/d');
        $company_where['a.id']   =   ['eq',$id];
        if(stripos($paid_sn,'staff_paid') !== false){
            $table_name = 'staff_paid';
            $where['paid_id']   =   ['eq',$id];
        }else{
            $table_name = 'staff_mypays';
            $where['pay_id']   =   ['eq',$id];
        }

        /*$company_id = M($table_name)->alias('a')->where($company_where)
                    ->join('users u','u.user_id = a.user_id','left')
                    ->join('staff staff','staff.id = u.staff_id','left')
                    ->getField('company_id');*/
        $company_id = M($table_name)->alias('a')->where($company_where)
                    ->join('staff staff','staff.id = a.staff_id','left')
                    ->getField('company_id');
        if($company_id){
         #   $where['a.paid_sn']   =   ['eq',$paid_sn];
            $where['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$company_id})"];
            $list   =   db('member_commission a')->field('a.*,member.real_name')
                            ->join('company_member member','member.id = a.member_id','left')
                            ->where($where)
                            ->select();
        }
        
        $this->assign('list',$list);
        return $this->fetch('tongji/member_list');
    }


    function view_offline_log(){
        $id = I('get.id/d');
        $tz = I('get.tz/d');
        if($tz == 2){
            $table_name = 'staff_mypays';
        }else{
            $table_name = 'staff_paid';
        }

        $item = M($table_name)->where($where)->alias('a')
                            ->field('a.*,staff.store_id,staff.company_id,store.cname store_name,company.cname company_name,staff.real_name staff_name,tl.paid_sn is_store_collection')
                            ->join('staff staff','staff.id = a.staff_id')
                            ->join('company store','store.cid = staff.store_id')
                            ->join('company company','company.cid = staff.company_id')
                            ->join('transfer_log tl','tl.paid_sn = a.paid_sn','left')
                            ->find($id);
        $this->assign('item',$item);
        return $this->fetch('view_offline_log');
    }


}