<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * 各公司，实体等级管理
 */
namespace app\admin\controller; 
use app\admin\logic\CompanyLogic;
use think\AjaxPage;
use think\Page;
use think\Db;
use app\admin\model\CompanyLevelModel;

class CompanyLevel extends Base {

    public $model;
    public $pk;
    public $indexUrl;
    public function _initialize() {
        parent::_initialize();   
        $this->pk ='id';
        $this->indexUrl = U('Admin/CompanyLevel/index');
        $this->addUrl = U('Admin/CompanyLevel/add');

    }

    public function index(){
        $company_id = I('company_id');
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        if($company_id!= ''){
            $store_list = db('company')->where('parent_id','eq',$company_id)->select();
        }
        
        $this->assign('store_list',$store_list);
        $company_id = I('get.company_id/d');
        if($company_id){
            $where['c_parent_id'] = ['eq',$company_id];
        }
        $store_id = I('get.store_id/d');
        if($store_id){
            $where['c_parent_id'] = ['eq',$store_id];
        }
        if($key_word = I('key_word/s')){
            $where['cname|lv_name'] =   ['like',"%{$key_word}%"];
        }
        
        foreach (I('get.') as $key => $value) {
            $query_string['query'][$key]  =   $value;
        }
        if(empty($query_string)){
            $query_string = [];
        }

        // 查询状态为1的用户数据 并且每页显示10条数据
        $list = Db::name('CompanyLevel')
                        ->alias('level')
                        ->field('level.*,company.cname')
                        ->join('__COMPANY__ company','company.cid = level.c_parent_id','left')
                        ->where($where)
                        ->order('id desc')
                        ->paginate(10,false,$query_string);
        $count = Db::name('CompanyLevel')
                        ->alias('level')
                        ->field('level.*,company.cname')
                        ->join('__COMPANY__ company','company.cid = level.c_parent_id','left')
                        ->where($where)
                        ->count();
        $this->assign('list',$list);


        /*查询设置有问题的层级*/
        
        #$company_where['is_lock']   =   ['eq',0];
        $company_list = M('company')->alias('company')
                                ->field('cid,cname')  
                                ->where($company_where)
                                ->select();
        #查询出所有层级
        $level_list = db('company_level')->select();
        foreach ($company_list as $key => $value) {
            foreach ($level_list as $k => $v) {
                if($v['c_parent_id'] == $value['cid']){
                    if($v['is_staff'] == 0){
                        $company_list[$key]['member_profit']    +=   $v['profit'];
                    }else{
                        $company_list[$key]['staff_profit']    +=   $v['profit'];
                    }
                    unset($level_list[$k]);
                }
            }
        }
        foreach ($company_list as $key => $value) {
            if($value['member_profit']>1){
                $error .= "<li>" .$value['cname'] . " 成员层级比率设置出问题了！！请尽快修复，否则<font color=#ff0000>丢钱了！！！！！！丢钱了！！！！！！丢钱了！！！！！！</font></li>";
            }
            if($value['staff_profit'] > 1){
                $error .= "<li>" .$value['cname'] . " 员工层级比率设置出问题了！！请尽快修复，否则<font color=#ff0000>丢钱了！！！！！！丢钱了！！！！！！丢钱了！！！！！！</font></li>";
            }
        }
        $this->assign('errormsg',$error);
        return $this->fetch('index');
    }
    /*
    public function ajaxindex(){
        $condition = array();
        $key_word     = I('key_word')     ? $condition['key_word']     = trim(I('key_word')) : '';
        $c_parent_id  = I('c_parent_id')  ? $condition['c_parent_id']  = trim(I('c_parent_id'))  : '';
        $c_parent_id2 = I('c_parent_id2') ? $condition['c_parent_id2'] = trim(I('c_parent_id2')) : '';

        $where = ' 1 = 1 '; // 搜索条件
        // 关键词搜索               
        
        if($key_word)
        {
            $where = "$where and cname like '%$key_word%'";
        }

        if($c_parent_id2 > 0 && !$c_parent_id)
            $where .= " and c_parent_id = ".$c_parent_id2; 
        if($c_parent_id > 0)
            $where .= " and c_parent_id = ".$c_parent_id;

        $count = M('company_level')->where($where)->count();
        $Page  = new AjaxPage($count,20);
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key] = urlencode($val);
        }

        $list  = M('company_level')->where($where)->order("{$this->pk} desc")->limit($Page->firstRow.','.$Page->listRows)->select();

        $CompanyLogic = new CompanyLogic();
        foreach ($list as $k => $val) {
            $list[$k]['parent_str'] = ''; 
            $level_cat = $CompanyLogic->find_parent_cat($val['c_parent_id']); // 获取分类默认选中的下拉框
            if($level_cat){
                foreach (array_reverse($level_cat) as $v) {
                    $company_name = M('Company')->field('cname,level')->where('cid='.$v)->find();
                    if($company_name['level'] == 2) $list[$k]['parent_str'] .= '>';
                    $list[$k]['parent_str'] .= $company_name['cname'];
                }
            }
        }

        $show = $Page->show();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);       
        return $this->fetch();
    }
*/




