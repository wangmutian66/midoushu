<?php
/**

 * 自动运行程序，主要用于订单全返,返利，以及线下部分分红提成
 * Author: TK     
 * Date: 2018年5月23日10:43:45
 */

namespace app\auto\controller;
use think\Controller;
use think\Db;
use think\Session;
use think\Cache;

class Index extends Controller{
    
     /*
     * 初始化操作
     */
    public function _initialize() 
    {
        Session::start();
        if(session('admin_id') > 0 ){
        }else{
            #http://www.midoushu.com/auto/indexnew/auto_fully/user_name/tkauto/psw/653234
            $user_name = I('get.user_name/s');
            $psw = I('get.psw/d');
            if($user_name != 'tkauto' && $psw!= '653234'){
                $this->error('请先登录超级管理员后台',U('/Admin/Admin/login'),1);
            }
        }
    }
   
    /*全自动进行流程入口
    crontab -e
    分 时 
    42 14 * * * /usr/bin/curl https://www.midoushu.com/auto/index/auto_fully/user_name/tkauto/psw/653234
    上面代表  每天  14点42分  访问 后面的网址  注意 需要区分 http 和 https 
    service crond restart
    */
    public function auto_fully(){
        ini_set("max_execution_time", 30000);
        file_put_contents("auto_fully.log", '全自动返利程序开始：'.date('Y-m-d H:i:s'). PHP_EOL, FILE_APPEND);
        $order_where['order_status']  = ['in','2,4'];           //已收货和已完成 
        $order_where['rebate_status'] = ['eq',0];
        //查询出现金商城订单
        $list = db('order')->where($order_where)->select();
        if($list){
            foreach ($list as $key => $value) {
                $list[$key]['is_red'] = 0;
            }
        }
        //查询出红包商城订单
        $red_list = db('order_red')->where($order_where)->select();
        if($red_list){
            foreach ($red_list as $key => $value) {
                $red_list[$key]['is_red']   =   1;
            }
        }
        
        $all_list = array_merge($list,$red_list);  // 组合现金订单和米豆订单

        if(empty($all_list)) {
            file_put_contents("auto_fully.log", '暂无可处理全返订单！ ----------全自动返利程序结束：'.date('Y-m-d H:i:s'). PHP_EOL, FILE_APPEND);
            exit('暂无可处理全返订单！');
        }
        
        $all_list = $this->bubble_sort($all_list);  // 排序
        // dump($all_list);die;
        if($all_list){  // 所有全返订单
            foreach ($all_list as $key => $value) {
                $r = $this->rebate($value);  // 进行返利算法
                // 判断是否是米豆
                if($value['is_red'] ==  1) {
                    $is_red = '是';
                    // 米豆订单  查询条件
                    $order_red_update_sql[] = ['order_id'=>$r['order_id'],'rebate_status'=>$r['rebate_status'],'rebate_info'=>$r['rebate_info']];
                }else{
                    $is_red = '否';
                    // 现金订单  查询条件
                    $order_update_sql[] = ['order_id'=>$r['order_id'],'rebate_status'=>$r['rebate_status'],'rebate_info'=>$r['rebate_info']];
                }
                file_put_contents("auto_fully.log", '时间：'.date('Y-m-d H:i:s')." 订单ID：{$value['order_id']} 状态:{$r['rebate_status']} 信息:{$r['rebate_info']} 是否为红包商城：{$is_red}". PHP_EOL, FILE_APPEND);
                
            }
            // 更新订单全返状态
            if($order_update_sql){
                model('order')->saveAll($order_update_sql);
            }
            if($order_red_update_sql){
                model('order_red')->saveAll($order_red_update_sql);
            }
        }
        file_put_contents("auto_fully.log", '全自动返利程序结束：'.date('Y-m-d H:i:s'). PHP_EOL, FILE_APPEND);
    }

