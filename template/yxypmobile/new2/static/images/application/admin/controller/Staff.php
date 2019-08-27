<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\admin\controller; 
use app\admin\logic\StaffLogic;
use app\admin\logic\CompanyLogic;
use think\AjaxPage;
use think\Page;
use think\Db;
use think\Request;
use app\admin\model\StaffModel;

class Staff extends Base {

    var $table_name;
    var $model;
    var $pk;
    var $indexUrl;
    var $company_model;
    var $company_level_model;
    public function _initialize() {
        parent::_initialize();   
        $this->table_name = 'staff';
        $this->pk ='id';
        $this->model = M($this->table_name);
        $this->indexUrl = U('Admin/Staff/Index');
        $this->company_model = M('company');
        $this->company_level_model = M('company_level');
    }

    public function index(){
        $t = I('t'); 
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $level_id = I('level_id') ? I('level_id') : '';
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id){
            $store_list = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
            $where['company_id'] = ['eq',$company_id];
        }
        if($store_id){
            $where['store_id']  =   ['eq',$store_id];
        }
        if(($t || $t == 0) && $t!=''){
            $where['type']  =   ['eq',$t];
        }
        if($level_id){
            $where['company_level'] = ['eq',$level_id];
        }
        if($key_word = I('key_word')){
            $where['real_name|mobile'] = ['eq',$key_word];
        }
        $count = M('staff')->alias('staff')->where($where)
                        ->join('__COMPANY__ c','staff.company_id = cid','left')
                        ->count();
        $Page  = new Page($count,20);

