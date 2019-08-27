<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\company\controller; 
use think\Config;
use think\Controller;
use think\response\Json;
use think\Db;
use think\Session;
use think\Cookie;
class Base extends Controller { 

    var $company_id;
   /**
     * 析构函数
     */
    function __construct() 
    {
        Session::start();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        $this->company_id = session('company.cid');
        parent::__construct();
   }    
    
    /*
     * 初始化操作
     */
    public function _initialize() 
    {
        //过滤不需要登陆的行为
        if(in_array(ACTION_NAME,array('login','logout','vertify','tree','doLogin'))){
            //return;
        }else{
            $session_id =  session('company.cid');
            if(Cookie('company.cid') && empty($session_id)){
                session('company.cid',Cookie('company.cid'));
                $save_data = ['last_login'=>time(),'last_ip'=>request()->ip()];
                M('company')->where("cid = ".session('company.cid'))->save($save_data);
                session('company.last_login_time',$save_data['last_login']);
                session('company.last_login_ip',$save_data['last_ip']);
            }
            if(session('company.cid') > 0 ){
                $this->check_priv();//检查管理员菜单操作权限
            }else{
                #2018-11-12  张洪凯  忘记密码跳过登录
                $not_login = ['forgetpwd','login'];
                if(!in_array(strtolower(ACTION_NAME), $not_login)){
                    $this->error('请先登录',U('/Company/System/login'),1);
                }
            }
        }
        $this->public_assign();
    }

    /*权限管理，暂未开发*/
    function check_priv(){
        return ;
    }
   

   /**
     * 保存公告变量到 smarty中 比如 导航 
     */
    public function public_assign()
    {
       $company_info = db('company')->cache("company_{$this->company_id}")->find($this->company_id);           $this->assign('company_info',$company_info);
       $tpshop_config = array();
       $tp_config = M('config')->cache(true)->select();
       foreach($tp_config as $k => $v)
       {
          $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
       }
       $this->assign('tpshop_config', $tpshop_config);
    }


    public function ajaxReturn($data,$type = 'json'){                        
        exit(json_encode($data));
    }    
    
}