    //返利
    function rebate($order){
        $res['order_id']    =   $order['order_id']; // 订单ID
        // 判断是否是米豆订单
        if($order['is_red'] == 0){
            $url = U('/Admin/order/detail',['order_id'=>$order['order_id']]);
        }else{
            $url = U('/Admin/order_red/detail',['order_id'=>$order['order_id']]);
        }
        $a = "<a href='{$url}' target='_blank'>查看</a>"; // 查看链接
        $cost_sum = $order['tk_cost_price'] + $order['tk_cost_operating']; //商品成本价 + 运营成本
        #如果成本价为0，则不进行全返         $cost_sum 总成本价格
        if($cost_sum <= 0){
            $res['rebate_status']  =   -1;
            $res['rebate_info']    =   "{$a} 订单：{$order['order_id']} 成本价为0";
            return $res;
        }
        $is_red_str = '';
        if($order['is_red'] ==  1){
            //如果是红包商城   保留小数点后9位
            $profits_money = bcsub(bcsub($order['midou_money'],$cost_sum,9),$order['shipping_price'],9);
            $is_red_str = '红包商城';
        }else{
            #profits_money 是总利润    订单应付金额 - 成本价 - 运费
            $profits_money = bcsub(bcsub($order['order_amount'],$cost_sum,9),$order['shipping_price'],9);
            $is_red_str = '现金商城';
        }
        // dump($profits_money);die;
        if($profits_money<=0){  // 判断利润
            //如果利润小于0 直接返回退出 不进行全返 资金池等
            $res['rebate_status']  =   -1;
            $res['rebate_info']    =   "{$a} 利润为0";
            return $res;
        }
        //取出总比例
        #资金池 0，全返 1，线下 2，创业合伙人 3 总百分比
        $total_ratio = explode('|',tpCache('proportion.total_ratio')); 
        #---------------------------------
        #线下开始
        #查询购买人的基本信息
        $buy_user = db('users')->alias('user')
                ->field('user.user_id,user.staff_id,user.nickname,staff.uname staff_name,store.cname store_name,store_id,company_id,company.cname company_name,staff.type t,staff.parent_id,staff.is_lock staff_lock')
                ->join('staff staff','staff.id = user.staff_id','left')
                ->join('company store','store.cid = staff.store_id','left')
                ->join('company company','company.cid = staff.company_id','left')
                ->find($order['user_id']);
        # $buy_user['staff_id'] = 0;
        #如果存在创业合伙人
        if($buy_user['staff_id']){  #条件  && $buy_user['staff_lock']===0
            #创业合伙人部分开始         利润总额 * 后台设定的比例
            #$promoters_moeny = $profits_money * $total_ratio[3];
            $promoters_moeny = bcmul($profits_money,$total_ratio[3],9); // 计算创业合伙人 推广费
            if($buy_user['staff_lock'] != 1){       //如果创业合伙人没有被锁住
                // 创业合伙人收益 更新
                $staff_update_list[] = ['id'=>$buy_user['staff_id'],'money'=>['exp',"money + {$promoters_moeny}"],'cumulative_money'=>['exp',"cumulative_money + {$promoters_moeny}"]];
                // 收益记录
                $account_staff_insert_log[] = ['staff_id'=>$buy_user['staff_id'],'staff_money'=>$promoters_moeny,'create_time'=>NOW_TIME,'desc'=>"{$is_red_str} 用户订单：{$order['order_id']} 创业推广员分红"];
                $commission_staff_insert_log[] = ['staff_id'=>$buy_user['staff_id'],'money'=>$promoters_moeny,'create_time'=>NOW_TIME,'info'=>"{$is_red_str} 用户订单：{$order['order_id']} 创业推广员分红",'buy_id'=>$order['user_id'],'order_id'=>$order['order_id'],'order_sn'=>$order['order_sn'],'is_tj'=>1]; 
            }
            #创业合伙人部分结束
         
            #子公司部分开始
            #先将子公司分红总体计算出来
            $company_money_rebate = bcmul($profits_money,$total_ratio[2],9); // 线下返利比率
            #查询后台设置子公司部分的比例  员工 0，实体店 1，子公司 2
            $company_rebate = explode('|',tpCache('proportion.company_rebate'));
            #计算员工部分  子公司总额 * 子公司员工部分
            $staff_money   = bcmul($company_money_rebate,$company_rebate[0],9);
            #计算实体店部分  子公司总额 * 子公司实体店部分
            $store_money   = bcmul($company_money_rebate,$company_rebate[1],9);
            #计算子公司部分  子公司总额 * 子公司部分
            $company_money = bcmul($company_money_rebate,$company_rebate[2],9);
            
            if($buy_user['store_id'] != 0){  //存在实体
                #员工部分
                $staff_where['store_id'] = ['eq',$buy_user['store_id']];
                $staff_where['is_lock']  = ['eq',0];
                $staff_where['type'] = ['eq',0];
                $staff_list = db('staff')->where($staff_where) 
                                         ->alias('staff')
                                         ->field('staff.id,company_level,lv.profit')
                                         ->join("company_level lv",'lv.id = staff.company_level')
                                         ->select();
                if($staff_list){
                    #判断每个层级有多少人  将数据格式化 方便计算
                    foreach ($staff_list as $key => $value) {
                        $temp_staff_profit[$value['company_level']]['num']       += 1;       // 层级总人数
                        $temp_staff_profit[$value['company_level']]['sonlist'][] =  $value;  // 层级下员工信息
                        $temp_staff_profit[$value['company_level']]['profit']    =  $value['profit'];  // 分红比例
                    }
                    // 遍历 分红 信息
                    foreach ($temp_staff_profit as $key => $value) {
                        # $staff_money_avg = $staff_money * $value['profit'] / $value['num'];
                        # 层级内员工平分返利
                        $staff_money_avg = bcdiv(bcmul($staff_money,$value['profit'],9),$value['num'],9);
                        // 遍历员工返利信息
                        foreach ($value['sonlist'] as $k => $v) {  
                            $staff_update_list[] = ['id'=>$v['id'],'money'=>['exp',"money + {$staff_money_avg}"],'cumulative_money'=>['exp',"cumulative_money + {$staff_money_avg}"]];
                            #资金记录  
                            $account_staff_insert_log[] = ['staff_id'=>$v['id'],'staff_money'=>$staff_money_avg,'create_time'=>NOW_TIME,'desc'=>"{$is_red_str}用户订单：{$order['order_id']} 店员返利"];   #'buy_id'=>$order['user_id']
                            #佣金记录
                            $commission_staff_insert_log[] = ['staff_id'=>$v['id'],'money'=>$staff_money_avg,'create_time'=>NOW_TIME,'info'=>"{$is_red_str}用户订单：{$order['order_id']} 店员返利",'buy_id'=>$order['user_id'],'order_id'=>$order['order_id'],'order_sn'=>$order['order_sn'],'is_tj'=>0];  
                        }
                    }
                }
                
                #员工部分结束
                #实体店部分  查询实体店成员
                $store_where['parent_id'] = ['eq',$buy_user['store_id']];
                $store_where['is_lock']   = ['eq',0];
                $store_member_list = db('company_member')->where($store_where)
                                 ->alias('member')
                                 ->field('member.id,company_level,lv.profit')
                                 ->join("company_level lv",'lv.id = member.company_level')
                                 ->select();
                // 遍历实体店成员
                if($store_member_list){
                    #判断每个层级有多少人  将数据格式化 方便计算
                    foreach ($store_member_list as $key => $value) {
                        $temp_member_profit[$value['company_level']]['num']       += 1;
                        $temp_member_profit[$value['company_level']]['sonlist'][] = $value;
                        $temp_member_profit[$value['company_level']]['profit']    = $value['profit'];
                    }


                    foreach ($temp_member_profit as $key => $value) {
                        # $store_money_avg = $store_money * $value['profit'] / $value['num'];
                        # 层级内每个成员平均返利

                        $store_money_avg = bcdiv(bcmul($store_money,$value['profit'],9),$value['num'],9);
                        foreach ($value['sonlist'] as $k => $v) {
                            $member_update_list[] = ['id'=>$v['id'],
                                                    'money'=>['exp',"money + {$store_money_avg}"],
                                                    'cumulative_money'=>['exp',"cumulative_money + {$store_money_avg}"]
                                                    ];
                            #用户资金记录
                            $account_member_insert_log[]  =   ['member_id'=>$v['id'],'member_money'=>$store_money_avg,'create_time'=>NOW_TIME,'desc'=>"{$is_red_str}用户订单：{$order['order_id']} 实体店成员返利"]; #
                            #用户佣金记录
                            $commission_member_insert_log[]  =   ['member_id'=>$v['id'],'money'=>$store_money_avg,'create_time'=>NOW_TIME,'info'=>"{$is_red_str}用户订单：{$order['order_id']} 实体店成员返利",'buy_id'=>$order['user_id'],'order_id'=>$order['order_id'],'order_sn'=>$order['order_sn']];
                        }
                    }
                }

                #实体店部分结束

                #子公司部分
                #查询子公司成员
                $company_where['parent_id']   =   ['eq',$buy_user['company_id']];
                $company_where['is_lock'] =  ['eq',0];
                $company_member_list = db('company_member')->where($company_where)
                                         ->alias('member')
                                         ->field('member.id,company_level,lv.profit')
                                         ->join("company_level lv",'lv.id = member.company_level')
                                         ->select();
                // 遍历子公司成员
                if($company_member_list){
                    #判断每个层级有多少人  将数据格式化 方便计算
                    foreach ($company_member_list as $key => $value) {
                        $temp_member_company_profit[$value['company_level']]['num']  += 1;
                        $temp_member_company_profit[$value['company_level']]['sonlist'][] =   $value;
                        $temp_member_company_profit[$value['company_level']]['profit']  =   $value['profit'];
                    }

                    foreach ($temp_member_company_profit as $key => $value) {
                        # $company_money_avg = $company_money * $value['profit'] / $value['num'];
                        # 层级内成员平均返利

                        $company_money_avg = bcdiv(bcmul($company_money,$value['profit'],9),$value['num'],9);
                        foreach ($value['sonlist'] as $k => $v) {
                            $member_update_list[] = ['id'=>$v['id'],
                                                        'money'=>['exp',"money + {$company_money_avg}"],
                                                        'cumulative_money'=>['exp',"cumulative_money + {$company_money_avg}"]
                                                    ];
                            #资金记录
                            $account_member_insert_log[] = ['member_id'=>$v['id'],'member_money'=>$company_money_avg,'create_time'=>NOW_TIME,'desc'=>"{$is_red_str}用户订单：{$order['order_id']} 子公司成员返利"];   
                            #,'buy_id'=>$order['user_id']
                            $commission_member_insert_log[] = ['member_id'=>$v['id'],'money'=>$company_money_avg,'create_time'=>NOW_TIME,'info'=>"{$is_red_str}用户订单：{$order['order_id']} 子公司成员返利",'buy_id'=>$order['user_id'],'order_id'=>$order['order_id'],'order_sn'=>$order['order_sn']];
                        }
                    }
                }

                #子公司部分结束
                #author:张洪凯
                #推荐子公司部分开始
                #查询子公司是否有上级推荐子公司
                $company_sign_sql   =   " (`company_id` = {$buy_user['company_id']}  AND `store_id` = {$buy_user['store_id']}) OR (`company_id` = {$buy_user['company_id']} and `store_id` = 0)";
                $cache_key = md5($company_sign_sql);
                $t_company_list = M('company_sign')->where($company_sign_sql)
                                    ->alias('company_sign')
                                    ->field('company_sign.*,lv.profit')
                                    ->join('company_level lv',"lv.id = company_level",'left')
                                    ->cache($cache_key)
                                    ->select();
                if($t_company_list){
                    foreach ($t_company_list as $key => $value) {
                        if($value['store_id'] > 0){
                            #满足此条件由实体店给推荐子公司分红返利
                            $t_company_money = $store_money;
                        }else{
                            #否则由子公司给推荐子公司分红返利
                            $t_company_money = $company_money;
                        }
                        $t_member_company_profit[$value['company_level']]['num']  += 1;
                        $t_member_company_profit[$value['company_level']]['sonlist'][] =   $value;
                        $t_member_company_profit[$value['company_level']]['profit']  =  $value['profit'];
                        $t_member_company_profit[$value['company_level']]['money'] = $t_company_money;
                    }
                    foreach ($t_member_company_profit as $key => $value) {
                        $company_money_avg = bcdiv(bcmul($value['money'],$value['profit'],9),$value['num'],9);
                        if($company_money_avg > 0){
                            foreach ($value['sonlist'] as $k => $v) {
                                $param = [];
                                $param['t_company_id']  =   $v['t_company_id'];
                                $param['t_company_money'] =   $company_money_avg;
                                $param['order_sn']  =   $order['order_sn'];
                                $param['order_id']  =   $order['order_id'];
                                $param['user_id']   =   $order['user_id'];
                                $param['is_red_str']    =   $is_red_str;
                                $t_result_array[] = $this->do_company_rebate($param);
                            }
                        }
                        
                    }
                    if($t_result_array){
                        foreach ($t_result_array as $key => $value) {
                            #需要检测返回的数据是否存在
                            if(isset($value['member_update_list'])){
                                if(isset($member_update_list)){
                                    $member_update_list = array_merge($member_update_list,$value['member_update_list']);
                                }else{
                                    $member_update_list =  $value['member_update_list'];
                                }
                            }
                            if(isset($value['account_member_insert_log'])){
                                if(isset($account_member_insert_log)){
                                    $account_member_insert_log = array_merge($account_member_insert_log,$value['account_member_insert_log']);
                                }else{
                                    $account_member_insert_log = $value['account_member_insert_log'];
                                }
                            }
                            if(isset($value['commission_member_insert_log'])){
                                if(isset($commission_member_insert_log)){
                                    $commission_member_insert_log = array_merge($commission_member_insert_log,$value['commission_member_insert_log']);
                                }else{
                                    $commission_member_insert_log = $value['commission_member_insert_log'];
                                }
                            }  
                        }
                    }
                    
                    
                }
                #推荐子公司部分结束
            }
            
            
        }
        #子公司部分结束
        #--------------------------------
        
        # 资金池 start
        $capital_pool_money = bcmul($profits_money,$total_ratio[0],9);
        # $capital_pool_money = 0;
        if($capital_pool_money > 0){
            //更改资金池资金
            $capital_pool_data = ['update_time'=>NOW_TIME,'money'=>['exp',"money + {$capital_pool_money}"]];
        //    M('capital_pool')->update();
            //增加资金池日志
            $capital_pool_log_data = ['user_id'=>$order['user_id'],
                                        'money'=>$capital_pool_money,
                                        'create_time'=>NOW_TIME,
                                        'remark'=>"{$a} {$is_red_str}订单：{$order['order_id']}",
                                        'act'=>'/auto/index',
                                        'control'=>'rebate_start',
                                        'order_sn'=>$order['order_sn']
                                    ];
        }
        #资金池end

        #全返start  进入全返的利润
        $back_money = bcmul($profits_money,$total_ratio[1],9);
        # 全返档数比例
        $back_proportion = explode('|',tpCache('proportion.back_proportion'));
        $temp_numbers = 0;
        foreach ($back_proportion as $k => $v) {
            $temp_arr = explode(',', $v);
            $temp_numbers = bcadd($temp_numbers,$temp_arr[0],9);   // 该档占总单数 数量
            $back_proportion[$k] =   [$temp_numbers,$temp_arr[1]];  // 该档占总全返比率
        }

        //查询当前订单之前的所有订单  并且返利的金额小于  花费现金的总额   2018.07.25  修改为 按照 确认收货时间排序
        $previous_order_list = M('order')->field('order_id,user_id,order_amount,shipping_price,already_rebate,add_time,confirm_time')->where("confirm_time < {$order['confirm_time']} and order_status in(2,4) and already_rebate < (order_amount - shipping_price) and is_allreturn = 1")->order('confirm_time asc')->select();
     
        if(empty($previous_order_list)){
            //如果之前没有订单，那么则结束该函数
            $res['rebate_status'] = 1;
            $res['rebate_info']   = $is_red_str . '资金池OK，线下分红OK，订单之前没有订单，全返未执行';
            return $res;
        }
        #计算之前订单总数 ,
        $previous_order_list_sum =(count($previous_order_list) == 1) ? 1 : count($previous_order_list)-1;
        //统计每个层级有多少个订单,并将层级进行格式化
        foreach ($previous_order_list as $key => $value) {
            $current_position =  sprintf("%.2f", $key/$previous_order_list_sum);    //计算当前位置，小数表示 
            foreach ($back_proportion as $k => $v) {
                if($current_position <= $v[0]){
                    $back_proportion[$k]['sum'] += 1;   #计算每个层级有多少个订单
                    break;
                }
            }
            $user_ids[] =   $value['user_id'];
        }
        //进行计算全返部分
        //遍历全返订单
        foreach ($previous_order_list as $key => $value) {
            $current_position =  sprintf("%.2f", $key/$previous_order_list_sum); //判断当前循环位置 
            foreach ($back_proportion as $k => $v) {
                if($current_position <= $v[0]){
                    #判断当前订单还剩下多少返利
                    $limit_money = bcsub(bcsub($value['order_amount'],$value['shipping_price'],9),$value['already_rebate'],9);
                    
                    #返利部分 * 总反比部分的百分比 再除以当前本档次的总人数
                    $money  =   bcdiv(bcmul($back_money,$v[1],9),$v['sum'],9); 
                    if($money > $limit_money){ // 如果剩余全返金额 小于 此轮应返金额
                        $money = $limit_money; // 应返金额为 剩余全返金额
                    }
                    $user_list[$value['user_id']]['money'] =  bcadd($money,$user_list[$value['user_id']]['money'],9);
                    $order_update_rebate[]  =   ['order_id'=>$value['order_id'],'already_rebate'=>['exp',"already_rebate + {$money}"]];
                    break;
                }
            }
        }
        #因为有的用户肯能会很多个订单，一笔订单反多次，但是记录加钱只有一次，省着显示太乱
        foreach ($user_list as $key => $value) {
            $previous_log_data[] = ['uid'         => $key,
                                    'money'       => $value['money'],
                                    'create_time' => NOW_TIME,
                                    'insert_type' => 1,
                                    'order_id'    => $order['order_id'],
                                    'buy_uid'     => $order['user_id'],
                                    'order_sn'    => $order['order_sn'],
                                    ];
            $modify_user_money[] = ['user_id'=>$key,
                                    'rebate_money'=>['exp'," rebate_money + {$value['money']}"],
                                    'rebate_money_all'=>['exp'," rebate_money_all + {$value['money']}"]
                                    ];
        }
        
        Db::startTrans();
        try{
            #线下返利链
            #更新员工创业合伙人表 余额
            if($staff_update_list){
                model('staff')->saveAll($staff_update_list);     
            }
            if($account_staff_insert_log){
                 db('staff_account_log')->insertAll($account_staff_insert_log); //员工资金记录
            } 
            if($commission_staff_insert_log){
                db('staff_commission')->insertAll($commission_staff_insert_log); #员工佣金记录表    
            }
            if($member_update_list){
                model('CompanyMember')->saveAll($member_update_list);    
            }
            if($account_member_insert_log){
                db('member_account_log')->insertAll($account_member_insert_log);  //成员佣金表    
            }
            if($commission_member_insert_log){
                db('member_commission')->insertAll($commission_member_insert_log); #成员佣金记录表    
            }
            #资金池
            db('capital_pool')->where('id',1)->update($capital_pool_data);
            #资金池日志
            db('capital_pool_log')->save($capital_pool_log_data);
            #全返
            if($previous_log_data){
                db('previous_log')->insertAll($previous_log_data);  // 添加全返记录
            }
            if($modify_user_money){
                model('Users')->saveAll($modify_user_money);   // 更新用户 全返金额
            }
            if($order_update_rebate){
                model("Order")->saveAll($order_update_rebate);   // 更新订单全返状态 
            }
            Db::commit(); 
            #全返结束
            $res['rebate_status']  =   1;
            $res['rebate_info']    =   "返利成功";   
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $res['rebate_status']  =   -1;
            $res['rebate_info']    =   "事务回滚，返利失败，请寻找程序员查询详细原因";
        }
        return $res;
    }


