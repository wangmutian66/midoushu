<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 个人学习免费, 如果商业用途务必到TPshop官网购买授权.
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */ 
namespace app\home\controller;
use app\common\logic\CommentLogic;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use think\Controller;
use think\Db;
use think\Session;

class ReturnBase extends Controller {
    public $session_id;
    public $cateTrre = array();
    public $user;
    public $user_id;
    public $tpshop_config;
    /*
     * 初始化操作
     */
    public function _initialize() {
        if (input("unique_id")) {           // 兼容手机app
            session_id(input("unique_id"));
            Session::start();
        }
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
    	  $this->session_id = session_id(); // 当前的 session_id
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用
        
        // 判断当前用户是否手机                
        if(isMobile())
            cookie('is_mobile','1',3600); 
        else 
            cookie('is_mobile','0',3600);
             
        $this->public_assign();
        $this->new_goods();

        // liyi 2018.05.09
        if(session('?user'))
        {
            $user = $user_old = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user',$user);  //覆盖session 中的 user               
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $logic = new UsersLogic();
            $user = $logic->get_info($this->user_id);
            $user = $user['result'];
            // 待评论数
            $commentLogic = new CommentLogic;
            $com_num      = $commentLogic->getWaitCommentNum($this->user_id); //待评论数
            $this->assign('com_num',$com_num);
            // 待支付数
            $orderLogic  = new OrderLogic;
            $waitpay_num = $orderLogic->getWaitPayOrderNum($this->user_id);
            $this->assign('waitpay_num',$waitpay_num);
            // 待发货数
            $waitpost_num = $orderLogic->getWaitPostOrderNum($this->user_id);
            $this->assign('waitpost_num',$waitpost_num);
            $waitreceive_num = $orderLogic->getWaitReceiveOrderNum($this->user_id);
            $this->assign('waitreceive_num',$waitreceive_num);
            $this->assign('user',$user);
        }

    }
    /**
     * 保存公告变量到 smarty中 比如 导航 
     */
    public function public_assign()
    {
        $tpshop_config = array();
        $tp_config = M('config')->cache(true,TPSHOP_CACHE_TIME)->select();       
        foreach($tp_config as $k => $v)
        {
           	if($v['name'] == 'hot_keywords'){
           	  	$tpshop_config['hot_keywords'] = explode('|', $v['value']);
           	}       	  
            $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
        } 

        $this->tpshop_config = $tpshop_config;                       
       
        $goods_category_tree = get_goods_return_category_tree();  
        $this->cateTrre = $goods_category_tree;
        $this->assign('goods_category_tree', $goods_category_tree);                     
        $brand_list = M('brand')->cache(true)->field('id,name,parent_cat_id,logo,is_hot')->where("parent_cat_id>0")->select();
        $this->assign('brand_list', $brand_list);
        $this->assign('tpshop_config', $this->tpshop_config);
        $user = session('user');
        $this->assign('username',$user['nickname']);

        /*客服相关*/
        if(ACTION_NAME == 'goodsInfo'){
            $goods_id = I('id/d');
            $suppliers_id = db('goods')->where(['goods_id'=>$goods_id])->value('suppliers_id');
            $chat_group_id = db('suppliers')->where(['suppliers_id'=>$suppliers_id])->value('chat_group_id');   //查询默认分组
            if(!$chat_group_id){
                //供货商没有设置客服
                $chat_group_id = db('chat_group')->where("is_default = 1")->getField('id');
            }
            $this->assign('chat_group_id',$chat_group_id);
            //查询该分组下面有没有在线的客服
            $user_list = db('users')->where("chat_group_id = {$chat_group_id} and is_line = 1")->field('user_id')->select(); 
            $this->assign('is_default_chat',(empty($user_list)?0:1));
        }
        

    }

    // 新品推荐
    public function new_goods()
    {
        $newmap['is_on_sale'] = 1;
        $newmap['is_new']     = 1;
        $countcus = M('goods')->cache(true)->order("new_sort desc")->where($newmap)->count();  //获取总记录数
        $max_num = $countcus-3;
        if($max_num > 0) $max_num; else $max_num=0;
        $startnum = rand(0,$max_num);
        $newgoodsLists = M('goods')->cache(true)->where($newmap)->limit($startnum.',3')->select();
        $this->assign('newgoodsLists',$newgoodsLists);
    }

    /*
     * 
     */
    public function ajaxReturn($data)
    {
        exit(json_encode($data));
    }
}