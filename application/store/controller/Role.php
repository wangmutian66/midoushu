<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\store\controller; 

use think\Db;

class Role extends Base {


	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
    }

    public function right_list(){

        $menuArr = include APP_PATH.'store/conf/menu.php';
        $name = I('name');

        if($name){
            $condition['name|right'] = array('like',"%$name%");
            $right_list = M('store_system_menu')->order('id desc')->select();
        }else{
            $right_list = M('store_system_menu')->order('id desc')->select();
        }

        $power = isset($_SESSION['power'])?session('power'):"0";

        $this->assign('power',$power);
        $this->assign('right_list',$right_list);
        $this->assign('group',$menuArr["storeMenu"]);
        return $this->fetch();
    }


    /**
     * [添加/编辑角色]
     * @author 王牧田
     * @date 2018-12-24
     * @return mixed
     */
    public function edit_right(){
        $storecid = session("store.cid");
        if(IS_POST){
            $data = I('post.');
            $data['right'] = implode(',',$data['right']);
            $data['store_id'] = $storecid;
            if(!empty($data['id'])){
                M('store_system_menu')->where(array('id'=>$data['id']))->save($data);
            }else{
                if(M('store_system_menu')->where(array('name'=>$data['name']))->count()>0){
                    $this->error('该权限名称已添加，请检查',U('System/right_list'));
                }
                unset($data['id']);
                M('store_system_menu')->add($data);
            }
            $this->success('操作成功',U('Role/right_list'));
            exit;
        }
        $id = I('id');
        if($id){
            $info = M('store_system_menu')->where(array('id'=>$id))->find();
            $info['right'] = explode(',', $info['right']);
            $this->assign('info',$info);
        }
        $menuArr = include APP_PATH.'store/conf/menu.php';


        $planPath = APP_PATH.'store/controller';
        $planList = array();
        $dirRes   = opendir($planPath);
        while($dir = readdir($dirRes))
        {
            if(!in_array($dir,array('.','..','.svn')))
            {
                $planList[] = basename($dir,'.php');
            }
        }
        $this->assign('planList',$planList);
        $this->assign('group',$menuArr["storeMenu"]);
        return $this->fetch();
    }

    public function right_del(){
        $id = I('del_id');

        if(is_array($id)){
            $id = implode(',', $id);
        }

        if(!empty($id)){
            $r = M('store_system_menu')->where("id in ($id)")->delete();
            if($r){
                exit(json_encode(1));
            }else{
                exit(json_encode('删除失败'));
            }
        }else{
            exit(json_encode('参数有误'));
        }
    }

    function ajax_get_action()
    {
        $control = I('controller');
        $advContrl = get_class_methods("app\\store\\controller\\".str_replace('.php','',$control));
        $baseContrl = get_class_methods('app\store\controller\Base');
        $diffArray  = array_diff($advContrl,$baseContrl);
        $html = '';
        foreach ($diffArray as $val){
            $html .= "<option value='".$val."'>".$val."</option>";
        }
        exit($html);
    }




    public function role(){
        $storecid = session("store.cid");
        $where["store_id"] = $storecid;
        $list = M('store_role')->where($where)->order('role_id desc')->select();
        file_put_contents("./public/data/role.txt",M('store_role')->getlastsql());
        $this->assign('list',$list);
        return $this->fetch();
    }

    public function role_info(){

        $role_id = I('get.role_id/d');
        $detail = array();
        if($role_id){
            $detail = M('store_role')->where("role_id",$role_id)->find();
            $detail['act_list'] = explode(',', $detail['act_list']);
            $this->assign('detail',$detail);
        }
        $right = M('store_system_menu')->order('id')->select();
        foreach ($right as $val){
            if(!empty($detail)){
                $val['enable'] = in_array($val['id'], $detail['act_list']);
            }
            $modules[$val['group']][] = $val;
        }
        //权限组
        $menuArr = include APP_PATH.'store/conf/menu.php';
        $this->assign('group',$menuArr["storeMenu"]);

        $this->assign('modules',$modules);
        return $this->fetch();
    }

    public function roleSave(){
        $data = I('post.');
        $res = $data['data'];
        $res['act_list'] = is_array($data['right']) ? implode(',', $data['right']) : '';
        $storecid = session("store.cid");
        $res["store_id"] = $storecid;
        if(empty($res['act_list']))
            $this->error("请选择权限!");
        if(empty($data['role_id'])){
            $store_role = Db::name('store_role')->where(['role_name'=>$res['role_name']])->find();
            if($store_role){
                $this->error("已存在相同的角色名称!");
            }else{
                $r = M('store_role')->add($res);
            }
        }else{
            $store_role = Db::name('store_role')->where(['role_name'=>$res['role_name'],'role_id'=>['<>',$data['role_id']]])->find();
            if($store_role){
                $this->error("已存在相同的角色名称!");
            }else{
                $r = D('store_role')->where('role_id', $data['role_id'])->save($res);
            }
        }
        if($r){
            $this->success("操作成功!",U('store/Role/role'));
        }else{
            $this->error("操作失败!",U('store/Role/role'));
        }
    }

    public function roleDel(){
        $role_id = I('post.role_id/d');
        $admin = M('store_admin')->where('role_id',$role_id)->find();
        if($admin){
            exit(json_encode("请先清空所属该角色的管理员"));
        }else{
            $d = M('store_role')->where("role_id", $role_id)->delete();
            if($d){
                exit(json_encode(1));
            }else{
                exit(json_encode("删除失败"));
            }
        }
    }



    public function index(){
        $list     = array();
        $storecid = session("store.cid");
        $where["store_id"] = $storecid;
        $keywords = I('keywords/s');
        if(empty($keywords)){
            $res = D('store_admin')->where($where)->select();
        }else{
            $where["user_name"] = ["like",'%'.$keywords.'%'];
            $res = DB::name('store_admin')->where($where)->order('admin_id')->select();
        }
        $role = D('store_role')->getField('role_id,role_name');
        if($res && $role){
            foreach ($res as $val){
                $val['role'] =  $role[$val['role_id']];
                $val['add_time'] = date('Y-m-d H:i:s',$val['add_time']);
                $list[] = $val;
            }
        }
        $this->assign('list',$list);
        return $this->fetch();
    }


    public function admin_info(){
        $admin_id = I('get.admin_id/d',0);
        if($admin_id){
            $info = D('store_admin')->where("admin_id", $admin_id)->find();
            $info['password'] =  "";
            $this->assign('info',$info);
        }
        $act = empty($admin_id) ? 'add' : 'edit';
        $this->assign('act',$act);
        $storecid = session("store.cid");
        $where["store_id"] = $storecid;
        $role = D('store_role')->where($where)->select();
        $this->assign('role',$role);
        return $this->fetch();
    }


    public function adminHandle(){
        $data = I('post.');
        if(empty($data['password'])){
            unset($data['password']);
        }else{
            $data['password'] = encrypt($data['password']);
        }
        if($data['act'] == 'add'){
            $storecid = session("store.cid");
            unset($data['admin_id']);
            $data['add_time'] = time();
            $data['store_id'] = $storecid;
            if(D('company')->where("mobile", $data['user_name'])->count()){
                $this->error("此用户名已被注册，请更换",U('store/Role/admin_info'));
            }else if(D('store_admin')->where("user_name", $data['user_name'])->count()){
                $this->error("此用户名已被注册，请更换",U('store/Role/admin_info'));
            }else{

                $r = D('store_admin')->add($data);
            }
        }

        if($data['act'] == 'edit'){
            $r = D('store_admin')->where('admin_id', $data['admin_id'])->save($data);
        }

        if($data['act'] == 'del' && $data['admin_id']>1){
            $r = D('store_admin')->where('admin_id', $data['admin_id'])->delete();
            exit(json_encode(1));
        }

        if($r){
            $this->success("操作成功",U('store/Role/index'));
        }else{
            $this->error("操作失败",U('store/Role/index'));
        }
    }
}