    #43 * * * * /usr/bin/curl https://www.midoushu.com/auto/index/offline_rebate/user_name/tkauto/psw/653234
    /*线下代付 分红       */
    #http://midoushu.com/Auto/Index/offline_rebate
    public function offline_rebate(){
        die();
        $where['pay_status']    = ['eq',1]; // 已支付
        $where['rebate_status'] = ['eq',0]; // 未分红
        $list = db('staff_paid')->alias('a')
                        ->where($where)
                        ->field('a.*,store.proportion,staff.is_lock staff_lock,staff.type t,staff.store_id,staff.company_id,u.staff_id tgy_id,store.is_public,store.tgyxxbl,store.tscbl,store.yscbl')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('company store','store.cid = staff.store_id')
                        ->join('company company','company.cid = staff.company_id')
                        ->join('users u','u.user_id = a.user_id')
                        ->order('id desc')
                        ->select();
    //    dump($list );die;
        #资金池 0，全返 1，线下 2，创业合伙人 3 总百分比
        $txian = explode('|',tpCache('proportion.txian'));  
    //    print_r($txian);die; 
        foreach ($list as $key => $value) {
            $arr = $this->offline_back($value,$txian);
            db('staff_paid')->where("id = {$value['id']}")->update($arr); // 更新分红状态
        }
    }

/*
  42 * * * * /usr/bin/curl https://www.midoushu.com/auto/index/offline_rebate/user_name/tkauto/psw/653234
  43 * * * * /usr/bin/curl https://www.midoushu.com/auto/index/sweep_rebate/user_name/tkauto/psw/653234
*/

