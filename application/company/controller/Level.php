<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\company\controller; 
use think\AjaxPage;
use think\Controller;
use think\Config;
use think\Page;
use think\Db;
class Level extends Base {

#	var $cid;
	/**
     * 析构函数
     */
    function _initialize() 
    {
    #	$this->cid = Session('company.cid');
        parent::_initialize();
   } 

    public function index(){
        $where ['parent_id']= ['eq',$this->company_id];
        if($key_word = I('get.key_word/s')) $where['cname'] = ['like',"%{$key_word}%"] ;
        $count = M('Company')->where($where)->count();
        $pager = new Page($count,15);
        $list = M('Company')->where($where)->order('cid desc')->limit($pager->firstRow.','.$pager->listRows)->select();
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch();
    }

   

    /*查看下级*/
    function staff(){
        $store_id = I('get.store_id');
        
        /*查询本公司下方所有实体店*/
        $store_list = TK_get_company_store($this->company_id);
        $this->assign('store_list',$store_list);
        /*列表开始*/
        $p    = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        ($store_id) ? ($where['store_id'] = ['eq',$store_id]) : ('');
        $where['company_id'] = ['eq',$this->company_id];
        $t = I('get.t');
        if($t==2){
            $where['type']  =   ['eq',0];
        }elseif($t == 1){
            $where['type']  =   ['eq',1];
        }
        $key_word = I('get.key_word');
        $list = M('staff')->alias('staff')
                    ->field('staff.*,company.cname,lv_name')
                    ->join("__COMPANY__ company",'company.cid = staff.store_id','left')
                    ->join("company_level lv",'lv.id = staff.company_level','left')
                    ->where($where)
                    ->where(function ($query) use ($key_word) {
                        if($key_word){
                            $query->where('real_name', 'like',"%{$key_word}%")->whereor('phone','eq',$key_word);
                        }
                    })
                    ->order("id desc")
                    ->page("$p,$size")
                    ->select();
    #    echo M('staff')->getlastsql();die;
        $count = db('staff')->alias('staff')
                    ->where($where)
                    ->join("__COMPANY__ company",'company.cid = staff.store_id','left')
                    ->join("company_level lv",'lv.id = staff.company_level','left')
                    ->where(function ($query) use ($key_word) {
                        if($key_word){
                            $query->where('real_name', 'like',"%{$key_word}%")->whereor('phone','eq',$key_word);
                        }
                    })
                    ->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);

        return $this->fetch();
    }
    
    //查看员工详细信息
    function staff_view(){
        if($id = I('get.id/d')){
            $staff = db('staff')->alias('staff')
                        ->field('staff.*,store.cname store_name,company.cname company_name')
                        ->join('company store','store.cid = staff.store_id')
                        ->join('company company','company.cid = staff.company_id')
                        ->find($id);

            /*二维码*/
            $this->assign('staff',$staff);
            return $this->fetch();
        }else{
            $this->error('参数错误！');
        }
    }

    /*查看实体详细信息*/
    function store_view(){
        if($id = I('get.store_id/d')){
            $item = db('company')->find($id);
            $this->assign('item',$item);
            return $this->fetch();
        }else{
            $this->error('参数错误！');
        }
    }
   


    /*查看成员*/
    /*function member(){
        if($id = I('get.store_id/d')){
            $item = db('company')->find($id);
            $this->assign('item',$item);
            return $this->fetch();
        }else{
            $this->error('参数错误！');
        }
    }*/
    /*树状图*/
    function Tree(){
        return $this->fetch();
    }

    function ajax_tree_json(){
        $tree_data = S('level_tree_data');
        if($tree_data){
            $this->ajaxReturn($tree_data);
        }
        $data['name']   =   get_company_name($this->company_id);
        $data['children'][0]['name'] = '实体店';
        $data['children'][0]['children'] = db('company')->field('cname name,cid')->where('parent_id','eq',$this->cid)->order('cid asc')->select();
        $company_last_ids = TK_get_row($data['children'][0]['children'],'cid');
        $company_last_ids = implode(',', $company_last_ids);
        /*查询出当前实体下方所有员工*/
        $staff_list = M('staff')->field('uname name,store_id,type,id staff_id,parent_id')->where('store_id','in',$company_last_ids)->select();
        /*查询当前实体下方所有成员*/
        $member_list = M('company_member')->field('real_name name,parent_id')->where('parent_id','in',$company_last_ids)->select();

        foreach ($data['children'][0]['children'] as $key => $value) {
            #添加员工数据
            $data['children'][0]['children'][$key]['children'][0]['name'] =  '员工';
            foreach ($staff_list as $k => $v) {
                if($v['store_id'] == $value['cid'] && $v['type'] != 1){
                    #找到员工了， 需要先将他的推广员加进去
                    foreach ($staff_list as $k1 => $v1) {
                        if($v['staff_id'] == $v1['parent_id']){
                            $v['children'][] =  $v1;  
                       
                        }
                    }
                  //  $v['name']  =   $v['name'] . '<a href="http://www.baidu.com">编辑</a>';
                    $v['links'] =   "http://baidu.com";
                    $data['children'][0]['children'][$key]['children'][0]['children'][] =  $v;
                    unset($staff_list[$k]);
                } 
            }
            #判断如果改成没有相应员工 则删除节点，防止太多看不清
            if(empty($data['children'][0]['children'][$key]['children'][0]['children'])){
                unset($data['children'][0]['children'][$key]['children'][0]);
            }
            //琢磨了一下 如果删除节点  好像数组下标会出错
            $data['children'][0]['children'][$key]['children'][1]['name'] =  '成员';
            foreach ($member_list as $k => $v) {
                if($v['parent_id'] == $value['cid']){
                    $data['children'][0]['children'][$key]['children'][1]['children'][] =  $v;
                    unset($member_list[$k]);
                }
            }
            if(empty($data['children'][0]['children'][$key]['children'][1]['children'])){
                unset($data['children'][0]['children'][$key]['children'][1]);
            }
        }
        $data['children'][1]['name'] = '成员';
        $data['children'][1]['children']    =   M('company_member')->field('real_name name')->where('parent_id','eq',$this->company_id)->select();
        S('level_tree_data',$data);
     //   $data['children'] = 
        echo json_encode($data);
    }

}