<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\mobilered\controller;

use app\common\model\TeamRedFound;
use app\common\logic\RedUsersLogic;
use app\common\logic\RedOrderLogic;
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
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            $this->assign('user_id', $this->user_id);
        } else {
            header("location:" . U('User/login'));
            exit;
        }
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
        $where = ' user_id=' . $this->user_id;
        $store_id = I('get.store_id');

        //条件搜索
        if(I('get.type')){
            $where .= C(strtoupper(I('get.type')));
        }
        $where.=' and order_prom_type < 5 ';//虚拟订单和拼团订单不列出来
        $count = M('order_red')->where($where)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $order_str = "order_id DESC";
        if($store_id){
            $where.=' and is_store = 1';
        }else{
            //$where.=' and is_store = 0 ';
        }


        $order_list = M('order_red')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        

        $store_ids =  array_column($order_list,'store_id'); //获取二维数组某一列的数据
        $store_ids = array_unique($store_ids);  //去除重复
        $cname = db('company')->where(["cid"=>["in",$store_ids]])->column("cname","cid");

        //获取订单商品
        $model = new RedUsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
            $order_list[$k]['cname'] = $cname[$v['store_id']];
        }

        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = 0;
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
        }


        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('page', $show);
        $this->assign('lists', $order_list);
        $this->assign('active', 'order_list');
        $this->assign('active_status', I('get.type'));
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_order_list');
            exit;
        }
        return $this->fetch();
    }
    //拼团订单列表
    public function team_list(){
        $type = input('type');
        $Order = new \app\common\model\OrderRed();
        $order_where = ['order_prom_type' => 6, 'user_id' => $this->user_id, 'deleted' => 0,'pay_code'=>['<>','cod']];//拼团基础查询
        switch (strval($type)) {
            case 'WAITPAY':
                //待支付订单
                $order_where['pay_status'] = 0;
                $order_where['order_status'] = 0;
                break;
            case 'WAITTEAM':
                //待成团订单
                $found_order_id  = Db::name('team_red_found')->where(['user_id'=>$this->user_id,'status'=>1])->getField('order_id',true);//团长待成团
                $follow_order_id = Db::name('team_red_follow')->where(['found_user_id'=>$this->user_id,'status'=>1])->getField('order_id',true);//团员待成团
                $team_order_id   = array_merge($found_order_id,$follow_order_id);
                if (count($team_order_id) > 0) {
                    $order_where['order_id'] = ['in', $team_order_id];
                }
                break;
            case 'WAITSEND':
                //待发货
                $order_where['pay_status'] = 1;
                $order_where['shipping_status'] = ['<>',1];
                $order_where['order_status'] = ['in','0,1'];
                break;
            case 'WAITRECEIVE':
                //待收货
                $order_where['shipping_status'] = 1;
                $order_where['order_status'] = 1;
                break;
            case 'WAITCCOMMENT':
                //已完成
                $order_where['order_status'] = 2;
                break;
        }
        $request = Request::instance();
        $order_count = $Order->where($order_where)->count();
        $page = new Page($order_count, 10);
        $order_list = $Order->with('orderRedGoods')->where($order_where)->limit($page->firstRow . ',' . $page->listRows)->order('order_id desc')->select();
        $this->assign('order_list',$order_list);
        if ($request->isAjax()) {
            return $this->fetch('ajax_team_list');
//            $this->ajaxReturn(['status'=>1,'msg'=>'获取成功','result'=>$order_list]);
        }
        return $this->fetch();
    }

    public function team_detail(){
        $order_id = input('order_id');
        $Order = new \app\common\model\OrderRed();
        $TeamFound = new TeamRedFound();
        $order_where = ['order_prom_type' => 6, 'order_id' => $order_id, 'deleted' => 0];
        $order = $Order->with('orderRedGoods')->where($order_where)->find();
        if (empty($order)) {
            $this->error('该订单记录不存在或已被删除');
        }
        $orderTeamFound = $order->teamFound;
        if ($orderTeamFound) {
            //团长的单
            $this->assign('orderTeamFound', $orderTeamFound);//团长
        } else {
            //去找团长
            $teamFound = $TeamFound::get(['found_id' => $order->teamFollow['found_id']]);
            $this->assign('orderTeamFound', $teamFound);//团长
        }
        $this->assign('order', $order);
        return $this->fetch();
    }

    /**
     * 订单详情
     * @return mixed
     */
    public function order_detail()
    {
        $id = I('get.id/d');
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order_red')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        $model = new RedUsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        foreach ($data['result'] as $k=>$row){
            $data['result'][$k]["suppliers_phone"] = db('suppliers')->where(["suppliers_id"=>$row['suppliers_id']])->value("suppliers_phone");
        }
        $order_info['goods_list'] = $data['result'];

        //$order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] -$order_info['coupon_price'] - $order_info['discount'];

        $region_list = get_region_list();
        $invoice_no = M('DeliveryRedDoc')->where("order_id", $id)->getField('invoice_no', true);
        $order_info[invoice_no] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_red_action')->where(array('order_id' => $id))->select();
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('region_list', $region_list);
        $this->assign('order_info', $order_info);
        $this->assign('order_action', $order_action);


        //快递查询START
        #2018-11-28  张洪凯  优化
        $res = syncDelivery($id,1);
        $this->assign('wuliudata',$res['log']);
        //快递查询END

        if (I('waitreceive')) {  //待收货详情
            return $this->fetch('wait_receive_detail');
        }
        return $this->fetch();
    }

    /**
     * 物流跟踪
     * @return mixed
     */
    public function get_delivery()
    {
        $id = I('get.order_id/d');
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order_red')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }


        //快递查询START
        #张洪凯  2018-11-27
        $rst = M('delivery_red_log')->where("order_id=".$id)->find();
        $res = syncDelivery($id,1);
        //快递查询END

        $this->assign('kdata',$rst);
        $this->assign('wuliudata',$res['log']);
        $this->assign('order_info',$order_info);
        $this->assign('wuliuarr',config('delivery')['wuliuarr']);


        return $this->fetch();
    }

    /**
    * 取消订单
    */
    public function cancel_order()
    {
        $id = I('get.id/d');

        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order_red')->field('order_status,shipping_status,pay_status')->where($map)->find();

        if($order_info['shipping_status'] == 1){
            $data = array('msg'=>'该订单已发货，不支持取消');
            $this->ajaxReturn($data);
            exit();
        }
        if($order_info['order_status'] == 2){
            $data = array('msg'=>'该订单已收货，不支持取消');
            $this->ajaxReturn($data);
            exit();
        }
        if($order_info['order_status'] == 3){
            $data = array('msg'=>'该订单已取消');
            $this->ajaxReturn($data);
            exit();
        }
        if($order_info['order_status'] == 4){
            $data = array('msg'=>'该订单已完成，不支持取消');
            $this->ajaxReturn($data);
            exit();
        }

        $logic = new RedOrderLogic();
        $data = $logic->cancel_order($this->user_id, $id);
        $this->ajaxReturn($data);
    }
    /**
     * 确定收货成功
     */
    public function order_confirm()
    {
        $id = I('id/d', 0);
        $data = confirm_order_red($id, $this->user_id);

        if(request()->isAjax()){
            $this->ajaxReturn($data);
        }
        if ($data['status'] != 1) {
            $this->error($data['msg'],U('Mobilered/Order/order_list'));
        } else {
            $model = new RedUsersLogic();
            $order_goods = $model->get_order_goods($id);
            $this->assign('order_goods', $order_goods);
            return $this->fetch();
            exit;
        }
    }
    //订单支付后取消订单
    public function refund_order()
    {
        $order_id = I('get.order_id/d');

        $order = M('order_red')
            ->field('order_id,consignee,mobile,pay_code,pay_name,user_money,integral_money,coupon_price,order_amount')
            ->where(['order_id' => $order_id, 'user_id' => $this->user_id])
            ->find();

        if($order['order_status'] == 2){
            $this->error('该订单已收货，不支持申请售后');
            exit();
        }
        if($order['order_status'] == 3){
            $this->error('该订单已取消');
            exit();
        }
        if($order['order_status'] == 4){
            $this->error('该订单已完成，不支持申请售后');
            exit();
        }
        $this->assign('user',  $this->user);
        $this->assign('order', $order);
        return $this->fetch();
    }
    //申请取消订单
    public function record_refund_order()
    {
        $order_id   = input('post.order_id', 0);
        $user_note  = input('post.user_note', '');
        $consignee  = input('post.consignee', '');
        $mobile     = input('post.mobile', '');
        //$store_id     = input('post.store_id', '');
        
        $logic = new \app\common\logic\RedOrderLogic;
        $return = $logic->recordRefundOrder($this->user_id, $order_id, $user_note, $consignee, $mobile);
        if($return['status'] == 1){
            $this->ajaxReturn(['status' => 1, 'msg' => '取消成功']);
        }else{
            $this->ajaxReturn($return);
        }
    }

    /**
     * 申请退货
     */
    public function return_goods()
    {
        $rec_id = I('rec_id',0);
        $return_goods = M('return_red_goods')->where(array('rec_id'=>$rec_id))->find();
        if(!empty($return_goods))
        {
            //$this->error('已经提交过退货申请!',U('Order/return_goods_info',array('id'=>$return_goods['id'])));
            $this->error('已经提交过退货申请!');
        }

        $order_goods = M('order_red_goods')->where(array('rec_id'=>$rec_id))->find();
        $order = M('order_red')->where(array('order_id'=>$order_goods['order_id'],'user_id'=>$this->user_id))->find();

        if($order['order_status'] == 2){
            $this->error('该订单已收货，不支持申请售后');
            exit();
        }
        if($order['order_status'] == 3){
            $this->error('该订单已取消');
            exit();
        }
        if($order['order_status'] == 4){
            $this->error('该订单已完成，不支持申请售后');
            exit();
        }

        if($order_goods['suppliers_id']){
            $region_list = get_region_list();
            $this->assign('region_list',$region_list);
            $suppliers_info = get_suppliers_info($order_goods['suppliers_id']);
            $this->assign('suppliers_info', $suppliers_info);
        }


        $confirm_time_config = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            $this->error('已经超过' . $confirm_time_config . "天内退货时间");
            // return ['result'=>-1,'msg'=>'已经超过' . $confirm_time_config . "天内退货时间"];
        }
        if(empty($order))$this->error('非法操作');

        if($order_goods['suppliers_id']){
            $region_list = get_region_list();
            $this->assign('region_list',$region_list);
            $suppliers_info = get_suppliers_info($order_goods['suppliers_id']);
            $this->assign('suppliers_info', $suppliers_info);
        }

        if(IS_POST)
        {
            /* dump($_POST);
            die;*/
            $model = new RedOrderLogic();
            $res = $model->addReturnGoods($rec_id,$order);  //申请售后
            if($res['status']==1)$this->success($res['msg'],U('Order/return_goods_list'));
            $this->error($res['msg']);
        }
        $region_id[] = tpCache('shop_info.province');
        $region_id[] = tpCache('shop_info.city');
        $region_id[] = tpCache('shop_info.district');
        $region_id[] = 0;
        $return_address = M('region')->where("id in (".implode(',', $region_id).")")->getField('id,name');

        $this->assign('return_address', $return_address);
        $this->assign('goods', $order_goods);
        $this->assign('order',$order);
        return $this->fetch();
    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
        //退换货商品信息
        $count = M('return_red_goods')->where("user_id", $this->user_id)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('return_red_goods')->where("user_id", $this->user_id)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');  //获取商品ID
        if (!empty($goods_id_arr)){
            $goodsList = M('goods_red')->where("goods_id", "in", implode(',', $goods_id_arr))->getField('goods_id,goods_name');
        }
        $state = C('REFUND_STATUS');
        $this->assign('goodsList', $goodsList);
        $this->assign('list', $list);
        $this->assign('state',$state);
        $this->assign('page', $page->show());// 赋值分页输出
        if (I('is_ajax')) {
            return $this->fetch('ajax_return_goods_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        $id = I('id/d', 0);
        $return_goods = M('return_red_goods')->where("id = $id")->find();

        if($return_goods['suppliers_id']){
            $region_list = get_region_list();
            $this->assign('region_list',$region_list);
            $suppliers_info = get_suppliers_info($return_goods['suppliers_id']);
            $this->assign('suppliers_info', $suppliers_info);
        }

        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        if ($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $goods = M('goods_red')->where("goods_id = {$return_goods['goods_id']} ")->find();
        // 米豆换算
        $midouInfo = getMidou($goods['goods_id']);
        $goods['midou']       = $midouInfo['midou'];
        $goods['midou_money'] = $midouInfo['midou_money'];
        $goods['midou_index'] = $midouInfo['midou_index'];

        $state = C('REFUND_STATUS');
        $this->assign('state',$state);
        $this->assign('goods', $goods);
        $this->assign('return_goods', $return_goods);
        return $this->fetch();
    }

    public function return_goods_refund()
    {
        $order_sn = I('order_sn');
        $where = array('user_id'=>$this->user_id);
        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        $where['status'] = 5;
        $count = M('return_red_goods')->where($where)->count();
        $page = new Page($count,10);
        $list = M('return_red_goods')->where($where)->order("id desc")->limit($page->firstRow, $page->listRows)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr))
            $goodsList = M('goods_red')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');
        $this->assign('goodsList', $goodsList);
        $state = C('REFUND_STATUS');
        $this->assign('list', $list);
        $this->assign('state',$state);
        $this->assign('page', $page->show());// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 取消售后服务
     * @author lxl
     * @time 2017-4-19
     */
    public function return_goods_cancel(){
        $id = I('id',0);
        if(empty($id))$this->error('参数错误');
        $return_goods = M('return_red_goods')->where(array('id'=>$id,'user_id'=>$this->user_id))->find();
        if(empty($return_goods)) $this->error('参数错误');
        M('return_red_goods')->where(array('id'=>$id))->save(array('status'=>-2,'canceltime'=>time()));
        $this->success('取消成功',U('Order/return_goods_list'));
        exit;
    }
    /**
     * 换货商品确认收货
     * @author lxl
     * @time  17-4-25
     * */
    public function receiveConfirm(){
        $return_id=I('return_id/d');
        $return_info=M('return_red_goods')->field('order_id,order_sn,goods_id,spec_key')->where('id',$return_id)->find(); //查找退换货商品信息
        $update = M('return_red_goods')->where('id',$return_id)->save(['status'=>3]);  //要更新状态为已完成
        if($update) {
            M('order_red_goods')->where(array(
                'order_id' => $return_info['order_id'],
                'goods_id' => $return_info['goods_id'],
                'spec_key' => $return_info['spec_key']))->save(['is_send' => 2]);  //订单商品改为已换货
            $this->success("操作成功", U("Order/return_goods_info", array('id' => $return_id)));
        }
        $this->error("操作失败");
    }

    /**
     *  评论晒单
     * @return mixed
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status');
        $logic = new \app\common\logic\RedCommentLogic;
        $result = $logic->getComment($user_id, $status); //获取评论列表
        $this->assign('comment_list', $result['result']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_comment_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *添加评论
     */
    public function add_comment()
    {
        if (IS_POST) {
            // 晒图片
            $files = request()->file('comment_img_file');
            $save_url = 'public/upload/comment/' . date('Y', time()) . '/' . date('m-d', time());
            foreach ($files as $file) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $image_upload_limit_size = config('image_upload_limit_size');
                $info = $file->rule('uniqid')->validate(['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img[] = '/'.$save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($file->getError());
                }
            }
            if (!empty($comment_img)) {
                $add['img'] = serialize($comment_img);
            }

            $user_info = session('user');
            $logic = new RedUsersLogic();
            $add['goods_id'] = I('goods_id/d');
            $add['email'] = $user_info['email'];
            $hide_username = I('hide_username');
            if (empty($hide_username)) {
                $add['username'] = $user_info['nickname'];
            }
            $add['is_anonymous'] = $hide_username;  //是否匿名评价:0不是\1是
            $add['order_id'] = I('order_id/d');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            $add['is_show'] = 1; //默认显示
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['add_time'] = time();
            $add['ip_address'] = request()->ip();
            $add['user_id'] = $this->user_id;

            //添加评论
            $row = $logic->add_comment($add);
            if ($row['status'] == 1) {
                $this->success('评论成功', U('/Mobilered/Order/comment', array('status'=>1)));
                exit();
            } else {
                $this->error($row['msg']);
            }
        }
        $rec_id = I('rec_id/d');
        $order_goods = M('order_red_goods')->where("rec_id", $rec_id)->find();
        $this->assign('order_goods', $order_goods);
        return $this->fetch();
    }

    /**
     * 待收货列表
     * @author lxl
     * @time   2017/1
     */
    public function wait_receive()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索
        if (I('type') == 'WAITRECEIVE') {
            $where .= C(strtoupper(I('type')));
        }
        $count = M('order_red')->where($where)->count();
        $pagesize = C('PAGESIZE');
        $Page = new Page($count, $pagesize);
        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order_red')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //获取订单商品
        $model = new RedUsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
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
            $invoice_no = M('DeliveryRedDoc')->where("order_id", $value['order_id'])->getField('invoice_no', true);
            $order_list[$key][invoice_no] = implode(' , ', $invoice_no);
        }
        $this->assign('page', $show);
        $this->assign('order_list', $order_list);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_wait_receive');
            exit;
        }
        return $this->fetch();
    }

    /*退换货增加运单号码
    作者：TK
    2018年5月28日11:32:34
    */
    function buy_post_shipping(){
        $data['shipping_code'] = I('post.shipping_code/s',0);
        $data['shipping_post_code'] = I('post.shipping_post_code/s');
        $data['shipping_post_remark'] = I('post.shipping_post_remark/s');
        $return_id = I('post.return_id/d');
        if(db('return_red_goods')->where("user_id = {$this->user_id}")->cache(true)->find($return_id)){
            if($data['shipping_code'] && $data['shipping_post_code']){
                $r = db('return_red_goods')->where("id = {$return_id}")->update($data);
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
        
        $this->ajaxReturn($res);
    }
    

    /**
    * 二维码
    */
    public function erweima()
    {
           
        $weixin =  M('wx_user')->find();
        $openid = M('oauth_users')->where('user_id',$this->user_id)->find();
        $access_token = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$weixin[appid]&secret=$weixin[appsecret]";
        $access_msg = json_decode(file_get_contents($access_token));
        $token = $access_msg->access_token;
        $subscribe_msg = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$token&openid=$openid[openid]";
        $subscribe = json_decode(file_get_contents($subscribe_msg));
        $subscribe = $subscribe->subscribe;
         
        $this->ajaxReturn($subscribe);
      
        
    }
}