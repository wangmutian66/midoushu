<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\member\controller; 
use think\Config;
use think\Cache;
use think\Controller;
use think\Db;
use think\Session;
use think\Cookie;
class Base extends Controller { 

    var $member_id;
   /**
     * 析构函数
     */
    function __construct() 
    {
        Session::start();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        $this->member_id = Session('member.id');
        parent::__construct();
   }    
    
    /*
     * 初始化操作
     */
    public function _initialize() 
    {
        //过滤不需要登陆的行为
        if(in_array(ACTION_NAME,array('login','logout','vertify'))){
            //return;
        }else{
            $cookie_staff_id = Cookie('member.id');
            if($cookie_staff_id && empty($this->staff_id)){
                session('member.id',Cookie('member.id'));
                $save_data = ['last_login'=>time(),'last_ip'=>request()->ip()];
                M('company_member')->where("id = ".session('member.id'))->save($save_data);
                session('member.last_login',$save_data['last_login']);
                session('member.last_ip',$save_data['last_ip']);
            }
            if(session('member.id') > 0 ){
                $this->public_assign();
            }else{
                $not_login = ['forgetpwd','login'];
                if(!in_array(strtolower(ACTION_NAME), $not_login)){
                    $this->error('请先登录',U('/Member/System/login'));
                }
            }
        }
        $this->public_assign();
    }



   /**
     * 保存公告变量到 smarty中 比如 导航 
     */
    public function public_assign()
    {
        $member_info = M('company_member')->cache("member_{$this->member_id}")
                            ->alias('member')
                            ->field('member.*,lv_name level_name,c.cname company_name,level.present_money,level.present_time_start,level.present_time_end,level.service_charge')
                            ->join('company_level level','level.id = member.company_level','left')
                            ->join('company c','c.cid = member.parent_id','left')
                            ->find($this->member_id);
      /*  echo M('staff')->getlastsql();die;
        */
        $config = tpCache('shop_info');

        $member_info['service_charge'] = $config['poundage'];
        //dump($member_info);die;
        $this->assign('member_info',$member_info);
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