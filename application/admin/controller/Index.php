<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\admin\controller; 
use think\AjaxPage;
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
class Index extends Base {

    public function index(){
        $this->pushVersion();
        $act_list = session('act_list');
        $menu_list = getMenuList($act_list);         
        $this->assign('menu_list',$menu_list);//view
        $admin_info = getAdminInfo(session('admin_id'));
        $order_amount = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();
        $this->assign('order_amount',$order_amount);
        $this->assign('admin_info',$admin_info);             
        $this->assign('menu',getMenuArr());   //view2
        return $this->fetch();
    }
   
    public function welcome(){

    	$this->assign('sys_info',$this->get_sys_info());
        // $today = strtotime("-1 day");
    	$today = strtotime(date("Y-m-d")); // 今天 00:00
        $count['handle_order']  = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();//现金区待确认订单
        $count['handle_order_red']  = M('order_red')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();//米豆区待确认订单
        $count['new_order']     = M('order')->where("add_time>$today or add_time=$today")->count();     //今天现金区新增订单
        $count['new_order_red']     = M('order_red')->where("add_time>$today or add_time=$today")->count();     //今天米豆区新增订单
        $count['goods']         = M('goods')->where("1=1")->count();                  //现金区商品总数
        $count['goods_red']         = M('goods_red')->where("1=1")->count();                  //米豆区商品总数
    	$count['article']       = M('article')->where("1=1")->count();                //文章总数
    	$count['users']         = M('users')->where("1=1")->count();                  //会员总数
    	$count['today_login']   = M('users')->where("last_login>$today or last_login=$today")->count();   //今日访问
    	$count['new_users']     = M('users')->where("reg_time>$today or reg_time=$today")->count();     //新增会员
        $count['comment']       = M('comment')->where("is_show=0 and (add_time>$today or add_time=$today)")->count();          //最新评论
        $members               = M('users')->field('midou_all,midou')->select();     
        foreach($members as  $k=>$v){
                $count['create'] += number_format(floatval($v['midou_all']),3);
                $count['recovery'] += number_format(floatval($v['midou_all'])-floatval($v['midou']),3);
            
        }
        //线上今天新增订单 生成米豆
        $add_order_today = Db::name('order')
                        ->alias('o')
                        ->join('__ORDER_GOODS__ og','o.order_id = og.order_id', 'LEFT')
                        ->where("(o.add_time >$today or o.add_time=$today) and  o.order_status = 2 and o.pay_status = 1")
                        ->select();
        foreach($add_order_today as  $kk=>$vv){
            $midouInfo = returnMidou($vv['goods_id']);
            $today_midou += $midouInfo['midou'];

        }
        
        $staff_mypays_today = M('staff_mypays')->where("(create_time >$today or create_time=$today) and pay_status = 1")->select();
        foreach($staff_mypays_today as  $ks=>$vs){
            $return_midou += $vs['return_midou'];
            
        }
        if($today_midou == 0 || $today_midou =='') $today_midou = 0;
        if($return_midou == 0 || $return_midou =='') $return_midou = 0;
        $count['create_red'] = number_format(floatval($today_midou) + floatval($return_midou),3);
        //今天米豆区完成支付米豆数 回收 
        $order_red_coutent     = M('order_red')->where("(add_time >$today or add_time=$today) and  order_status = 2 and pay_status = 1")->select();
        foreach($order_red_coutent as  $kt=>$vt){
            if($vt['midou'] == 0)  {
                $count['recovery_red'] = 0;
            }else{
                $count['recovery_red'] += number_format(floatval($vt['midou']),3);
            }
        }
    	$this->assign('count',$count);
        return $this->fetch();
    }   
    
    public function get_sys_info(){
		$sys_info['os']             = PHP_OS;
		$sys_info['zlib']           = function_exists('gzclose') ? 'YES' : 'NO';//zlib
		$sys_info['safe_mode']      = (boolean) ini_get('safe_mode') ? 'YES' : 'NO';//safe_mode = Off		
		$sys_info['timezone']       = function_exists("date_default_timezone_get") ? date_default_timezone_get() : "no_timezone";
		$sys_info['curl']			= function_exists('curl_init') ? 'YES' : 'NO';	
		$sys_info['web_server']     = $_SERVER['SERVER_SOFTWARE'];
		$sys_info['phpv']           = phpversion();
		$sys_info['ip'] 			= GetHostByName($_SERVER['SERVER_NAME']);
		$sys_info['fileupload']     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') :'unknown';
		$sys_info['max_ex_time'] 	= @ini_get("max_execution_time").'s'; //脚本最大执行时间
		$sys_info['set_time_limit'] = function_exists("set_time_limit") ? true : false;
		$sys_info['domain'] 		= $_SERVER['HTTP_HOST'];
		$sys_info['memory_limit']   = ini_get('memory_limit');	                                
        $sys_info['version']   	    = file_get_contents(APP_PATH.'admin/conf/version.php');
		$mysqlinfo = Db::query("SELECT VERSION() as version");
		$sys_info['mysql_version']  = $mysqlinfo[0]['version'];
		if(function_exists("gd_info")){
			$gd = gd_info();
			$sys_info['gdinfo'] 	= $gd['GD Version'];
		}else {
			$sys_info['gdinfo'] 	= "未知";
		}
		return $sys_info;
    }
    