    /*线下扫码付款 分红*/
    #https://www.midoushu.com/auto/index/sweep_rebate/user_name/tkauto/psw/653234
    #mds.com/Auto/Index/sweep_rebate/user_name/tkauto/psw/653234
    public function sweep_rebate(){
        $where['pay_status']    = ['eq',1];
        $where['rebate_status'] = ['eq',0];
        $list = db('staff_mypays')->alias('a')
                        ->where($where)
                        ->field('a.id,a.paid_sn,a.user_id,a.staff_id,staff.store_id,staff.company_id,a.money,store.proportion,staff.is_lock staff_lock,staff.type t,store.is_public,store.tgyxxbl,store.tscbl,store.yscbl,u.staff_id tgy_id')
                        ->join('staff staff','staff.id = a.staff_id')
                        ->join('company store','store.cid = staff.store_id')
                  //      ->join('company company','company.cid = staff.company_id')
                        ->join('users u','u.user_id = a.user_id')
                        ->order('id asc')
                        ->limit(1000)
                        ->select();

        if($list){
            #创业合伙人 0,线下 1总百分比
            $txian = explode('|',tpCache('proportion.txian'));   
            foreach ($list as $key => $value) {
                $arr = $this->offline_back($value,$txian,false);
                db('staff_mypays')->where("id = {$value['id']}")->update($arr); // 更新分红状态
            }
        }else{
            print_r('没有数据!');
        }
        
    }
    /*对二维数组进行冒泡排序*/  // 到底有没用？？？待定
    function bubble_sort($list,$column='confirm_time'){
        #一个思路 将add_time 放入建值中   然后 用 array_multisort ， 嘛 不过好像不行   统一时间的订单很容易冲突
        foreach($list as $key => $value){
            $tims[] = $value[$column];
        }
        array_multisort($tims, SORT_ASC,$list);
        return $list;
    }

    function findMoney($str=''){
        $str=trim($str);
        if(empty($str)){return '';}
        $reg='/\d+\.\d+/is';//匹配数字的正则表达式
        preg_match_all($reg,$str,$result);
        if(is_array($result) && !empty($result) && !empty($result[0]) && !empty($result[0][0])){
            return $result[0][0];
        }
        return 0;
    }

