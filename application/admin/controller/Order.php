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
use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Db;
use think\Request;

class Order extends Base {
    public  $order_status;
    public  $pay_status;
    public  $shipping_status;
    public  $refuse_status;
 //   var $is_red;
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        C('TOKEN_ON',false); // 关闭表单令牌验证
        $this->order_status    = C('ORDER_STATUS');
        $this->pay_status      = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        $this->refuse_status   = C('REFUSE_STATUS');
        // 订单 支付 发货状态
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);
        $this->assign('refuse_status',$this->refuse_status);
    }

    /*
     *订单首页
     */
    public function index(){

    	$begin = date('Y-m-d',strtotime("-1 year"));//30天前
    	$end = date('Y/m/d',strtotime('+1 days')); 	
    	$this->assign('timegap',$begin.'-'.$end);

        $suppliersList = M("suppliers")->where('')->select();

        foreach ($suppliersList as $k => $val) {
            $name=getFirstCharter(mb_substr($val['suppliers_name'],0,1,'utf-8')) .' '. $val['suppliers_name'];

            $str = "";
            switch ($val['is_check']) {
                case '0':
                    $str = "(未审核)";
                    break;
                case '1':
                    $str = "(审核中)";
                    break;
                case '2':
                    $str = "(审核未通过)";
                    break;
                case '3':
                    if($val['status'] == 0)
                        $str = "(已冻结)";
                    if($val['status'] == 1)
                        $str = "(营业)";
                    else
                        $str = "(审核通过)";
                    break;
                default:
                    $str = "(未审核)";
                    break;
            }
            $nameList[] =$val['suppliers_name'] = $name.$str;
            $suppliersList[$k] = $val;
        }
            array_multisort($nameList,SORT_STRING,SORT_ASC,$suppliersList);

        // dump($suppliersList);die();
        $this->assign('suppliersList', $suppliersList);

        return $this->fetch();
    }

    /*
     *Ajax首页
     */
    public function ajaxindex(){
        $orderLogic = new OrderLogic();       
        $timegap    = I('timegap');
        if($timegap){
        	$gap   = explode('-', $timegap);
        	$begin = strtotime($gap[0]);
        	$end   = strtotime($gap[1]);
        }else{
            //@new 新后台UI参数
            $begin = strtotime(I('add_time_begin'));
            $end   = strtotime(I('add_time_end'));
        }
        
        // 搜索条件
        $condition = array();
        $keyType  = I("keytype");
        $keywords = I('keywords','','trim');
        
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        // $consignee ? $condition['consignee'] = trim($consignee) : false;
        //2018-09-26 李鑫修改查询订单模糊查询
        $consignee ? $condition['consignee'] = array('like',"%$consignee%") : false;
        //---修改结束
        if($begin && $end){
        	$condition['add_time'] = array('between',"$begin,$end");
        }

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $condition['suppliers_id'] = array('gt', 0);
        } else if($sp && $sp == 2){
            $condition['suppliers_id'] = 0;
        }

        $condition['order_prom_type'] = array('lt',5);
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        // $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        //2018-09-26 李鑫修改查询订单模糊查询
        $order_sn ? $condition['order_sn'] = array('like',"%$order_sn%") : false;
        //---修改结束
        
        I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;
        I('pay_status1')  != '' ? $condition['pay_status']   = I('pay_status1')  : false;
        I('pay_status')   != '' ? $condition['pay_status']   = I('pay_status')   : false;
        I('pay_code')     != '' ? $condition['pay_code']     = I('pay_code')     : false;
        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;
        I('user_id') ? $condition['user_id'] = trim(I('user_id')) : false;
        I('suppliers_id') ? $condition['suppliers_id']  = trim(I('suppliers_id')) : false;    // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $condition['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09
        (I('is_allreturn') !== '') ? $condition['is_allreturn'] = I('is_allreturn') : false;  // 是否全返商品
        $sort_order = I('order_by','DESC').' '.I('sort');
        //modify
        // $condition['is_red'] = ['eq',$this->is_red];
        $count = M('order')->where($condition)->count();
        // echo M('order')->where($condition)->getlastsql();
        $Page  = new AjaxPage($count,12);
        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);
        // $orderList=  Db::name('order')->alias('o')->join('order_goods og','g.order_id=og.order_id ')->field('og.*,o.*')->where($condition)->limit("$Page->firstRow,$Page->listRows")->order($sort_order)->select();
        foreach ($orderList as $k => $val) {
            $val['back_midou'] = 0;
            $orderGoods = $orderLogic->getOrderGoods($val['order_id']);

            foreach ($orderGoods as $k2 => $val2) {
                $midouInfo = returnMidou($val2['goods_id']);
                $val2['back_midou'] = $midouInfo['midou']; // 购买商品赠送米豆
                $val['back_midou'] += $val2['goods_num']*$val2['back_midou']; // 订单赠送米豆累计
            }
            $res = Db::name('order_goods')->where('order_id='.$val['order_id'])->field('goods_num,goods_price,member_goods_price,rec_id,goods_name')->select();
            foreach ($res as $kc=>$row){
                $res[$kc] = $row['goods_price']*$row['goods_num'];
                $resname[$kc] = $row['goods_name'];
            }

            $val['price'] = array_sum($res);
            $val['goodsname'] = $resname[0]; 
            $val['goodsnames'] = json_encode($resname);     
            $orderList[$k] = $val;
        }

        // dump($orderList);die();
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    
    //虚拟订单
    public function virtual_list(){
        header("Content-type: text/html; charset=utf-8");
        exit("正在开发");
    }
    // 虚拟订单
    public function virtual_info(){
        header("Content-type: text/html; charset=utf-8");
        exit("正在开发");
    }

    public function virtual_cancel(){
        $order_id = I('order_id/d');
        if(IS_POST){
            $admin_note = I('admin_note');
            $order = M('order')->where(array('order_id'=>$order_id))->find();
            if($order){
                $r = M('order')->where(array('order_id'=>$order_id))->save(array('order_status'=>3,'admin_note'=>$admin_note));
                if($r){
                    $orderLogic = new OrderLogic();
                    $orderLogic->orderActionLog($order_id,$admin_note, '取消订单');
                    $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
                }else{
                    $this->ajaxReturn(array('status'=>-1,'msg'=>'操作失败'));
                }
            }else{
                $this->ajaxReturn(array('status'=>-1,'msg'=>'订单不存在'));
            }
        }
        $order = M('order')->where(array('order_id'=>$order_id))->find();
        $this->assign('order',$order);
        return $this->fetch();
    }

    public function verify_code(){
        if(IS_POST){
            $vr_code = trim(I('vr_code'));
            if (!preg_match('/^[a-zA-Z0-9]{15,18}$/',$vr_code)) {
                $this->ajaxReturn(['status'=>0,'msg' => '兑换码格式错误，请重新输入']);
            }
            $vr_code_info = M('vr_order_code')->where(array('vr_code'=>$vr_code))->find();
            $order = M('order')->where(['order_id'=>$vr_code_info['order_id']])->field('order_status,order_sn,user_note')->find();
            if($order['order_status'] > 1 ){
                $this->ajaxReturn(['status'=>0,'msg' => '兑换码对应订单状态不符合要求']);
            }
            if(empty($vr_code_info)){
                $this->ajaxReturn(['status'=>0,'msg' => '该兑换码不存在']);
            }
            if ($vr_code_info['vr_state'] == '1') {
                $this->ajaxReturn(['status'=>0,'msg' => '该兑换码已被使用']);
            }
            if ($vr_code_info['vr_indate'] < time()) {
                $this->ajaxReturn(['status'=>0,'msg'=>'该兑换码已过期，使用截止日期为： '.date('Y-m-d H:i:s',$vr_code_info['vr_indate'])]);
            }
            if ($vr_code_info['refund_lock'] > 0) {//退款锁定状态:0为正常,1为锁定(待审核),2为同意
                $this->ajaxReturn(['status'=>0,'msg'=> '该兑换码已申请退款，不能使用']);
            }
            $update['vr_state'] = 1;
            $update['vr_usetime'] = time();
            M('vr_order_code')->where(array('vr_code'=>$vr_code))->save($update);
            //检查订单是否完成
            $condition = array();
            $condition['vr_state'] = 0;
            $condition['refund_lock'] = array('in',array(0,1));
            $condition['order_id'] = $vr_code_info['order_id'];
            $condition['vr_indate'] = array('gt',time());
            $vr_order = M('vr_order_code')->where($condition)->select();
            if(empty($vr_order)){
                $data['order_status'] = 2;  //此处不能直接为4，不然前台不能评论
                $data['confirm_time'] = time();
                M('order')->where(['order_id'=>$vr_code_info['order_id']])->save($data);
                M('order_goods')->where(['order_id'=>$vr_code_info['order_id']])->save(['is_send'=>1]);  //把订单状态改为已收货
            }
            $order_goods = M('order_goods')->where(['order_id'=>$vr_code_info['order_id']])->find();
            if($order_goods){
                $result = [
                    'vr_code'=>$vr_code,
                    'order_goods'=>$order_goods,
                    'order'=>$order,
                    'goods_image'=>goods_thum_images($order_goods['goods_id'],240,240),
                ];
                $this->ajaxReturn(['status'=>1,'msg'=>'兑换成功','result'=>$result]);
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>'虚拟订单商品不存在']);
            }
        }
        return $this->fetch();
    }

    //虚拟订单临时支付方法，以后要删除
    public function generateVirtualCode(){
        $order_id = I('order_id/d');
        // 获取操作表
        $order = M('order')->where(array('order_id'=>$order_id))->find();
        update_pay_status($order['order_sn'], ['admin_id'=>session('admin_id'),'note'=>'订单付款成功']);
        $vr_order_code = Db::name('vr_order_code')->where('order_id',$order_id)->find();
        if(!empty($vr_order_code)){
            $this->success('成功生成兑换码', U('Order/virtual_info',['order_id'=>$order_id]), 1);
        }else{
            $this->error('生成兑换码失败', U('Order/virtual_info',['order_id'=>$order_id]), 1);
        }
    }
    


    /**
     * 订单详情
     * @param int $id 订单id
     */
    public function detail($order_id){
        $orderLogic = new OrderLogic();
        $order      = $orderLogic->getOrderInfo($order_id);
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $button     = $orderLogic->getOrderButton($order);
        // 获取操作记录
        $action_log = M('order_action')->where(array('order_id'=>$order_id))->order('log_time desc')->select();
        $has_user   = false;
        $adminIds   = [];

        //拒绝发货记录
        $refuse_info = M('order_refuse')->where(array('order_id'=>$order_id,'refuse_status'=>1))->find();
        $this->assign('refuse',$refuse_info);

        $refuse_no_info = M('order_refuse')->where(array('order_id'=>$order_id,'refuse_status'=>3))->find();
        $this->assign('refuse_no',$refuse_no_info);

        //查找用户昵称
        foreach ($action_log as $k => $v){
            if ($v['action_user']) {
                $adminIds[$k] = $v['action_user'];
            } else {
                $has_user = true;
            }
        }

        if($order['user_id']) $has_user = true;
        if($adminIds && count($adminIds) > 0){
            $admins = M("admin")->where("admin_id in (".implode(",",$adminIds).")")->getField("admin_id , user_name", true);
        }
        if($has_user){
            $user = M("users")->field('user_id,nickname')->where('user_id',$order['user_id'])->find();
        }

        foreach ($orderGoods as $k => $val) {
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $orderGoods[$k]    = $val;
        }

    	$this->assign('admins',$admins);  
        $this->assign('user', $user);
        $this->assign('order',$order);
        $this->assign('action_log',$action_log);
        $this->assign('orderGoods',$orderGoods);

        $split = count($orderGoods) >1 ? 1 : 0;
        foreach ($orderGoods as $val){
            $rec_ids[]  =   $val['rec_id'];
        	if($val['goods_num']>1){
        		$split = 1;
        	}
        }
        $return_where['order_id'] =   ['eq',$order_id];
        $return_where['rec_id'] = ['in',$rec_ids];
        $return_list = db('return_goods')->where($return_where)->select_key('rec_id');
        // dump($return_list);
        $this->assign('return_list',$return_list);
        $this->assign('split',$split);
        $this->assign('button',$button);
        return $this->fetch();
    }

    

    /**
     * 订单编辑
     * @param int $id 订单id
     */
    public function edit_order(){
    	$order_id = I('order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        if($order['shipping_status'] != 0){
            $this->error('已发货订单不允许编辑');
            exit;
        }

        $orderGoods = $orderLogic->getOrderGoods($order_id);

       	if(IS_POST)
        {

            $order['suppliers_id'] = I('suppliers_id');
            $order['consignee']     = I('consignee');     // 收货人
            $order['province']      = I('province');      // 省份
            $order['city']          = I('city');          // 城市
            $order['district']      = I('district');      // 县
            $order['address']       = I('address');       // 收货地址
            $order['mobile']        = I('mobile');        // 手机
            $order['invoice_title'] = I('invoice_title'); // 发票
            $order['admin_note']    = I('admin_note');    // 管理员备注
            $order['supplier_note'] = I('supplier_note'); //
            $order['shipping_code'] = I('shipping');      // 物流方式
            $order['shipping_name'] = M('plugin')->where(array('status'=>1,'type'=>'shipping','code'=>I('shipping')))->getField('name');
            $order['pay_code']      = I('payment');       // 支付方式
            $order['pay_name']      = M('plugin')->where(array('status'=>1,'type'=>'payment','code'=>I('payment')))->getField('name');
            $goods_id_arr = I("goods_id/a");


            $new_goods = $old_goods_arr = array();
            //################################订单添加商品
            if($goods_id_arr){
            	$new_goods = $orderLogic->get_spec_goods($goods_id_arr);
            	foreach($new_goods as $key => $val)
            	{
            		$val['order_id'] = $order_id;
            		$rec_id = M('order_goods')->add($val);//订单添加商品
            		if(!$rec_id)
            			$this->error('添加失败');
            	}
            }

            //################################订单修改删除商品
            $old_goods = I('old_goods/a');
            foreach ($orderGoods as $val){
            	if(empty($old_goods[$val['rec_id']])){
            		M('order_goods')->where("rec_id=".$val['rec_id'])->delete();//删除商品
            	}else{
            		//修改商品数量
            		if($old_goods[$val['rec_id']] != $val['goods_num']){
            			$val['goods_num'] = $old_goods[$val['rec_id']];
            			M('order_goods')->where("rec_id=".$val['rec_id'])->save(array('goods_num'=>$val['goods_num']));
            		}
            		$old_goods_arr[] = $val;
            	}
            }

            $goodsArr = array_merge($old_goods_arr,$new_goods);
            $result = calculate_price($order['user_id'],$goodsArr,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0);
            if($result['status'] < 0)
            {
            	$this->error($result['msg']);
            }

            //################################修改订单费用
            $order['goods_price']    = $result['result']['goods_price']; // 商品总价
            $order['shipping_price'] = $result['result']['shipping_price'];//物流费
            $order['order_amount']   = $result['result']['order_amount']; // 应付金额
            $order['total_amount']   = $result['result']['total_amount']; // 订单总价
            $o = M('order')->where('order_id='.$order_id)->save($order);

            $l = $orderLogic->orderActionLog($order_id,'修改订单','修改订单');//操作日志
            if($o && $l){
                adminLog('修改订单(order_id:'.$order_id.')');
            	$this->success('修改成功',U('Admin/Order/editprice',array('order_id'=>$order_id)));
            }else{
            	$this->success('修改失败',U('Admin/Order/detail',array('order_id'=>$order_id)));
            }
            exit;
        }
        // 获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //获取订单城市
        $city =  M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();
        //获取订单地区
        $area =  M('region')->where(array('parent_id'=>$order['city'],'level'=>3))->select();
        //获取支付方式
        $payment_list = M('plugin')->where(array('status'=>1,'type'=>'payment'))->select();
        //获取配送方式
        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping','suppliers_id'=>$order['suppliers_id']))->select();
        //  供货商
        $suppliersList = M("suppliers")->where('is_check = 3 AND status = 0')->select();

        $this->assign('suppliersList', $suppliersList);
        $this->assign('order',$order);
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);
        $this->assign('orderGoods',$orderGoods);
        $this->assign('shipping_list',$shipping_list);
        $this->assign('payment_list',$payment_list);
        return $this->fetch();
    }



    /**
     * 添加一笔订单
     */
    public function add_order()
    {
        $order = array();
        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取订单城市
        $city     = M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();
        //  获取订单地区
        $area     = M('region')->where(array('parent_id'=>$order['city'],'level'=>3))->select();
        //  获取配送方式
        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping','suppliers_id'=>0))->select();
        //  获取支付方式
        $payment_list  = M('plugin')->where(array('status'=>1,'type'=>'payment'))->select();
        //  供货商
        $suppliersList = M("suppliers")->where('is_check = 3 AND status = 0')->select();
        if(IS_POST)
        {
            $order['user_id']       = I('user_id');       // 用户id 可以为空
            $order['consignee']     = I('consignee');     // 收货人
            $order['province']      = I('province');      // 省份
            $order['city']          = I('city');          // 城市
            $order['district']      = I('district');      // 县
            $order['address']       = I('address');       // 收货地址
            $order['mobile']        = I('mobile');        // 手机           
            $order['invoice_title'] = I('invoice_title'); // 发票
            $order['admin_note']    = I('admin_note');    // 管理员备注            
            $order['add_time']      = time();             //                    
            $order['shipping_code'] = I('shipping');      // 物流方式
            $order['shipping_name'] = M('plugin')->where(array('status'=>1,'type'=>'shipping','code'=>I('shipping')))->getField('name');            
            $order['pay_code']      = I('payment');       // 支付方式            
            $order['pay_name']      = M('plugin')->where(array('status'=>1,'type'=>'payment','code'=>I('payment')))->getField('name');            
                            
            $goods_id_arr = I("goods_id/a");
            $orderLogic   = new OrderLogic();
            $order_goods  = $orderLogic->get_spec_goods($goods_id_arr); // 获取订单商品信息         
            
            $order_goods_arr = array_group_by($order_goods, 'suppliers_id'); // 分组后的 订单商品 数据

            $px = 1;
            $parent_sn = $order['order_sn'] = date('YmdHis').mt_rand(1000,9999); // 订单编号;
            foreach ($order_goods_arr as $key => $value) {
                $result = calculate_price($order['user_id'],$value,$order['shipping_code'],0,$order[province],$order[city],$order[district],0,0,0);  //获取订单总额
                if($result['status'] < 0)   
                {
                     $this->error($result['msg']);      
                }
                $order['goods_price']    = $result['result']['goods_price'];    // 商品总价
                $order['shipping_price'] = $result['result']['shipping_price']; // 物流费
                $order['order_amount']   = $result['result']['order_amount'];   // 应付金额
                $order['total_amount']   = $result['result']['total_amount'];   // 订单总价
                $order['tk_cost_price']  = $result['result']['tk_cost_price'];  // 订单总成本
                $order['suppliers_id']   = $value[0]['suppliers_id'];           // 供货商ID
                if($px > 1){
                    $order['order_sn']   = date('YmdHis').mt_rand(1000,9999); // 订单编号;
                    $order['parent_sn']  = $parent_sn;                    // 父单单号 
                }    
               
                // 添加订单
                $order_id = M('order')->add($order);
                $order_insert_id = DB::getLastInsID();
                if($order_id)
                {
                    foreach($value as $key => $val)
                    {
                        $val['order_id'] = $order_id;
                        $rec_id = M('order_goods')->add($val);
                        if(!$rec_id)
                            $this->error('添加失败');                                  
                    }
      
                    M('order_action')->add([
                        'order_id'        => $order_id,
                        'action_user'     => session('admin_id'),
                        'order_status'    => 0,  //待支付
                        'shipping_status' => 0,  //待确认
                        'action_note'     => $order['admin_note'],
                        'status_desc'     => '提交订单',
                        'log_time'        => time()
                    ]);
                    adminLog('添加订单(order_id:'.$order_id.')');
                }
                else{
                    $this->error('添加失败');
                } 
                $px++;
            } 
            
            $this->success('添加商品成功',U("Admin/Order/detail",array('order_id'=>$order_insert_id)));
            exit(); 
        }  

        $this->assign('suppliersList', $suppliersList);
        $this->assign('shipping_list',$shipping_list);
        $this->assign('payment_list',$payment_list);
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);        
        return $this->fetch();
    }

    public function get_plugin_shipping(){
        $suppliers_id = I('suppliers_id') ? I('suppliers_id') : 0;
        $shipping_where['status'] = 1;
        $shipping_where['type'] = array('eq', 'shipping');
        $shipping_where['suppliers_id'] = array('eq', $suppliers_id);
        $plugin_shipping = M('plugin')->where($shipping_where)->select();//插件物流
        $this->assign('plugin_shipping', $plugin_shipping);
        return $this->fetch();
    }
    
    /**
     * 选择搜索商品
     */
    public function search_goods()
    {
        $suppliers_id = I('suppliers_id/d');

        $brandList =  M("brand")->select();
        $categoryList =  M("goods_category")->select();
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        $where = ' is_on_sale = 1 AND is_virtual =' . I('is_virtual/d',0); //搜索条件
        if($suppliers_id) $where .= ' AND suppliers_id ='.$suppliers_id;
        I('intro')  && $where = "$where and ".I('intro')." = 1";
        if(I('cat_id')){
            $this->assign('cat_id',I('cat_id'));            
            $grandson_ids = getCatGrandson(I('cat_id')); 
            $where = " $where  and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
                
        }
        if(I('brand_id')){
            $this->assign('brand_id',I('brand_id'));
            $where = "$where and brand_id = ".I('brand_id');
        }
        if(!empty($_REQUEST['keywords']))
        {
            $this->assign('keywords',I('keywords'));
            $where = "$where and (goods_name like '%".I('keywords')."%' or keywords like '%".I('keywords')."%')" ;
        }
        $goods_count =M('goods')->where($where)->count();
        $Page = new Page($goods_count,C('PAGESIZE'));
        $goodsList = M('goods')->where($where)->order('goods_id DESC')->limit($Page->firstRow,$Page->listRows)->select();
                
        foreach($goodsList as $key => $val)
        {
            $spec_goods = M('spec_goods_price')->where("goods_id = {$val['goods_id']}")->select();
            $goodsList[$key]['spec_goods'] = $spec_goods;            
        }
        if($goodsList){
            //计算商品数量
            foreach ($goodsList as $value){
                if($value['spec_goods']){
                    $count += count($value['spec_goods']);
                }else{
                    $count++;
                }
            }
            $this->assign('totalSize',$count);
        }

        $this->assign('page',$Page->show());
        $this->assign('goodsList',$goodsList);
        return $this->fetch();
    }


    /*
     * 拆分订单
     */
    public function split_order(){
    	$order_id = I('order_id');  //订单ID
    	$orderLogic = new OrderLogic();
    	$order = $orderLogic->getOrderInfo($order_id);  // 获取订单信息
    	if($order['shipping_status'] != 0){             // 判断发货状态
    		$this->error('已发货订单不允许编辑');
    		exit;
    	}
    	$orderGoods = $orderLogic->getOrderGoods($order_id);  // 获取订单商品
    	if(IS_POST){
    		$data = I('post.');
    		//################################先处理原单剩余商品和原订单信息
    		$old_goods = I('old_goods/a');  // 原订单商品信息 

    		foreach ($orderGoods as $val){                                        // 循环数据库 订单商品
    			if(empty($old_goods[$val['rec_id']])){                            // 如果提交的原订单商品不存在
    				M('order_goods')->where("rec_id=".$val['rec_id'])->delete();  //删除商品
    			}else{
    				//修改商品数量
    				if($old_goods[$val['rec_id']] != $val['goods_num']){ 
    					$val['goods_num'] = $old_goods[$val['rec_id']];
    					M('order_goods')->where("rec_id=".$val['rec_id'])->save(array('goods_num'=>$val['goods_num']));
    				}
    				$oldArr[] = $val;//剩余商品
    			}
    			$all_goods[$val['rec_id']] = $val;  //存下 所有商品信息
    		}

            // 获取订单金额
    		$result = calculate_price($order['user_id'],$oldArr,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0);
    		if($result['status'] < 0)
    		{
    			$this->error($result['msg']);
    		}

    		//修改订单费用
    		$res['goods_price']    = $result['result']['goods_price'];  // 商品总价
    		$res['order_amount']   = $result['result']['order_amount']; // 应付金额
    		$res['total_amount']   = $result['result']['total_amount']; // 订单总价
            $order['tk_cost_price']  = $result['result']['tk_cost_price'];  // 订单总成本
    		M('order')->where("order_id=".$order_id)->save($res); 
			//################################原单处理结束

    		//################################新单处理
    		for($i=1;$i<20;$i++){
                $temp = $this->request->param($i.'_old_goods/a');  // 获取新订单
    			if(!empty($temp)){
    				$split_goods[] = $temp;
    			}
    		}

    		foreach ($split_goods as $key=>$vrr){       // 循环 新订单列表
    			foreach ($vrr as $k=>$v){               // 循环 新订单商品
    				$all_goods[$k]['goods_num'] = $v;
    				$brr[$key][] = $all_goods[$k];
    			}
    		}

    		foreach($brr as $goods){
    			$result = calculate_price($order['user_id'],$goods,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0);
    			if($result['status'] < 0)
    			{
    				$this->error($result['msg']);
    			}
    			$new_order = $order;
    			$new_order['order_sn']  = date('YmdHis').mt_rand(1000,9999);
    			$new_order['parent_sn'] = $order['order_sn'];
    			//修改订单费用
    			$new_order['goods_price']    = $result['result']['goods_price'];  // 商品总价
    			$new_order['order_amount']   = $result['result']['order_amount']; // 应付金额
    			$new_order['total_amount']   = $result['result']['total_amount']; // 订单总价
    			$new_order['add_time'] = time();
    			unset($new_order['order_id']);
    			$new_order_id = DB::name('order')->insertGetId($new_order);       //插入订单表
    			foreach ($goods as $vv){
    				$vv['order_id'] = $new_order_id;
    				unset($vv['rec_id']);
    				$nid = M('order_goods')->add($vv);//插入订单商品表
    			}
    		}
    		//################################新单处理结束
    		$this->success('操作成功',U('Admin/Order/detail',array('order_id'=>$order_id)));
            exit;
    	}

    	foreach ($orderGoods as $val){
    		$brr[$val['rec_id']] = array('goods_num'=>$val['goods_num'],'goods_name'=>getSubstr($val['goods_name'], 0, 35).$val['spec_key_name']);
    	}
    	$this->assign('order',$order);
    	$this->assign('goods_num_arr',json_encode($brr));
    	$this->assign('orderGoods',$orderGoods);
    	return $this->fetch();
    }

    /*
     * 价钱修改
     */
    public function editprice($order_id){
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $this->editable($order);
        if(IS_POST){
        	$admin_id = session('admin_id');
            if(empty($admin_id)){
                $this->error('非法操作');
                exit;
            }
            $update['discount'] = I('post.discount');
            $update['shipping_price'] = I('post.shipping_price');
			$update['order_amount'] = $order['goods_price'] + $update['shipping_price'] - $update['discount'] - $order['user_money'] - $order['integral_money'] - $order['coupon_price'];
            $tk_cost_price   =   I('post.tk_cost_price',0) * -1;
            $update['tk_cost_price'] = ['exp',"tk_cost_price + {$tk_cost_price}"];
            $row = M('order')->where(array('order_id'=>$order_id))->save($update);
            if(!$row){
                $this->success('没有更新数据',U('Admin/Order/editprice',array('order_id'=>$order_id)));
            }else{
                $this->success('操作成功',U('Admin/Order/detail',array('order_id'=>$order_id)));
            }
            exit;
        }
        $this->assign('order',$order);
        return $this->fetch();
    }

    /**
     * 订单删除
     * @param int $id 订单id
     */
    public function delete_order(){
        $order_id = I('post.order_id/d',0);
    	$orderLogic = new OrderLogic();
        $order_sn= M('order')->where('order_id',$order_id)->getField('order_sn');
        adminLog('米豆区订单删除(order_id:'.$order_id.'订单号:'.$order_sn.')');
    	$del = $orderLogic->delOrder($order_id,$this->suppliers_id);
        $this->ajaxReturn($del);
    }

    /**
     * 订单取消付款
     * @param $order_id
     * @return mixed
     */
    public function pay_cancel($order_id){
    	if(I('remark')){
    		$data = I('post.');
    		$note = array('退款到用户余额','已通过其他方式退款','不处理，误操作项');
    		if($data['refundType'] == 0 && $data['amount']>0){
    			accountLog($data['user_id'], $data['amount'], 0,  '退款到用户余额');
    		}
    		$orderLogic = new OrderLogic();
            $orderLogic->orderProcessHandle($data['order_id'],'pay_cancel');
    		$d = $orderLogic->orderActionLog($data['order_id'],'支付取消',$data['remark'].':'.$note[$data['refundType']]);
    		if($d){
    			exit("<script>window.parent.pay_callback(1);</script>");
    		}else{
    			exit("<script>window.parent.pay_callback(0);</script>");
    		}
    	}else{
    		$order = M('order')->where("order_id=$order_id")->find();
    		$this->assign('order',$order);
    		return $this->fetch();
    	}
    }

    /**
     * 订单打印
     * @param int $id 订单id
     */
    public function order_print(){
    	$order_id = I('order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        $order['full_address'] = $order['province'].' '.$order['city'].' '.$order['district'].' '. $order['address'];
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $shop = tpCache('shop_info');
        $this->assign('order',$order);
        $this->assign('shop',$shop);
        $this->assign('orderGoods',$orderGoods);
        $template = I('template','print');
        return $this->fetch($template);
    }

    /**
     * 快递单打印
     */
    public function shipping_print(){
        $order_id = I('get.order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        //查询是否存在订单及物流
        $shipping = M('plugin')->where(array('code'=>$order['shipping_code'],'type'=>'shipping'))->find();
        if(!$shipping){
        	$this->error('物流插件不存在');
        }
		if(empty($shipping['config_value'])){
			$this->error('请设置'.$shipping['name'].'打印模板');
		}
        $shop = tpCache('shop_info');//获取网站信息
        $shop['province'] = empty($shop['province']) ? '' : getRegionName($shop['province']);
        $shop['city'] = empty($shop['city']) ? '' : getRegionName($shop['city']);
        $shop['district'] = empty($shop['district']) ? '' : getRegionName($shop['district']);

        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        if(empty($shipping['config'])){
        	$config = array('width'=>840,'height'=>480,'offset_x'=>0,'offset_y'=>0);
        	$this->assign('config',$config);
        }else{
        	$this->assign('config',unserialize($shipping['config']));
        }
        $template_var = array("发货点-名称", "发货点-联系人", "发货点-电话", "发货点-省份", "发货点-城市",
        		 "发货点-区县", "发货点-手机", "发货点-详细地址", "收件人-姓名", "收件人-手机", "收件人-电话",
        		"收件人-省份", "收件人-城市", "收件人-区县", "收件人-邮编", "收件人-详细地址", "时间-年", "时间-月",
        		"时间-日","时间-当前日期","订单-订单号", "订单-备注","订单-配送费用");
        $content_var = array($shop['store_name'],$shop['contact'],$shop['phone'],$shop['province'],$shop['city'],
        	$shop['district'],$shop['phone'],$shop['address'],$order['consignee'],$order['mobile'],$order['phone'],
        	$order['province'],$order['city'],$order['district'],$order['zipcode'],$order['address'],date('Y'),date('M'),
        	date('d'),date('Y-m-d'),$order['order_sn'],$order['admin_note'],$order['shipping_price'],
        );
        $shipping['config_value'] = str_replace($template_var,$content_var, $shipping['config_value']);
        $this->assign('shipping',$shipping);
        return $this->fetch("Plugin/print_express");
    }


    /**
     * 发货单列表
     */
    public function delivery_list(){

        $suppliersList = M("suppliers")->where('')->select();
        foreach ($suppliersList as $k => $val) {
            $str = "";
            switch ($val['is_check']) {
                case '0':
                    $str = "(未审核)";
                    break;
                case '1':
                    $str = "(审核中)";
                    break;
                case '2':
                    $str = "(审核未通过)";
                    break;
                case '3':
                    if($val['status'] == 0)
                        $str = "(已冻结)";
                    if($val['status'] == 1)
                        $str = "(营业)";
                    else
                        $str = "(审核通过)";
                    break;
                default:
                    $str = "(未审核)";
                    break;
            }
            $val['suppliers_name'] = $val['suppliers_name'].$str;
            $suppliersList[$k] = $val;
        }

        $this->assign('suppliersList', $suppliersList);

        return $this->fetch();
    }


    /*
     * ajax 发货订单列表
    */
    public function ajaxdelivery(){
        $condition = array();
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $condition['suppliers_id'] = array('gt', 0);
        } else if($sp && $sp == 2){
            $condition['suppliers_id'] = 0;
        }
        I('suppliers_id2') ? $condition['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09
        // I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        // I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
        //2018-09-26 李鑫修改发货订单模糊查询
        $consignee=I('consignee');
        $order_sn=I('order_sn');
        $consignee ? $condition['consignee'] = array('like',"%$consignee%") : false;
        $order_sn != '' ? $condition['order_sn'] = array('like',"%$order_sn%") : false;
        //修改结束
        $shipping_status = I('shipping_status');
        $condition['shipping_status'] = empty($shipping_status) ? array('neq',1) : $shipping_status;
        $condition['order_status']    = array('in','1,2,4');
        $condition['pay_status']    =   ['eq',1];
        $count = M('order')->where($condition)->count();
        $Page  = new AjaxPage($count,10);
        //搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            if(!is_array($val)){
                $Page->parameter[$key]   =   urlencode($val);
            }
        }
        // dump($condition);die();
        $show = $Page->show();
        $orderList = M('order')->where($condition)->limit($Page->firstRow.','.$Page->listRows)->order('add_time DESC')->select();
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }


    public function export_delivery(){
        $condition = array();
        
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $condition['suppliers_id'] = array('gt', 0);
        } else if($sp && $sp == 2){
            $condition['suppliers_id'] = 0;
        }
        I('suppliers_id2') ? $condition['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09
        I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
        $shipping_status = I('shipping_status');
        $condition['shipping_status'] = empty($shipping_status) ? array('neq',1) : $shipping_status;
        $condition['order_status']    = array('in','1,2,4');

        $order_ids = I('order_ids');
        if($order_ids){
            $condition['order_id'] = array('in', $order_ids);
        }

        $orderList = M('order')->where($condition)->order('add_time DESC')->select();
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">下单时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">联系电话</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所选物流</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">物流费用</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单总价</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            foreach($orderList as $k=>$val){
                if($val['pay_time'] > 0) $val['pay_time'] = date('Y-m-d H:i:s',$val['pay_time']); else $val['pay_time'] = '货到付款';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['add_time']).' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['consignee'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shipping_name'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shipping_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_time'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'delivery');
        exit();
    }


    /**
     * 生成发货单
     */
    public function deliveryHandle(){
        $orderLogic = new OrderLogic();
		$data = I('post.');

		$res = $orderLogic->deliveryHandle($data);
		if($res){
            adminLog('生成发货单(order_id:'.$data['order_id'].')');
			$this->success('操作成功',U('/Admin/Order/delivery_info',array('order_id'=>$data['order_id'])));
		}else{
			$this->success('操作失败',U('/Admin/Order/delivery_info',array('order_id'=>$data['order_id'])));
		}
    }


    public function delivery_info(){
    	$order_id = I('order_id');
    	$orderLogic = new OrderLogic();
    	$order = $orderLogic->getOrderInfo($order_id);
    	$orderGoods = $orderLogic->getOrderGoods($order_id,2);

        $return_where['order_id'] =   ['eq',$order_id];
        foreach ($orderGoods as $val){
            $rec_ids[]  =   $val['rec_id'];
        }
        $return_where['rec_id'] = ['in',$rec_ids];
        $return_list = db('return_goods')->where($return_where)->select_key('rec_id');
        $this->assign('return_list',$return_list);

        if(!$orderGoods)$this->error('此订单商品已完成退货或换货');//已经完成售后的不能再发货
		$delivery_record = M('delivery_doc')->alias('d')->where('d.order_id='.$order_id)->select();
		if($delivery_record){
			$order['invoice_no'] = $delivery_record[count($delivery_record)-1]['invoice_no'];
		}
		$this->assign('order',$order);
		$this->assign('orderGoods',$orderGoods);
		$this->assign('delivery_record',$delivery_record);//发货记录
		//$shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping','suppliers_id'=>$order['suppliers_id']))->select();
        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping','suppliers_id'=>0))->select();
		$this->assign('shipping_list',$shipping_list);
    	return $this->fetch();
    }

    // 退款单
    public function refund_order_list(){

        $suppliersList = M("suppliers")->where('')->select();
        foreach ($suppliersList as $k => $val) {
            $str = "";
            switch ($val['is_check']) {
                case '0':
                    $str = "(未审核)";
                    break;
                case '1':
                    $str = "(审核中)";
                    break;
                case '2':
                    $str = "(审核未通过)";
                    break;
                case '3':
                    if($val['status'] == 0)
                        $str = "(已冻结)";
                    if($val['status'] == 1)
                        $str = "(营业)";
                    else
                        $str = "(审核通过)";
                    break;
                default:
                    $str = "(未审核)";
                    break;
            }
            $val['suppliers_name'] = $val['suppliers_name'].$str;
            $suppliersList[$k] = $val;
        }
        $this->assign('suppliersList', $suppliersList);

        $orderLogic = new OrderLogic();
        $condition = array();

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $condition['suppliers_id'] = array('gt', 0);
        } else if($sp && $sp == 2){
            $condition['suppliers_id'] = 0;
        }
        I('suppliers_id2') ? $condition['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09
        // I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        // I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
        // I('mobile')   ? $condition['mobile'] = trim(I('mobile')) : false;
        //2018-09-26 李鑫修改退款单模糊查询
        $consignee=I('consignee');
        $order_sn=I('order_sn');
        $mobile=I('mobile');
        I('consignee') ? $condition['consignee'] = array('like',"%$consignee%") : false;
        I('order_sn') != '' ? $condition['order_sn'] =array('like',"%$order_sn%") : false;
        I('mobile')   ? $condition['mobile'] = array('like',"%$mobile%") : false;
        //修改结束
        $condition['shipping_status'] = 0;
        $condition['order_status'] = 3;
        $condition['pay_status'] = array('gt',0);

        $count = M('order')->where($condition)->count();
        $Page  = new Page($count,10);
        //搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            if(!is_array($val)){
                $Page->parameter[$key] = urlencode($val);
            }
        }
        $show = $Page->show();
        $orderList = M('order')->where($condition)->limit($Page->firstRow.','.$Page->listRows)->order('add_time DESC')->select();
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    public function export_refund_order_list(){
        $orderLogic = new OrderLogic();
        $condition = array();

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $condition['suppliers_id'] = array('gt', 0);
        } else if($sp && $sp == 2){
            $condition['suppliers_id'] = 0;
        }
        I('suppliers_id2') ? $condition['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09
        I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
        I('mobile')   ? $condition['mobile'] = trim(I('mobile')) : false;
        $condition['shipping_status'] = 0;
        $condition['order_status'] = 3;
        $condition['pay_status'] = array('gt',0);

        $order_ids = I('order_ids');
        if($order_ids){
            $condition['order_id'] = array('in', $order_ids);
        }

        $orderList = M('order')->where($condition)->order('add_time DESC')->select();
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">下单时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">联系电话</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所选物流</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">物流费用</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">处理状态</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            foreach($orderList as $k=>$val){
                if($val['pay_time'] > 0) $val['pay_time'] = date('Y-m-d H:i:s',$val['pay_time']); else $val['pay_time'] = '货到付款';
                if($val['pay_status'] == 1) $val['pay_status'] = '待处理'; else if($val['pay_status'] == 3) $val['pay_status'] = '已退款'; else $val['pay_status'] = '已拒绝';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['add_time']).' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['consignee'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shipping_name'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shipping_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_time'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_status'].'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'refund_order_list');
        exit();
    }
    
    public function refund_order_info($order_id){
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $this->assign('order',$order);
        $this->assign('orderGoods',$orderGoods);
        return $this->fetch();
    }

    //取消订单退款原路退回
    public function refund_order(){
        $data = I('post.');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($data['order_id']);

        $total_fee = M('order')->where('transaction_id',$order['transaction_id'])->sum('order_amount');
        //M('order')->where('order_id ='.$data['order_id'])->save($data);

        if(!$order){
            $this->error('订单不存在或参数错误');
        }
        if($data['pay_status'] == 3){
            if($data['refund_type'] == 1){
                //取消订单退款退余额
                if(updateRefundOrder($order,1)){
                    M('order')->where(array('order_id'=>$order['order_id']))->save($data);
                    adminLog('退款到账户余额(order_id:'.$order['order_id'].')');
                    $this->success('成功退款到账户余额');
                }else{
                    $this->error('退款失败');
                }
            }
            if($data['refund_type']== 0){
                //取消订单支付原路退回
                if($order['pay_code'] == 'weixin' || $order['pay_code'] == 'weixinH5' || $order['pay_code'] == 'alipay' || $order['pay_code'] == 'alipayMobile'){

                    if($order['pay_code'] == 'weixin' || $order['pay_code'] == 'weixinH5'){
                        accountLog($order['user_id'], $order['user_money'], 0, $order['integral'], $desc = '退款', $midou_all = 0, $distribut_money = 0, $order['order_id'], $order['order_sn']);
                        include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
                        $payment_obj =  new \weixin();
                        $data = array('transaction_id'=>$order['transaction_id'],'total_fee'=>$total_fee,'refund_fee'=>$order['order_amount']);
                        $result = $payment_obj->payment_refund($data);
                        if($result['return_code'] == 'SUCCESS'  && $result['result_code'] == 'SUCCESS'){
                            $data['pay_status']   = 3;
                            $data['order_status'] = 3;
                            $data['admin_note']   = I('admin_note/s');
                            M('order')->where(array('order_id'=>$order['order_id']))->save($data);//更改订单状态
                            adminLog('退款到微信(order_id:'.$order['order_id'].')');
                            $this->success('退款成功',U('/admin/Order/refund_order_info/',['order_id'=>$order['order_id']]));
                        }else{
                            $this->error($result['err_code_des']);
                        }
                    }else{
                        accountLog($order['user_id'], $order['user_money'], 0, $order['integral'], $desc = '退款', $midou_all = 0, $distribut_money = 0, $order['order_id'], $order['order_sn']);

                        include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
                        $payment_obj = new \alipay();
                        $detail_data = $order['transaction_id'].'^'.$order['order_amount'].'^'.'用户申请订单退款';
                        $data = array('transaction_id'=>$order['transaction_id'],'refund_money'=>$order['order_amount'],'refund_mark'=>$data['admin_note'],'out_request_no'=>date('YmdHi').$order['order_id']);
                        $result = $payment_obj->payment_refund($data);
                        if($result['msg'] == 'Success'){
                            $data['pay_status']   = 3;
                            $data['order_status'] = 3;
                            $data['admin_note']   = I('admin_note/s');
                            M('order')->where(array('order_id'=>$order['order_id']))->save($data);
                            adminLog('退款到支付宝(order_id:'.$order['order_id'].')');
                            $this->success('退款成功');
                        } else {
                            $this->success('退款失败');
                        }
                    }
                }else{
                    $this->error('该订单支付方式不支持在线退回');
                }
            }
        }else{
            M('order')->where(array('order_id'=>$order['order_id']))->save($data);
            $this->success('拒绝退款操作成功');
        }
    }


    /**
     * 退货单列表
     */
    public function return_list(){

        $suppliersList = M("suppliers")->cache('suppliers_list')->select();
        foreach ($suppliersList as $k => $val) {
            $str = "";
            switch ($val['is_check']) {
                case '0':
                    $str = "(未审核)";
                    break;
                case '1':
                    $str = "(审核中)";
                    break;
                case '2':
                    $str = "(审核未通过)";
                    break;
                case '3':
                    if($val['status'] == 0)
                        $str = "(已冻结)";
                    if($val['status'] == 1)
                        $str = "(营业)";
                    else
                        $str = "(审核通过)";
                    break;
                default:
                    $str = "(未审核)";
                    break;
            }
            $val['suppliers_name'] = $val['suppliers_name'].$str;
            $suppliersList[$k] = $val;
        }
        $this->assign('suppliersList', $suppliersList);

        return $this->fetch();
    }

    /*
     * ajax 退货订单列表
     */
    public function ajax_return_list(){
        // 搜索条件
        $order_sn   =  trim(I('order_sn'));
        $order_by   = I('order_by') ? I('order_by') : 'addtime';
        $sort_order = I('sort_order') ? I('sort_order') : 'desc';
        $status     =  I('status');

        $where = " 1 = 1 ";

        I('suppliers_id2') && $where .= " and suppliers_id=".trim(I('suppliers_id2'));  // 供货商ID liyi 2018.07.09

        $order_sn && $where.= " and order_sn like '%$order_sn%' ";
        empty($order_sn)&& !empty($status) && $where.= " and status = '$status' ";

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " suppliers_id > 0";
        } else if($sp && $sp == 2){
            $where .= " suppliers_id = 0";
        }

        $count = M('return_goods')->where($where)->count();
        $Page  = new AjaxPage($count,13);
        $show = $Page->show();
        $list = M('return_goods')->where($where)->order("$order_by $sort_order")->limit("{$Page->firstRow},{$Page->listRows}")->select();
    //    echo M('return_goods')->getlastsql();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr)){
            $goods_list = M('goods')->where("goods_id in (".implode(',', $goods_id_arr).")")->getField('goods_id,goods_name');
        }
        $state = C('REFUND_STATUS');
        $this->assign('state',$state);
        $this->assign('goods_list',$goods_list);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }

    public function export_return_list(){
        // 搜索条件
        $order_sn   = trim(I('order_sn'));
        $order_by   = I('order_by') ? I('order_by') : 'addtime';
        $sort_order = I('sort_order') ? I('sort_order') : 'desc';
        $status     = I('status');
        $state      = C('REFUND_STATUS');

        $where = " 1 = 1 ";
        I('suppliers_id2') && $where .= " and suppliers_id=".trim(I('suppliers_id2'));  // 供货商ID liyi 2018.07.09
        $order_sn && $where.= " and order_sn like '%$order_sn%' ";
        empty($order_sn)&& !empty($status) && $where.= " and status = '$status' ";

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " suppliers_id > 0";
        } else if($sp && $sp == 2){
            $where .= " suppliers_id = 0";
        }

        $order_ids = I('order_ids');
        if($order_ids){
            $where.= ' and order_id in ('.$order_ids.')';
        }

        $list = M('return_goods')->where($where)->order("$order_by $sort_order")->limit("{$Page->firstRow},{$Page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr)){
            $goods_list = M('goods')->where("goods_id in (".implode(',', $goods_id_arr).")")->getField('goods_id,goods_name');
        }

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="600">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">类型</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
        $strTable .= '</tr>';
        if(is_array($list)){
            foreach($list as $k=>$val){
                if($val['pay_time'] > 0) $val['pay_time'] = date('Y-m-d H:i:s',$val['pay_time']); else $val['pay_time'] = '货到付款';
                if($val['type'] == 1) $val['type'] = '退货退款'; else if($val['type'] == 2) $val['type'] = '换货'; else $val['type'] = '仅退款';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$goods_list[$val['goods_id']].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['type'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['addtime']).' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$state[$val['status']].'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'return_list');
        exit();
    }

    /**
     * 删除某个退换货申请
     */
    public function return_del(){
        $id = I('get.id');
        M('return_goods')->where("id = $id")->delete();
        $this->success('成功删除!');
    }

    /**
     * 退换货操作
     */
    public function return_info()
    {
        $orderLogic   = new OrderLogic();
        $return_id    = I('id'); //
        $return_goods = M('return_goods')->where("id= $return_id")->find();

        $order_goods_num = M('order_goods')->where("order_id=".$return_goods['order_id'])->sum('goods_num');

        if(!$return_goods)
        {
            $this->error('非法操作!');
            exit;
        }
        $user  = M('users')->where("user_id = {$return_goods[user_id]}")->find();
        $goods = M('goods')->where("goods_id = {$return_goods[goods_id]}")->find();
        $type_msg = array('仅退款','退货退款','换货');
        $status_msg = C('REFUND_STATUS');
        if(IS_POST)
        {
            $data = I('post.');

            if($data['refund_money'] > $return_goods['money'])
                $this->error('退款金额不可大于最大退款金额');

            if($return_goods['type'] == 2 && $return_goods['is_receive'] == 1){
            	$data['seller_delivery']['express_time'] = date('Y-m-d H:i:s');
            	$data['seller_delivery'] = serialize($data['seller_delivery']); //换货的物流信息
            }
            $note ="退换货:{$type_msg[$return_goods['type']]}, 状态:{$status_msg[$data['status']]},处理备注：{$data['remark']}";
            $result = M('return_goods')->where("id= $return_id")->save($data);
            if($result && $data['status']==1)
            {
                $order_data_status['order_status'] = 7;
                $edit_order_status = M('order')->where("order_id=".$return_goods['order_id'])->save($order_data_status);
                #老张 修改  判断  获取订单商品总数
                $order_goods_num = M('order_goods')->where("order_id=".$return_goods['order_id'])->sum('goods_num');
                $return_goods_num = M('return_goods')->where("order_id=".$return_goods['order_id']." AND status = 3")->sum('goods_num');
                #判断退货数量是否大于购买商品总数
                if($return_goods_num >= $order_goods_num){
                    $order_data['order_status'] = 6;
                    M('order')->where("order_id=".$return_goods['order_id'])->save($order_data); // 更新订单状态
                }

                //审核通过才更改订单商品状态，进行退货，退款时要改对应商品修改库存
                $order = \app\common\model\Order::get($return_goods['order_id']);
                $commonOrderLogic = new \app\common\logic\OrderLogic();
                $commonOrderLogic->alterReturnGoodsInventory($order,$return_goods['rec_id']); //审核通过，恢复原来库存
                if($return_goods['type'] < 2){
                    $orderLogic->disposereRurnOrderCoupon($return_goods); // 是退货可能要处理优惠券
                }
            }
            $orderLogic->orderActionLog($return_goods['order_id'],'退换货',$note);
            adminLog('退换货(order_id:'.$return_goods['order_id'].')');
            $this->success('修改成功!');
            exit;
        }
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        if($return_goods['imgs']) $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $this->assign('id',$return_id); // 用户
        $this->assign('user',$user); // 用户
        $this->assign('goods',$goods);// 商品
        $this->assign('return_goods',$return_goods);// 退换货
        $order = M('order')->where(array('order_id'=>$return_goods['order_id']))->find();
        $this->assign('order',$order);//退货订单信息
        return $this->fetch();
    }

    //售后退款原路退回
    public function refund_back(){

    	$return_id = I('id');
        $return_goods = M('return_goods')->where("id= $return_id")->find();
    	$rec_goods = M('order_goods')->where(array('order_id'=>$return_goods['order_id'],'goods_id'=>$return_goods['goods_id']))->find();
    	$order = M('order')->where(array('order_id'=>$rec_goods['order_id']))->find();
        $total_fee = M('order')->where('transaction_id',$order['transaction_id'])->sum('order_amount');
        
    	if($order['pay_code'] == 'weixin' || $order['pay_code'] == 'weixinH5' || $order['pay_code'] == 'alipay' || $order['pay_code'] == 'alipayMobile'){
    		$orderLogic = new OrderLogic();
    		$return_goods['refund_money'] = $orderLogic->getRefundGoodsMoney($return_goods);
    		if($order['pay_code'] == 'weixin' || $order['pay_code'] == 'weixinH5'){
    			include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
    			$payment_obj =  new \weixin();
    			$data = array('transaction_id'=>$order['transaction_id'],'total_fee'=>$total_fee,'refund_fee'=>$return_goods['refund_money']);
    			$result = $payment_obj->payment_refund($data);
    			if($result['return_code'] == 'SUCCESS'  && $result['result_code'] == 'SUCCESS'){
                     $order_data_pay['pay_status'] = 5;
                     $edit_order_status = M('order')->where("order_id=".$return_goods['order_id'])->save($order_data_pay);
    				updateRefundGoods($return_goods['rec_id']);//订单商品售后退款
    				$this->success('退款成功');
    			}else{
    				$this->error($result['return_msg']);
    			}
    		}else{
    			include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
    			$payment_obj = new \alipay();
    			$detail_data = $order['transaction_id'].'^'.$return_goods['refund_money'].'^'.'用户申请订单退款';
    			$data = array('transaction_id'=>$order['transaction_id'],'refund_money'=>$return_goods['refund_money'],'refund_mark'=>$return_goods['refund_mark'],'out_request_no'=>date('YmdHi').$rec_goods['rec_id']);
    			$result = $payment_obj->payment_refund($data);
                if($result['msg'] == 'Success'){
                    $order_data_pay['pay_status'] = 5;
                    $edit_order_status = M('order')->where("order_id=".$return_goods['order_id'])->save($order_data_pay);
                    $this->success('退款成功');
                } else {
                    $this->success('退款失败');
                }
    		}
    	}else{
    		$this->error('该订单支付方式不支持在线退回');
    	}
    }
    /**
     * 退货，余额+积分支付
     * 有用三方金额支付的不走这个方法
     */
    public function refund_balance(){
		$data = I('POST.'); 
		$return_goods = M('return_goods')->where(array('rec_id'=>$data['rec_id']))->find();
        if(empty($return_goods)) $this->ajaxReturn(['status'=>0,'msg'=>"参数有误"]); 
        $order_data_pay['pay_status'] = 5;
        $edit_order_status = M('order')->where("order_id=".$return_goods['order_id'])->save($order_data_pay);
		M('return_goods')->where(array('rec_id'=>$data['rec_id']))->save($data);
		updateRefundGoods($data['rec_id'],1);//售后商品退款
		$this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U("Admin/Order/return_list")]);
		
    }

    /**
     * 管理员生成申请退货单
     */
    public function add_return_goods()
   {
            $order_id = I('order_id');
            $goods_id = I('goods_id');

            $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id")->find();
            if(!empty($return_goods))
            {
                $this->error('已经提交过退货申请!',U('Admin/Order/return_list'));
                exit;
            }
            $order = M('order')->where("order_id = $order_id")->find();

            $data['order_id'] = $order_id;
            $data['order_sn'] = $order['order_sn'];
            $data['goods_id'] = $goods_id;
            $data['addtime']  = time();
            $data['user_id']  = $order[user_id];
            $data['remark'] = '管理员申请退换货'; // 问题描述
            M('return_goods')->add($data);
            $this->success('申请成功,现在去处理退货',U('Admin/Order/return_list'));
            exit;
    }

    /**
     * 订单操作
     * @param $id
     */
    public function order_action(){    	
        $orderLogic = new OrderLogic();
        $action = I('get.type');
        $order_id = I('get.order_id');
        if($action && $order_id){

            if($action !=='pay'){  // 不是付款
                $convert_action = C('CONVERT_ACTION')["$action"];
                $res = $orderLogic->orderActionLog($order_id,$convert_action,I('note'));
                if($action == 'refuse'){
                    $res2 = $orderLogic->orderRefuseLog($order_id,0,$convert_action,1,I('note'));
                }
                if($action == 'refuse_yes'){
                    $res2 = $orderLogic->orderRefuseLog($order_id,0,$convert_action,2,I('note'));
                }
                if($action == 'refuse_no'){
                    $res2 = $orderLogic->orderRefuseLog($order_id,0,$convert_action,3,I('note'));
                }
            }

        	$a = $orderLogic->orderProcessHandle($order_id,$action,array('note'=>I('note'),'admin_id'=>0)); // 操作订单

        	if($res !== false && $a !== false){
                if ($action == 'remove') {
                    exit(json_encode(array('status' => 1, 'msg' => '操作成功', 'data' => array('url' => U('admin/order/index')))));
                }
        	 	exit(json_encode(array('status' => 1,'msg' => '操作成功')));
        	}else{
                if ($action == 'remove') {
                    exit(json_encode(array('status' => 0, 'msg' => '操作失败', 'data' => array('url' => U('admin/order/index')))));
                }
        	 	exit(json_encode(array('status' => 0,'msg' => '操作失败')));
        	}
        }else{
        	$this->error('参数错误',U('Admin/Order/detail',array('order_id'=>$order_id)));
        }
    }
    
    public function order_log(){
    	$timegap = urldecode(I('timegap'));
    	if($timegap){
    		$gap = explode('-', $timegap);
            $timegap_begin = $gap[0];
            $timegap_end = $gap[1];
    		$begin = strtotime($timegap_begin);
    		$end = strtotime($timegap_end);
    	}else{
    	    //@new 兼容新模板
            $timegap_begin = urldecode(I('timegap_begin'));
            $timegap_end = urldecode(I('timegap_end'));
    	    $begin = strtotime($timegap_begin);
    	    $end = strtotime($timegap_end);
    	}
    	$condition = array();
    	$log =  M('order_action');
    	if($begin && $end){
    		$condition['log_time'] = array('between',"$begin,$end");
    	}
        //2018-09-26 李鑫 修改订单日志订单ID模糊查询
        $order_id=I('order_sn');
        if ($order_id) {
            $condition['order_id'] = array('like',"%$order_id%");
        }
        //修改结束
    	$admin_id = I('admin_id');
		if($admin_id >0 ){
			$condition['action_user'] = $admin_id;
		}
        // modify
        // $condition['is_red'] = ['eq',$this->is_red];
    	$count = $log->where($condition)->count();
    	$Page = new Page($count,20);

    	foreach($condition as $key=>$val) {
    		$Page->parameter[$key] = urlencode($begin.'_'.$end);
    	}
    	$show = $Page->show();
    	$list = $log->where($condition)->order('action_id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $orderIds = [];
        foreach ($list as $log) {
            if (!$log['action_user']) {
                $orderIds[] = $log['order_id'];
            }
        }

        if ($orderIds) {
            $users = M("users")->alias('u')->join('__ORDER__ o', 'o.user_id = u.user_id')->getField('o.order_id,u.nickname');
        }

        $this->assign('timegap_begin',$timegap_begin);
        $this->assign('timegap_end',$timegap_end);
        $this->assign('users',$users);
    	$this->assign('list',$list);
    	$this->assign('pager',$Page);
    	$this->assign('page',$show);   	
    	$admin = M('admin')->getField('admin_id,user_name');
    	$this->assign('admin',$admin);    	
    	return $this->fetch();
    }

    public function export_order_log(){
        $timegap = urldecode(I('timegap'));
        if($timegap){
            $gap = explode('-', $timegap);
            $timegap_begin = $gap[0];
            $timegap_end = $gap[1];
            $begin = strtotime($timegap_begin);
            $end = strtotime($timegap_end);
        }else{
            //@new 兼容新模板
            $timegap_begin = urldecode(I('timegap_begin'));
            $timegap_end = urldecode(I('timegap_end'));
            $begin = strtotime($timegap_begin);
            $end = strtotime($timegap_end);
        }
        $condition = array();
        $log =  M('order_action');
        if($begin && $end){
            $condition['log_time'] = array('between',"$begin,$end");
        }
        $admin_id = I('admin_id');
        if($admin_id >0 ){
            $condition['action_user'] = $admin_id;
        }

        $action_ids = I('action_ids');
        if($action_ids){
            $condition['action_id'] = array('in', $action_ids);
        }
        
        foreach($condition as $key=>$val) {
            $Page->parameter[$key] = urlencode($begin.'_'.$end);
        }

        $list = $log->where($condition)->order('action_id desc')->select();
        $orderIds = [];
        foreach ($list as $log) {
            if (!$log['action_user']) {
                $orderIds[] = $log['order_id'];
            }
        }
        if ($orderIds) {
            $users = M("users")->alias('u')->join('__ORDER__ o', 'o.user_id = u.user_id')->getField('o.order_id,u.nickname');
        }

        $admin = M('admin')->getField('admin_id,user_name');

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">操作动作</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">操作员</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">操作备注</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">操作时间</td>';
        $strTable .= '</tr>';
        if(is_array($list)){
            foreach($list as $k=>$val){
                if($val['action_user'] != 0) $val['action_user'] = $admin[$val['action_user']]; else $val['ction_user'] = $users[$val['order_id']];
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['order_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['status_desc'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['action_user'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['action_note'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['log_time']).'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'order_log');
        exit();
    }

    /**
     * 检测订单是否可以编辑
     * @param $order
     */
    private function editable($order){
        if($order['shipping_status'] != 0){
            $this->error('已发货订单不允许编辑');
            exit;
        }
        return;
    }

    public function return_percentage_fileput()
    {

		\think\Config::set('show_error_msg',true);
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 50 : $_REQUEST['size'];
        $timegap    = I('timegap');
        if($timegap){
            $gap   = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end   = strtotime($gap[1]);
        }else{
            //@new 新后台UI参数
            $begin = strtotime(I('add_time_begin'));
            $end   = strtotime(I('add_time_end'));
        }
        
        $where = array();//搜索条件

        $keyType   = I("keytype");
        $keywords  = I('keywords','','trim');
        
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $where['a.consignee'] = trim($consignee) : false;

        if($begin && $end){
            $where['a.add_time'] = array('between',"$begin,$end");
        }

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where['a.suppliers_id'] = array('gt', 0);
        } else if($sp && $sp == 2){
            $where['a.suppliers_id'] = 0;
        }

        $where['a.order_prom_type']      = array('lt',5);
        $order_sn = ($keyType && $keyType  == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $where['a.order_sn'] = trim($order_sn) : false;
        
        I('order_status')    != '' ? $where['a.order_status'] = I('order_status') : false;
        I('pay_status1')     != '' ? $where['a.pay_status']   = I('pay_status1')  : false;
        I('pay_status')      != '' ? $where['a.pay_status']   = I('pay_status')   : false;
        I('pay_code')        != '' ? $where['a.pay_code']     = I('pay_code')     : false;
        I('shipping_status') != '' ? $where['a.shipping_status'] = I('shipping_status') : false;
        I('user_id1')      ? $where['a.user_id']      = trim(I('user_id1')) : false;
        I('user_id')       ? $where['a.user_id']      = trim(I('user_id')) : false;
        I('suppliers_id')  ? $where['a.suppliers_id'] = trim(I('suppliers_id')) : false;   // 供货商ID liyi 2018.04.18
        I('suppliers_id2') ? $where['a.suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09


        if($order_ids){
            $where['a.order_id'] = ['in', $order_ids];
        }
        $list = M('order')->where($where)->alias('a')
            ->field('a.*,b.goods_num,b.goods_price,b.member_goods_price,b.rec_id,b.goods_id,b.goods_sn,b.spec_key_name,c.goods_name,c.cat_id,c.tax_rate,d.name d_name,e.suppliers_name,e.suppliers_id e_suppliers_id')
            ->join('order_goods b', 'a.order_id = b.order_id', 'left')
            ->join('goods c', 'b.goods_id = c.goods_id', 'left')
            ->join('goods_category d', 'c.cat_id = d.id', 'left')
            ->join('suppliers e', 'a.suppliers_id = e.suppliers_id', 'left')
            ->order("a.order_id desc")->page("$p,$size")->select();
        $count = M('order')->where($where)->alias('a')
            ->field('a.*,b.goods_num,b.goods_price,b.member_goods_price,b.rec_id,b.goods_id,b.goods_sn,b.spec_key_name,c.goods_name,c.cat_id,c.tax_rate,d.name d_name,e.suppliers_name,e.suppliers_id e_suppliers_id')
            ->join('order_goods b', 'a.order_id = b.order_id', 'left')
            ->join('goods c', 'b.goods_id = c.goods_id', 'left')
            ->join('goods_category d', 'c.cat_id = d.id', 'left')
            ->join('suppliers e', 'a.suppliers_id = e.suppliers_id', 'left')->count();

        $user_id = $_SESSION["think"]["user"]["user_id"];
        $dir_url = "./public/order/data_" . $user_id . "/";
        if (!is_dir($dir_url)) {
            mkdir($dir_url, 0777, true);
        }
        $Page = new Page($count, $size);
        if ($Page->nowPage <= $Page->totalPages) {
            file_put_contents($dir_url . "/return_com_" . $Page->nowPage . ".txt", json_encode($list));
            return ceil($Page->nowPage / $Page->totalPages * 100);
        }

    }



    public function export_order()
    {
        $user_id = $_SESSION["think"]["user"]["user_id"];
        $dir_url = "./public/order/data_" . $user_id . "/";
        $files = scandir($dir_url);
        unset($files[0]);
        unset($files[1]);
        $filelenght = count($files);
        $orderList = [];
        for ($i = 1; $i <= $filelenght; $i++) {
            $data = file_get_contents($dir_url . "return_com_" . $i . ".txt");
            $row = json_decode($data, true);
            $orderList = array_merge($orderList, $row);
        }
    	$strTable ='<table width="500" border="1">';
    	$strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">序号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">订单ID</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="120">日期</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货地址</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">电话</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">赠送米豆</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">实际支付</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品成本总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运营成本总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">总利润</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付方式</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否参与福利</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单状态</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付状态</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">发货状态</td>';
      	$strTable .= '<td style="text-align:center;font-size:12px;" width="120">收货时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品数量</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品信息</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户备注</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">管理员备注</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">税率</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供货商</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">分类</td>';
    	$strTable .= '</tr>';
	    if(is_array($orderList)){
	    	$region	= get_region_list();
            $n = 0;
	    	foreach($orderList as $k=>$val){
                $n++;
                if($val['is_allreturn'] == 1) $allreturn_str = '福利订单';
                else $allreturn_str = '普通订单'; 
				if($val['add_time'])$val['create_time']=date('Y-m-d H:i:s',$val['add_time']);
                if($val['confirm_time'])$val['confirm_time']=date('Y-m-d H:i:s',$val['confirm_time']);
                //$val['back_midou'] = 0;
                $midouInfo = returnMidou($val['goods_id']);
                $back_midou= $midouInfo['midou']; // 购买商品赠送米豆
                $back_midou += $val['goods_num']*$val['back_midou']; // 订单赠送米豆累计
                $list[$k]['back_midou'] = num_float2($back_midou);
                $goodsTaxRate = 0.000;
                $res[] = $val['goods_price']*$val['goods_num'];
                //取得税率
                $val['price'] = array_sum($res); 
                if($val['price'] ){
                    $price = $val['price'];
                }
	    		$strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$n.'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_id'].'</td>';
	    		$strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['create_time'].' </td>';	    		
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['consignee'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'."{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}".' </td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].'</td>';

                $strTable .= '<td style="text-align:left;font-size:12px;">'.num_float2($back_midou).'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shipping_price'].'</td>';
                // $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">';
                 if($price!=$val['goods_price']){
                    $strTable .= '<span style="color: red;">'.$val['goods_price'].'</span>&nbsp;<span style="text-decoration: line-through;">'.$price.'</span>';
                }else{
                    $strTable .= $val['goods_price'];
                }
                $strTable .= '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tk_cost_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tk_cost_operating'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['goods_price']-$val['tk_cost_price']-$val['tk_cost_operating']).'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_name'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;" width="*">'.$allreturn_str.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$this->order_status[$val['order_status']].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$this->pay_status[$val['pay_status']].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$this->shipping_status[$val['shipping_status']].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['confirm_time'].' </td>';	
	    		$strGoods="";
                $goods_num = 0;
                $midouInfo = returnMidou($val['goods_id']);
                $val['back_midou'] = $midouInfo['midou'];
                $goods_num = $goods_num + $val['goods_num'];
                $strGoods .= "商品编号：".$val['goods_sn']." 商品名称：".$val['goods_name'];
                if ($val['spec_key_name'] != '') $strGoods .= " 规格：".$val['spec_key_name'];
                $strGoods .= " 赠送米豆：".$val['back_midou'];
                $strGoods .= "<br />";
                if($val['suppliers_id'] == 0){
                    $val['suppliers_name'] = '自营';
                }
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$goods_num.' </td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$strGoods.' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['user_note'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['admin_note'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tax_rate'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['suppliers_name'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['d_name'].' </td>';
	    		$strTable .= '</tr>';
	    	}
	    }
         // die();
        $strTable .='</table>';
        downloadExcel($strTable, '现金商品订单导出');
        $this->removeDir($dir_url);
        adminLog('现金商品订单导出');
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
    
    public function ajaxOrderNotice(){
        $order_amount = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();
        echo $order_amount;
    }

    public function batchDelivery(){

        if(IS_POST){
            $arr = Request::instance()->post('arr/a');
            $res = [];
            $orderLogic = new OrderLogic();
            foreach ($arr as $k=>$row){
                $order = db('order')->where(["order_id"=>$row['order_id']])->field("shipping_status,shipping_name")->find();
                $order_goods = db('order_goods')->where(["order_id"=>$row['order_id']])->column('rec_id');
                $arr[$k]['shipping']=$order["shipping_status"];
                $arr[$k]['shipping_name']=$order["shipping_name"];
                $arr[$k]['goods'] = $order_goods;
                $res[] = $orderLogic->deliveryHandle($arr[$k]);
            }

            $result['error'] = 0;
            return json($result);

        }else{
            $condition = array();
            $_GET['orderid']?$condition['order_id'] = ['in',$_GET['orderid']]:false;

            $sp = I('sp','','intval');
            if($sp && $sp == 1){
                $condition['suppliers_id'] = array('gt', 0);
            } else if($sp && $sp == 2){
                $condition['suppliers_id'] = 0;
            }
            I('suppliers_id2') ? $condition['suppliers_id'] = trim(I('suppliers_id2')) : false;   // 供货商ID liyi 2018.07.09
            I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
            I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
            $shipping_status = I('shipping_status');
            $condition['shipping_status'] = empty($shipping_status) ? array('neq',1) : $shipping_status;
            $condition['order_status']    = array('in','1,2,4');
            $count = M('order')->where($condition)->count();

            $Page  = new AjaxPage($count,10);
            //搜索条件下 分页赋值
            foreach($condition as $key=>$val) {
                if(!is_array($val)){
                    $Page->parameter[$key]   =   urlencode($val);
                }
            }

            $orderList = M('order')->where($condition)->order('add_time DESC')->select();

            foreach ($orderList as $k=>$row){
                $orderList[$k]["shipping_list"] = M('plugin')->where(array('status'=>1,'type'=>'shipping','suppliers_id'=>$row['suppliers_id']))->select();
            }

            $this->assign('orderList',$orderList);
            return $this->fetch();
        }
    }



}