    // 在线升级系统
    public function pushVersion()
    {            
        if(!empty($_SESSION['isset_push']))
            return false;    
        $_SESSION['isset_push'] = 1;    
        error_reporting(0);//关闭所有错误报告
        $app_path = dirname($_SERVER['SCRIPT_FILENAME']).'/';
        $version_txt_path = $app_path.'/application/admin/conf/version.php';
        $curent_version = file_get_contents($version_txt_path);

        $vaules = array(            
                'domain'=>$_SERVER['SERVER_NAME'], 
                'last_domain'=>$_SERVER['SERVER_NAME'], 
                'key_num'=>$curent_version, 
                'install_time'=>INSTALL_DATE,
                'serial_number'=>SERIALNUMBER,
         );     
         $url = "http://service.tp-shop.cn/index.php?m=Home&c=Index&a=user_push&".http_build_query($vaules);
         stream_context_set_default(array('http' => array('timeout' => 3)));
         file_get_contents($url);         
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

            #2018-11-21 张洪凯
            #商品每次上架都要更新上架时间
            if($field == 'is_on_sale'){
                if($value == 1){
                    $on_time = time();
                }else{
                    $on_time = 0;
                }
                M($table)->where("$id_name = $id_value")->save(array('on_time'=>$on_time));
            }

            #2018-11-30 张洪凯
            #根据点击的顺序设置排序，后点击的序号大，取消恢复0
            #现金区：
            #is_new  新品推荐
            #is_hot  热销商品
            #is_tao_sell  每日一淘
            #is_hot_sell  优品热吗
            #is_brand_sell 品牌直供
            #米豆区：
            #is_new  新品推荐
            #is_hot  热销商品
            #is_temai  特卖专区
            #is_douding 豆丁专区
            $field_arr = array('is_new','is_hot','is_tao_sell','is_hot_sell','is_brand_sell','is_douding','is_temai','is_yxyp');
            $sort_arr = array('is_new'=>'new_sort','is_hot'=>'hot_sort','is_tao_sell'=>'tao_sort','is_hot_sell'=>'hot_sell_sort','is_brand_sell'=>'brand_sort','is_douding'=>'dou_sort','is_temai'=>'temai_sort','is_yxyp'=>'yxyp_sort');
            if(in_array($field,$field_arr)){
                if($value == 1){
                    $sort_max = db($table)->where([$field=>1])->max($sort_arr[$field]);
                    db($table)->where("$id_name = $id_value")->save([$sort_arr[$field]=>$sort_max+1]);
                }else{
                    db($table)->where("$id_name = $id_value")->save([$sort_arr[$field]=>0]);
                }
            }
            if ($table =='goods' || $table =='goods_red') {
                if ($value=='0') {
                    $check ='否';
                }else{
                    $check ='是';
                }
                if ($field=='is_recommend') {
                    $fieldname='推荐';
                }else if ($field=='is_new') {
                    $fieldname='新品';
                }else if ($field=='is_hot') {
                    $fieldname='热卖';
                }else if ($field=='is_on_sale') {
                    $fieldname='上/下架';
                }else if ($field=='is_allreturn') {
                    $fieldname='福利';
                }else if ($field=='is_tgy_good') {
                    $fieldname='推购';
                }else if ($field=='is_hot_sell') {
                    $fieldname='优热';
                }else if ($field=='is_brand_sell') {
                    $fieldname='品供';
                }else if ($field=='is_tao_sell') {
                    $fieldname = '一淘';
                }else if ($field=='is_temai') {
                        $fieldname='特卖';
                }else if ($field=='store_count') {
                    $fieldname='库存';
                }else{
                    $fieldname='审核';
                }
                if ($table == 'goods') {
                    $shopname ='现金商城';
                }else if($table == 'goods_red'){
                    $shopname ='米豆商城';
                }
                adminLog($shopname.'操作('.$id_name.':'.$id_value.'；字段：'.$fieldname.'；修改:'.$check.')');
            }
    }	    
   

    public function about(){
    	return $this->fetch();
    }
}