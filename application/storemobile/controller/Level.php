<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\storemobile\controller; 
use think\AjaxPage;
use think\Controller;
use think\Config;
use think\Page;
use think\Db;
class Level extends Base {

	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
   } 

    public function index(){
        $where ['parent_id']= ['eq',$this->store_id];
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
        /*列表开始*/
        $p    = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $where['store_id'] = ['eq',$this->store_id];
    #    dump($where);die;
        // $t = I('get.t/d',0);
        // if($t==2){
        //     $where['type']  =   ['eq',0];
        // }elseif($t == 1){
        //     $where['type']  =   ['eq',1];
        // }
        $key_word = I('get.key_word');
        $list = M('staff')->alias('staff')
                    ->field('staff.*,store.cname,lv_name')
                    ->join("__COMPANY__ store",'store.cid = staff.store_id','left')
                    ->join("company_level lv",'lv.id = staff.company_level','left')
                    ->where($where)
                    ->where(function ($query) use ($key_word) {
                        if($key_word){
                            $query->where('real_name', 'like',"%{$key_word}%")->whereor('phone','like',"%{$key_word}%");
                        }
                    })
                    ->order("id desc")
                    ->page("$p,$size")
                    ->select();
    #    echo M('staff')->getlastsql();die;
        $count = db('staff')->alias('staff')
                    ->where($where)
                    ->join("__COMPANY__ store",'store.cid = staff.store_id','left')
                    ->join("company_level lv",'lv.id = staff.company_level','left')
                    ->where(function ($query) use ($key_word) {
                        if($key_word){
                            $query->where('real_name', 'like',"%{$key_word}%")->whereor('phone','like',"%{$key_word}%");
                        }
                    })
                    ->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);

        return $this->fetch('staff');
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


    
}