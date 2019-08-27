<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * Author: 当燃      
 * Date: 2015-09-21
 */

namespace app\admin\controller;
use think\Db;
use think\Page;

class BackDateLimit extends Base{

    function index(){
        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $list  = M('backdatelimit')->where($map)->order("id desc")->page("$p,$size")->select();
        $count = M('backdatelimit')->where($map)->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);        
        return $this->fetch('index');
    }

    function save_data(){
        $data = I('post.date');
       
        if($data){
            $array = explode(' - ',$data);
       
            $data = ['start_date' =>strtotime($array[0]), 
                    'end_date' => strtotime($array[1]),
                    'sort'=>I('sorts/d'),
                    'proportion'=>I('percentage/d'),
                    'backmidou'=>I('backmidou/d')
                    ];
            $r = db('backdatelimit')->insert($data);
            if($r){
                $msg['status']  =   1;
                $msg['info']    =   '添加成功!';
            }else{
                $msg['status']  =   0;
                $msg['info']    =   '添加失败!';
            }
            echo json_encode($msg);
        }
        
    }
    function del(){
        $id = I('get.id/d');
        if($id){
            db('backdatelimit')->where('id',$id)->delete();
            $this->success('删除成功！');
        }
    }
    /*拷贝数据
    http://kfxt.com/index.php?m=Admin&c=back_date_limit&a=copy_data
    */
    function copy_data(){
        $where['order_status']  =   ['in','2,4'];
    //    $where['already_rebate']    =   ['exp',"< (order_amount - shipping_price)"];
        $where['is_allreturn']  =   ['eq',1];
        $order_list = db('order')->field('order_id,user_id,order_amount,shipping_price,already_rebate,add_time,confirm_time')->where($where)->select();

        foreach ($order_list as $key => $value) {
            $insert_data[]  =   [   'order_id'=>$value['order_id'],
                                    'user_id'=>$value['user_id'],
                                    'order_amount'=>$value['order_amount'],             //应付金额
                                    'shipping_price'=>$value['shipping_price'],         //运费
                                    'already_rebate'=>$value['already_rebate'],         //已反金额
                                    'add_time'=>$value['add_time'],
                                    'confirm_time'=>$value['confirm_time'],                 //收货时间
                                    'total_rebate'=>bcsub($value['order_amount'],$value['shipping_price'],9),       //应返利金额
                                ];
        }
        $result = db('order_old_rebate')->insertAll($insert_data);
        dump($result);die;
    }
    /*提现*/
    function tocash(){
        $user_id     = I('user_id/d');
        $create_time = I('create_time');
        $start_time  = I('start_time');
        $end_time    = I('end_time');
        $status      = I('status',-2);

        $create_time  = str_replace("+"," ",$create_time);
        $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);

        $this->assign('start_time',$create_time3[0]);
        $this->assign('end_time',$create_time3[1]);

        // 查询条件
        $where = array();
        if($create_time){
            $where['cash.create_time'] =  array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));            
        }
        if($start_time && $end_time){
            $where['cash.create_time'] =  array(array('gt', strtotime($start_time)), array('lt', strtotime($end_time)));
        }
        if($status > -2) {
            $where['cash.status'] = $status;
        }
        $user_id && $where['u.user_id'] = $user_id;


        $count = Db::name('newtocash')->alias('cash')->join('__USERS__ u', 'u.user_id = cash.user_id', 'left')->where($where)->count();
        $Page  = new Page($count,20);

        $list = Db::name('newtocash')
                ->alias('cash')
                ->field('cash.*,u.nickname,mobile')
                ->join('__USERS__ u', 'u.user_id = cash.user_id', 'left')
                ->where($where)->order("cash.id desc")
                ->limit($Page->firstRow.','.$Page->listRows)
                ->select();

        $backdatelimit = db('backdatelimit')->order('sort asc')->cache(true)->select();
        foreach ($list as $key => $value) {
            foreach ($backdatelimit as $k => $v) {
                if($v['start_date'] < $value['add_time'] && $v['end_date'] > $value['add_time']){
                    $list[$key]['ready']    =  $value['money'] * $v['proportion'] / 100;
                    $list[$key]['midou']    =  $value['money'] * $v['backmidou'] / 100;
                }
            }
        }
   //     dump($list);die;
        $show  = $Page->show();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    /*ajax 审核通过申请*/
    function doCash(){
        $admin_id = session('admin_id');
        $id = I('id/a');
        $status = I('status');
        $cash_list = db('newtocash')->where('id','in',$id)->select();
        $backdatelimit = db('backdatelimit')->order('sort asc')->cache(true)->select();
        if(!$backdatelimit){
            $res['status']  =   0;
            $res['info']    =   '尚未设置相关百分比，请设置相关百分比';
            $this->ajaxReturn($res);
        }
        foreach ($cash_list as $key => $value) {
            if($status == 1){
                $is_calculation =   0;
                foreach ($backdatelimit as $k => $v) {
                    if($v['start_date'] < $value['add_time'] && $v['end_date'] > $value['add_time']){
                        $ready = $value['money'] * $v['proportion'] / 100;
                        $midou = $value['money'] * $v['backmidou'] / 100;
                        if($is_calculation == 0){
                            $user_save[]    =  ['user_id'=>$value['user_id'],
                                            'midou'=>['exp',"midou + {$midou}"],
                                            'user_money'=>['exp',"user_money + {$ready}"]
                                            ];
                            $newtocashList[]    =   ['id'=>$value['id'],'admin_id'=>session('admin_id'),'status'=>1];
                            $order_data[]    =   ['order_id'=>$value['order_id'],'is_forward'=>2];
                            if($midou > 0){
                                $red_envelope_data[]  =   ['red_name'=>'返利提现','create_time'=>NOW_TIME,'source'=>'返利提现反米豆','money'=>$midou,'user_id'=>$value['user_id']];
                                $account_log[]  =   ['user_id'=>$value['user_id'],
                                                    'user_money'=>$ready,
                                                    'midou'=>$midou,
                                                    'midou_all'=>$midou,
                                                    'pay_points'=>0,
                                                    'change_time'=>NOW_TIME,
                                                    'desc'=>'福利商品提现',
                                                    'order_id'=>$value['order_id'],
                                                    'is_red'=>0,
                                                    'order_sn'=>$value['order_sn'],
                                                    ];
                                
                                $message = array(
                                    'admin_id'  => $admin_id,
                                    'message'   => "订单编号：{$value['order_sn']} 提现已经成功，赠送现金：{$ready} 元, 米豆：{$midou}",
                                    'category'  => 0,
                                    'send_time' => time(),
                                    'object'    => 'users'
                                );
                                $create_message_id = M('Message')->add($message);
                                M('user_message')->add(array('user_id' =>$value['user_id'], 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));
                            }
                        }else{
                            $res['status']  =   0;
                            $res['info']    =   '日期重叠，请检测日期重叠部分，并取消重叠部分日期，审核失败！';
                            $this->ajaxReturn($res);
                        }
                        $is_calculation =   1;
                    }
                }
                if($is_calculation == 0){
                    $res['status']  =   0;
                    $res['info']    =   '请查询相关百分比设置，其中有一部分的数据未进行百分比计算，审核失败！';
                    $this->ajaxReturn($res);
                }
            }else{
                $order_data[]    =   ['order_id'=>$value['order_id'],'is_forward'=>-1];
                $newtocashList[]    =   ['id'=>$value['id'],'status'=>-1,'remark'=>I('remark')];
                $message = array(
                    'admin_id'  => $admin_id,
                    'message'   => "订单编号：{$value['order_sn']} 提现失败，管理员：".I('remark'),
                    'category'  => 0,
                    'send_time' => time(),
                    'object'    => 'users'
                );
                $create_message_id = M('Message')->add($message);
                M('user_message')->add(array('user_id' =>$value['user_id'], 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));
            }
        }
      /*   $user_save && model('users')->saveAll($user_save);
        dump($user_save);die;*/
        $newtocashList && model('newtocash')->saveAll($newtocashList);
        
        $user_save && model('users')->saveAll($user_save);

        $order_data && model('order')->saveAll($order_data);    

        $red_envelope_data && M('red_envelope')->insertAll($red_envelope_data);
      
        $account_log && M('account_log')->insertAll($account_log);
        
        $res['status']  =   1;
        $res['info']    =   '操作成功！';
        $this->ajaxReturn($res);
    }

}