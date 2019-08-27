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
        $where['p.create_time']   =   ['between',"{$this->begin},{$this->end}"];
        if($company_id = I('get.company_id')){
            $where['staff.company_id']   =   ['eq',$company_id];
        }
        if($store_id = I('get.store_id')){
            $where['staff.store_id']   =   ['eq',$store_id];
        }
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id != 0){
            $store_list = M('company')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
        }
        $res = M("previous_log")->alias('p')
                                    ->field("p.*,FROM_UNIXTIME(p.create_time,'%Y-%m-%d') as gap")
                                    ->join('order ord','ord.order_id=p.order_id','left')
                                    ->join('users users','users.user_id=ord.user_id','left')
                                    ->join('staff staff','staff.id=users.staff_id','left')
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
        //2018-09-26 李鑫 修改模糊查询
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        // $consignee ? $condition['consignee'] = trim($consignee) : false;
        $consignee ? $condition['consignee'] = array('like',"%$consignee%") : false;
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        // $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        $order_sn ? $condition['order_sn'] = array('like',"%$order_sn%") : false;
        $user_id =  ($keyType && $keyType == 'user_id') ? $keywords : I('user_id','','trim');
        // $user_id ? $condition['a.user_id'] = trim($user_id) : false;
        $user_id ? $condition['a.user_id'] = array('like',"%$user_id%") : false;
        //修改结束
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

        $order_id = $order_sn = [];
        foreach ($orderList as $k=>$value){
            $order_id[] = $value['order_id'];
            $order_sn[] = $value['order_sn'];
        }
        $w['order_sn'] = ['in',implode(",",$order_sn)];
        $w['create_time']   =   ['between',"{$this->begin},{$this->end}"];

        $staff_commission = db('staff_commission')->where($w)->select();

        $previous_log = db('previous_log')->field("order_sn,money")->where($w)->select();//时间

        $capital_pool_log = db('capital_pool_log')->field("order_sn,money")->select();

        $member_commission = db('member_commission')->where($w)->select();

        $company_member = db('company_member')->field("id,parent_id")->select();

        foreach ($orderList as $key => $value) {
            //统计该订单给员工分润
            $map['order_id']    =   ['eq',$value['order_id']];
            $map['order_sn']    =   ['eq',$value['order_sn']];
            $staff_map = $map;
            $staff_map['is_tj'] =   ['eq',0];
            $orderList[$key]['staff_money'] =   $this->arrayQuerySum($staff_commission,$staff_map,'money');
            $tj_map   =   $map;
            $tj_map['is_tj'] =  ['eq',1];
            $orderList[$key]['tj_money'] =   $this->arrayQuerySum($staff_commission,$tj_map,'money');//推广员的钱
            if($value['store_id']){
                $orderList[$key]['store_money']    =  $this->arrayQuerySum($member_commission,$map,'money',$company_member,$value['store_id']);  //实体店成员的钱
            }
            if($value['company_id']){

                $orderList[$key]['company_money']    =  $this->arrayQuerySum($member_commission,$map,'money',$company_member,$value['company_id']);

            }else{
                $orderList[$key]['company_money']    =   0;
            }

            #资金池
            $orderList[$key]['zjc_money'] =   $this->arrayQuerySum($capital_pool_log,['order_sn'=>['eq',$value['order_sn']]],'money');
            $orderList[$key]['quanfan'] =   $this->arrayQuerySum($previous_log,['order_sn'=>['eq',$value['order_sn']]],'money');
        }



        preg_match_all('/^m\=/is',$_SERVER['QUERY_STRING'],$res);
        $queryString = "";
        if(empty($res[0])){
            $queryString = $_SERVER['QUERY_STRING'];
        }

        $resultUrl = $_SERVER['REQUEST_URI'];
        preg_match_all('/'.ACTION_NAME.'(.*?)(p\/\d+)?$/is',$resultUrl,$res1);

        $this->assign('ajaxdata',empty($res1[1][0])?"":$res1[1][0]);
        $this->assign('list',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        $this->assign('is_red',$is_red);

        return $this->fetch('return_percentage');
    }






