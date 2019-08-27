<?php
/**
 * tpshop
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\admin\controller;
use app\admin\logic\RedGoodsLogic;
use think\Db;
use think\Page;

class ReportRed extends Base{
    public $begin;  // 开始
    public $end;    // 结束
    public function _initialize(){
        parent::_initialize();
        $start_time = I('start_time');  // 开始时间
        if(I('start_time')){
           $begin    = urldecode($start_time);
           $end_time = I('end_time');   // 结束时间
           $end      = urldecode($end_time);
        }else{
           $begin = date('Y-m-d', strtotime("-3 month")); //30天前  ···明明是 3 个月前
           $end   = date('Y-m-d', strtotime('+1 days'));  // 1 天后
        }
        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);
        $this->begin = strtotime($begin);
        $this->end   = strtotime($end)+86399;  // 比 24 小时 少 1 s
    }
    
    public function index(){
        $now = strtotime(date('Y-m-d'));  // 今天 0 点 时间戳
        $today['today_amount'] = M('order_red')->where("add_time>$now AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4)")->sum('total_amount');//今日销售总额
        $today['today_order']  = M('order_red')->where("add_time>$now and (pay_status=1 or pay_code='cod')")->count(); //今日支付订单数
        $today['cancel_order'] = M('order_red')->where("add_time>$now AND order_status=3")->count(); //今日取消订单
        if ($today['today_order'] == 0) { // 如果订单数为 0 
            $today['sign'] = round(0, 2); // 四舍五入 两位小数
        } else {
            $today['sign'] = round($today['today_amount'] / $today['today_order'], 2); // 每单平均 销售额
        }
        $this->assign('today',$today);  
        $select_year = $this->select_year; // ？？？没找到定义
        $begin       = $this->begin;
        $end         = $this->end;

        $where = " add_time >$begin and add_time < $end AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4) ";
        // liyi 2018.04.18
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " AND suppliers_id > 0";
            $suppliers_id = I('suppliers_id','','intval');
            if($suppliers_id){
                $where .= " AND suppliers_id = ".$suppliers_id;
            }
        } else if($sp && $sp == 2){
            $where .= " AND suppliers_id = 0";
        }
        
        $res = Db::name("order_red".$select_year)
            ->field(" COUNT(*) as tnum, sum(order_amount) as amount, sum(shipping_price) as shipping_amount, sum(tk_cost_price) as cost_amount,sum(tk_cost_operating) as cost_operating,sum(goods_price) as goods_amount, sum(midou) as midou, sum(midou_money) as midou_money, FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap ")
            ->where($where)
            ->group('gap')
            ->select();
        foreach ($res as $val){
            $arr[$val['gap']] = $val['tnum'];         // 日期下，订单数
            $brr[$val['gap']] = $val['amount'];       // 日期下，销售额
            $crr[$val['gap']] = $val['cost_amount'];      // 日期下，商品成本
            $drr[$val['gap']] = $val['cost_operating'];   // 日期下，运营成本
            $err[$val['gap']] = $val['goods_amount']; // 日期下，销售额
            $frr[$val['gap']] = ($val['amount'] - $val['shipping_amount'] - $val['cost_amount'] - $val['cost_operating']); 
            $grr[$val['gap']] = $val['midou'];        // 日期下，米豆
            $hrr[$val['gap']] = $val['midou_money'];  // 日期下，现金部分
            $irr[$val['gap']] = $val['shipping_amount'];  // 订单运费 
            $tnum           += $val['tnum'];            // ???什么情况？总数？
            $tamount        += $val['amount'];          // ???什么情况？订单金额？
            $cost_amount    += $val['cost_amount'];     // ???什么情况？总数？
            $cost_operating += $val['cost_operating'];   // ???什么情况？总数？
            $goods_amount   += $val['goods_amount'];    // ???什么情况？订单金额？
            $goods_lr       += ($val['amount'] - $val['shipping_amount'] - $val['cost_amount'] - $val['cost_operating']);   // ???什么情况？订单金额？
            $midou          += $val['midou'];
            $midou_money    += $val['midou_money'];
            $shipping_amount+= $val['shipping_amount'];  // ？
        }

        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $tmp_num            = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
            $tmp_amount         = empty($brr[date('Y-m-d',$i)]) ? 0 : $brr[date('Y-m-d',$i)];
            $tmp_cost_amount    = empty($crr[date('Y-m-d',$i)]) ? 0 : $crr[date('Y-m-d',$i)];
            $tmp_cost_operating = empty($drr[date('Y-m-d',$i)]) ? 0 : $drr[date('Y-m-d',$i)];
            $tmp_goods_amount   = empty($err[date('Y-m-d',$i)]) ? 0 : $err[date('Y-m-d',$i)];
            $tmp_goods_lr       = empty($frr[date('Y-m-d',$i)]) ? 0 : $frr[date('Y-m-d',$i)];
            $tmp_midou          = empty($grr[date('Y-m-d',$i)]) ? 0 : $grr[date('Y-m-d',$i)];
            $tmp_midou_money    = empty($hrr[date('Y-m-d',$i)]) ? 0 : $hrr[date('Y-m-d',$i)];
            $tmp_shipping_amount = empty($irr[date('Y-m-d',$i)]) ? 0 : $irr[date('Y-m-d',$i)];
            $tmp_sign            = empty($tmp_num) ? 0 : round($tmp_amount/$tmp_num,2);                      
            $order_arr[]          = $tmp_num;
            $amount_arr[]         = $tmp_amount;
            $cost_amount_arr[]    = $tmp_cost_amount;
            $cost_operating_arr[] = $tmp_cost_operating;
            $goods_amount_arr[]   = $tmp_goods_amount;    
            $goods_lr_arr[]       = $tmp_goods_lr; 
            $midou_arr[]          = $tmp_midou;  
            $midou_money_arr[]    = $tmp_midou_money;   
            $shipping_amount_arr[]= $tmp_shipping_amount;        
            $sign_arr[]           = $tmp_sign;
            $date                 = date('Y-m-d',$i);
            $list[]               = array('day'=>$date,'order_num'=>$tmp_num,'amount'=>$tmp_amount,'cost_amount'=>$tmp_cost_amount,'cost_operating'=>$tmp_cost_operating,'goods_amount'=>$tmp_goods_amount,'goods_lr'=>$tmp_goods_lr,'shipping_amount'=>$tmp_shipping_amount,'midou'=>$tmp_midou,'midou_money'=>$tmp_midou_money,'sign'=>$tmp_sign,'end'=>date('Y-m-d',$i+24*60*60));
            $day[]              = $date;
        }

        rsort($list);  // 对数组 $list 中的元素按字母进行降序排序
        $this->assign('list',$list);
        $result = array('order'=>$order_arr,'amount'=>$amount_arr,'sign'=>$sign_arr,'time'=>$day,'cost_amount'=>$cost_amount_arr,'cost_operating'=>$cost_operating_arr,'goods_amount'=>$goods_amount_arr,'goods_lr'=>$goods_lr_arr,'shipping_amount'=>$shipping_amount_arr,'midou'=>$midou_arr,'midou_money'=>$midou_money_arr);
        $this->assign('result',json_encode($result));
        return $this->fetch();
    }

    public function recharge(){
        $now = strtotime(date('Y-m-d'));  // 今天 0 点 时间戳
        $today['today_amount'] = M('recharge')->where("ctime>$now AND pay_status=1")->sum('account'); //今日销售总额
        $today['today_order']  = M('recharge')->where("ctime>$now AND pay_status=1")->count();        //今日支付订单数
        $today['cancel_order'] = M('recharge')->where("ctime>$now AND pay_status=2")->count();           //今日取消订单
        if ($today['today_order'] == 0) { // 如果订单数为 0 
            $today['sign'] = round(0, 2); // 四舍五入 两位小数
        } else {
            $today['sign'] = round($today['today_amount'] / $today['today_order'], 2); // 每单平均 销售额
        }
        $this->assign('today',$today);  
        $select_year = $this->select_year; // ？？？没找到定义
        $begin       = $this->begin;
        $end         = $this->end;

        $where = " ctime >$begin and ctime < $end AND pay_status=1 ";
        
        $res = Db::name("recharge".$select_year)
            ->field(" COUNT(*) as tnum,sum(account) as amount, FROM_UNIXTIME(ctime,'%Y-%m-%d') as gap ")
            ->where($where)
            ->group('gap')
            ->select();
        foreach ($res as $val){
            $arr[$val['gap']] = $val['tnum'];   // 日期下，订单数
            $brr[$val['gap']] = $val['amount']; // 日期下，销售额
            $tnum    += $val['tnum'];    // ???什么情况？总数？
            $tamount += $val['amount'];  // ???什么情况？总金额？
        }

        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $tmp_num      = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
            $tmp_amount   = empty($brr[date('Y-m-d',$i)]) ? 0 : $brr[date('Y-m-d',$i)];
            $tmp_sign     = empty($tmp_num) ? 0 : round($tmp_amount/$tmp_num,2);                        
            $order_arr[]  = $tmp_num;
            $amount_arr[] = $tmp_amount;            
            $sign_arr[]   = $tmp_sign;
            $date         = date('Y-m-d',$i);
            $list[]       = array('day'=>$date,'order_num'=>$tmp_num,'amount'=>$tmp_amount,'sign'=>$tmp_sign,'end'=>date('Y-m-d',$i+24*60*60));
            $day[]        = $date;
        }

        rsort($list);  // 对数组 $list 中的元素按字母进行降序排序
        $this->assign('list',$list);
        $result = array('order'=>$order_arr,'amount'=>$amount_arr,'sign'=>$sign_arr,'time'=>$day);
        $this->assign('result',json_encode($result));
        return $this->fetch();
    }

    public function saleTop(){
        $sql = "select goods_name,goods_sn,sum(goods_num) as sale_num,sum(goods_num*goods_price) as sale_amount,sum(goods_num*cost_price) as cost_amount,sum(goods_num*cost_operating) as operating_amount,sum(goods_num*midou_money-goods_num*cost_price-goods_num*cost_operating) as lr_amount,sum(midou) as midou_amount,sum('midou_money') as midou_money_amount from __PREFIX__order_red_goods ";
        $sql .=" where is_send = 1";
        // liyi 2018.04.18
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $sql .= " AND suppliers_id > 0";
        } else if($sp && $sp == 2){
            $sql .= " AND suppliers_id = 0";
        }
        $sql .=" group by goods_id order by sale_num DESC limit 100";
        $res = DB::cache(true,3600)->query($sql);
        $this->assign('list',$res);
        return $this->fetch();
    }


        public function export_saleTop(){
        $sql = "select goods_name,goods_sn,sum(goods_num) as sale_num,sum(goods_num*goods_price) as sale_amount,sum(goods_num*cost_price) as cost_amount,sum(goods_num*cost_operating) as operating_amount,sum(goods_num*midou_money-goods_num*cost_price-goods_num*cost_operating) as lr_amount,sum(midou) as midou_amount,sum('midou_money') as midou_money_amount from __PREFIX__order_red_goods ";
        $sql .=" where is_send = 1";
        // liyi 2018.04.18
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $sql .= " AND suppliers_id > 0";
        } else if($sp && $sp == 2){
            $sql .= " AND suppliers_id = 0";
        }
        $rec_ids = I('rec_ids');
        if($rec_ids){
            $sql .= " AND rec_id IN (".$rec_ids.")";
        }

        $sql .=" group by goods_id order by sale_num DESC limit 100";
        $order_list = DB::cache(true,3600)->query($sql);

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:30px;">排行</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="600">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">货号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">销售量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">销售额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品成本</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运营成本</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">利润</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">现金</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">均价</td>';
        $strTable .= '</tr>';
        if(is_array($order_list)){
            foreach($order_list as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.($k+1).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['sale_num'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['sale_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['operating_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['lr_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_money_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.round(($val['sale_amount']/$val['sale_num']),2).'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($order_list);
        downloadExcel($strTable,'saleTopRed');
        exit();
    }

    /**
     * 统计报表 - 供货商排行
     * @return mixed
     * @author liyi
     */
    public function supplierTop(){
        $suppliers_phone = I('suppliers_phone');
        $suppliers_name  = I('suppliers_name');
        $order_where = [
            'o.add_time'=>['egt',$this->begin],
            'o.add_time'=>['elt',$this->end],
            'o.pay_status'=>1,
            'o.suppliers_id'=>['gt',0]
        ];
        if($suppliers_phone){
            $suppliers_where['suppliers_phone'] =$suppliers_phone;
        }       
        if($suppliers_name){
            $suppliers_where['suppliers_name'] = $suppliers_name;
        }
        if($suppliers_where){   //有查询单个用户的条件就去找出user_id
            $suppliers_id = Db::name('suppliers')->where($suppliers_where)->getField('suppliers_id');
            $order_where['o.suppliers_id'] = $suppliers_id;
        }

        $count = Db::name('order_red')->alias('o')->where($order_where)->group('o.suppliers_id')->count();  //统计数量
        $Page = new Page($count,20);
        $list = Db::name('order_red')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.order_amount) as amount,sum(o.tk_cost_price) as cost_amount,sum(o.tk_cost_operating) as operating_amount,sum(o.goods_price) as goods_amount,o.suppliers_id,u.suppliers_phone,u.suppliers_name,u.suppliers_contacts')
            ->join('suppliers u','o.suppliers_id=u.suppliers_id','LEFT')
            ->where($order_where)
            ->group('o.suppliers_id')
            ->order('amount DESC')
            ->limit($Page->firstRow,$Page->listRows)
            ->cache(true)->select();   //以用户ID分组查询
        $this->assign('pager',$Page);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /**
     * 统计报表 - 会员排行
     * @return mixed
     */
    public function userTop(){

        $mobile = I('mobile');
        $email = I('email');
        $order_where = [
            'o.add_time'=>['egt',$this->begin],
            'o.add_time'=>['elt',$this->end],
            'o.pay_status'=>1
        ];
        if($mobile){
            $user_where['mobile'] =$mobile;
        }       
        if($email){
            $user_where['email'] = $email;
        }
        if($user_where){   //有查询单个用户的条件就去找出user_id
            $user_id = Db::name('users')->where($user_where)->getField('user_id');
            $order_where['o.user_id']=$user_id;
        }

        $count = Db::name('order_red')->alias('o')->where($order_where)->group('o.user_id')->count();  //统计数量
        $Page = new Page($count,20);
        $list = Db::name('order_red')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.order_amount) as amount,o.user_id,u.mobile,u.email,u.nickname')
            ->join('users u','o.user_id=u.user_id','LEFT')
            ->where($order_where)
            ->group('o.user_id')
            ->order('amount DESC')
            ->limit($Page->firstRow,$Page->listRows)
            ->cache(true)->select();   //以用户ID分组查询
        $this->assign('pager',$Page);
        $this->assign('list',$list);
        return $this->fetch();
    }

    public function saleOrder(){
        $end_time = $this->begin+24*60*60;
        $order_where = "o.add_time>$this->begin and o.add_time<$end_time";  //交易成功的有效订单
        // liyi 2018.04.18
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $order_where .= " AND o.suppliers_id > 0";
            $suppliers_id = I('suppliers_id','','intval');
            if($suppliers_id){
                $order_where .= " AND o.suppliers_id = ".$suppliers_id;
            }
        } else if($sp && $sp == 2){
            $order_where .= " AND o.suppliers_id = 0";
        }

        $order_count = Db::name('order_red')->alias('o')->where($order_where)->whereIn('order_status','1,24')->count();
        $Page = new Page($order_count,20);
        $order_list = Db::name('order_red')->alias('o')
            ->field('o.order_id,o.order_sn,o.goods_price,o.shipping_price,o.total_amount,o.order_amount,o.tk_cost_price,o.tk_cost_operating,o.midou,o.midou_money,o.add_time,u.user_id,u.nickname')
            ->join('users u','u.user_id = o.user_id','left')
            ->where($order_where)->whereIn('order_status','1,2,4')
            ->limit($Page->firstRow,$Page->listRows)->select();
        $this->assign('order_list',$order_list);
        $this->assign('page',$Page->show());
        return $this->fetch();
    }


    public function export_saleOrder(){
        $end_time    = $this->begin+24*60*60;
        $order_where = "o.add_time>$this->begin and o.add_time<$end_time";  //交易成功的有效订单
        // liyi 2018.04.18
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $order_where .= " AND o.suppliers_id > 0";
            $suppliers_id = I('suppliers_id','','intval');
            if($suppliers_id){
                $order_where .= " AND o.suppliers_id = ".$suppliers_id;
            }
        } else if($sp && $sp == 2){
            $order_where .= " AND o.suppliers_id = 0";
        }

        $order_ids = I('order_ids');
        if($order_ids){
            $order_where .= " AND o.order_id IN (".$order_ids.")";
        }

        $order_list = Db::name('order_red')->alias('o')
            ->field('o.order_id,o.order_sn,o.goods_price,o.shipping_price,o.total_amount,o.order_amount,o.tk_cost_price,o.tk_cost_operating,o.midou,o.midou_money,o.add_time,u.user_id,u.nickname')
            ->join('users u','u.user_id = o.user_id','left')
            ->where($order_where)->whereIn('order_status','1,2,4')
            ->select();

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">用户名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">应付款金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">物流价格</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品成本总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运营成本总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单现金</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">利润总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">下单日期</td>';
        $strTable .= '</tr>';
        if(is_array($order_list)){
            foreach($order_list as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shipping_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tk_cost_price'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tk_cost_operating'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['order_amount'] - $val['shipping_price'] - $val['tk_cost_price'] - $val['tk_cost_operating']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_money'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['add_time']).'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($order_list);
        downloadExcel($strTable,'saleOrderRed');
        exit();

    }


    /**
     * 销售明细列表
     */
    public function saleList(){
        $cat_id = I('cat_id',0);
        $brand_id = I('brand_id',0);
        $where = "o.add_time>$this->begin and o.add_time<$this->end and order_status in(1,2,4) ";  //交易成功的有效订单
        if($cat_id>0){
            $where .= " and (g.cat_id=$cat_id or g.extend_cat_id=$cat_id)";
            $this->assign('cat_id',$cat_id);
        }
        if($brand_id>0){
            $where .= " and g.brand_id=$brand_id";
            $this->assign('brand_id',$brand_id);
        }
        // liyi 2018.04.18
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " AND o.suppliers_id > 0";
        } else if($sp && $sp == 2){
            $where .= " AND o.suppliers_id = 0";
        }

        $count = Db::name('order_red_goods')->alias('og')
            ->join('order o','og.order_id=o.order_id ','left')
            ->join('goods g','og.goods_id = g.goods_id','left')
            ->where($where)->count();  //统计数量
        $Page = new Page($count,20);
        $show = $Page->show();

        $res = Db::name('order_red_goods')->alias('og')->field('og.*,o.order_sn,o.shipping_name,o.pay_name,o.add_time')
            ->join('order_red o','og.order_id=o.order_id ','left')
            ->join('goods_red g','og.goods_id = g.goods_id','left')
            ->where($where)->limit($Page->firstRow,$Page->listRows)
            ->order('o.add_time desc')->select();
            
        $this->assign('list',$res);
        $this->assign('pager',$Page);
        $this->assign('page',$show);

        $GoodsLogic = new RedGoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();  //获取排好序的品牌列表
        $categoryList = $GoodsLogic->getSortCategory(); //获取排好序的分类列表
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        return $this->fetch();
    }


    public function export_saleList(){
        $end_time    = $this->begin+24*60*60;
        $cat_id = I('cat_id',0);
        $brand_id = I('brand_id',0);
        $where = "o.add_time>$this->begin and o.add_time<$this->end and order_status in(1,2,4) ";  //交易成功的有效订单
        if($cat_id>0){
            $where .= " and (g.cat_id=$cat_id or g.extend_cat_id=$cat_id)";
            $this->assign('cat_id',$cat_id);
        }
        if($brand_id>0){
            $where .= " and g.brand_id=$brand_id";
            $this->assign('brand_id',$brand_id);
        }
        // liyi 2018.04.18
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " AND o.suppliers_id > 0";
        } else if($sp && $sp == 2){
            $where .= " AND o.suppliers_id = 0";
        }

        $order_ids = I('order_ids');
        if($order_ids){
            $where .= " AND og.order_id IN (".$order_ids.")";
        }

        $order_list = Db::name('order_goods')->alias('og')->field('og.*,o.order_sn,o.shipping_name,o.pay_name,o.add_time')
            ->join('order o','og.order_id=o.order_id ','left')
            ->join('goods g','og.goods_id = g.goods_id','left')
            ->where($where)
            ->order('o.add_time desc')->select();

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="600">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品货号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">售价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品成本价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运营成本价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">总售价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">总利润</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">现金部分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">出售日期</td>';
        $strTable .= '</tr>';
        if(is_array($order_list)){
            foreach($order_list as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_num'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_operating'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['goods_price']*$val['goods_num']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['midou_money']*$val['goods_num'] - $val['cost_price']*$val['goods_num'] - $val['cost_operating']*$val['goods_num']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['midou']*$val['goods_num']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['midou_money']*$val['goods_num']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['add_time']).'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($order_list);
        downloadExcel($strTable,'saleListRed');
        exit();
    }

    
    public function user(){
        $today = strtotime(date('Y-m-d'));
        $month = strtotime(date('Y-m-01'));
        $user['today'] = D('users')->where("reg_time>$today")->count();//今日新增会员
        $user['month'] = D('users')->where("reg_time>$month")->count();//本月新增会员
        $user['total'] = D('users')->count();//会员总数
        $user['user_money'] = D('users')->sum('user_money');//会员余额总额
        $res = M('order_red')->cache(true)->distinct(true)->field('user_id')->select();
        $user['hasorder'] = count($res);
        $this->assign('user',$user);
        $sql = "SELECT COUNT(*) as num,FROM_UNIXTIME(reg_time,'%Y-%m-%d') as gap from __PREFIX__users where reg_time>$this->begin and reg_time<$this->end group by gap";
        $new = DB::query($sql);//新增会员趋势
        foreach ($new as $val){
            $arr[$val['gap']] = $val['num'];
        }
        
        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $brr[] = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
            $day[] = date('Y-m-d',$i);
        }       
        $result = array('data'=>$brr,'time'=>$day);
        $this->assign('result',json_encode($result));                   
        return $this->fetch();
    }
    
    //财务统计
    public function finance(){
        $sql = "SELECT sum(a.order_amount) as amount,sum(b.goods_num*b.member_goods_price) as goods_amount,sum(a.shipping_price) as shipping_amount, sum(a.tk_cost_price) as cost_price, sum(a.tk_cost_operating) as cost_operating, sum(b.goods_num*b.midou) as midou_amount, sum(b.goods_num*b.midou_money) as midou_money_amount,";
        $sql .= "sum(a.coupon_price) as coupon_amount,FROM_UNIXTIME(a.add_time,'%Y-%m-%d') as gap from __PREFIX__order_red a left join __PREFIX__order_red_goods b on a.order_id=b.order_id ";
        $sql .= " where a.add_time>$this->begin and a.add_time<$this->end AND a.pay_status=1 and a.shipping_status=1 and b.is_send=1";
        // liyi 2018.04.18
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $sql .= " AND b.suppliers_id > 0";
        } else if($sp && $sp == 2){
            $sql .= " AND b.suppliers_id = 0";
        }

        $sql .= " group by gap order by a.add_time";

        $res = DB::cache(true)->query($sql);//物流费,交易额,成本价
        
        foreach ($res as $val){
            $arr[$val['gap']] = $val['goods_amount'];
            $brr[$val['gap']] = $val['cost_price'];
            $crr[$val['gap']] = $val['cost_operating'];
            $drr[$val['gap']] = $val['shipping_amount'];
            $err[$val['gap']] = $val['coupon_amount'];
            //$frr[$val['gap']] = $val['midou_money_amount'] - $val['cost_price_amount'] - $val['cost_operating_amount'];
            $cb = $val['shipping_amount'] + $val['cost_price'] + $val['cost_operating'];
            $frr[$val['gap']] = bcsub($val['amount'],$cb,2);
            $grr[$val['gap']] = $val['midou_amount'];
            $hrr[$val['gap']] = $val['midou_money_amount'];
            $irr[$val['gap']] = $val['amount'];
        }
            
        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $date = $day[]         = date('Y-m-d',$i);
            $tmp_goods_amount      = empty($arr[$date]) ? 0 : $arr[$date];
            $tmp_cost_amount       = empty($brr[$date]) ? 0 : $brr[$date];
            $tmp_operating_amount  = empty($crr[$date]) ? 0 : $crr[$date];
            $tmp_shipping_amount   = empty($drr[$date]) ? 0 : $drr[$date];
            $tmp_coupon_amount     = empty($err[$date]) ? 0 : $err[$date];
            $tmp_lr_amount         = empty($frr[$date]) ? 0 : $frr[$date];
            $tmp_midou_amount      = empty($grr[$date]) ? 0 : $grr[$date];
            $tmp_midou_money_amount= empty($hrr[$date]) ? 0 : $hrr[$date];
            $tmp_amount            = empty($irr[$date]) ? 0 : $irr[$date];
            
            $goods_arr[]       = $tmp_goods_amount;
            $cost_arr[]        = $tmp_cost_amount;
            $operating_arr[]   = $tmp_operating_amount;
            $shipping_arr[]    = $tmp_shipping_amount;
            $coupon_arr[]      = $tmp_coupon_amount;
            $lr_arr[]          = $tmp_lr_amount;
            $midou_arr[]       = $tmp_midou_amount;
            $midou_money_arr[] = $tmp_midou_money_amount;
            $amount_arr[]   = $tmp_amount;
            $list[] = array('day'=>$date,'goods_amount'=>$tmp_goods_amount,'cost_amount'=>$tmp_cost_amount,'operating_amount'=>$tmp_operating_amount,
                    'shipping_amount'=>$tmp_shipping_amount,'coupon_amount'=>$tmp_coupon_amount,'amount'=>$tmp_amount,'lr_amount'=>$tmp_lr_amount,'midou_amount'=>$tmp_midou_amount,'midou_money_amount'=>$tmp_midou_money_amount,'end'=>date('Y-m-d',$i+24*60*60));
        }
        rsort($list);
        $this->assign('list',$list);
        $result = array('goods_arr'=>$goods_arr,'cost_arr'=>$cost_arr,'operating_arr'=>$operating_arr,'shipping_arr'=>$shipping_arr,'coupon_arr'=>$coupon_arr,'lr_arr'=>$lr_arr,'midou_arr'=>$midou_arr,'midou_money_arr'=>$midou_money_arr,'amount_arr'=>$amount_arr,'time'=>$day);
        $this->assign('result',json_encode($result));
        return $this->fetch();
    }
    
    public function expense_log(){
        $map = array();
        $add_time_begin = I('add_time_begin');
        $add_time_end = I('add_time_end');
        $begin = strtotime($add_time_begin);
        $end = strtotime($add_time_end);
        $admin_id = I('admin_id');
        if($begin && $end){
            $map['addtime'] = array('between',"$begin,$end");
        }
        if($admin_id){
            $map['admin_id'] = $admin_id;
        }
        $count = M('expense_red_log')->where($map)->count();
        $page = new Page($count);
        $lists  = M('expense_red_log')->where($map)->limit($page->firstRow.','.$page->listRows)->select();
        $this->assign('page',$page->show());
        $this->assign('total_count',$count);
        $this->assign('add_time_begin',$add_time_begin);
        $this->assign('add_time_end',$add_time_end);
        $this->assign('list',$lists);
        $admin = M('admin')->getField('admin_id,user_name');
        $this->assign('admin',$admin);
        $typeArr = array('','会员提现','订单退款','其他','供货商提现');//数据库设计问题
        $this->assign('typeArr',$typeArr);
        return $this->fetch();
    }

  /**
     * 运营概况详情
     * @return mixed
     */
    public function financeDetail(){
        $end_time = $this->begin+24*60*60;
        $order_where = "o.add_time>$this->begin and o.add_time<$end_time AND o.pay_status=1 and o.shipping_status=1 and og.is_send=1";  //交易成功的有效订单
        $order_count = Db::name('order_red')->alias('o')->join('order_red_goods og','o.order_id = og.order_id','left')->join('users u','u.user_id = o.user_id','left')->where($order_where)->group('o.order_id')->count();
        $Page = new Page($order_count,50);
        $order_list = Db::name('order_red')->alias('o')
            ->field('o.order_id,o.order_sn,o.order_prom_amount,o.coupon_price,o.goods_price,o.tk_cost_price,o.tk_cost_operating,o.midou,o.midou_money,o.shipping_price,o.total_amount,o.order_amount,o.add_time,u.user_id,u.nickname')
            ->join('order_red_goods og','o.order_id = og.order_id','left')
            ->join('users u','u.user_id = o.user_id','left')
            ->where($order_where)
            ->group('o.order_id')
            ->limit($Page->firstRow,$Page->listRows)->select();

        foreach ($order_list as $k => $val) {
            $cb = $val['shipping_price'] + $val['tk_cost_price'] + $val['tk_cost_operating'];
            $val['lr_amount'] = bcsub($val['order_amount'],$cb,2);
            $order_list[$k] = $val;
        }

        $this->assign('order_list',$order_list);
        $this->assign('page',$Page);
        return $this->fetch();
    }


    public function export_financeDetail(){
        $end_time = $this->begin+24*60*60;
        $order_where = "o.add_time>$this->begin and o.add_time<$end_time AND o.pay_status=1 and o.shipping_status=1 and og.is_send=1";  //交易成功的有效订单

        $order_ids = I('order_ids');
        if($order_ids){
            $order_where .= " AND og.order_id IN (".$order_ids.")";
        }

        $order_list = Db::name('order_red')->alias('o')
            ->field('o.order_id,o.order_sn,o.order_prom_amount,o.coupon_price,o.goods_price,o.tk_cost_price,o.tk_cost_operating,o.midou,o.midou_money,o.shipping_price,o.total_amount,o.order_amount,o.add_time,u.user_id,u.nickname')
            ->join('order_red_goods og','o.order_id = og.order_id','left')
            ->join('users u','u.user_id = o.user_id','left')
            ->where($order_where)
            ->group('o.order_id')
            ->select();

        foreach ($order_list as $k => $val) {
            $cb = $val['shipping_price'] + $val['tk_cost_price'] + $val['tk_cost_operating'];
            $val['lr_amount'] = bcsub($val['order_amount'],$cb,2);
            $order_list[$k] = $val;
        }

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">订单ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单优惠</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单应付金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">物流价格</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单商品成本</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单运营成本</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单利润</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单现金</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">下单日期</td>';
        $strTable .= '</tr>';
        if(is_array($order_list)){
            foreach($order_list as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_sn'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['coupon_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shipping_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tk_cost_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tk_cost_operating'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['lr_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_money'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['add_time']).'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($order_list);
        downloadExcel($strTable,'saleListRed');
        exit();
    }


}