        $list = M('staff')
                    ->alias('staff')
                    ->where($where)
                    ->field('staff.*,lv.lv_name,c.cname as company_name')
                    ->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
                    ->join('__COMPANY__ c','staff.company_id = cid','left')
                    ->order("{$this->pk} desc")
                    ->limit($Page->firstRow.','.$Page->listRows)
                    ->select(); 
        $show = $Page->show();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch('Index');
    }

    
    

    // 统一后台效果
    #下面的这个函数  是用于在用户界面(detail.html)选择相应的推广员，现在用不到了，以后如果再用到直接复制粘贴就行，就不删除了
    public function search_promotders(){
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id = I('get.company_id/d')){
            $store_list = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
         #   dump($store_list);die;
            $this->assign('store_list',$store_list);
            $map['company_id'] = ['eq',$company_id];
        }
        if($store_id = I('get.store_id/d')){
            $map['store_id'] = ['eq',$store_id];
        }
        if($key_word = I('get.key_word/s')){
            $map['phone|real_name'] = ['like',"%{$key_word}%"];
        }
        $map['type']    =   ['eq',1];
        $list = M('staff')
                    ->alias('staff')
                    ->where($map)
                    ->field('staff.*,lv_name,company.cname as company_name,store.cname store_name')
                    ->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
                    ->join('__COMPANY__ company',"company.cid = staff.company_id",'left')
                    ->join('__COMPANY__ store',"store.cid = staff.store_id",'left')
                    ->order("{$this->pk} desc")
                    ->page("$p,$size")
                    ->select();
      #  echo M('staff')->getlastsql();
        $count = M('staff')->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);        
        return $this->fetch();
    }

    /*视图查看,排序*/
    function view(){
        $p    = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        if($company_id = I('get.company_id/d')){
            $map['company_id'] = ['eq',$company_id];
            $join_str = 'staff.company_id = company.cid';
        }
        if($store_id = I('get.store_id/d')){
            $map['store_id'] = ['eq',$store_id];
            $join_str = 'staff.store_id = company.cid';
        }
        $list = $this->model
                    ->alias('staff')
                    ->where($map)
                    ->field('staff.*,lv_name,company.cname as company_name')
                    ->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
                    ->join('__COMPANY__ company',$join_str,'left')
                    ->order("{$this->pk} desc")
                    ->page("$p,$size")
                    ->select();
        $count = $this->model->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);        
        return $this->fetch();
    }

    function view_son(){
        $rid = I('get.id/d');
        if($top_id = I('get.top_id/d')){
            $map['top_id']    =   ['eq',$top_id];
            $map['rid']   =   ['eq',$rid];
        }
        $list = $this->model
                    ->alias('staff')
                    ->where($map)
                    ->field('staff.*,lv_name,company.cname as company_name')
                    ->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
                    ->join('__COMPANY__ company','staff.top_id = company.cid','left')
                    ->order("{$this->pk} desc")
                    ->select();

        if($list){
            foreach ($list as $key => $value) {
                $list[$key]['money']    =   tk_money_format($value['money']);
                $list[$key]['frozen']    =   tk_money_format($value['frozen']);
            }
            $data['status'] =   1;
            $data['list']   =   $list;
        }else{
            $data['status'] =   0;
            $data['list']   =   $this->model->getlastsql();
        }
        if(Request::instance()->isAjax()){
            $this->ajaxReturn($data);
        }else{
            return $data;
        }

    }

    // 添加 员工
    public function add(){
        /*查询实体店列表*/
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $level_id = I('level_id') ? I('level_id') : '';
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($store_id){
            $company_id = M('company')->field('parent_id')->find($store_id)['parent_id'];
            $this->assign('company_id',$company_id);
        }
        $store_list = M('company')->where('parent_id','eq',$company_id)->select();
        $this->assign('store',$store_list);
        /*查询所有层级*/
        $id = ($store_id) ? ($store_id) : ($company_id);
        $level_list = M('CompanyLevel')->where('c_parent_id','eq',$id)->select();
        $this->assign('level_list',$level_list);

        if(IS_POST){
            $data                = I('post.');
            $data['psw']         = encrypt($data['psw']);
            $staff_obj           = new StaffLogic();
            $res                 = $staff_obj->addStaff($data);
            if($res['status'] == 1){
                $msg['status'] = 1;
                $msg['info']    =   U('/Admin/Staff/index',array('company_id'=>$company_id,'store_id'=>$store_id,'t'=>$data['type']));
            }else{
                $msg['status']  =   0;
                $msg['info']    =   $res['msg'];
            }
            $this->ajaxReturn($msg);
        }

        //初始化某些值
        $item = ['money'=>0,
                'frozen'=>0,
                'is_lock'=>1,
                'service_charge'=>0,
                'present_money'=>0,
                'service_charge'=>0,
                'present_time_start'=>7,
                'present_time_end'=>20,
                ];
        $this->assign('item',$item);
        return $this->fetch('Form');
    }

    public function edit(){
        $id   = I('get.id');
        $staff = M('staff')->where(array('id'=>$id))->find();
        if(!$staff)
            exit($this->error('员工不存在'));

        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id = $staff['company_id']){
            $store_list = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
        }
    
        if( $staff['store_id'] ){
            $level_list = M('company_level')->where('c_parent_id = '.$staff['store_id'].' AND is_staff = 1')->select();
            $this->assign('level_list', $level_list);
            $stafflist = M('staff')->where("store_id = {$staff['store_id']} AND type = 0 and id !={$staff['id']}")->select();
            $this->assign('stafflist', $stafflist); 
        }

        if(IS_POST){
            $data     = I('post.');
            $password = I('post.psw');
            if(empty($password)){
                unset($data['psw']);
            }else{
                $data['psw'] = encrypt($data['psw']);
            }
            $store_id  = $data['store_id'];
            $staff_obj = new StaffLogic();
            $res       = $staff_obj->updateStaff($data['id'],$data);
            if($res['status'] == 1){;
                $msg['status'] = 1;
                $msg['info']    =   U('/Admin/Staff/index',['company_id'=>$data['company_id'],'store_id'=>$store_id,'t'=>$data['type']]);
            }else{
                $msg['status'] = 1;
                $msg['info']    =  $res['msg'];
            }
            $this->ajaxReturn($msg);
        }

        $this->assign('acts','updata');
        $this->assign('pk',$this->pk);
        $this->assign('staff', $staff);
        return $this->fetch('Form');
    }



    function del(){
        if($id = I('id/d')){
            if(db('staff')->delete($id)){
                $this->success('删除成功！',$this->indexUrl);
            }else{
                $this->error('删除失败！');
            }
        }else{
            $this->error('非法操作');
        }
    }


    /*根据公司ID获取该公司下所有用户*/
    function GetTop(){
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

   


    function ajax_get_staff(){
        $store_id = I('get.store_id');
        $staff_id = I('get.staff_id');
        if($store_id){
            $where['store_id']  =   ['eq',$store_id];
            $where['type']  =   ['eq',0];
            if($staff_id){
                $where['id']  =   ['neq',$staff_id];
            }
            $where['parent_id'] = ['eq',''];
            $staff_list = db('staff')->field('id,uname')->where($where)->cache(true)->select();
            if($staff_list){
                $data['status'] =   1;
                $data['info']   =   $staff_list;
            }else{
                $data['status'] =   0;
            }
            $this->ajaxReturn($data);
        }
    }


    #更换域名重新生成二维码
    public function re_qrcode(){
        return $this->fetch();
    }

    public function create_qrcode(){
        extract($_GET); 
        $est1 = ExecTime();
        if(empty($sstime)) $sstime = time();

        foreach (I('get.') as $key => $value) {
            $query_string['query'][$key]  =   $value;
        }
        if(empty($query_string)){
            $query_string = [];
        }
        $totalnum = db('staff')->count();

        $list = Db::name('staff')->paginate(10,false,$query_string);
        $StaffLogic = new StaffLogic;
        foreach ($list as $key => $value) {
            $tjnum++;
            $save_data[]    =   ['id'=>$value['id'],'qrcode'=>$StaffLogic->qrcode($value['id'])];
        }
        if(empty($page)){
            $page = 2;
        }else{
            $page++;
        }
        $staff_mode = new staffModel;
        $staff_mode->saveAll($save_data);
        $t2 = ExecTime();
        $t2 = ($t2 - $est1);
        $ttime = time() - $sstime;
        $ttime = number_format(($ttime / 60),2);

        //返回提示信息
        $tjlen = $totalnum>0 ? ceil( ($tjnum/$totalnum) * 100 ) : 100;
        $tjsta = "<div style='width:200;height:15;border:1px solid #898989;text-align:left'><div style='width:{$tjlen}%;height:15;background-color:#829D83'></div></div>";
        $tjsta .= "<br/>本次用时：".number_format($t2,2)."，总用时：$ttime 分钟，到达位置：".($page)."<br/>完成创建文件总数的：$tjlen %，继续执行任务...";
        if($tjnum < $totalnum)
        {
            $nurl  = "/Admin/Staff/create_qrcode/page/{$page}";
            $nurl .= "?tjnum={$tjnum}&seltime=$seltime&sstime=$sstime&stime=".urlencode($stime);
            ShowMsg($tjsta,$nurl,0,1000);
            exit();
        }
        else
        {
            ShowMsg("完成所有更新任务！，生成二维码：$totalnum 总用时：{$ttime} 分钟。","javascript:;");
        }

    }


    /*推广员申请*/
    function tk_apply(){
        $count = M('apply_promoters')->alias('a')->where($where)
                        ->join('users user','a.user_id = user.user_id','left')
                        ->join('staff staff','a.staff_id = staff.id','left')
                        ->count();
        $Page  = new Page($count,20);

        $list = M('apply_promoters')
                    ->alias('a')
                    ->where($where)
                    ->field('a.*,user.mobile user_mobile,staff.real_name staff_name')
                    ->join('users user','a.user_id = user.user_id','left')
                    ->join('staff staff','a.staff_id = staff.id','left')
                    ->order("id desc")
                    ->limit($Page->firstRow.','.$Page->listRows)
                    ->select(); 
        $show = $Page->show();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    /*推广员申请状态*/
    function do_apply(){
        $status = I('get.status');
        $text = I('get.text/s');
        $id = I('get.id/d');
        if($status == -1 && empty($text)){
            $res['status']  =   0;
            $res['info']    =   '作废必须输入备注！';
            $this->ajaxReturn($res);
        }
        if(db('apply_promoters')->where("id = {$id}")->update(['status'=>$status,'remark'=>$text,'update_time'=>NOW_TIME])){
            $res['status']  =   1;
            $res['info']    =   '设置成功！';

        }else{
            $res['status']  =   0;
            $res['info']    =   '设置失败！';
        }
        $this->ajaxReturn($res);
    }

}