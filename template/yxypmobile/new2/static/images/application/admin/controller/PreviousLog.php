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

class PreviousLog extends Base {
    
    public function _initialize() {
        parent::_initialize();        
    }
    
    public function index(){
        $res   = $list = array();
        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $where = " order_id != 0 ";
        $list  = M('PreviousLog')->alias('plog')->field('*,count(id) back_sum')
                        ->where($where)
                        ->group('order_id')
                        ->page("$p,$size")
                        ->select();
        if($list){
            $list = $this->bubble_sort($list,'create_time');
       
                
        }
        $count = M('PreviousLog')->where($where)->group('order_id')->count(); // 查询满足要求的总记录数
            
        $pager = new Page($count,$size); // 实例化分页类 传入总记录数和每页显示的记录数
        $this->assign('list',$list);     // 赋值数据集
        $this->assign('pager',$pager);   // 赋值分页输出    
        return $this->fetch();
       
    }

    /*对二维数组进行冒泡排序*/
    function bubble_sort($list,$column='add_time'){
        #一个思路 将add_time 放入建值中   然后 用 array_multisort ， 嘛 不过好像不行   统一时间的订单很容易冲突
        foreach($list as $key => $value){
            $tims[] = $value[$column];
        }
        array_multisort($tims, SORT_ASC,$list);
        return $list;
    }

    public function view(){
        $order_id = I('post.order_id/d');
        if($order_id){
            $table_model = M('PreviousLog');
            $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
            $size = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
            $list = $table_model
                ->alias('plog')
                ->field('plog.*,u.nickname,u.user_id,u.mobile,user.nickname ni,user.user_id us,user.mobile mo')
                ->join('__USERS__ u ','plog.uid = u.user_id')
                ->join('__USERS__ user ','plog.buy_uid = user.user_id')
                ->where("plog.order_id = {$order_id}")
                ->page("$p,$size")
                ->select();
            $str = '';
            $count = $table_model
                        ->alias('plog')
                        ->field('plog.*,u.nickname,u.user_id,u.mobile')
                        ->join('__USERS__ u ','plog.uid = u.user_id')
                        ->where("plog.order_id = {$order_id}")->count();
            $pager = new Page($count,$size);// 实例化分页类 传入总记录数和每页显示的记录数
            //$page = $pager->show();//分页显示输出
            $this->assign('list',$list);// 赋值数据集
            $this->assign('pager',$pager);// 赋值分页输出
            return $this->fetch();
        }else{
            $this->error('订单ID获取失败');
        }
        
    }
    


}