//    public function return_percentage_export(){
//
//        //file_put_contents("./public/log/previousLog_.txt","1123456");
//        //exit();
//        ini_set ('memory_limit', '500M') ;
//        set_time_limit(0);
//        // 搜索条件
//        $condition = array();
//        $keyType  = I("keytype");
//        $keywords = I('keywords','','trim');
//        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
//        $consignee ? $condition['consignee'] = trim($consignee) : false;
//        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
//        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
//        $user_id =  ($keyType && $keyType == 'user_id') ? $keywords : I('user_id','','trim');
//        $user_id ? $condition['a.user_id'] = trim($user_id) : false;
//        $condition['order_status'] = ['in','2,4'];
//
//        $is_red = I('get.is_red/d',2);
//        $rebate_status = I('rebate_status/d',1);
//
//        if($is_red == 2){
//            $table_name= 'order';
//            $where['is_allreturn']  =   ['eq',1];
//            if($rebate_status == 1){
//                $condition['(order_amount - shipping_price)']  =   ['exp'," > already_rebate"];
//            }else{
//                $condition['(order_amount - shipping_price)']  =   ['exp'," <= already_rebate"];
//            }
//        }else{
//            $table_name= 'order_red';
//            # $condition['midou_money']   =   ['neq',0];
//            $condition['midou_money']   =   ['exp',' > shipping_price and midou_money != 0'];
//            if($rebate_status == 1){
//                $condition['(midou_money - shipping_price)']  =   ['exp'," > already_rebate"];
//            }else{
//                $condition['(midou_money - shipping_price)']  =   ['exp'," <= already_rebate"];
//
//            }
//        }
//        $condition['confirm_time']   =   ['between',"{$this->begin},{$this->end}"];
//        $sort_order ='confirm_time asc';
//        //获取订单列表
//        $orderList =  M($table_name)->alias('a')->where($condition)
//            ->field('a.*,staff.store_id,staff.company_id,u.staff_id tgy_id,staff.real_name tgy_name,store.cname store_name,company.cname company_name')
//            ->join('users u','u.user_id = a.user_id','left')
//            ->join('staff staff','staff.id = u.staff_id','left')
//            ->join('company store','store.cid = staff.store_id','left')
//            ->join('company company','company.cid = staff.company_id','left')
//            ->order($sort_order)
//            ->select();
//        $order_id = $order_sn = [];
//        if($is_red == 2){
//            //现金商城
//            foreach ($orderList as $key => $value) {
//                $order_id[] = $value['order_id'];
//                $order_sn[] = $value['order_sn'];
//                $orderList[$key]['rebate_price']   =   bcsub($value['order_amount'],$value['shipping_price'],4);        //已返利金额  相减
//                if($value['already_rebate'] != 0){
//                    $orderList[$key]['progress_bar']   =  bcmul(bcdiv($value['already_rebate'],$orderList[$key]['rebate_price'],9),100,2);
//                }else{
//                    $orderList[$key]['progress_bar']   =   0;
//                }
//
//            }
//        }else{
//            //红包商城
//            foreach ($orderList as $key => $value) {
//                $order_id[] = $value['order_id'];
//                $order_sn[] = $value['order_sn'];
//                $orderList[$key]['rebate_price']   =   bcsub($value['midou_money'],$value['shipping_price'],4);
//                if($value['already_rebate'] != 0){
//                    $orderList[$key]['progress_bar']   =  bcmul(bcdiv($value['already_rebate'],$orderList[$key]['rebate_price'],9),100,2);
//                }else{
//                    $orderList[$key]['progress_bar']   =   0;
//                }
//
//            }
//        }
//
//        $w['order_id'] = ['in',$order_id];
//        $w['order_sn'] = ['in',$order_sn];
//        $w['create_time']   =   ['between',"{$this->begin},{$this->end}"];
//
//        $staff_commission = db('staff_commission')->where($w)->field("id,is_tj,create_time")->select();
//        $capital_pool_log = db('capital_pool_log')->cache(true,7200)->field("order_sn,money")->select();
//        $member_commission = db('member_commission')->cache(true,7200)->where($w)->select();
//        unset($w['order_id']);
//        unset($w['order_sn']);
//        $company_member = db('company_member')->field("id,parent_id")->where($w)->select();
//
//        foreach ($orderList as $key => $value) {
//            //统计该订单给员工分润
//            $map['order_id']    =   ['eq',$value['order_id']];
//            $map['order_sn']    =   ['eq',$value['order_sn']];
//            $staff_map = $map;
//            $staff_map['is_tj'] =   ['eq',0];
//            $orderList[$key]['staff_money'] =   $this->arrayQuerySum($staff_commission,$staff_map,'money');
//
//            $tj_map   =   $map;
//            $tj_map['is_tj'] =  ['eq',1];
//
//            $orderList[$key]['tj_money'] =   $this->arrayQuerySum($staff_commission,$tj_map,'money');//推广员的钱
//            if($value['store_id']){
//
//                $orderList[$key]['store_money']    =  $this->arrayQuerySum($member_commission,$map,'money',$company_member,$value['store_id']);  //实体店成员的钱
//            }
//            if($value['company_id']){
//                $orderList[$key]['company_money']    =  $this->arrayQuerySum($member_commission,$map,'money',$company_member,$value['company_id']);
//
//            }else{
//                $orderList[$key]['company_money']    =   0;
//            }
//
//            #资金池
//            if($capital_pool_log){
//                $orderList[$key]['zjc_money'] =   $this->arrayQuerySum($capital_pool_log,['order_sn'=>['eq',$value['order_sn']]],'money');
//            }else{
//                $orderList[$key]['zjc_money'] =  0;
//            }
//
//            if($previous_log){
//                $orderList[$key]['quanfan'] =   $this->arrayQuerySum($previous_log,['order_sn'=>['eq',$value['order_sn']]],'money');
//            }else{
//                $orderList[$key]['quanfan'] =   0;
//            }
//
//        }
//
//        $strTable ='<table width="1000" border="1">';
//        $strTable .= '<tr>';
//        $strTable .= '<td>订单编号</td><td>确认收货时间</td><td>是否福利</td><td>分红总额</td><td>已反金额</td><td>推广金额</td><td>推广姓名</td><td>员工金额</td><td>实体店</td><td>实体店金额</td><td>子公司</td><td>子公司金额</td><td>资金池</td><td>全返</td><td>进度</td>';
//        $strTable .= '</tr>';
//
//        foreach ($orderList as $row){
//            $strTable .= '<tr>';
//            $strTable .= "<td>{$row["order_sn"]}</td><td>".date('Y-m-d H:i:s',$row['confirm_time'])."</td><td>";
//            if($row["is_allreturn"]==1){
//                $strTable.="全返";
//            }else{
//                $strTable.="非全返";
//            }
//            $strTable .= "</td><td>".tk_money_format($row["rebate_price"])."</td><td>".tk_money_format($row["already_rebate"])."</td><td>".tk_money_format($row["tj_money"])."</td><td>{$row['tgy_name']}</td><td>".tk_money_format($row["staff_money"])."</td><td>".$row['store_name']."</td><td>".tk_money_format($row["store_money"])."</td><td>".$row['company_name']."</td><td>".tk_money_format($row["company_money"])."</td><td>".tk_money_format($row["zjc_money"])."</td><td>".tk_money_format($row["quanfan"])."</td><td>".$row["progress_bar"]."%</td>";
//            $strTable .= '</tr>';
//        }
//        $strTable .= '</table>';
//
//        downloadExcel($strTable,'现金订单分红信息');
//        exit();
//    }




    //[根据条件指定数组key求知]
    public function arrayQuerySum($array,$map,$sumKey,$company_member=array(),$parent=0){
        $where = [];
        $arr = [];
        $parent_id=[];
        foreach ($map as $key=>$value){
            $where[$key] =$value[1];
        }
        if(!empty($company_member)){
            foreach ($company_member as $value){
                $parent_id[$value['parent_id']][]=$value['id'];
            }
        }

        foreach ($array as $key => $row){

            $flag=true;
            if(!empty($company_member) && $parent!=""){
                if(empty($parent_id[$parent])){
                    $flag = false;
                }else{
                    $flag = in_array($row['member_id'],$parent_id[$parent]);
                }
            }
            if(count(array_intersect_assoc($where,$row)) == count($map) && $flag){
                $arr[$key]=$row[$sumKey];
            }
        }

        return array_sum($arr);
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
        //2018-09-26 李鑫 修改模糊查询
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        // $consignee ? $condition['consignee'] = trim($consignee) : false;
        $consignee ? $condition['consignee'] = array('like',"%$consignee%") : false;
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        // $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        $order_sn ? $condition['order_sn'] = array('like',"%$order_sn%") : false;
        //修改结束
        
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
            //Db::execute("truncate table tp_list_order");
            Db::execute("delete from tp_list_order");
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


    /**
     * [将全返数据保存在本地 txt 文件里]
     * @author 王牧田
     * @date 2018-09-07
     * @return float
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function return_percentage_fileput(){
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
        $limitpage = I('get.limitpage/d',25);
        $count = M($table_name)->alias('a')->where($condition)->count();

        $Page  = new Page($count,$limitpage);

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

        $order_id = $order_sn = [];
        foreach ($orderList as $k=>$value){
            $order_id[] = $value['order_id'];
            $order_sn[] = $value['order_sn'];
        }
    //    $w['order_id'] = ['in',implode(",",$order_id)];
        $w['order_id'] = ['in',$order_id];
        $w['order_sn'] = ['in',$order_sn];
        $w['create_time']   =   ['between',"{$this->begin},{$this->end}"];

        $staff_commission = db('staff_commission')->where($w)->select();

        $previous_log = db('previous_log')->field("order_sn,money")->where($w)->select();//时间

        $capital_pool_log = db('capital_pool_log')->field("order_sn,money")->select();

        $member_commission = db('member_commission')->where($w)->select();

        $company_member = db('company_member')->field("id,parent_id")->select();

        foreach ($orderList as $key => $value) {
            //统计该订单给员工分润
            $map['order_id']    =   ['eq',$value['order_id']];
            $map['order_sn']    =   ['eq',$value['order_sn']];
            $staff_map = $map;
            $staff_map['is_tj'] =   ['eq',0];
            $orderList[$key]['staff_money'] =   $this->arrayQuerySum($staff_commission,$staff_map,'money');
            $tj_map   =   $map;
            $tj_map['is_tj'] =  ['eq',1];
            $orderList[$key]['tj_money'] =   $this->arrayQuerySum($staff_commission,$tj_map,'money');//推广员的钱
            if($value['store_id']){
                $orderList[$key]['store_money']    =  $this->arrayQuerySum($member_commission,$map,'money',$company_member,$value['store_id']);  //实体店成员的钱
            }
            if($value['company_id']){

                $orderList[$key]['company_money']    =  $this->arrayQuerySum($member_commission,$map,'money',$company_member,$value['company_id']);

            }else{
                $orderList[$key]['company_money']    =   0;
            }

            #资金池
            $orderList[$key]['zjc_money'] =   $this->arrayQuerySum($capital_pool_log,['order_sn'=>['eq',$value['order_sn']]],'money');
            $orderList[$key]['quanfan'] =   $this->arrayQuerySum($previous_log,['order_sn'=>['eq',$value['order_sn']]],'money');
        }


        $user_id = $_SESSION['think']['user']['user_id'];

        $dir_url = "./public/data/data_".$user_id."/";

        if(!is_dir($dir_url)) {
            mkdir($dir_url, 0755, true);
        }
        if($Page->nowPage <= $Page->totalPages){
            file_put_contents($dir_url."/return_percentage_".$Page->nowPage.".txt",json_encode($orderList));
            return ceil($Page->nowPage/$Page->totalPages * 100);
        }

    }


    /**
     * [进度条完事将全返的txt文件导出excel]
     * @author 王牧田
     * @date 2018-09-07
     */
    public function return_percetage_downExcel(){
        $user_id = $_SESSION['think']['user']['user_id'];
        $dir_url = "./public/data/data_".$user_id."/";
        $files = scandir($dir_url);
        unset($files[0]);
        unset($files[1]);
        $filelenght =count($files);
        $orderList = [];
        for($i=1;$i<=$filelenght;$i++){
            $data = file_get_contents($dir_url."return_percentage_".$i.".txt");

            $row=json_decode($data,true);

            $orderList = array_merge($orderList,$row);

        }

        $strTable ='<table width="1000" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td>订单编号</td><td>用户id</td><td>确认收货时间</td><td>是否福利</td><td>分红总额</td><td>已反金额</td><td>推广金额</td><td>推广姓名</td><td>员工金额</td><td>实体店</td><td>实体店金额</td><td>子公司</td><td>子公司金额</td><td>资金池</td><td>全返</td><td>进度</td>';
        $strTable .= '</tr>';

        foreach ($orderList as $row){
            $order_sn = html_entity_decode("&nbsp;".$row["order_sn"]);
            $strTable .= '<tr>';
            $strTable .= "<td>".$order_sn."</td><td>".$row['user_id']."</td><td>".date('Y-m-d H:i:s',$row['confirm_time'])."</td><td>";
            if($row["is_allreturn"]==1){
                $strTable.="全返";
            }else{
                $strTable.="非全返";
            }
            $strTable .= "</td><td>".tk_money_format($row["rebate_price"])."</td><td>".tk_money_format($row["already_rebate"])."</td><td>".tk_money_format($row["tj_money"])."</td><td>{$row['tgy_name']}</td><td>".tk_money_format($row["staff_money"])."</td><td>".$row['store_name']."</td><td>".tk_money_format($row["store_money"])."</td><td>".$row['company_name']."</td><td>".tk_money_format($row["company_money"])."</td><td>".tk_money_format($row["zjc_money"])."</td><td>".tk_money_format($row["quanfan"])."</td><td>".$row["progress_bar"]."%</td>";
            $strTable .= '</tr>';
        }
        $strTable .= '</table>';
        downloadExcel($strTable,'现金订单分红信息');
        $this->removeDir($dir_url);
        exit();
    }

    //删除非空目录的解决方案
    public function removeDir($dirName)
    {
        if(! is_dir($dirName))
        {
            return false;
        }
        $handle = @opendir($dirName);
        while(($file = @readdir($handle)) !== false)
        {
            if($file != '.' && $file != '..')
            {
                $dir = $dirName . '/' . $file;
                is_dir($dir) ? removeDir($dir) : @unlink($dir);
            }
        }
        closedir($handle);

        return rmdir($dirName) ;
    }


    public function ranking(){

       return  $this->fetch();
    }





}