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
class Article extends Base {

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
        $Article =  M('Article_notice'); 
        $res  = $list = array();
        $p    = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        
        $where = " 1 = 1 and is_open = 1 ";
        $keywords = trim(I('keywords'));
        $keywords && $where.=" and title like '%$keywords%' ";
        $res   = $Article->where($where)->order('article_id desc')->page("$p,$size")->select();
        $count = $Article->where($where)->count(); // 查询满足要求的总记录数
        $pager = new Page($count,$size);           // 实例化分页类 传入总记录数和每页显示的记录数
        //$page = $pager->show();//分页显示输出
        $typeArr = array('全部可见','仅会员可见','仅供货商可见','仅子公司可见','仅员工可见','仅推广员可见');
        if($res){
            foreach ($res as $val){
                $val['article_type'] = $typeArr[$val['article_type']];
                $val['add_time'] = date('Y-m-d H:i:s',$val['add_time']);                
                $list[] = $val;
            }
        }
        $this->assign('cats',$cats);
        $this->assign('cat_id',$cat_id);
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pager);// 赋值分页输出        
        return $this->fetch();
    }


    public function detail(){
        $info = array();
        $info['publish_time'] = time()+3600*24;
        if(I('GET.article_id')){
           $article_id = I('GET.article_id');
           $info = M('article_notice')->where('article_id='.$article_id)->find();
        }
        $this->assign('info',$info);
        return $this->fetch();
    }
    
   
}