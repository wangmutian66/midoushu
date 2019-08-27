<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 *  短信平台短信模板管理
 */
namespace app\admin\controller; 
use think\Page;
use think\Db;

class CapitalPool extends Base {

  //  public  $mod;
    
    public function _initialize() {
        parent::_initialize();        
     //   $this->mod = db('capital_pool');
    }
    
    public function index(){
        $item = M('capital_pool')->field('c.*,a.user_name admin_name')->alias('c')->join('__ADMIN__ a ','c.admin_id = a.admin_id','left')->find(1);  // 查询资金管理池
		$this->assign('item',$item);
        return $this->fetch('index');
       
    }
    /*资金池冲全返*/
    function out_back(){
        $money = M('capital_pool')->getField('money');
      #  dump($money);die;
        $this->assign('money',$money);
        return $this->fetch('out_back');
    }

    
    /*资金池日志管理*/
    public function log_list(){
        $res   = $list = array();
        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $where = " 1 = 1 ";
        $list  = M('capital_pool_log')
                        ->alias('pool_log')
                        ->field('pool_log.*,u.nickname,u.user_id,u.mobile,a.user_name admin_name')
                        ->join('__USERS__ u ','pool_log.user_id = u.user_id','left')
                        ->join('__ADMIN__ a ','pool_log.admin_id = a.admin_id','left')
                        ->where($where)->order('pool_log.id desc')->page("$p,$size")->select();
        $count = M('capital_pool_log')->where($where)->count(); // 查询满足要求的总记录数
        $pager = new Page($count,$size);// 实例化分页类 传入总记录数和每页显示的记录数
        //$page = $pager->show();//分页显示输出
        
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pager);// 赋值分页输出        
        return $this->fetch();
    }

    function export(){
        $list  = M('capital_pool_log')
                        ->alias('pool_log')
                        ->field('pool_log.*,u.nickname,u.user_id,u.mobile,a.user_name admin_name')
                        ->join('__USERS__ u ','pool_log.user_id = u.user_id','left')
                        ->join('__ADMIN__ a ','pool_log.admin_id = a.admin_id','left')
                        ->order('pool_log.id desc')->limit(1000)->select();
        $strTable ='<table width="1000" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">是否为管理员</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">资金</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">更新时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">备注</td>';
        $strTable .= '</tr>';

        foreach ($list as $key => $value) {
            $strTable .= '<tr>';
            if($value['user_id']){
                $s1 = $value['user_id'];
            }else{
                $s1 = $value['admin_name'];
            }
            if($value['admin_id'] != 0){
                $s2 =   '是';
            }else{
                $s2 =   '否';
            }
            $strTable .= '<td style="text-align:center;font-size:12px;">'.$s1.'</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$s2.' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'. $value['money'] .' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$value['create_time']).'</td>';
            $strTable .= '<td style="vnd.ms-excel.numberformat:@">'.$value['remark'].'</td>';            
            $strTable .= '</tr>';
        }
        $strTable .='</table>';

        downloadExcel($strTable,'资金池日志');
        exit();
    }

    /*修改资金池资金*/
    function modify(){
        $log_data['money']  =  $money = I('post.modify_money/f');
        $capital_data['money'] =   ['exp',"money + {$money}"];
        $log_data['admin_id']   =   $capital_data['admin_id']   =   $_SESSION['admin_id'];
        $log_data['create_time']    =  $capital_data['update_time']    =   NOW_TIME;
        $log_data['remark'] =   I('remark/s');
        if(empty($log_data['remark'])){
            $msg['status']  =   0;
            $msg['info']    =   '修改资金必须填写备注';
        }else{
            if($log_data['admin_id'] == 1){
                $tims = NOW_TIME;
                $r = db('capital_pool')->find(1);
                if(($money < 0) && abs($money) > $r['money']){
                    $msg['status']  =   0;
                    $msg['info']    =   '资金池资金不足!';
                }else{
                    db('capital_pool')->where('id = 1')->save($capital_data);
                    $log_data['act']    =   'modify';
                    $log_data['control']    =   'admin';
                    db('capital_pool_log')->add($log_data);
                    $msg['status']  =   1;
                }
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '资金池只有超级管理员能修改，您无权限修改!';
            }
        }
        $this->ajaxReturn($msg);
    }

    function do_back(){
        $back_money = I('money/f');
        $capital_moeny = M('capital_pool')->getField('money');  
       /* $res['status']  =   1;
        $res['info']    =   '每次冲击金额不得小于100';
        $this->ajaxReturn($res);*/
        if($back_money < 100){
            $res['status']  =   0;
            $res['info']    =   '每次冲击金额不得小于100';
            $this->ajaxReturn($res);
        }
        if($back_money > $capital_moeny){
            $res['status']  =   0;
            $res['info']    =   '使用的金额大于当前资金池剩余金额';
            $this->ajaxReturn($res);
        }
        $dangshu = I('dangshu/d',0);
        ini_set("max_execution_time", 30000);
   #     $total_ratio = explode('|',tpCache('proportion.total_ratio'));
        #全返start
    #    $back_money = bcmul($money,$total_ratio[1],9);
        #全返档数比例
        $back_proportion = explode('|',tpCache('proportion.back_proportion'));

        $temp_numbers = 0;
        foreach ($back_proportion as $k => $v) {
            $temp_arr = explode(',', $v);
            $temp_numbers =  bcadd($temp_numbers,$temp_arr[0],9);
            $back_proportion[$k] =   [$temp_numbers,$temp_arr[1]];
        }
        //查询当前订单之前的所有订单  并且返利的金额小于  花费现金的总额
        $previous_order_list = M('order')->field('order_id,user_id,order_amount,shipping_price,already_rebate,confirm_time')->where("confirm_time < ".NOW_TIME." and order_status in(2,4) and already_rebate < (order_amount - shipping_price) and is_allreturn = 1 ")->order('confirm_time asc')->select();
       
      /*  dump($previous_order_list);
        die;*/
        if(empty($previous_order_list)){
            //如果之前没有订单，那么则结束该函数
            $res['stauts']  =   0;
            $res['info']    =   '没有订单，全返冲击未执行';
            $this->ajaxReturn($res);
        }

    

        #计算之前订单总数 ,
        $previous_order_list_sum =(count($previous_order_list) == 1) ? 1 : count($previous_order_list)-1;
        //统计每个层级有多少个订单,并将层级进行格式化
        foreach ($previous_order_list as $key => $value) {
            $current_position =  sprintf("%.2f", $key/$previous_order_list_sum);    //计算当前位置，小数表示 
        #    $current_position =  bcdiv($key,$previous_order_list_sum,2); 
            foreach ($back_proportion as $k => $v) {
                if($current_position <= $v[0]){
                    $back_proportion[$k]['sum'] += 1;   #计算每个层级有多少个订单
                    break;
                }
            }
            $user_ids[] =   $value['user_id'];
        }
        $total_money = 0;
/*        dump($back_proportion);
        die;*/
        
        //进行计算全返部分
        foreach ($previous_order_list as $key => $value) {
            $current_position =  sprintf("%.2f", $key/$previous_order_list_sum);    //判断当前循环位置
        #    $current_position =  bcdiv($key,$previous_order_list_sum,2); 
            foreach ($back_proportion as $k => $v) {
                if($current_position <= $v[0]){
                    if($dangshu != 0){
                        if(($dangshu - 1) != $k){
                            break;
                        }
                    }
                //    echo $k;
                    #判断当前订单还剩下多少返利
                   
                    $limit_money = bcsub(bcsub($value['order_amount'],$value['shipping_price'],9),$value['already_rebate'],9);
                    

                    if($dangshu != 0){
                        $money =    bcdiv($back_money,$v['sum'],9);
                    }else{
                        #返利部分 * 总反比部分的百分比 再除以当前本档次的总人数
                        $money  =   bcdiv(bcmul($back_money,$v[1],9),$v['sum'],9); 
                    }
                  
                    if($money > $limit_money){
                        $money = $limit_money;
                    }
                 //     echo $money;die;
                    $user_list[$value['user_id']]['money'] =  bcadd($money,$user_list[$value['user_id']]['money'],9);
                    $order_update_rebate[]  =   ['order_id'=>$value['order_id'],'already_rebate'=>['exp',"already_rebate + {$money}"]];
                    //计算总花费金额
                    $total_money = bcadd($total_money,$money,9);
                    break;
                }
            }
        }
      /*  dump($order_update_rebate);
        die;*/
        #因为有的用户肯能会很多个订单，一笔订单反多次，但是记录加钱只有一次，省着显示太乱
        foreach ($user_list as $key => $value) {
            $previous_log_data[] = ['uid'=>$key,
                                    'money'=>$value['money'],
                                    'create_time'   =>  NOW_TIME,
                                    'insert_type'   =>  1,
                                    'order_id'  =>  $order['order_id'],
                                    'buy_uid'  =>  $order['user_id'],
                                    'order_sn'=>$order['order_sn'],
                                    ];
            $modify_user_money[]    =   ['user_id'=>$key,
                                            'rebate_money'=>['exp'," rebate_money + {$value['money']}"],
                                            'rebate_money_all'=>['exp'," rebate_money_all + {$value['money']}"]
                                        ];
        }
        if($previous_log_data){
            db('previous_log')->insertAll($previous_log_data);
        }
        if($modify_user_money){
            model('Users')->saveAll($modify_user_money);
        }
        if($order_update_rebate){
            model("Order")->saveAll($order_update_rebate);
        }
  

        $capital_data['money'] =   ['exp',"money - {$total_money}"];
        $log_data['admin_id']   =   $capital_data['admin_id']   =   $_SESSION['admin_id'];
        $log_data['create_time']    =  $capital_data['update_time']    =   NOW_TIME;
        db('capital_pool')->where('id = 1')->update($capital_data);
        $log_data['money'] = ($total_money * -1);
        $log_data['act']    =   'do_back';
        $log_data['control']    =   'admin';
        $log_data['remark'] =   I('remark/s');
        db('capital_pool_log')->add($log_data);
        #全返结束
        $res['status']  =   1;

      
        if((string)$total_money < (string)$back_money){
            $h = bcsub($total_money,$back_money,9);
            $res['info']    =   "预计使用{$back_money},实际使用{$total_money},剩余{$h}";
        }else{
            $res['info']    =   '资金池冲击全返成功！';
        }
        $this->ajaxReturn($res);
    }

}