    // 线下利润  返利
    function offline_back($buy_user,$txian,$is_paid=true){
        // 判断分红比例
        if($buy_user['proportion'] <= 0 || $buy_user['money'] <= 0){
            #利润比为空 返回
            $res['rebate_status']   =   -1;
            return $res;
        }

        #计算利润总额
        $profits_money = bcmul($buy_user['proportion'],$buy_user['money'],9);
        // 创业合伙人 0，线下 1 比例
        if($buy_user['tgyxxbl']){
            $tgyxxbl = explode('|',$buy_user['tgyxxbl']);
        }
     
        if($buy_user['tgy_id']){  // 判断创业合伙人ID
            #创业合伙人部分开始    利润总额 * 后台设定的比例
            if($buy_user['is_public'] == 1){  // 参与大盘比例
                $promoters_moeny = bcmul($profits_money,$txian[0],9);
            }else{
                if($tgyxxbl[0]){
                    //张洪凯标注：推广员线上得到的钱，现在是自己得，要分给员工、实体店、子公司
                    $promoters_moeny = bcmul($profits_money,$tgyxxbl[0],9);
                }
            }
            #查询创业合伙人信息
            $tgy_info = db('staff')->cache("auto_staff_{$buy_user['tgy_id']}")->find($buy_user['tgy_id']);

            if($tgy_info['is_lock'] != 1){      //如果创业合伙人没有被锁住
                if(isset($promoters_moeny) && $promoters_moeny > 0){
                    //推广员推广分成，推广员-实体店-子公司
                    $result_array = $this->tuiguang_back($buy_user,$promoters_moeny,$is_paid);
                    if($result_array){
                        extract($result_array);
                    }
                    if(isset($staff_update_list)){
                        $order_save_data['tgy_money'] = $this->findMoney($staff_update_list[0]['money'][1]);
                    }
                }
            }
            #创业合伙人部分结束
        }
        
        #子公司部分开始
        if($buy_user['is_public'] == 1){  // 参与大盘
            #先将子公司分红总体计算出来
            $company_money_rebate = bcmul($profits_money,$txian[1],9);
            #查询后台设置子公司部分的比例
            $xian_ms = explode('|',tpCache('proportion.xian_ms'));
            #计算员工部分             子公司总额 * 子公司员工部分
            $staff_money = bcmul($company_money_rebate,$xian_ms[0],9);
            #计算实体店部分            子公司总额 * 子公司实体店部分
            $store_money    =   bcmul($company_money_rebate,$xian_ms[1],9);
            #计算子公司部分            子公司总额 * 子公司部分
            $company_money = bcmul($company_money_rebate,$xian_ms[2],9);
        }else{  // 不参与大盘
            #先将子公司分红总体计算出来
            $company_money_rebate = bcmul($profits_money,$tgyxxbl[1],9);
            $yscbl = explode('|', $buy_user['yscbl']);
            #计算员工部分             子公司总额 * 子公司员工部分
            $staff_money = bcmul($company_money_rebate,$yscbl[0],9);
            #计算实体店部分            子公司总额 * 子公司实体店部分
            $store_money    =   bcmul($company_money_rebate,$yscbl[1],9);
            #计算子公司部分            子公司总额 * 子公司部分
            $company_money = bcmul($company_money_rebate,$yscbl[2],9);
        }

        if($buy_user['store_id'] != 0){   // 实体店
            #员工部分
            $staff_where['id']            = ['eq',$buy_user['staff_id']];   // 员工ID
            $staff_where['is_lock']       = ['eq',0];                       // 是否被锁
            $staff_where['type'] = ['eq',0];                                // 层级
            $staff_info = db('staff')->where($staff_where) 
                                     ->alias('staff')
                                     ->cache("auto_staff_{$buy_user['staff_id']}")
                                     ->field('staff.id,company_level')
                                     ->find();
            if($staff_info){ // 存在员工信息
                if($staff_money > 0){
                    $service_award = $staff_update_list[] = ['id'=>$buy_user['staff_id'],
                                        'money'=>['exp',"money + {$staff_money}"],
                                        'cumulative_money'=>['exp',"cumulative_money + {$staff_money}"]
                                        ];
                    if($is_paid){  // 代付订单
                        #资金记录  
                        $account_staff_insert_log[] = ['staff_id'=>$buy_user['staff_id'],
                                                        'staff_money'=>$staff_money,
                                                        'create_time'=>NOW_TIME,
                                                        'desc'=>"代付订单：{$buy_user['paid_sn']} 店员服务返利"];
                        #佣金记录
                        $commission_staff_insert_log[]  =   ['staff_id'=>$buy_user['staff_id'],
                                                            'money'=>$staff_money,
                                                            'create_time'=>NOW_TIME,
                                                            'info'=>"代付订单：{$buy_user['paid_sn']} 店员服务返利",
                                                            'buy_id'=>$buy_user['user_id'],
                                                            'paid_id'=>$buy_user['id'],
                                                            'order_sn'=>$buy_user['paid_sn'],
                                                            'is_tj'=>0];
                    }else{
                        #资金记录  
                        $account_staff_insert_log[]  =   ['staff_id'=>$buy_user['staff_id'],
                                                            'staff_money'=>$staff_money,
                                                            'create_time'=>NOW_TIME,
                                                            'desc'=>"扫码订单：{$buy_user['paid_sn']} 店员服务返利"];

                        $commission_staff_insert_log[]  =   ['staff_id'=>$buy_user['staff_id'],
                                                            'money'=>$staff_money,
                                                            'create_time'=>NOW_TIME,
                                                            'info'=>"扫码订单：{$buy_user['paid_sn']} 店员服务返利",
                                                            'buy_id'=>$buy_user['user_id'],
                                                            'pay_id'=>$buy_user['id'],
                                                            'order_sn'=>$buy_user['paid_sn'],
                                                            'is_tj'=>0];
                    }
                }                
            }
            $order_save_data['staff_money'] = $this->findMoney($service_award['money'][1]);

            #员工部分结束
            #实体店部分          查询实体店成员
            $store_where['parent_id'] = ['eq',$buy_user['store_id']]; // 实体店ID
            $store_where['is_lock']   = ['eq',0];  // 是否被锁
            $store_member_list = db('company_member')->where($store_where)
                                ->alias('member')
                                ->cache("auto_store_member_{$buy_user['store_id']}")
                                ->field('member.id,company_level,lv.profit')
                                ->join("company_level lv",'lv.id = member.company_level')
                                ->select();
            if($store_member_list){         #如果实体店存在成员
                #判断每个层级有多少人  将数据格式化 方便计算
                foreach ($store_member_list as $key => $value) {
                    $temp_member_profit[$value['company_level']]['num']  += 1;
                    $temp_member_profit[$value['company_level']]['sonlist'][] =   $value;
                    $temp_member_profit[$value['company_level']]['profit']  =   $value['profit'];
                }
                foreach ($temp_member_profit as $key => $value) {
                    $store_money_avg = bcdiv(bcmul($store_money,$value['profit'],9),$value['num'],9);
                    foreach ($value['sonlist'] as $k => $v) {
                        if($store_money_avg > 0){
                            $member_update_list[] = ['id'=>$v['id'],
                                                'money'=>['exp',"money + {$store_money_avg}"],
                                                'cumulative_money'=>['exp',"cumulative_money + {$store_money_avg}"]
                                                ];
                            if($is_paid){
                                #用户资金记录
                                $account_member_insert_log[]    = ['member_id'=>$v['id'],'member_money'=>$store_money_avg,'create_time'=>NOW_TIME,'desc'=>"代付订单：{$buy_user['paid_sn']} 实体店成员返利"]; #
                                #用户佣金记录
                                $commission_member_insert_log[] = ['member_id'=>$v['id'],
                                                                    'money'=>$store_money_avg,
                                                                    'create_time'=>NOW_TIME,
                                                                    'info'=>"代付订单：{$buy_user['paid_sn']} 实体店成员返利",
                                                                    'buy_id'=>$buy_user['user_id'],
                                                                    'order_sn'=>$buy_user['paid_sn'],
                                                                    'paid_id'=>$buy_user['id']
                                                                ];
                            }else{
                                #用户资金记录
                                $account_member_insert_log[] = ['member_id'=>$v['id'],'member_money'=>$store_money_avg,'create_time'=>NOW_TIME,'desc'=>"扫码订单：{$buy_user['paid_sn']} 实体店成员返利"]; 
                                #用户佣金记录
                                $commission_member_insert_log[] = ['member_id'=>$v['id'],
                                'money'=>$store_money_avg,
                                'create_time'=>NOW_TIME,
                                'info'=>"扫码订单：{$buy_user['paid_sn']} 实体店成员返利",
                                'buy_id'=>$buy_user['user_id'],
                                'order_sn'=>$buy_user['paid_sn'],
                                'pay_id'=>$buy_user['id']];
                            }
                        }
                        
                    }
                }
                if($member_update_list){
                    foreach ($member_update_list as $key => $value) {
                        $order_save_data['store_money_sum'] += $this->findMoney($value['money'][1]);
                    }
                }
            }
            #实体店部分结束
            
            #子公司部分          查询子公司成员
            $company_where['parent_id'] = ['eq',$buy_user['company_id']]; // 子公司ID
            $company_where['is_lock']   = ['eq',0];  // 是否被锁
            $company_member_list = db('company_member')->where($company_where)
                                     ->alias('member')
                                     ->cache("auto_company_member_{$buy_user['store_id']}")
                                     ->field('member.id,company_level,lv.profit')
                                     ->join("company_level lv",'lv.id = member.company_level')
                                     ->select();
            if($company_member_list){       
                #如果子公司存在人员
                #判断每个层级有多少人  将数据格式化 方便计算
                foreach ($company_member_list as $key => $value) {
                    $temp_member_company_profit[$value['company_level']]['num']  += 1;
                    $temp_member_company_profit[$value['company_level']]['sonlist'][] =   $value;
                    $temp_member_company_profit[$value['company_level']]['profit']  =   $value['profit'];
                }
                foreach ($temp_member_company_profit as $key => $value) {
                    $company_money_avg = bcdiv(bcmul($company_money,$value['profit'],9),$value['num'],9);
                    if($company_money_avg > 0){
                        foreach ($value['sonlist'] as $k => $v) {
                            $company_update_list[] = $member_update_list[]   =   ['id'=>$v['id'],
                                                        'money'=>['exp',"money + {$company_money_avg}"],
                                                        'cumulative_money'=>['exp',"cumulative_money + {$company_money_avg}"]
                                                    ];
                            if($is_paid){
                                #资金记录
                                $account_member_insert_log[]  =   ['member_id'=>$v['id'],'member_money'=>$company_money_avg,'create_time'=>NOW_TIME,'desc'=>"代付订单：{$buy_user['paid_sn']} 子公司成员返利"];   #,'buy_id'=>$order['user_id'] 
                                $commission_member_insert_log[]  =   ['member_id'=>$v['id'],
                                                                        'money'=>$company_money_avg,
                                                                        'create_time'=>NOW_TIME,
                                                                        'info'=>"代付订单：{$buy_user['paid_sn']} 子公司成员返利",
                                                                        'buy_id'=>$buy_user['user_id'],
                                                                        'order_sn'=>$buy_user['paid_sn'],
                                                                        'paid_id'=>$buy_user['id']];
                            }else{
                                #资金记录
                                $account_member_insert_log[]  =   ['member_id'=>$v['id'],'member_money'=>$company_money_avg,'create_time'=>NOW_TIME,'desc'=>"扫码订单：{$buy_user['paid_sn']} 子公司成员返利"];   #,'buy_id'=>$order['user_id']
                                $commission_member_insert_log[]  =   ['member_id'=>$v['id'],
                                'money'=>$company_money_avg,
                                'create_time'=>NOW_TIME,
                                'info'=>"扫码订单：{$buy_user['paid_sn']} 子公司成员返利",
                                'buy_id'=>$buy_user['user_id'],
                                'order_sn'=>$buy_user['paid_sn'],
                                'pay_id'=>$buy_user['id']];
                            } 
                        }
                    }
                }
                #冗余数据所用
                if($company_update_list){
                    foreach ($company_update_list as $key => $value) {
                        $order_save_data['company_money'] += $this->findMoney($value['money'][1]);
                    }
                }
            }
            #子公司部分结束
            #author:张洪凯
            #查询子公司是否有上级推荐子公司
            $company_sign_sql   =   " (`company_id` = {$buy_user['company_id']}  AND `store_id` = {$buy_user['store_id']}) OR (`company_id` = {$buy_user['company_id']} and `store_id` = 0)";
            $cache_key = md5($company_sign_sql);
            $t_company_list = M('company_sign')->where($company_sign_sql)
                                ->alias('company_sign')
                                ->field('company_sign.*,lv.profit')
                                ->join('company_level lv',"lv.id = company_level",'left')
                                ->cache($cache_key)
                                ->select(); 
            if($t_company_list){
                foreach ($t_company_list as $key => $value) {
                    if($value['store_id'] > 0){
                        #满足此条件由实体店给推荐子公司分红返利
                        $t_company_money = $store_money;
                    }else{
                        #否则由子公司给推荐子公司分红返利
                        $t_company_money = $company_money;
                    }
                    $t_member_company_profit[$value['company_level']]['num']  += 1;
                    $t_member_company_profit[$value['company_level']]['sonlist'][] =   $value;
                    $t_member_company_profit[$value['company_level']]['profit']  =  $value['profit'];
                    $t_member_company_profit[$value['company_level']]['money'] = $t_company_money;
                }
                foreach ($t_member_company_profit as $key => $value) {
                    $company_money_avg = bcdiv(bcmul($value['money'],$value['profit'],9),$value['num'],9);
                    if($company_money_avg > 0){
                        foreach ($value['sonlist'] as $k => $v) {
                            $param = [];
                            $param['t_company_id']  =   $v['t_company_id'];
                            $param['t_company_money']    =   $company_money_avg;
                            $param['user_id']   =   $buy_user['user_id'];
                            $param['paid_sn']   =   $buy_user['paid_sn'];
                            $param['id']   =   $buy_user['id'];
                            $t_result_array[] = $this->do_company_rebate($param);
                        }
                    }
                }
                #将返回的sql语句与外部的sql语句整合
                if($t_result_array){
                    foreach ($t_result_array as $key => $value) {
                        #需要检测返回的数据是否存在
                        if(isset($value['member_update_list'])){
                            if(isset($member_update_list)){
                                $member_update_list = array_merge($member_update_list,$value['member_update_list']);
                            }else{
                                $member_update_list =  $value['member_update_list'];
                            }
                            $referee_company_money    =   $value['member_update_list'];
                        }
                        if(isset($value['account_member_insert_log'])){
                            if(isset($account_member_insert_log)){
                                $account_member_insert_log = array_merge($account_member_insert_log,$value['account_member_insert_log']);
                            }else{
                                $account_member_insert_log = $value['account_member_insert_log'];
                            }
                        }
                        if(isset($value['commission_member_insert_log'])){
                            if(isset($commission_member_insert_log)){
                                $commission_member_insert_log = array_merge($commission_member_insert_log,$value['commission_member_insert_log']);
                            }else{
                                $commission_member_insert_log = $value['commission_member_insert_log'];
                            }
                        }  
                    }
                }
                
                if($referee_company_money){
                    foreach ($referee_company_money as $key => $value) {
                        $order_save_data['referee_company_money'] += $this->findMoney($value['money'][1]);
                    }
                }
                
            }
            #推荐子公司部分结束
        }
        
        /*计算剩余金额*/
        $surplus = 0;
        if($staff_update_list){
            foreach ($staff_update_list as $key => $value) {
                $surplus    =   bcadd($surplus,$this->findMoney($value['money'][1]),9);
            }
        }
        if($member_update_list){
            foreach ($member_update_list as $key => $value) {
                $surplus    =   bcadd($surplus,$this->findMoney($value['money'][1]),9);
            }
        }
        if($surplus > 0){
            $order_save_data['surplus'] =  bcsub($buy_user['money'],$surplus,9); 
        }
        if(empty($staff_update_list) && empty($member_update_list)){
            $res['rebate_status']   =   -2;
            $res = array_merge($res,$order_save_data);
            return $res;
        }

        Db::startTrans();
        try{
            #更新员工创业合伙人表 余额
            if($staff_update_list){
                model('staff')->saveAll($staff_update_list); 
            }
            if($account_staff_insert_log){
                db('staff_account_log')->insertAll($account_staff_insert_log);       //员工资金记录
            }
            if($account_member_insert_log){
                db('member_account_log')->insertAll($account_member_insert_log);       //成员佣金表
            }
            if($commission_staff_insert_log){
                db('staff_commission')->insertAll($commission_staff_insert_log); #员工佣金记录表
            }
            if($member_update_list){
                model('CompanyMember')->saveAll($member_update_list);
            }
            if($commission_member_insert_log){
                db('member_commission')->insertAll($commission_member_insert_log); #成员佣金记录表
            }

            Db::commit();
            $res['rebate_status']   =   1;
            $res = array_merge($res,$order_save_data);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $res['rebate_status']   =   0;
        }         
        return $res;
        #子公司部分结束
    }


