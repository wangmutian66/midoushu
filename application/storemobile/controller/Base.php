<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\storemobile\controller; 
use think\Config;
use think\Controller;
use think\response\Json;
use think\Db;
use think\Session;
use think\Cookie;
class Base extends Controller { 
    var $company_id;
    var $store_id;
   /**
     * 析构函数
     */
    function __construct() 
    {
        Session::start();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        $this->store_id = session('store.cid');
        parent::__construct();
        $this->getMenus();
   }    
    
    /*
     * 初始化操作
     */
    public function _initialize() 
    {
        //过滤不需要登陆的行为
        if(in_array(ACTION_NAME,array('login','logout','doLogin'))){
            //return;
        }else{
            if(Cookie('store.cid') && empty($this->store_id)){
                session('store.cid',Cookie('store.cid'));
                $save_data = ['last_login'=>time(),'last_ip'=>request()->ip()];
                M('company')->where("cid = ".$this->store_id)->save($save_data);
                session('store.last_login_time',$save_data['last_login']);
                session('store.last_login_ip',$save_data['last_ip']);
            }
            if(session('store.cid') > 0 ){
                $this->check_priv();//检查管理员菜单操作权限
            }else{
                $this->error('请先登录',U('/Storemobile/System/login'),1);
            }
        }
        $this->public_assign();
    }

    /*权限管理，暂未开发*/
    function check_priv1(){
        return ;
    }
   

   /**
     * 保存公告变量到 smarty中 比如 导航 
     */
    public function public_assign()
    {
        $store_info = db('company')->cache("store_{$this->store_id}")->find($this->store_id);
      //  dump($store_info);die;
        $this->assign('store_info',$store_info);
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



    function getMenus(){
        $menuArr = include APP_PATH.'storemobile/conf/menu.php';
        $act_list = session('act_list');
        $right = M('store_system_menu')->where("id", "in", $act_list)->cache(true)->getField('right',true);
        $storeMenus = $menuArr["storeMenu"];
        $r_str=implode(',',$right);
        $right = strtolower($r_str);
        $right=explode(',',$right);


        if($act_list != "all"){
            foreach ($storeMenus as $k=>$row){
                $url = str_replace('/Storemobile/','',$row["url"]);
                $url = str_replace('/Storemobile/','',$url);
                $urlarr = explode("/",$url);
                $urlarr1 =  strtolower($urlarr[0]."@".$urlarr[1]);
                if((!in_array($urlarr1,$right)) && $row['default'] == 0){
                    unset($storeMenus[$k]);
                }
            }
        }

        $this->assign("menu",$storeMenus);

    }


    public function check_priv()
    {
        $ctl = CONTROLLER_NAME;
        $act = ACTION_NAME;
        $act_list = session('act_list');
        //无需验证的操作
        $uneed_check = array('index','Logout','login','vertifyHandle','vertify','imageUp','upload','login_task','Statistics');
        //$right = M('store_system_menu')->where("id", "in", $act_list)->cache(true)->getField('right',true);

        if($ctl == 'Index' || $act_list == 'all'){
            //后台首页控制器无需验证,超级管理员无需验证
            return true;
        }elseif(request()->isAjax() || strpos($act,'ajax')!== false || in_array($act,$uneed_check)){

            //所有ajax请求不需要验证权限
            return true;
        }else{

            $right = M('store_system_menu')->where("id", "in", $act_list)->cache(true)->getField('right',true);

            $role_right = "";
            foreach ($right as $val){
                $role_right .= $val.',';
            }
            $role_right = explode(',', $role_right);
            //检查是否拥有此操作权限
            $ctilct = strtolower($ctl.'@'.$act);
            $role_right_str=implode(',',$role_right);
            $right_str = strtolower($role_right_str);
            $right=explode(',',$right_str);

            if(!in_array($ctilct, $right) && $ctilct != "system@statistics" ){



                $this->error('您没有操作权限['.($ctl.'@'.$act).'],请联系超级管理员分配权限'); //,U('/Store/'.$rightMenus[0].'/'.$rightMenus[1])
            }
        }
    }
}