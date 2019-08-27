<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\staff\controller; 
/*use think\Config;
use think\Controller;
use think\Db;
*/
use think\Cache;
use think\Session;
use think\Cookie;
use app\mobile\controller\MobileBase;
class Base extends MobileBase { 

    var $staff_id;
   
    /*
     * 初始化操作
     */
    public function _initialize() 
    {
        parent::_initialize();
        $this->staff_id = session('staff.id');
        $cookie_staff_id = Cookie('staff.id');
        if($cookie_staff_id && empty($this->staff_id)){
            session('staff.id',Cookie('staff.id'));
            $save_data = ['last_login'=>time(),'last_ip'=>request()->ip()];
            M('staff')->where("id = ".session('staff.id'))->save($save_data);
            session('staff.last_login',$save_data['last_login']);
            session('staff.last_ip',$save_data['last_ip']);
        }
        if($this->staff_id > 0 ){
            $this->staff_public_assign();
        }else{
            $not_login = ['forgetpwd','login'];
            if(!in_array(strtolower(ACTION_NAME), $not_login)){
               $this->error('请先登录',U('/Staff/System/login')); 
            }
        }
    }
   /**
     * 保存公告变量到 smarty中 比如 导航 
     */
    public function staff_public_assign()
    {
        #检测是否有需要支付的代付 过期的让其过期
        $paid_second_cache = Cache::get("pay_status_{$this->staff_id}");
        if($paid_second_cache < (NOW_TIME-86400) || empty($s)){
            $where['staff_id'] = ['eq',$this->staff_id];
            $where['pay_status']    =   ['eq',0];
            db('staff_paid')->where($where)->whereTime('create_time','<','-3 day')->update(['pay_status'=>-1]);
            Cache::set("pay_status_{$staff_id}",time(),86400);
        }

        $staff_info = M('staff')->cache("public_staff_{$this->staff_id}")
                            ->alias('staff')
                            ->field('staff.*,lv_name level_name,store.cname store_name,company.cname company_name,level.service_charge,level.present_money,level.present_time_start,level.present_time_end')
                            ->join('company_level level','level.id = staff.company_level','left')
                            ->join('company store','store.cid = staff.store_id','left')
                            ->join('company company','company.cid = staff.company_id','left')
                            ->find($this->staff_id);
        $this->assign('staff_info',$staff_info);
        $tpshop_config = array();
        $tp_config = M('config')->cache(true)->select();
        foreach($tp_config as $k => $v)
        {
            $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
        }
        $this->assign('tpshop_config', $tpshop_config);

    }
    

}