    #param  $t_company_id 推荐子公司的ID
    #param  $t_company_money 推按子公司应得的分红总额
    #return $return_res  将 需更新数据 组装后返回
    #推荐子公司返利
    public function do_company_rebate($param){
        $t_company_id = $param['t_company_id'];
        $t_company_money = $param['t_company_money'];

        $company_where['parent_id']   =   ['eq',$t_company_id];
        $company_where['is_lock'] =  ['eq',0];
        $company_member_list = db('company_member')->where($company_where)
            ->alias('member')
            ->field('member.id,company_level,lv.profit')
            ->cache("auto_company_member_{$t_company_id}")
            ->join("company_level lv",'lv.id = member.company_level')
            ->select();
        // 遍历子公司成员
        if($company_member_list){
            #判断每个层级有多少人  将数据格式化 方便计算
            foreach ($company_member_list as $key => $value) {
                $temp_member_company_profit[$value['company_level']]['num']  += 1;
                $temp_member_company_profit[$value['company_level']]['sonlist'][] =   $value;
                $temp_member_company_profit[$value['company_level']]['profit']  =   $value['profit'];
            }
            #统计子公司分红总额
            $company_money_count = 0;
            foreach ($temp_member_company_profit as $key => $value) {
                #层级内成员平均返利
                $company_money_avg = bcdiv(bcmul($t_company_money,$value['profit'],9),$value['num'],9);
                if($company_money_avg > 0){
                    foreach ($value['sonlist'] as $k => $v) {
                        $member_update_list[] = ['id'=>$v['id'],
                            'money'=>['exp',"money + {$company_money_avg}"],
                            'cumulative_money'=>['exp',"cumulative_money + {$company_money_avg}"]
                        ];
                        if(isset($param['paid_sn'])){
                            $account_member_insert_log[]  =   ['member_id'=>$v['id'],
                                                            'member_money'=>$company_money_avg,
                                                            'create_time'=>NOW_TIME,
                                                            'desc'=>"扫码订单：{$param['paid_sn']} 推荐子公司成员返利"];

                            $commission_member_insert_log[]  =   ['member_id'=>$v['id'],
                                'money'=>$company_money_avg,
                                'create_time'=>NOW_TIME,
                                'info'=>"扫码订单：{$param['paid_sn']} 推荐子公司成员返利",
                                'buy_id'=>$param['user_id'],
                                'order_sn'=>$param['paid_sn'],
                                'pay_id'=>$param['id']
                            ];
                        }else{
                            #资金记录
                            $account_member_insert_log[] = ['member_id'=>$v['id'],
                                'member_money'=>$company_money_avg,
                                'create_time'=>NOW_TIME,
                                'desc'=>"{$param['is_red_str']} 用户订单：{$param['order_id']} 推荐子公司成员返利"
                            ];
                          
                            $commission_member_insert_log[] = ['member_id'=>$v['id'],
                                'money'=>$company_money_avg,
                                'create_time'=>NOW_TIME,
                                'info'=>"{$param['is_red_str']} 用户订单：{$param['order_id']} 推荐子公司成员返利",
                                'buy_id'=>$param['user_id'],
                                'order_id'=>$param['order_id'],
                                'order_sn'=>$param['order_sn']
                            ];
                        }

                    }
                }
            }
       
            if($member_update_list){
                $return_res['member_update_list'] = $member_update_list;
            }
            if($account_member_insert_log){
                //成员佣金表
                $return_res['account_member_insert_log'] = $account_member_insert_log;
            }
            if($commission_member_insert_log){
                #成员佣金记录表
                $return_res['commission_member_insert_log'] = $commission_member_insert_log;
            }
        }
        return $return_res;
    }

