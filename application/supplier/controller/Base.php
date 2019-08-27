<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\supplier\controller; 
use app\common\logic\MessageLogic;
use think\Controller;
use think\Db;
use think\response\Json;
use think\Session;
class Base extends Controller { 

   /**
     * 析构函数
     */
    function __construct() 
    {
        Session::start();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        parent::__construct();
    } 

    var $suppliers_id;

    /*
     * 初始化操作
     */
    public function _initialize() 
    {
        //过滤不需要登陆的行为
        if(in_array(ACTION_NAME,array('login','logout','vertify','forget_pwd','set_pwd','finished'))){
            //return;
        }else{
            if(session('suppliers.suppliers_id') > 0 ){
                $this->suppliers_id = Session('suppliers.suppliers_id');
                $suppliersInfo = M('suppliers')->where('suppliers_id',$this->suppliers_id)->find();
                if($suppliersInfo)  Session('suppliers',$suppliersInfo);
                $suppliers_log_old = M('suppliers_log')->where('suppliers_id='.$this->suppliers_id)->order('id desc')->limit(1,1)->select();
                $suppliers_log = M('suppliers_log')->where('suppliers_id='.$this->suppliers_id)->order('id desc')->find();
                $this->assign('suppliers_log_old', $suppliers_log_old);  
                $this->assign('suppliers_log', $suppliers_log);

                $this->suppliers_id = Session('suppliers.suppliers_id');
                $this->assign('suppliers_id',$this->suppliers_id);
            }else{
                $this->error('请先登录',U('Supplier/Supplier/login'),1);
            }
        }
        $this->public_assign();
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
        $this->assign('tpshop_config', $tpshop_config);
        if(session('?suppliers'))
        {
            $handle_order = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod') and suppliers_id=".$this->suppliers_id)->count();//待处理订单 
            $this->assign('handle_order',$handle_order);    

            $msg_num = M('suppliers_message')->where("status=0 and suppliers_id=".$this->suppliers_id)->count();//待处理订单 
            $this->assign('msg_num',$msg_num);         
        }
                
    }


    public function ajaxReturn($data,$type = 'json'){                        
            exit(json_encode($data));
    }    
    
}