    public function add(){
        $company = M('company')->cache(true)->field('cid,cname')->where('parent_id = 0 AND level = 1')->select();
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        if(IS_POST){
            $data                = I('post.');
            $data['c_parent_id'] = I('company_id');
            $data['level']       = 1;
            $data['c_parent_id_path'] = '0_'.$data['c_parent_id'];
            $addBackFlag = I('addBackFlag',0);
            if( I('store_id') ){
                $data['c_parent_id'] = input('store_id');
                $data['level']       = 2;
                $data['c_parent_id_path'] = '0_'.I('company_id').'_'.$data['c_parent_id'];
            }

            $new_model = new CompanyLevelModel($data);
            if($new_model->allowField(true)->save($data)){
                if($addBackFlag == 1){
                    $this->success('新增数据成功！',$this->addUrl);
                }else{
                    $this->success('新增数据成功！',$this->indexUrl);
                }
            }else{
                $this->error('新增数据失败!');
            }
        }
        $this->assign('company', $company);
        return $this->fetch('form');
    }

    public function edit(){

        $id    = I('get.id/d');
        $item = M('company_level')->where(array('id'=>$id))->find();
        if(!$item)
            exit($this->error('信息不存在'));

        $company = M('company')->cache(true)->field('cid,cname')->where('parent_id = 0 AND level = 1')->select();
        $CompanyLogic = new CompanyLogic();
    //    $level_cat = $CompanyLogic->find_parent_cat($item['c_parent_id']); // 获取分类默认选中的下拉框
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        
        $parent_company = db('company')->where("cid = {$item['c_parent_id']}")->find();
        if($parent_company['parent_id'] != 0){
            $item['company_id'] =   $parent_company['parent_id'];
            $item['store_id']   =   $item['c_parent_id'];
        }else{
            $item['company_id'] =   $item['c_parent_id'];
            
        }
        $store_list = db('company')->where("parent_id = {$item['company_id']}")->select();
        $this->assign('store_list',$store_list);

   // dump($item );die;

        if(IS_POST){
            $data                = I('post.');
            $data['c_parent_id'] = I('company_id');
            $data['level']       = 1;
            $data['c_parent_id_path'] = '0_'.$data['c_parent_id'];
            if( I('store_id') ){
                $data['c_parent_id'] = input('store_id');
                $data['level']       = 2;
                $data['c_parent_id_path'] = '0_'.I('company_id').'_'.$data['store_id'];
            }

            $new_model = new CompanyLevelModel();
            // 过滤post数组中的非数据表字段数据
            if($new_model->allowField(true)->save($data,[$this->pk => I('post.'.$this->pk.'/d')])){
                $this->success('更新数据成功！',$this->indexUrl);
            }else{
                $this->error('更新数据失败');
            }
        }

        $this->assign('company', $company);
        $this->assign('level_cat', $level_cat);
        $this->assign('pk',$this->pk);
        $this->assign('item',$item);
        return $this->fetch('form');
    }
    
   
    /*public function doAdd(){
        $new_model = new CompanyLevelModel($_POST);
        if($new_model->allowField(true)->save($_POST)){
            $this->success('新增数据成功！',$this->indexUrl);
        }else{
            $this->error('新增数据失败!');
        }
    }

    function modify(){
        if($id = I('get.id/d')){
            $item = M('company_level')->find($id);
            $this->assign('acts','doModify');
            $this->assign('pk',$this->pk);
            $this->assign('item',$item);
            return $this->fetch('form');
        }else{
            $this->error('参数错误!');
        }
        
    }

    function doModify(){
        $new_model = new CompanyLevelModel();
        // 过滤post数组中的非数据表字段数据
        if($new_model->allowField(true)->save($_POST,[$this->pk => I('post.'.$this->pk.'/d')])){
            $this->success('更新数据成功！',$this->indexUrl);
        }else{
            $this->error('更新数据失败');
        }
    }*/

    function del(){
        if($id = I('post.id/d')){
            if(M('company_level')->delete($id)){
                $this->success('删除成功！',$this->indexUrl);
            }else{
                $this->error('删除失败！');
            }
        }else{
            $this->error('非法操作');
        }
    }

    #张洪凯  2018-11-6
    function ajax_del(){
        $DelInfoLogic = new \app\admin\logic\DelInfoLogic();
        $res = $DelInfoLogic->delete_info();
        $this->ajaxReturn($res);
    }


    /*获取某公司下方所有实体店*/
    function ajax_get_store()
    {
        $company_id = I('get.company_id');
        $store_list = $this->LX_get_company_storedesc($company_id);
        if ($store_list) {
            $data['status'] = 1;
            $data['list'] = $store_list;
        } else {
            $data['status'] = 0;
            $data['list'] = '';
        }
        $this->ajaxReturn($data);
    }

    /*
    LX 2018年11月12日
    查询子公司下方所有实体店倒序*/
    function LX_get_company_storedesc($cid){
    if(empty($cid)){return ;}
    $store_list = S('company_store_list_'.$cid);
    if(empty($store_list)){
        $where['parent_id']  =   ['eq',$cid];
        $store_list = M('company')->field('cid,cname,is_lock,contact,parent_id,parent_id_path,remark,update_time')->where($where)->order('cid desc')->select();
        S('company_store_list_'.$cid,$store_list);
    }
    return $store_list;
    }

}