    public function tuiguang_back($buy_user,$promoters_moeny,$is_paid){
        //会员消费扫描的二维码不是绑定的推广员的二维码时，对推广员、实体店（成员）、子公司（成员）进行返利拆分
        //查询推广员所在实体店、子公司ID
        $promoter_info = M('staff')->cache("auto_staff_{$buy_user['tgy_id']}")->find($buy_user['tgy_id']);
//        print_r($promoter_info);die;
        //推广员ID
        $staff_id = $buy_user['tgy_id'];
        //实体店ID
        $store_id = intval($promoter_info['store_id']); //消费门店的实体店ID
        //子公司ID
        $company_id = intval($promoter_info['company_id']);     //消费门店的公司ID

        //获取推广员，实体店，子公司推广分红比例设置参数
        $tscbl = explode('|',$buy_user['tscbl']);

        #计算推广员部分           异店消费返利总额 * 子公司推广员部分
        $t_staff_money = bcmul($promoters_moeny,$tscbl[0],9);
        #计算实体店部分           异店消费返利总额 * 子公司实体店部分
        $t_store_money    =   bcmul($promoters_moeny,$tscbl[1],9);
        #计算子公司部分           异店消费返利总额 * 子公司部分
        $t_company_money = bcmul($promoters_moeny,$tscbl[2],9);

        if($store_id != 0) {
            #推广员部分开始    ,必须为未锁定，并且分配金额大于0
            if ($promoter_info['is_lock'] == 0 && $t_staff_money > 0) {
                if($t_staff_money > 0){
                    $staff_update_list[] = ['id' => $staff_id,
                        'money' => ['exp', "money + {$t_staff_money}"],
                        'cumulative_money' => ['exp', "cumulative_money + {$t_staff_money}"]
                    ];
                    
                    if ($is_paid) {  // 代付订单
                        #资金记录
                        $account_staff_insert_log[] = ['staff_id' => $staff_id,
                            'staff_money' => $t_staff_money,
                            'create_time' => NOW_TIME,
                            'desc' => "代付订单：{$buy_user['paid_sn']} 推广员推广返利"];   #'buy_id'=>$order['user_id']
                        #佣金记录
                        $commission_staff_insert_log[] = ['staff_id' => $staff_id,
                            'money' => $t_staff_money,
                            'create_time' => NOW_TIME,
                            'info' => "代付订单：{$buy_user['paid_sn']} 推广员推广返利",
                            'buy_id' => $buy_user['user_id'],
                            'paid_id' => $buy_user['id'],
                            'order_sn' => $buy_user['paid_sn'],
                            'is_tj' => 1];
                    } else {
                        #资金记录
                        $account_staff_insert_log[] = ['staff_id' => $staff_id,
                            'staff_money' => $t_staff_money,
                            'create_time' => NOW_TIME,
                            'desc' => "扫码订单：{$buy_user['paid_sn']}推广员推广返利"];

                        $commission_staff_insert_log[] = ['staff_id' => $staff_id,
                            'money' => $t_staff_money,
                            'create_time' => NOW_TIME,
                            'info' => "扫码订单：{$buy_user['paid_sn']} 推广员推广返利",
                            'buy_id' => $buy_user['user_id'],
                            'pay_id' => $buy_user['id'],
                            'order_sn' => $buy_user['paid_sn'],
                            'is_tj' => 1];
                    }
                }
            }
            #推广员部分结束
            #实体店（成员）部分开始
            $store_where['parent_id'] = ['eq',$store_id]; // 实体店ID
            $store_where['is_lock']   = ['eq',0];  // 是否被锁
            $store_member_list = db('company_member')->where($store_where)
                ->alias('member')
                ->cache("auto_is_lock_{$store_id}")
                ->field('member.id,company_level,lv.profit')
                ->join("company_level lv",'lv.id = member.company_level')
                ->select();
           //     print_r($store_member_list);die;
            if($store_member_list && $t_store_money > 0){         #如果实体店存在成员
                #判断每个层级有多少人  将数据格式化 方便计算
                foreach ($store_member_list as $key => $value) {
                    $temp_member_profit[$value['company_level']]['num']  += 1;
                    $temp_member_profit[$value['company_level']]['sonlist'][] =   $value;
                    $temp_member_profit[$value['company_level']]['profit']  =   $value['profit'];
                }
                foreach ($temp_member_profit as $key => $value) {
                    $store_money_avg = bcdiv(bcmul($t_store_money,$value['profit'],9),$value['num'],9);
                    foreach ($value['sonlist'] as $k => $v) {
                        if($store_money_avg > 0){
                            $member_update_list[] = ['id'=>$v['id'],
                                'money'=>['exp',"money + {$store_money_avg}"],
                                'cumulative_money'=>['exp',"cumulative_money + {$store_money_avg}"]
                            ];
                            if($is_paid){
                                #用户资金记录
                                $account_member_insert_log[]    = ['member_id'=>$v['id'],'member_money'=>$store_money_avg,'create_time'=>NOW_TIME,'desc'=>"代付订单：{$buy_user['paid_sn']} 实体店成员推广返利"]; #
                                #用户佣金记录
                                $commission_member_insert_log[] = ['member_id'=>$v['id'],
                                    'money'=>$store_money_avg,
                                    'create_time'=>NOW_TIME,
                                    'info'=>"代付订单：{$buy_user['paid_sn']} 实体店成员推广返利",
                                    'buy_id'=>$buy_user['user_id'],
                                    'order_sn'=>$buy_user['paid_sn'],
                                    'paid_id'=>$buy_user['id']
                                ];
                            }else{
                                #用户资金记录
                                $account_member_insert_log[] = ['member_id'=>$v['id'],'member_money'=>$store_money_avg,'create_time'=>NOW_TIME,'desc'=>"扫码订单：{$buy_user['paid_sn']} 实体店成员推广返利"];
                                #用户佣金记录
                                $commission_member_insert_log[] = ['member_id'=>$v['id'],
                                    'money'=>$store_money_avg,
                                    'create_time'=>NOW_TIME,
                                    'info'=>"扫码订单：{$buy_user['paid_sn']} 实体店成员推广返利",
                                    'buy_id'=>$buy_user['user_id'],
                                    'order_sn'=>$buy_user['paid_sn'],
                                    'pay_id'=>$buy_user['id']];
                            }
                        }
                    }
                }
            }
            #实体店（成员）部分结束
            #子公司（成员）部分开始
            $company_where['parent_id'] = ['eq',$company_id]; // 子公司ID
            $company_where['is_lock']   = ['eq',0];  // 是否被锁
            $company_member_list = db('company_member')->where($company_where)
                ->alias('member')
                ->cache("auto_is_lock_{$company_id}")
                ->field('member.id,company_level,lv.profit')
                ->join("company_level lv",'lv.id = member.company_level')
                ->select();
            if($company_member_list){
                #如果子公司存在人员
                #判断每个层级有多少人  将数据格式化  方便计算
                foreach ($company_member_list as $key => $value) {
                    $temp_member_company_profit[$value['company_level']]['num']  += 1;
                    $temp_member_company_profit[$value['company_level']]['sonlist'][] =   $value;
                    $temp_member_company_profit[$value['company_level']]['profit']  =   $value['profit'];
                }
                foreach ($temp_member_company_profit as $key => $value) {
                    $company_money_avg = bcdiv(bcmul($t_company_money,$value['profit'],9),$value['num'],9);
                    foreach ($value['sonlist'] as $k => $v) {
                        if($company_money_avg > 0){
                            $member_update_list[]   =   ['id'=>$v['id'],
                                'money'=>['exp',"money + {$company_money_avg}"],
                                'cumulative_money'=>['exp',"cumulative_money + {$company_money_avg}"]
                            ];
                            if($is_paid){
                                #资金记录
                                $account_member_insert_log[]  =   ['member_id'=>$v['id'],'member_money'=>$company_money_avg,'create_time'=>NOW_TIME,'desc'=>"代付订单：{$buy_user['paid_sn']} 子公司成员推广返利"];
                                $commission_member_insert_log[]  =   ['member_id'=>$v['id'],
                                    'money'=>$company_money_avg,
                                    'create_time'=>NOW_TIME,
                                    'info'=>"代付订单：{$buy_user['paid_sn']} 子公司成员推广返利",
                                    'buy_id'=>$buy_user['user_id'],
                                    'order_sn'=>$buy_user['paid_sn'],
                                    'paid_id'=>$buy_user['id']];
                            }else{
                                #资金记录
                                $account_member_insert_log[]  =   ['member_id'=>$v['id'],
                                'member_money'=>$company_money_avg,
                                'create_time'=>NOW_TIME,
                                'desc'=>"扫码订单：{$buy_user['paid_sn']} 子公司成员推广返利"];   
                                #,'buy_id'=>$order['user_id']
                                $commission_member_insert_log[]  =   ['member_id'=>$v['id'],
                                    'money'=>$company_money_avg,
                                    'create_time'=>NOW_TIME,
                                    'info'=>"扫码订单：{$buy_user['paid_sn']} 子公司成员推广返利",
                                    'buy_id'=>$buy_user['user_id'],
                                    'order_sn'=>$buy_user['paid_sn'],
                                    'pay_id'=>$buy_user['id']];
                            }
                        }
                        

                    }
                }
            }
            if($staff_update_list) {
                $return_res['staff_update_list']    =   $staff_update_list;
            }
            if($account_staff_insert_log) {
                $return_res['account_staff_insert_log'] =   $account_staff_insert_log;
            }
            if($commission_staff_insert_log) {
                $return_res['commission_staff_insert_log']  =   $commission_staff_insert_log;
            }
            if($member_update_list) {
                $return_res['member_update_list']   =   $member_update_list;
            }
            if($account_member_insert_log) {
                $return_res['account_member_insert_log']    =   $account_member_insert_log;
            }
            if($commission_member_insert_log) {
                $return_res['commission_member_insert_log'] =   $commission_member_insert_log;
            }
            return $return_res;
        }
        
    }

