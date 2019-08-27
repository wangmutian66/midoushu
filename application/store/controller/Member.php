<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\store\controller; 
use think\AjaxPage;
use think\Controller;
use think\Config;
use think\Page;
use think\Db;

/*
    成员管理
*/
class Member extends Base {

	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
   } 

    public function index(){

        //搜索关键词
        $key_word = I('get.key_word');
        $where['parent_id'] = ['eq',$this->store_id];

        $count = M('CompanyMember')->where($where)->where(function ($query) use ($key_word) {
                                        if($key_word){
                                            $query->where('real_name', 'like',"%{$key_word}%")->whereor('phone','eq',$key_word);
                                        }
                                    })->count();
        $Page = $pager = new Page($count,14);
        $show  = $Page->show();
        $list = M('CompanyMember')->alias('cm')
                                    ->field('cm.*,cl.lv_name')
                                    ->join('CompanyLevel cl','cm.company_level = cl.id','left')
                                    ->where($where)
                                    ->where(function ($query) use ($key_word) {
                                        if($key_word){
                                            $query->where('real_name', 'like',"%{$key_word}%")->whereor('phone','eq',$key_word);
                                        }
                                    })
                                    ->order("id desc")
                                    ->limit($Page->firstRow.','.$Page->listRows)
                                    ->select();
      //  echo M('CompanyMember')->getlastsql();
        /*处理隶属关系*/
    /*    foreach ($list as $key => $value) {
            $temp_arr = explode('_', $value['parent_id_path']);
            $list[$key]['relation'] = get_company_name($temp_arr[2]);
        }*/
        $this->assign('store_list',$store_list);
        $this->assign('company_level_list',$company_level_list);
        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('list',$list);
        return $this->fetch('index');
    }

    /*查看成员详细*/
    public function view(){
        if($id = I('get.id/d')){
            $where['parent_id'] = ['eq',$this->store_id];
            $item = M('CompanyMember')->alias('cm')
                                    ->field('cm.*,cl.lv_name')
                                    ->join('CompanyLevel cl','cm.company_level = cl.id','left')
                                    ->where($where)
                                    ->cache(true)
                                    ->find($id);
           
            $this->assign('item',$item);
            return $this->fetch();
        }else{
            $this->error('参数错误');
        }
        
    }
    
   
}