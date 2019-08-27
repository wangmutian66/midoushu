<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\supplier\controller; 
use think\AjaxPage;
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
use think\Db;
class Index extends Base {

	var $suppliers_id;
	/**
     * 析构函数
     */
    function _initialize() 
    {
    	$this->suppliers_id = Session('suppliers.suppliers_id');
        parent::_initialize();
    } 

    public function index(){
        return $this->fetch();
    }

    public function welcome(){
        $suppliers_id = $this->suppliers_id;
        // $today = strtotime("-1 day");
        $today = strtotime(date("Y-m-d"));
        $count['over_order']    = M('order')->where("order_status in (2,4) and (pay_status=1 or pay_code='cod') and suppliers_id=".$suppliers_id)->count();//已完成订单
        $count['handle_order']  = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod') and suppliers_id=".$suppliers_id)->count();//待确认订单
        $count['new_order']     = M('order')->where("add_time>=$today and suppliers_id=".$suppliers_id)->count();//今天新增订单
        $count['goods']         = M('goods')->where("1=1 and suppliers_id=".$suppliers_id)->count();//商品总数
        $count['goods_is_sale'] = M('goods')->where("1=1 and is_on_sale=1 and suppliers_id=".$suppliers_id)->count(); //已上架商品数
        $count['comment']       = M('comment')->where("is_show=0 and suppliers_id=".$suppliers_id)->count();//最新评论
        $count['comment_z']     = M('comment')->where("suppliers_id=".$suppliers_id)->count();//最新评论

        $noticeList = M('article_notice')->where('(article_type = 0 OR article_type = 2) AND is_open = 1')->limit(5)->select();


        $order_new = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod') and suppliers_id=".$suppliers_id)->limit(4)->select();
        $this->assign('order_new',$order_new);
        $this->assign('count',$count);
        $this->assign('noticeList',$noticeList);
        return $this->fetch();
    }
   
    /**
     * ajax 修改指定表数据字段  一般修改状态 比如 是否推荐 是否开启 等 图标切换的
     * table,id_name,id_value,field,value
     */
    public function changeTableVal(){  
        $table    = I('table'); // 表名
        $id_name  = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field    = I('field'); // 修改哪个字段
        $value    = I('value'); // 修改字段值                        
        M($table)->where("$id_name = $id_value")->save(array($field=>$value)); // 根据条件保存修改的数据
    }       
   
}