    /*线下扫码付款 员工奖励  */
    #https://www.midoushu.com/auto/index/sweep_reward/user_name/tkauto/psw/653234
    public function sweep_reward(){

        $reward_switch = tpCache('basic.reward_switch');
        if($reward_switch){
            #当天未奖励员工消费记录
            $where['pay_status']    = ['eq',1];
            $where['reward_status'] = ['eq',0];
            $where['pay_time'] = ['between time',[date('Y-m-d').' 00:00:00',date('Y-m-d').' 23:59:59']];


            $list = db('staff_mypays')->where($where)->order('id')->select();

            #当天已奖励员工记录
            $where_success['create_time'] = ['between time',[date('Y-m-d').' 00:00:00',date('Y-m-d').' 23:59:59']];
            $list_success = db('staff_reward')->where($where_success)->order('id')->select();


            #员工累计每被扫码支付多少次
            $consumption_total_count = tpCache('basic.consumption_total_count');
            #每次扫码支付满多少元
            $consumption_money = tpCache('basic.consumption_money');
            #奖励员工多少元
            $reward_money = tpCache('basic.reward_money');
            #每天最多奖励多少次
            $reward_max_count = tpCache('basic.reward_max_count');

            $staff_arr = array();
            #根据员工ID将每个员工的没有结算的订单存储在一起
            foreach ($list as $key => $value) {
                $staff_arr[$value['staff_id']][] = $value;
            }

            $staff_total_count = array();
            foreach($staff_arr as $staff_id=>$value){
                #统计有多少次满足标准
                $count = 0;
                $mypays_id = array();
                foreach($value as $k=>$v){
                    if($v['money'] >= $consumption_money){
                        $mypays_id[$k]['id'] = $v['id'];
                        $mypays_id[$k]['money'] = $v['money'];
                        $count++;
                    }
                    if($count >=$consumption_total_count){
                        $staff_total_count[$staff_id]['count']++;
                        $staff_total_count[$staff_id]['mypays_id'][] = $mypays_id;
                        $count = 0;
                        $mypays_id = array();
                    }
                }

            }

            #统计当天每个员工已奖励的次数
            $staff_reward_ok = array();
            foreach($list_success as $key=>$value){
                $staff_reward_ok[$value['staff_id']]['count']++;
            }

            #将满足条件的员工奖励发放到对应员工的账户并记录流水
            foreach($staff_total_count as $staff_id=>$rst){

                if(isset($staff_reward_ok[$staff_id])){
                    $reward_count = $staff_reward_ok[$staff_id]['count'];
                }else{
                    $reward_count = 1;
                }

                foreach($rst['mypays_id'] as $v){
                    if($reward_count <= $reward_max_count){
                        $staff_update_list[] = ['id'=>$staff_id,
                            'money'=>['exp',"money + {$reward_money}"],
                            'cumulative_money'=>['exp',"cumulative_money + {$reward_money}"]
                        ];

                        /*$account_staff_insert_log[] = ['staff_id' => $staff_id,
                            'staff_money' => $reward_money,
                            'create_time' => NOW_TIME,
                            'desc' => "员工线下扫码奖励"];*/

                        $commission_staff_insert_log[]  =   ['staff_id'=>$staff_id,
                            'money'=>$reward_money,
                            'create_time'=>NOW_TIME,
                            'info'=>"员工线下扫码奖励",
                            'mypays_ids'=>serialize($v)]
                        ;

                        foreach($v as $sv){
                            $mypays_update_list[] = ['id'=>$sv['id'],
                                'reward_status'=>1
                            ];
                        }
                    }
                    $reward_count++;
                }

            }

            if($staff_update_list){
                model('staff')->saveAll($staff_update_list);
            }
            if($mypays_update_list){
                model('staff_mypays')->saveAll($mypays_update_list);
            }
            /*if($account_staff_insert_log){
                db('staff_account_log')->insertAll($account_staff_insert_log);       //员工资金记录
            }*/
            if($commission_staff_insert_log){
                db('staff_reward')->insertAll($commission_staff_insert_log);   #员工奖励记录表
            }

            echo "员工奖励结算完毕！";
        }



    }


}