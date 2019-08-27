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
use think\Config;
use app\admin\model\CompanyLevelModel;

class CompanySign extends Base {

    public $model;
    public $pk;
    public $indexUrl;
    public function _initialize() {
        parent::_initialize();   
        $this->pk ='id';
        $this->indexUrl = U('Admin/CompanySign/index');

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
            $where['company_id'] = ['eq',$company_id];
        }
        $store_id = I('get.store_id/d');
        if($store_id){
            $where['store_id'] = ['eq',$store_id];
        }

        foreach (I('get.') as $key => $value) {
            $query_string['query'][$key]  =   $value;
        }
        if(empty($query_string)){
            $query_string = [];
        }

        $list = Db::name('CompanySign')
            ->alias('sign')
            ->field('sign.*,company.cname as company_name,store.cname as store_name,t_company.cname as t_company_name')
            ->join('__COMPANY__ t_company','t_company.cid = sign.t_company_id','left')
            ->join('__COMPANY__ company','company.cid = sign.company_id','left')
            ->join('__COMPANY__ store','store.cid = sign.store_id','left')
            ->where($where)
            ->order('addtime desc')
            ->paginate(10,false,$query_string);

        $profit = array();
        foreach($list as $key=>$v){
            if($v['company_level']){
                $r = M('company_level')->field('profit')->find($v['company_level']);
                if($r){
                    $profit[$key] = $r['profit'];
                }
            }


        }

        $this->assign('list',$list);
        $this->assign('profit',$profit);
        return $this->fetch('index');
    }

    function del(){
        if($id = I('post.id/d')){
            $c = M('company_sign')->where("id=$id")->field('company_id,t_company_id')->find();
            $t_company_id = M('company')->where("cid=".$c['company_id'])->value('t_company_id');

            if(M('company_sign')->delete($id)){
                $t_arr = explode(',',$t_company_id);
                $len = count($t_arr);
                for($i=0;$i<$len;++$i){
                    if($t_arr[$i] == $c['t_company_id']){
                        unset($t_arr[$i]);
                    }
                }
                M('company')->where("cid=".$c['company_id'])->update(['t_company_id'=>implode(',',$t_arr)]);

                $this->success('推荐关系解除成功！',$this->indexUrl);
            }else{
                $this->error('推荐关系解除失败！');
            }
        }else{
            $this->error('非法操作');
        }
    }

    function edit(){


        $id = I('get.id/d');
        $data = Db::name('CompanySign')
            ->alias('sign')
            ->field('sign.*,company.cname as company_name,store.cname as store_name,t_company.cname as t_company_name')
            ->join('__COMPANY__ t_company','t_company.cid = sign.t_company_id','left')
            ->join('__COMPANY__ company','company.cid = sign.company_id','left')
            ->join('__COMPANY__ store','store.cid = sign.store_id','left')
            ->where("id=$id")
            ->find();

        if (IS_POST) {
            $data = I('post.');
            $res = M('company_sign')->update($data);
            if ($res !== false) {
                $msg['status'] = 1;
            } else {
                $msg['status'] = 0;
                $msg['info'] = '更新失败';
            }
            $this->ajaxReturn($msg);
        }

        if($data['company_level']){
            $r = M('company_level')->field('profit')->find($data['company_level']);
            if($r){
                $data['profit'] = $r['profit'];
            }
        }

        $this->assign($data);

        $levelwhere['is_elite'] = ['eq',1];
        if($data['store_id'] > 0){
            $levelwhere['c_parent_id'] = ['eq',$data['store_id']];
        }else{
            $levelwhere['c_parent_id'] = ['eq',$data['company_id']];
        }
        $level_list = M('company_level')->where($levelwhere)->select();
        $this->assign('level_list', $level_list);
        return $this->fetch();
    }

    function doSave(){
        $id = I('get.id/d');

    }



    

}