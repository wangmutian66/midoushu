<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * 2015-11-21
 */
namespace app\applet_app\controller;

use app\common\model\TeamFound;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use think\Page;
use think\Request;
use think\db;

class Order extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    public function _initialize()
    {
        parent::_initialize();
     
        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);
    }

    /**
     * 订单列表
     * @return mixed
     */
    public function order_list()
    {
        $p = I('p/d',1);
        $user_id = I('user_id/d');
        $where = ' user_id=' . $user_id;
        //条件搜索
        if(I('get.type')){
            if(strtoupper(I('get.type')=="WAITPAY")){
                $where .= ' AND pay_status = 0 AND pay_code !="cod" AND order_status in (0,1)';
            }elseif(strtoupper(I('get.type')=="WAITSEND")){
                $where .= ' AND (pay_status=1 OR pay_code="cod") AND order_status =1 ';
            }else{
                $where .= C(strtoupper(I('get.type')));
            }
            
        }
        $where.=' and order_prom_type < 5 ';//虚拟订单和拼团订单不列出来
        $count = M('order')->where($where)->count();
        $Page = new Page($count, 10);
        $page= object_to_array($Page);
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->page($p,C('PAGESIZE'))->select();
        $orderstatus = C('ORDER_STATUS');
        $shippingstatus =  C('SHIPPING_STATUS');
        $paystatus = C('PAY_STATUS');

        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $data = $model->get_order_goods($v['order_id']);
            foreach ($data['result'] as $key => $value) {
                if (strstr(goods_thum_images($value['goods_id'],400,400),'http')) {
                    $data['result'][$key]['goodsimg']=goods_thum_images($value['goods_id'],400,400);
                }else{
                    $data['result'][$key]['goodsimg']=URL.goods_thum_images($value['goods_id'],400,400);
                }
            }
            $order_list[$k]['goods_list'] = $data['result'];
            $order_list[$k]['order_status_name'] =$orderstatus[$v['order_status']];
            $order_list[$k]['shipping_status_name'] =$shippingstatus[$v['shipping_status']];
            $order_list[$k]['pay_status_name'] =$paystatus[$v['pay_status']];
        }
        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = 0;
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
        }

       $result['page']['nowPages']=$p;
        $result['page']['totalPages']=$page['totalPages'];
        $result['lists']=$order_list;
        $result['active_status']=I('get.type');
        exit(formt($result));
    
    }
    
    

    /**
     * 订单详情
     * @return mixed
     */
    public function order_detail()
    {
        $id = I('get.order_id/d');
        $map['order_id'] = $id;
        $map['user_id'] =  I('get.user_id');
        $order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
              exit(formt('',201,'没有获取到订单信息'));
           
        }
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);

        foreach ($data['result'] as $k=>$row){
            $data['result'][$k]["suppliers_phone"] = db('suppliers')->where(["suppliers_id"=>$row['suppliers_id']])->value("suppliers_phone");
            // $img = db('goods_images')->where(["goods_id"=>$row['goods_id']])->value('image_url');
            if (strstr(goods_thum_images($row['goods_id'],400,400),'http')) {
                     $data['result'][$k]["goodsimg"] = goods_thum_images($row['goods_id'],400,400);
                }else{
                     $data['result'][$k]["goodsimg"] = URL.goods_thum_images($row['goods_id'],400,400);
                }
            
        }
        $order_info['goods_list'] = $data;
        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no', true);
        $order_info['invoice_no'] = implode(' , ', $invoice_no);
        $order_info['add_time'] = date('Y-m-d H:i:s', $order_info['add_time']);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id' => $id))->select();
        

        //快递查询START
        $krs = M('delivery_log')->where("order_id=".$id)->find();
        if($krs['status'] == 3){
            $wuliudata = $krs['data'];
        }else{
            //参数设置
            $post_data = array();
            $post_data["customer"] = 'DF32DE3F16EDCCC68C701048A18A8AA8';
            $key= 'MGUIHCmb6562' ;
            $post_data["param"] = '{"com":"'.$krs['shipping_code'].'","num":"'.$krs['invoice_no'].'"}';

            $url='http://poll.kuaidi100.com/poll/query.do';
            $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
            $post_data["sign"] = strtoupper($post_data["sign"]);
            $o="";
            foreach ($post_data as $k=>$v)
            {
                $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
            }
            $post_data=substr($o,0,-1);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL,$url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            $result = curl_exec($ch);
            $data = str_replace("\"",'"',$result );
            $data = json_decode($data,true);


            if($data['result'] !== false){
                $wuliudata = $data['data'];
                $ukdata['status'] = $data['state'];
                $ukdata['data'] = serialize($data['data']);
                M('delivery_log')->where("order_id=".$id)->update($ukdata);
            }
        }
       
        $order_list['order_status']=C('ORDER_STATUS');
        $order_list['shipping_status']=C('SHIPPING_STATUS');
        $order_list['pay_status']=C('PAY_STATUS');
        $order_list['order_info']=$order_info;
        $order_list['order_action']=$order_action;
        $order_list['wuliudata']=$wuliudata;
        exit(formt($order_list));
      
       
    }

    /**
     * 物流跟踪
     * @return mixed
     */
    public function get_delivery()
    {
        $id = I('get.order_id/d');
        $map['order_id'] = $id;
        $map['user_id'] = I('get.user_id');
        $order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            exit(formt('',201,'没有获取到订单信息'));
        }


        //快递查询START
        $krs = M('delivery_log')->where("order_id=".$id)->find();
        if($krs['status'] == 3){
            $wuliudata = unserialize($krs['data']);
        }else{
            //参数设置
            $post_data = array();
            $post_data["customer"] = 'DF32DE3F16EDCCC68C701048A18A8AA8';
            $key= 'MGUIHCmb6562' ;
            $post_data["param"] = '{"com":"'.$krs['shipping_code'].'","num":"'.$krs['invoice_no'].'"}';

            $url='http://poll.kuaidi100.com/poll/query.do';
            $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
            $post_data["sign"] = strtoupper($post_data["sign"]);
            $o="";
            foreach ($post_data as $k=>$v)
            {
                $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
            }
            $post_data=substr($o,0,-1);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL,$url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            $result = curl_exec($ch);
            $data = str_replace("\"",'"',$result );
            $data = json_decode($data,true);


            if($data['result'] !== false){
                $wuliudata = $data['data'];
                $ukdata['status'] = $data['state'];
                $ukdata['data'] = serialize($data['data']);
                M('delivery_log')->where("order_id=".$id)->update($ukdata);
            }
        }
   
        $delivery['kdata']=$krs;
        $delivery['wuliudata']=$wuliudata;
        $delivery['order_info']=$order_info;
        exit(formt($delivery));
        //快递查询END

    }

    /**
    * 取消订单
    */
    public function cancel_order()
    {
        $id = I('order_id');
        $map['order_id'] = $id;
        $user_id = $map['user_id'] = I('user_id');
        $order_info = M('order')->field('order_status,shipping_status,pay_status')->where($map)->find();

        if($order_info['shipping_status'] == 1){
            exit(formt('',201,'该订单已发货，不支持取消'));
        }
        if($order_info['order_status'] == 2){
            exit(formt('',201,'该订单已收货，不支持取消'));
        }
        if($order_info['order_status'] == 3){
            exit(formt('',201,'该订单已取消'));
        }
        if($order_info['order_status'] == 4){
            exit(formt('',201,'该订单已完成，不支持取消'));
        }

        //检查是否有积分，余额支付
        $logic = new OrderLogic();
        $data = $logic->cancel_order($user_id, $id);
        if ($data['status']=='-1') {
            exit(formt('',201,$data['msg']));
        }else{
            exit(formt($data['msg']));
        }
        
    }
    /**
     * 确定收货成功
     */
    public function order_confirm()
    {
        $id = I('order_id/d', 0);
        $user_id = I('user_id');
        $data = confirm_order($id, $user_id);

        if ($data['status'] != 1) {
            exit(formt('',201,$data['msg']));
        } else {
            exit(formt($order_confirm));
        }
    }
     /**
     * 确定收货成功
     */
    public function order_confirms()
    {
        $id = I('order_id/d', 0);
      
        $model = new UsersLogic();
        $order_goods = $model->get_order_goods($id);
        $order_confirm['order_goods']=$order_goods;
        exit(formt($order_confirm));
        
    }
    //订单支付后取消订单
    public function refund_order()
    {
        $order_id = I('get.order_id/d');
        $user_id = I('user_id/d');
        $order = M('order')
            ->field('order_id,consignee,mobile,pay_code,pay_name,user_money,integral_money,coupon_price,order_amount,order_status,shipping_status,pay_status')
            ->where(['order_id' => $order_id, 'user_id' => $user_id])
            ->find();

    
        if($order['order_status'] == 2){
            exit(formt('',201,'该订单已收货，不支持申请售后'));
        }
        if($order['order_status'] == 3){
            exit(formt('',201,'该订单已取消'));
        }
        if($order['order_status'] == 4){
            exit(formt('',201,'该订单已完成，不支持申请售后'));
        }
        $refund_order['user']=$user_id;
        $refund_order['order']=$order;
        exit(formt($refund_order));
    }
    //申请取消订单
    public function record_refund_order()
    {
        $order_id   = I('order_id');
        $user_note  = I('user_note');
        $consignee  = I('consignee');
        $mobile     = I('mobile');
        $user_id     = I('user_id');
      
        $logic = new \app\common\logic\OrderLogic;
        $return = $logic->recordRefundOrder($user_id, $order_id, $user_note, $consignee, $mobile);
        if ($return['status']=='1') {
            exit(formt('',200,'取消成功'));
        }else{
            exit(formt('',201,$return['msg']));
        }
    }

    /**
     * 申请退货
     */
    public function return_goods()
    {

        $rec_id = I('rec_id',0);
        $user_id = I('user_id');
        $return_goods = M('return_goods')->where(array('rec_id'=>$rec_id))->find();


        if(!empty($return_goods))
        {
            exit(formt('',201,'已经提交过退货申请!'));
        }

        $order_goods = M('order_goods')->where(array('rec_id'=>$rec_id))->find();

        $order = M('order')->where(array('order_id'=>$order_goods['order_id'],'user_id'=>$user_id))->find();

        $confirm_time_config = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            exit(formt('',201,'已经超过' . $confirm_time_config . "天内退货时间"));
        }

        if(empty($order)){
            exit(formt('',201,'非法操作'));
        }
        if($order['order_status'] == 2){
            exit(formt('',201,'该订单已收货，不支持申请售后'));
        }
        if($order['order_status'] == 3){
            exit(formt('',201,'该订单已取消'));
        }
        if($order['order_status'] == 4){
            exit(formt('',201,'该订单已完成，不支持申请售后'));
        }

        if($order_goods['suppliers_id']){
            $suppliers_info = get_suppliers_info($order_goods['suppliers_id']);
            $return_goods['suppliers_info']=$suppliers_info;
        }

        $return_goods['tpshop_config'] = tpCache('shop_info');



        if(I('post')==1)
        {
            $img = I("return_imgs");
            $res = $this->addReturnGoods($rec_id,$order,$img);  //申请售后
            if($res['status']==1){
                exit(formt('',200,$res['msg']));
            }else{
                exit(formt('',201,$res['msg']));
            }
        }

        $region_id[] = tpCache('shop_info.province');
        $region_id[] = tpCache('shop_info.city');
        $region_id[] = tpCache('shop_info.district');
        $region_id[] = 0;
        $return_address = M('region')->where("id in (".implode(',', $region_id).")")->getField('id,name');
        $order_goods["goods_thum_images"] = goods_thum_images($order_goods['goods_id'],"100","100");
        $return_goods['return_address']=implode(" ",$return_address);
        $return_goods['goods']=$order_goods;
        $return_goods['order']=$order;
        exit(formt($return_goods));

    }


    public function wx_upload_img(){
        $img = $this->uploadReturnGoodsImg();
        $data['img'] = $img['result']; //兼容小程序，多传imgs
        $data['rec_id'] = I('rec_id',0);
        $data['user_id'] = I('user_id');
        $result = M('wechat_img')->add($data);
        
        exit(formt($res));
    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {

        //退换货商品信息
        $user_id = I('user_id/d');
        $count = M('return_goods')->where("user_id", $user_id)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('return_goods')->where("user_id", $user_id)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');  //获取商品ID
        if (!empty($goods_id_arr)){
            $goodsList = M('goods')->where("goods_id", "in", implode(',', $goods_id_arr))->getField('goods_id,goods_name');
        }
         foreach ($list as $key => $value) {
            $list[$key]['goodsname']=$goodsList[$value['goods_id']];
            $list[$key]['addtime']=date('Y-m-d H:i:s', $value['addtime']);
            // $list[$key]['imgs']=goods_thum_images($value['goods_id'],400,400,'red');
            if (strstr(goods_thum_images($value['goods_id'],400,400),'http')) {
                    $list[$key]['imgs']=goods_thum_images($value['goods_id'],400,400);
                }else{
                    if (!empty(goods_thum_images($value['goods_id'],400,400))) {
                        $list[$key]['imgs']=URL.goods_thum_images($value['goods_id'],400,400);
                    }
                }
        }
        $state = C('REFUND_STATUS');
        $return_list['list']=$list;
        $return_list['state']=$state;
        $page= object_to_array($page);
        $return_list['pages']['totalPages']=$page['totalPages'];
        return formt($return_list);
       
    }

    /**
     *  退货详情
     */
    public function return_goods_info()
    {
         $id = I('id/d', '0');
        $return_goods = M('return_goods')->where("id = $id")->find();

        if($return_goods['suppliers_id']){
            $region_list = get_region_list();
            $suppliers_info = get_suppliers_info($return_goods['suppliers_id']);
           
            $return_info['address'] = $region_list[$suppliers_info['post_province']].$region_list[$suppliers_info['post_city']].$suppliers_info['post_address'];
            $return_info['phone'] = $suppliers_info['post_mobile'];
            $return_info['post_consignee'] = $suppliers_info['post_consignee'];
        }else{
             $return_info['address'] = tpCache('shop_info.address');
             $return_info['phone'] = tpCache('shop_info.phone');
        }
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        if ($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', URL.$return_goods['imgs']);

        $return_goods['addtime'] =date('Y-m-d H:i:s', $return_goods['addtime']);
         if ($return_goods['type']=='0') {
            $return_goods['type'] ='退款';
        }else if($return_goods['type']=='1'){
            $return_goods['type'] ='退货退款';
        }else{
            $return_goods['type'] ='换货';
        }
        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->field('goods_id,shop_price,goods_name')->find();
        if (strstr(goods_thum_images($goods['goods_id'],200,200),'http')) {
             $goods["goodsimg"] = goods_thum_images($goods['goods_id'],200,200);
        }else{
             $goods["goodsimg"] = URL.goods_thum_images($goods['goods_id'],200,200);
        }
        $state = C('REFUND_STATUS');
        $return_info['state']=$state[$return_goods['status']];
        $return_info['goods']=$goods;
        $return_info['return_goods']=$return_goods;
        return formt($return_info);
    }

   

    /**
     * 取消售后服务
     * @author lxl
     * @time 2017-4-19
     */
    public function return_goods_cancel(){
        $id = I('id/d',0);
        $user_id = I('user_id',0);

        if(empty($id))return formt('',201,'参数错误');
        $return_goods = M('return_goods')->where(array('id'=>$id,'user_id'=>$user_id))->find();
        if(empty($return_goods)) return formt('',201,'参数错误');
        M('return_goods')->where(array('id'=>$id))->save(array('status'=>-2,'canceltime'=>time()));
        return formt('',200,'取消成功');
    }
    /**
     * 换货商品确认收货
     * @author lxl
     * @time  17-4-25
     * */
    public function receiveConfirm(){
        $return_id=I('return_id/d');
        $return_info=M('return_goods')->field('order_id,order_sn,goods_id,spec_key')->where('id',$return_id)->find(); //查找退换货商品信息
        $update = M('return_goods')->where('id',$return_id)->save(['status'=>3]);  //要更新状态为已完成
        if($update) {
            M('order_goods')->where(array(
                'order_id' => $return_info['order_id'],
                'goods_id' => $return_info['goods_id'],
                'spec_key' => $return_info['spec_key']))->save(['is_send' => 2]);  //订单商品改为已换货
            return formt($id,200,'操作成功');
        }
        return formt('',201,'操作失败');
    }

    /**
     *  评论晒单列表
     * @return mixed
     */
    public function comment()
    {
        $user_id = I('get.user_id');
        $status = I('get.status');
        $logic = new \app\common\logic\CommentLogic;
        $result = $logic->getComment($user_id, $status); //获取评论列表
        foreach ($result['result'] as $k=>$row){
            $result['result'][$k]["add_time"] = date('Y-m-d H:i:s', $row['add_time']);;
            $result['result'][$k]["suppliers_phone"] = suppliersphone($row['suppliers_id']);
            $result['result'][$k]['shipping_time'] = date("Y-m-d H:i:s", $row['shipping_time']);
            if (strstr(goods_thum_images($row['goods_id'],400,400),'http')) {
                     $result['result'][$k]["goodsimg"] = goods_thum_images($row['goods_id'],400,400);
                }else{
                     $result['result'][$k]["goodsimg"] = URL.goods_thum_images($row['goods_id'],400,400);
                }
            
        }
        return formt($result['result']);
        
    }

    /**
     *添加评论
     */
    public function add_comment()
    {
        if (I('goods_id')) {

            $img=$this->uploadcommentGoodsImg();
            if ($img['status']=='1') {
                $add['img'] = serialize($img['result']);
                // $add['img'] = $img['result'];
            }

            $logic = new UsersLogic();
            $add['goods_id'] = I('goods_id');
            $hide_username = I('hide_username');
            if (empty($hide_username)) {
                $add['username']=I('username');
            }
            $add['is_anonymous'] = $hide_username;  //是否匿名评价:0不是\1是
            $add['order_id'] = I('order_id/d');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            $add['is_show'] = 1; //默认显示
            $add['content'] = I('content');
            $add['add_time'] = time();
            $add['ip_address'] = request()->ip();
            $add['user_id'] = I('user_id');

            //添加评论
            $row = $logic->add_comment($add);
            if ($row['status'] == 1) {
                return formt('',200,'评论成功');
                exit();
            } else {
                return formt('',201,$row['msg']);
            }
        }
        $rec_id = I('rec_id/d');
        $order_goods = M('order_goods')->where("rec_id", $rec_id)->find();
        if (strstr(goods_thum_images($order_goods['goods_id'],400,400),'http')) {
             $order_goods["goodsimg"] = goods_thum_images($order_goods['goods_id'],400,400);
        }else{
             $order_goods["goodsimg"] = URL.goods_thum_images($order_goods['goods_id'],400,400);
        }
        return formt($order_goods);
    }



    /**
     *添加评论详情信息
     */
    public function ajaxcomment()
    {
        $rec_id = I('rec_id/d');
        $user_id = I('user_id/d');
        $order_goods = M('order_goods')->where("rec_id", $rec_id)->find();
        if (strstr(goods_thum_images($order_goods['goods_id'],400,400),'http')) {
             $order_goods["goodsimg"] = goods_thum_images($order_goods['goods_id'],400,400);
        }else{
             $order_goods["goodsimg"] = URL.goods_thum_images($order_goods['goods_id'],400,400);
        }

        if (strstr($order_goods['img'],'http')) {
             $order_goods["img"] = $order_goods['img'];
        }else{
             $order_goods["img"] = URL.$order_goods['img'];
        }
        $order_goods['nickname']=M('users')->where('user_id', $user_id)->getField('nickname');
        if($order_goods['nickname'] == ''){
            $order_goods['nickname'] = M('users')->where('user_id', $user_id)->getField('mobile');
        }
        return formt($order_goods);
       
    }
    /**
     * 待收货列表
     * @author lxl
     * @time   2017/1
     */
    public function wait_receive()
    {
        $user_id = I('user_id');
        $where = ' user_id=' . $user_id;
        //条件搜索
        $where .= C(strtoupper('WAITRECEIVE'));
        $count = M('order')->where($where)->count();
        $pagesize = C('PAGESIZE');
        $Page = new Page($count, $pagesize);
        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $data = $model->get_order_goods($v['order_id']);
            foreach ($data['result'] as $keys => $val) {
                 if ($val['original_img']) {
                    $data['result'][$keys]['original_img']=URL.$val['original_img'];
                }
                $data['result'][$keys]['isreturn'] = M('return_goods')->where("order_id", $val['order_id'])->count();
            }
            $order_list[$k]['goods_list'] = $data['result'];

        }

        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = 0;
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
            //订单物流单号
            $invoice_no = M('DeliveryDoc')->where("order_id", $value['order_id'])->getField('invoice_no', true);
            $order_list[$key][invoice_no] = implode(' , ', $invoice_no);
            
        }
        $wait_receive['order_list']=$order_list;
        $wait_receive['page']['totalPages']=$Page->totalPages;
      
        return formt($wait_receive);
      
    }

    /*退换货增加运单号码
    作者：TK
    2018年5月28日11:32:34
    */
    function buy_post_shipping(){
        $user_id = I('post.user_id',0);
        $data['shipping_code'] = I('post.shipping_code/s',0);
        $data['shipping_post_code'] = I('post.shipping_post_code/s');
        $data['shipping_post_remark'] = I('post.shipping_post_remark/s');
        $return_id = I('post.return_id/d');
        if(db('return_goods')->where("user_id = {$user_id} and id = {$return_id}")->find()){
            if($data['shipping_code'] && $data['shipping_post_code']){
                $r = db('return_goods')->where("id = {$return_id}")->update($data);
                if($r){
                    $res['status']  =   1;
                    $res['info']    =   '添加运单号成功！';
                }else{
                    $res['status']  =   0;
                    $res['info']    =   '系统繁忙，请稍后再试！';
                }
            }else{
                $res['status']  =   0;
                $res['info']    =   '请填写运单号码！';
            }
        }else{
            $res['status']  =   0;
            $res['info']    =   '系统繁忙，请稍后再试';
        }
        
        if ($res['status']=="1") {
            return formt('',200,$res['info']);
        }else{
            return formt('',201,$res['info']);
        }

    }




    public function showOrderGood(){
        $id = I('post.order_id');
        $model = new UsersLogic();
        $order_goods = $model->get_order_goods($id);
        $order_confirm['order_goods']=$order_goods;
        exit(formt($order_confirm));
    }

    public function addReturnGoods($rec_id,$order,$img)
    {
        $data = I('post.');
        $confirm_time_config = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            return ['result'=>-1,'msg'=>'已经超过' . ($confirm_time_config ?: 0) . "天内退货时间"];
        }
        $data['imgs'] = $img; //兼容小程序，多传imgs
        $data['addtime'] = time();
        $data['user_id'] = $order['user_id'];
        $order_goods = M('order_goods')->where(array('rec_id'=>$rec_id))->find();
        if($data['type'] < 2){
            //退款申请，若该商品有赠送积分或优惠券，在平台操作退款时需要追回
            $rate = round($order_goods['member_goods_price']*$data['goods_num']/$order['goods_price'],2);
            if($order['order_amount']>0 && $order['order_amount']>$order['shipping_price'] && !empty($order['pay_code'])){
                $data['money']    = $data['refund_money']    = $rate*$order['order_amount'];      //退款金额
                $data['deposit']  = $data['refund_deposit']  = $rate*$order['user_money'];        //该退余额支付部分
                $data['integral'] = $data['refund_integral'] = floor($rate*$order['integral']);   //该退积分支付
            }else{
                if( $order['order_amount']>$order['shipping_price'] )
                    $data['deposit'] = $data['refund_deposit'] = $rate*$order['user_money']+$rate*($order['order_amount'] - $order['shipping_price']); //该退余额支付部分
                else
                    $data['deposit'] = $data['refund_deposit'] = $rate*$order['user_money'];
                
                $data['integral'] = $data['refund_integral'] = floor($rate*$order['integral']);//该退积分支付
            }
        }
        $data['suppliers_id']   =   $order_goods['suppliers_id'];
        $data['order_id']   =   $order_goods['order_id'];
        $data['order_sn']   =   $order_goods['order_sn'];
        $data['goods_id']   =   $order_goods['goods_id'];
        $data['type']   =   I('post.type/d',0);

        if(!empty($data['id'])){
            $result = M('return_goods')->where(array('id'=>$data['id']))->save($data);
        }else{
            $result = M('return_goods')->add($data);
        }

        if($result){
            return ['status'=>1,'msg'=>'申请成功'];
        }
        return ['status'=>-1,'msg'=>'申请失败'];
    }



    /**
     * 上传退换货图片，兼容小程序
     * @return array
     */
    public function uploadReturnGoodsImg()
    {
        $data = $_FILES['file'];
        $tmp = $data['tmp_name'];
        $dir = 'public/upload/return_goods/'.date("Y-m-d")."/";
        if (!($_exists = file_exists($dir))){
            $isMk = mkdir($dir);
        }
        $filename = time().I("user_id/d");
        $thumbPayImg = $dir.$filename.'.jpg';
        $res = move_uploaded_file($tmp, $thumbPayImg);
        // file_put_contents("./public/filepath.txt", $res);
        if($res){
            return formt($thumbPayImg,200,"上传成功");
        }else{
            return formt("",201,"上传失败");
        }
    }


/**
     * 上传退换货图片，兼容小程序
     * @return array
     */
    public function uploadcommentGoodsImg()
    {

        $return_imgs = '';
        if ($_FILES['comment_img_file']['tmp_name']) {
            $files = request()->file("comment_img_file");

            if (is_object($files)) {
                $files = [$files]; //可能是一张图片，小程序情况
            }
            $image_upload_limit_size = config('image_upload_limit_size');
            $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
             $dir = 'public/upload/comment/' . date('Y', time()) . '/' . date('m-d', time());
            if (!($_exists = file_exists($dir))){
                $isMk = mkdir($dir);
            }
            // $parentDir = date('Ymd');
            foreach($files as $key => $file){
                $info = $file->rule('uniqid')->validate($validate)->move($dir, true);
                if($info){
                    $filename = $info->getFilename();
                    $new_name = '/'.$dir.'/'.$filename;
                    $return_imgs[]= $new_name;
                }else{
                    return ['status' => -1, 'msg' => $file->getError()];//上传错误提示错误信息
                }
            }
            if (!empty($return_imgs)) {
                $return_imgs = implode(',', $return_imgs);// 上传的图片文件
            }
        }
        
        return ['status' => 1, 'msg' => '操作成功', 'result' => $return_imgs];
    }
}