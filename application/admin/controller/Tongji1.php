<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 *  子公司管理
 */
namespace app\admin\controller; 
use think\Db;
use think\Page;
use think\Cache;
use app\admin\logic\OrderLogic;
class Tongji extends Base {
    public $begin;  // 开始
    public $end;    // 结束
    public function _initialize() {
        parent::_initialize(); 
        $start_time = I('start_time');  // 开始时间
        if(I('start_time')){
           $begin    = urldecode($start_time);
           $end_time = I('end_time');   // 结束时间
           $end      = urldecode($end_time);
        }else{
           $begin = date('Y-m-d', strtotime("-1 month")); 
          #  $begin = date('Y-m-d', strtotime("-3 day")); 
          # $end   = date('Y-m-d', strtotime("+3 day"));  // 1 天后
            $end   = date('Y-m-d', NOW_TIME);;
        }

        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);
        $this->begin = strtotime($begin);
        $this->end   = strtotime($end)+86399;  // 比 24 小时 少 1 s  
    }
    
    // 子公司
    #子公司成员返利统计
    public function index(){
        return $this->fetch();
    }

    /*子公司成员统计*/
    function rebate(){
        $where['c.create_time']   =   ['between',"{$this->begin},{$this->end}"];
        if($company_id = I('get.company_id')){
            $where['m.parent_id']   =   ['eq',$company_id];
        }
        if($store_id = I('get.store_id')){
            $where['m.parent_id']   =   ['eq',$store_id];
        }
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id != 0){
            $store_list = M('company')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
        }
        $res = M("member_commission")->alias('c')
                                    ->field("c.*,FROM_UNIXTIME(c.create_time,'%Y-%m-%d') as gap,m.real_name,member_id,lv_name")
                                    ->join('company_member m',"m.id = c.member_id")
                                    ->join('company company',"company.cid = m.parent_id")
                                    ->join('company_level lv','lv.id = m.company_level')
                                    ->where($where)
                                    ->select();
        $order_counts = M("member_commission")->field("count(DISTINCT(order_id)) count_order,FROM_UNIXTIME(c.create_time,'%Y-%m-%d') as gap")->alias('c')->join('company_member m',"m.id = c.member_id")->where($where)->group('gap')->select_key('gap');

        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $date[] = date('Y-m-d',$i);
            foreach ($res as $key => $value) {
                $date_key = date('Y-m-d',$i);
                if($value['gap'] == $date_key){
                    $dats[$date_key]['money']  +=   $value['money'];
                    $dats[$date_key]['count_order'] = $order_counts[$date_key]['count_order'];
                }
            }
        }
        foreach ($res as $key => $value) {
            $list[$value['member_id']]['level']      = $value['lv_name'];
            $list[$value['member_id']]['member_name']    =   $value['real_name'];
            for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
                $date_key = date('Y-m-d',$i);
                if(!$list[$value['member_id']]['sonlist'][$date_key]){
                    $list[$value['member_id']]['sonlist'][$date_key]  =   0;
                }
                if($value['gap'] == $date_key){
                    $list[$value['member_id']]['sonlist'][$date_key]    +=   $value['money'];
                }
            }
        }
        $this->assign('dats',$dats);
        $this->assign('date',$date);
        $this->assign('list',$list);
        return $this->fetch();
    }
    #成员返利每日详细
    function viewBack(){
        $tims = I('tims');
        $start_time = strtotime($tims);
        $end_time   =   strtotime($tims) + 86400;
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $where['log.create_time']    =   ['between',"{$start_time},{$end_time}"];
        $list = M("member_commission")->alias('log')
                    ->where($where)
                    ->field("log.id,log.create_time,log.info,log.money money,uname,real_name,company.cname as company_name")
                    ->join('__COMPANY_MEMBER__ member','member.id = member_id','left')
                    ->join('__COMPANY__ company','member.parent_id = company.cid','left')
                    ->order("id desc")
                    ->page("$p,$size")
                    ->select();
        $count = M("member_commission")->alias('log')
                ->where($where)
                ->join('__COMPANY_MEMBER__ member','member.id = member_id','left')
                ->join('__COMPANY__ company','member.parent_id = company.cid','left')
                ->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);    
        return $this->fetch('viewBack');
    }

    function staffviewBack(){
        $tims = I('tims');
        $start_time = strtotime($tims);
        $end_time   =   strtotime($tims) + 86400;
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $map['log.create_time']    =   ['between',"{$start_time},{$end_time}"];
        $list = M("staff_commission")->alias('log')
                ->where($map)
                ->field('log.id,log.create_time,log.info,log.money money,uname,real_name,company.cname as company_name,store.cname as store_name')
                ->join('__STAFF__ staff','staff.id = staff_id','left')
                ->join('__COMPANY__ company','staff.company_id = company.cid','left')
                ->join('__COMPANY__ store','staff.store_id = store.cid','left')
                ->order("id desc")
                ->page("$p,$size")
                ->select();
        $count = M("staff_commission")->alias('log')
                ->where($map)
                ->join('__STAFF__ staff','staff.id = staff_id','left')
                ->join('__COMPANY__ company','staff.company_id = company.cid','left')
                ->join('__COMPANY__ store','staff.store_id = store.cid','left')->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);    
        return $this->fetch('staffviewBack');
    }



    /*员工统计*/
    function staff_rebate(){
        $where['c.create_time']   =   ['between',"{$this->begin},{$this->end}"];
        if($company_id = I('get.company_id')){
            $where['company_id']   =   ['eq',$company_id];
        }
        if($store_id = I('get.store_id')){
            $where['store_id']   =   ['eq',$store_id];
        }
        $t == I('get.t/d',0);
        if($t == 1){
            $where['type']  =   ['eq',1];
        }elseif($t == 2){
            $where['type'] == ['eq',2];
        }
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id != 0){
            $store_list = M('company')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
        }
        $res = M("staff_commission")->alias('c')
                                    ->field("c.*,FROM_UNIXTIME(c.create_time,'%Y-%m-%d') as gap,staff.real_name,staff.id staff_id,lv_name,company.cid company,store.cid store_id")
                                    ->join('staff staff',"staff.id = c.staff_id",'left')
                                    ->join('company store',"store.cid = staff.store_id",'left')
                                    ->join('company company',"company.cid = staff.company_id",'left')
                                    ->join('company_level lv','lv.id = staff.company_level','left')
                                    ->where($where)
                                    ->select();
        $order_counts = M("staff_commission")->field("count(DISTINCT(order_id)) count_order,FROM_UNIXTIME(c.create_time,'%Y-%m-%d') as gap")->alias('c')->where($where)->join('staff staff',"staff.id = c.staff_id")->group('gap')->select_key('gap');

        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $date[] = date('Y-m-d',$i);
            foreach ($res as $key => $value) {
                $date_key = date('Y-m-d',$i);
                if($value['gap'] == $date_key){
                    $dats[$date_key]['money']  +=   $value['money'];
                    $dats[$date_key]['count_order'] = $order_counts[$date_key]['count_order'];
                }
            }
        }
    //    dump($res);die;
        foreach ($res as $key => $value) {
            $list[$value['staff_id']]['level']      = $value['lv_name'];
            $list[$value['staff_id']]['member_name']    =   $value['real_name'];
            for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
                $date_key = date('Y-m-d',$i);
                if(!$list[$value['staff_id']]['sonlist'][$date_key]){
                    $list[$value['staff_id']]['sonlist'][$date_key]  =   0;
                }
                if($value['gap'] == $date_key){
                    $list[$value['staff_id']]['sonlist'][$date_key]    +=   $value['money'];
                }
            }
        }
        $this->assign('dats',$dats);
        $this->assign('date',$date);
        $this->assign('list',$list);
        return $this->fetch();

    }

    /*全返统计*/
    function allreturn(){
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id != 0){
            $store_list = M('company')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
        }
        $res = M("previous_log")->alias('p')
                                    ->field("p.*,FROM_UNIXTIME(p.create_time,'%Y-%m-%d') as gap")
                                    ->where($where)
                                    ->select();
        for($i=$this->end;$i>=$this->begin;$i=$i-24*3600){
            $date[] = date('Y-m-d',$i);
            foreach ($res as $key => $value) {
                $date_key = date('Y-m-d',$i);
                if(!$list[$date_key]['money']){
                    $list[$date_key]['money'] = 0;
                }
                if($value['gap'] == $date_key){
                    $list[$date_key]['money']  +=   $value['money'];
                #    $dats[$date_key]['count_order'] = $order_counts[$date_key]['count_order'];
                }
            }
        }
        $this->assign('date',$date);
        $this->assign('list',$list);
        return $this->fetch('allreturn');
    }
    /*查询全返信息*/
    function view_back_tk(){
        $tims = I('tims');
        $start_time = strtotime($tims);
        $end_time   =   strtotime($tims) + 86400;
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $where['p.create_time']    =   ['between',"{$start_time},{$end_time}"];
        $list = M("previous_log")->alias('p')
                                    ->field("p.*,u.mobile,u.nickname,buy_u.mobile buy_mobile,buy_u.nickname buy_name")
                                    ->where($where)
                                    ->join('users u','u.user_id = uid','left')
                                    ->join('users buy_u','buy_u.user_id = buy_uid','left')
                                    ->page("$p,$size")
                                    ->select();
        $count = M("previous_log")->alias('p')->where($where)->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);   
        return $this->fetch('view_back_tk'); 
    }

    function get_source(){
        $order_id = I('get.order_id/d');
        $is_red = I('get.is_red/d',0);
        $where['is_red']    =   ['eq',$is_red];
        $where['order_id']  =   ['eq',$order_id];
        $list = db('previous_log')->where($where)->select();
        $this->assign('list',$list);
        return $this->fetch('get_source');
    }
   
    function return_percentage(){
        // 搜索条件
        $condition = array();
        $keyType  = I("keytype");
        $keywords = I('keywords','','trim');
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        $user_id =  ($keyType && $keyType == 'user_id') ? $keywords : I('user_id','','trim');
        $user_id ? $condition['a.user_id'] = trim($user_id) : false;
        $condition['order_status'] = ['in','2,4'];

        $is_red = I('get.is_red/d',2);
        $rebate_status = I('rebate_status/d',1);
        if($is_red == 2){
            $table_name= 'order';
            $where['is_allreturn']  =   ['eq',1];
            if($rebate_status == 1){
                $condition['(order_amount - shipping_price)']  =   ['exp'," > already_rebate"];
            }else{
                $condition['(order_amount - shipping_price)']  =   ['exp'," <= already_rebate"];
            }
        }else{
            $table_name= 'order_red';
            # $condition['midou_money']   =   ['neq',0];
            $condition['midou_money']   =   ['exp',' > shipping_price and midou_money != 0'];
            if($rebate_status == 1){
                $condition['(midou_money - shipping_price)']  =   ['exp'," > already_rebate"];
            }else{
                $condition['(midou_money - shipping_price)']  =   ['exp'," <= already_rebate"];

            }
        }
        $condition['confirm_time']   =   ['between',"{$this->begin},{$this->end}"];
        $sort_order ='confirm_time asc';
        $limitpage = I('get.limitpage/d',15);
        $count = M($table_name)->alias('a')->where($condition)->count();

        $Page  = new Page($count,$limitpage);
        $show = $Page->show();
        //获取订单列表

        $orderList =  M($table_name)->alias('a')->where($condition)
                        ->limit($Page->firstRow,$Page->listRows)
                        ->field('a.*,staff.store_id,staff.company_id,u.staff_id tgy_id,staff.real_name tgy_name,store.cname store_name,company.cname company_name')
                        ->join('users u','u.user_id = a.user_id','left')
                        ->join('staff staff','staff.id = u.staff_id','left')
                        ->join('company store','store.cid = staff.store_id','left')
                        ->join('company company','company.cid = staff.company_id','left')
                        ->order($sort_order)
                        ->select();

        if($is_red == 2){
            //现金商城
            foreach ($orderList as $key => $value) {
                $orderList[$key]['rebate_price']   =   bcsub($value['order_amount'],$value['shipping_price'],4);        //已返利金额  相减
                if($value['already_rebate'] != 0){
                    $orderList[$key]['progress_bar']   =  bcmul(bcdiv($value['already_rebate'],$orderList[$key]['rebate_price'],9),100,2);
                }else{
                    $orderList[$key]['progress_bar']   =   0;
                }
                
            }
        }else{
            //红包商城
            foreach ($orderList as $key => $value) {
                $orderList[$key]['rebate_price']   =   bcsub($value['midou_money'],$value['shipping_price'],4);
                if($value['already_rebate'] != 0){
                    $orderList[$key]['progress_bar']   =  bcmul(bcdiv($value['already_rebate'],$orderList[$key]['rebate_price'],9),100,2);
                }else{
                    $orderList[$key]['progress_bar']   =   0;
                }
                
            }
        }

        foreach ($orderList as $key => $value) {
            //统计该订单给员工分润
            $map['order_id']    =   ['eq',$value['order_id']];
            $map['order_sn']    =   ['eq',$value['order_sn']];
            $staff_map = $map;
            $staff_map['is_tj'] =   ['eq',0];
            $orderList[$key]['staff_money'] =   db('staff_commission')->where($staff_map)->sum('money');
            $tj_map   =   $map;

            $tj_map['is_tj'] =  ['eq',1]; 
            $orderList[$key]['tj_money']    =   db('staff_commission')->where($tj_map)->sum('money');       //推广员的钱

            if($value['store_id']){
                $store_map = $map;
                $store_map['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$value['store_id']})"];
                $orderList[$key]['store_money']    =   db('member_commission')->where($store_map)->sum('money');     //实体店成员的钱
            }
           
            if($value['company_id']){
                $company_map = $map;
                $company_map['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$value['company_id']})"];
                $orderList[$key]['company_money']    =   db('member_commission')->where($company_map)->sum('money');     //子公司成员的钱
            }else{
                $orderList[$key]['company_money']    =   0;
            }

            #资金池
            $orderList[$key]['zjc_money']   =   db('capital_pool_log')->where(['order_sn'=>['eq',$value['order_sn']]])->getField('money');

            $orderList[$key]['quanfan'] =   db('previous_log')->where(['order_sn'=>['eq',$value['order_sn']]])->sum('money');
        }

        $this->assign('list',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);

        $this->assign('is_red',$is_red);

        return $this->fetch('return_percentage');
    }

    /*获取某笔订单谁吃到返利了  员工层   */
    function get_staff_list(){
        $where['order_id'] = ['eq',I('get.order_id')];
        $where['order_sn'] = ['eq',I('get.order_sn')];
        $where['is_tj'] =   ['eq',0];
        $list = db('staff_commission a')->field('a.*,staff.real_name')->join('staff staff','staff.id = a.staff_id','left')->where($where)->select();
        $this->assign('list',$list);
        return $this->fetch('staff_list');
    }

    /*获取某笔订单谁吃返利了 成员*/
    function get_store_list(){
        $where['order_id'] = ['eq',I('get.order_id')];
        $where['order_sn'] = ['eq',I('get.order_sn')];
     
        if(stripos($where['order_sn'][1],'midou') !== false){
            $table_name = 'order_red o';
        }else{
            $table_name = 'order o';
        }
        $store_id = db($table_name)->where($where)
                    ->join('users u','u.user_id = o.user_id')
                    ->join('staff staff','staff.id = u.staff_id')
                    ->getField('store_id');
        if($store_id){
            $where['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$store_id})"];
            $list   =   db('member_commission a')->field('a.*,member.real_name')
                            ->join('company_member member','member.id = a.member_id','left')
                            ->where($where)
                            ->select();
        }
        
        $this->assign('list',$list);
        return $this->fetch('member_list');
    }
    function get_user_list(){
      #  $where['order_id'] = ['eq',I('get.order_id')];
        $where['order_sn'] = ['eq',I('get.order_sn')];
        $list = db('previous_log a')->where($where)->field('a.*,u.real_name,mobile,nickname')->join('users u','u.user_id = a.uid')->select();
        $this->assign('list',$list);
        return $this->fetch('user_list');
    }

    /*获取某笔订单谁吃返利了 成员*/
    function get_company_list(){
        $where['order_id'] = ['eq',I('get.order_id')];
        $where['order_sn'] = ['eq',I('get.order_sn')];
        if(stripos($where['order_sn'][1],'midou') !== false){
            $table_name = 'order_red o';
        }else{
            $table_name = 'order o';
        }
        $company_id = db($table_name)->where($where)
                    ->join('users u','u.user_id = o.user_id')
                    ->join('staff staff','staff.id = u.staff_id')
                    ->getField('company_id');
        if($company_id){
            $where['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$company_id})"];
            $list   =   db('member_commission a')->field('a.*,member.real_name')
                            ->join('company_member member','member.id = a.member_id','left')
                            ->where($where)
                            ->select();
        }
        
        $this->assign('list',$list);
        return $this->fetch('member_list');
    }

    function error_order(){
        // 搜索条件
        $condition = array();
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        
        $is_red = I('get.is_red/d',2);
        if($is_red == 2){
            $table_name= 'order';
            $condition['is_allreturn']  =['eq',1];
        }else{
            $table_name= 'order_red';
        }

        $rebate_status = I('get.rebate_status/d',0);
        if($rebate_status == 2){
            $condition['rebate_status'] =   ['eq',0];
        }elseif($rebate_status == -1){
            $condition['rebate_status'] =   ['lt',0];
        }elseif($rebate_status == 1){
            $condition['rebate_status'] =   ['eq',1];
        }

        $condition['confirm_time']   =   ['between',"{$this->begin},{$this->end}"];
        $condition['order_status']  =   ['in','2,4'];
        
        $sort_order ='confirm_time asc';
        $count = M($table_name)->where($condition)->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        //获取订单列表
        $orderList =  M($table_name)->where($condition)
                        ->limit($Page->firstRow,$Page->listRows)
                        ->order($sort_order)
                        ->select();
    /*    echo M($table_name)->getlastsql();die;*/
        foreach ($orderList as $key => $value) {
            $cost_sum = $value['tk_cost_price'] + $value['tk_cost_operating']; 
            if($is_red == 2){
                $orderList[$key]['profits_money'] = bcsub(bcsub($value['order_amount'],$cost_sum,9),$value['shipping_price'],9);
            }else{
                $orderList[$key]['profits_money'] = bcsub(bcsub($value['midou_money'],$cost_sum,9),$value['shipping_price'],9);
            }
        }
      /*  dump($orderList);
        die;*/
        $this->assign('list',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);

    #    $this->assign('is_red',$is_red);

        return $this->fetch('error_order');
    }


    function initialize_order(){
        return $this->fetch('initialize_order');
    }

    function initialize_start(){
        $p = I('p/d',1);
        $size = 1000;
        $location = ($p - 1) * $size;
        if($p == 1){
        //     tp_account_log;
            Db::execute("truncate table tp_list_order");
            cache::rm('order_initialize');
            cache::rm('order_red_initialize');
            echo '初始化开始...........';
        }
        $where['order_status']  =   ['in','2,4'];
        $where['already_rebate']    =   ['exp'," < (order_amount - shipping_price)"];
        $where['is_allreturn']  =['eq',1];
        $previous_order_list = M('order o')->field('order_id,user_id,order_amount,shipping_price,already_rebate,add_time,confirm_time,order_sn')
                                ->where($where)
                                ->cache('order_initialize',600)
                                ->select();
       
        $i = 1;
        foreach ($previous_order_list as $key => $value) {
            $previous_order_list[$key]['xh']    =   $i;
            $i++;
        }
        $count = count($previous_order_list);
        $limit_list = array_slice($previous_order_list,$location,$size);
        if(empty($limit_list)){
            echo "总数 {$count} ,数据初始化完毕。";
        }else{
            foreach ($limit_list as $key => $value) {
                $insert_sql[]   =   ['order_id'=>$value['order_id'],
                                    'order_sn'=>$value['order_sn'],
                                    'user_id'=>$value['user_id'],
                                    'order_amount'=>$value['order_amount'],
                                    'shipping_price'=>$value['shipping_price'],
                                    'already_rebate'=>$value['already_rebate'],
                                    'add_time'=>$value['add_time'],
                                    'confirm_time'=>$value['confirm_time'], // 2018.07.25 liyi add
                                    'midou_money'=>$value['midou_money'],
                                    'is_red'=>$value['is_red'],
                                    'create_time'=>NOW_TIME,
                                    'xh'    =>  $value['xh'],
                                    ];
            }
            db('list_order')->insertAll($insert_sql);
            $insert_count   = $p * $size;
            $p++;
            $url = U('/Admin/Tongji/initialize_start',['p'=>$p]);
            echo "总数 {$count} ,已插入数据 {$insert_count},正在跳转...请稍后..<script>location.href='{$url}';</script>";
        }
        
    }


    function xh(){
        $tims = NOW_TIME;

        /*$start_time = strtotime();
        $end_time   =   strtotime($this->end_time) + 86400;*/
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $where['confirm_time']    =   ['between',"{$this->begin},{$this->end}"];
        $limitpage = I('get.limitpage/d',15);
        $count = M('list_order')->where($where)->count();

        $Page  = new Page($count,$limitpage);
        $show = $Page->show();
        $orderList = M('list_order a')
                    ->where($where)
                    ->field('a.*,staff.store_id,staff.company_id,u.staff_id tgy_id,staff.real_name tgy_name,store.cname store_name,company.cname company_name')
                    ->join('users u','u.user_id = a.user_id','left')
                    ->join('staff staff','staff.id = u.staff_id','left')
                    ->join('company store','store.cid = staff.store_id','left')
                    ->join('company company','company.cid = staff.company_id','left')
                    ->limit($Page->firstRow,$Page->listRows)
                    ->select();
        foreach ($orderList as $key => $value) {
            if($value['is_red'] != 1){
                $orderList[$key]['rebate_price']   =   bcsub($value['order_amount'],$value['shipping_price'],4);        //已返利金额  相减
                if($value['already_rebate'] != 0){
                    $orderList[$key]['progress_bar']   =  bcmul(bcdiv($value['already_rebate'],$orderList[$key]['rebate_price'],9),100,2);
                }else{
                    $orderList[$key]['progress_bar']   =   0;
                }
            }else{
                $orderList[$key]['rebate_price']   =   bcsub($value['midou_money'],$value['shipping_price'],4);
                if($value['already_rebate'] != 0){
                    $orderList[$key]['progress_bar']   =  bcmul(bcdiv($value['already_rebate'],$orderList[$key]['rebate_price'],9),100,2);
                }else{
                    $orderList[$key]['progress_bar']   =   0;
                }
            }
            
            //统计该订单给员工分润
            $map['order_id']    =   ['eq',$value['order_id']];
            $map['order_sn']    =   ['eq',$value['order_sn']];
            $staff_map = $map;
            $staff_map['is_tj'] =   ['eq',0];
            $orderList[$key]['staff_money'] =   db('staff_commission')->where($staff_map)->sum('money');
            $tj_map   =   $map;

            $tj_map['is_tj'] =  ['eq',1]; 
            $orderList[$key]['tj_money']    =   db('staff_commission')->where($tj_map)->sum('money');       //推广员的钱

            if($value['store_id']){
                $store_map = $map;
                $store_map['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$value['store_id']})"];
                $orderList[$key]['store_money']    =   db('member_commission')->where($store_map)->sum('money');     //实体店成员的钱
            }
           
            if($value['company_id']){
                $company_map = $map;
                $company_map['member_id'] = ['exp'," in (select id from tp_company_member where parent_id = {$value['company_id']})"];
                $orderList[$key]['company_money']    =   db('member_commission')->where($company_map)->sum('money');     //子公司成员的钱
            }else{
                $orderList[$key]['company_money']    =   0;
            }

            #资金池
            $orderList[$key]['zjc_money'] = db('capital_pool_log')->where(['order_sn'=>['eq',$value['order_sn']]])->getField('money');

            $orderList[$key]['quanfan'] = db('previous_log')->where(['order_sn'=>['eq',$value['order_sn']]])->sum('money');
        }
        $this->assign('list',$orderList);
        $this->assign('page',$show);
        $this->assign('count',$count);
        return $this->fetch('xh');
    }


    /*对二维数组进行冒泡排序*/
    function bubble_sort($list,$column='confirm_time'){
        #一个思路 将add_time 放入建值中   然后 用 array_multisort ， 嘛 不过好像不行   统一时间的订单很容易冲突
        foreach($list as $key => $value){
            $tims[] = $value[$column];
        }
        array_multisort($tims, SORT_ASC,$list);
        return $list;
    }
}