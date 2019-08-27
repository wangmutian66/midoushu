<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 */

namespace app\common\logic;

use think\Db;
use think\Cache;
use think\Image;
use think\Validate;
use app\common\model\WxTplMsg;
use app\common\logic\wechat\WechatUtil;

/**
 * 微信公众号的业务逻辑类
 */
class WxLogic
{
    static private $wx_user = null;
    static private $wechat_obj;

    public function __construct($config = null)
    {
        if (!self::$wx_user) {
            if ($config === null) {
                $config = Db::name('wx_user')->find();
            } 
            self::$wx_user = $config;
            self::$wechat_obj = new WechatUtil(self::$wx_user);
        }
    }


    /**
     * 系统默认模板消息
     * @return array
     */
    public function getDefaultTemplateMsg($template_sn = null)
    {
        $templates = [
            [
                "template_sn" => "OPENTM406161651",
                "title" => "下单成功提醒",
                "content" =>
                    "{{first.DATA}}\n\n"
                    ."商家名称：{{keyword1.DATA}}\n"
                    ."下单时间：{{keyword2.DATA}}\n"
                    ."商品明细：{{keyword3.DATA}}\n"
                    ."订单金额：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ],[
                "template_sn" => "OPENTM401992154",
                "title" => "订单支付成功提醒",
                "content" =>
                    "{{first.DATA}}\n\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."支付时间：{{keyword2.DATA}}\n"
                    ."支付金额：{{keyword3.DATA}}\n"
                    ."支付方式：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ],[
                "template_sn" => "OPENTM400925266",
                "title" => "订单取消成功提醒",
                "content" =>
                    "{{first.DATA}}\n\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."订单金额：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}",
            ],[
                "template_sn" => "OPENTM201541214",
                "title" => "订单发货提醒",
                "content" =>
                    "{{first.DATA}}\n\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."快递公司：{{keyword2.DATA}}\n"
                    ."快递单号：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}",
            ],[
                "template_sn" => "OPENTM201743389",
                "title" => "供货商新订单提醒",
                "content" =>
                    "{{first.DATA}}\n\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."订单概要：{{keyword2.DATA}}\n"
                    ."所属会员：{{keyword3.DATA}}\n"
                    ."会员手机：{{keyword4.DATA}}\n"
                    ."配送地址：{{keyword5.DATA}}\n"
                    ."{{remark.DATA}}",
            ],[
                "template_sn" => "OPENTM411340842",
                "title" => "实体店支付成功提醒",
                "content" =>
                    "{{first.DATA}}\n\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."支付时间：{{keyword2.DATA}}\n"
                    ."支付金额：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}",
            ]
        ];

        $templates = convert_arr_key($templates, 'template_sn');

        $valid_sns = ['OPENTM406161651','OPENTM401992154','OPENTM400925266','OPENTM201541214','OPENTM201743389','OPENTM411340842']; //目前支持的模板
        $valid_templates = [];
        foreach ($valid_sns as $sn) {
            if (isset($templates[$sn])) {
                $valid_templates[$sn] = $templates[$sn];
            }
        }

        if ($template_sn) {
            return $valid_templates[$template_sn];
        }
        return $valid_templates;
    }

    /**
     * 配置模板
     * @param $data array 配置
     */
    public function setTemplateMsg($template_sn, $data)
    {
        if (!isset($data['is_use']) && !isset($data['remark'])) {
            return ['status' => -1, 'msg' => '参数为空'];
        }

        $tpls = $this->getDefaultTemplateMsg();
        if (!key_exists($template_sn, $tpls)) {
            return ['status' => -1, 'msg' => "模板消息的编号[$template_sn]不存在"];
        }

        if ($tpl_msg = WxTplMsg::get(['template_sn' => $template_sn])) {
            $tpl_msg->save($data);
        } else {
            if (!$template_id = self::$wechat_obj->addTemplateMsg($template_sn)) {
                return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
            }
            WxTplMsg::create([
                'template_id' => $template_id,
                'template_sn' => $template_sn,
                'title' => $tpls[$template_sn]['title'],
                'is_use' => isset($data['is_use']) ? $data['is_use'] : 0,
                'remark' => isset($data['remark']) ? $data['remark'] : '',
                'add_time' => time(),
            ]);
        }

        return ['status' => 1, 'msg' => '操作成功'];
    }

    /**
     * 重置模板消息
     */
    public function resetTemplateMsg($template_sn)
    {
        if (!$template_sn) {
            return ['status' => -1, 'msg' => '参数不为空'];
        }

        if ($tpl_msg = WxTplMsg::get(['template_sn' => $template_sn])) {
            if ($tpl_msg->template_id) {
                self::$wechat_obj->delTemplateMsg($tpl_msg->template_id);
            }
            $tpl_msg->delete();
        }

        return ['status' => 1, 'msg' => '操作成功'];
    }

    /**
     * 发送模板消息（订单支付成功通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnPaySuccess($order)
    {

        $template_sn = 'OPENTM401992154';
        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();


        if($order && $this->getDefaultTemplateMsg($template_sn) && $tpl_msg && $tpl_msg->template_id && $user && $user['openid']){
            //获取支付时间
            $pay_time = M($order['table_name'])->where("order_id=".$order['order_id'])->value('pay_time');

            if($order['table_name'] == "order_red"){
                $order_amount = $order['midou']."米豆";
                $url = SITE_URL.url('/mobilered/order/order_detail?id='.$order['order_id']);
                $pay_name = "米豆支付";
            }else{
                $order_amount = $order['order_amount']."元";
                $url = SITE_URL.url('/mobile/order/order_detail?id='.$order['order_id']);
                $pay_name = $order['pay_name'];
            }

            $data = [
                'first' => ['value' => '您的订单已支付成功！'],
                'keyword1' => ['value' => $order['order_sn']],
                'keyword2' => ['value' => date('Y-m-d H:i:s',$pay_time)],
                'keyword3' => ['value' => $order_amount],
                'keyword4' => ['value' => $pay_name],
                'remark' => ['value' => $tpl_msg->remark ?: ''],
            ];

            $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        }


    }


    /**
     * 发送模板消息线下换购（订单支付成功通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnPaySuccessstore($order)
    {

        $template_sn = 'OPENTM401992154';
        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();


        if($order && $this->getDefaultTemplateMsg($template_sn) && $tpl_msg && $tpl_msg->template_id && $user && $user['openid']){
            //获取支付时间
            $pay_time = M($order['table_name'])->where("order_id=".$order['order_id'])->value('pay_time');

            if($order['table_name'] == "order_red"){
                $order_amount = $order['midou']."米豆";
                
                    $url = SITE_URL.url('/mobilered/order/order_detail?id='.$order['order_id'].'&store_id='.$order['store_id']);
                
                
            }

            $data = [
                'first' => ['value' => '您的订单已支付成功！'],
                'keyword1' => ['value' => $order['order_sn']],
                'keyword2' => ['value' => date('Y-m-d H:i:s',$pay_time)],
                'keyword3' => ['value' => $order_amount],
                'keyword4' => ['value' => $order['pay_name']],
                'remark' => ['value' => $tpl_msg->remark ?: ''],
            ];
            $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        }

    }

    /**
     * 发送模板消息（订单发货通知）
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgOnDeliver($deliver)
    {

        $template_sn = 'OPENTM201541214';
        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);

        $user = Db::name('oauth_users')->where(['user_id' => $deliver['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();

        if($deliver && $this->getDefaultTemplateMsg($template_sn) && $tpl_msg && $tpl_msg->template_id && $user && $user['openid']){
            $data = [
                'first' => ['value' => "订单{$deliver['order_sn']}发货成功！"],
                'keyword1' => ['value' => $deliver['order_sn']],
                'keyword2' => ['value' => $deliver['shipping_name']],
                'keyword3' => ['value' => $deliver['invoice_no']],
                'remark' => ['value' => $tpl_msg->remark ?: ''],
            ];

            $url = SITE_URL.url('/mobile/order/order_detail?id='.$deliver['order_id']);
            $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        }

    }

    /**
     * 发送模板消息（下单成功提醒）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnSubmitOrder($order)
    {

        $template_sn = 'OPENTM406161651';
        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);

        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();

        if($order && $this->getDefaultTemplateMsg($template_sn) && $tpl_msg && $tpl_msg->template_id && $user && $user['openid']){

            //获取商家名称
            if($order['suppliers_id'] == 0){
                $suppliers_name = '米豆薯商城';
            }else{
                $suppliers_name = M('suppliers')->where("suppliers_id=".$order['suppliers_id'])->value('suppliers_name');
            }

            if($order['red'] == 1){
                $unit = "米豆";
                $good_table = "order_red_goods";
                $url = SITE_URL.url('/mobilered/order/order_detail?id='.$order['order_id']);
            }else{
                $unit = "元";
                $good_table = "order_goods";
                $url = SITE_URL.url('/mobile/order/order_detail?id='.$order['order_id']);
            }

            //获取订单商品明细
            $goods_list = M($good_table)->field('goods_name')->where("order_id=".$order['order_id'])->select();
            $goods_str = "";
            foreach($goods_list as $vd){
                if($goods_str == ''){
                    $goods_str = $vd['goods_name'];
                }else{
                    $goods_str .= ','.$vd['goods_name'];
                }
            }

            $data = [
                'first' => ['value' => "订单{$order['order_sn']}提交成功！"],
                'keyword1' => ['value' => $suppliers_name],
                'keyword2' => ['value' => date('Y-m-d H:i:s',$order['add_time'])],
                'keyword3' => ['value' => $goods_str],
                'keyword4' => ['value' => $order['order_amount'].$unit],
                'remark' => ['value' => $tpl_msg->remark ?: '','color'=>'#ff0000'],
            ];

            $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        }

    }

    /**
     * 发送模板消息（取消订单通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnCancelOrder($order)
    {

        $template_sn = 'OPENTM400925266';
        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);

        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();

        if($order && $this->getDefaultTemplateMsg($template_sn) && $tpl_msg && $tpl_msg->template_id && $user && $user['openid']){
            $data = [
                'first' => ['value' => "您的订单已取消成功！"],
                'keyword1' => ['value' => $order['order_sn']],
                'keyword2' => ['value' => $order['order_amount']."元"],
                'remark' => ['value' => $tpl_msg->remark ?: ''],
            ];

            $url = SITE_URL.url('/mobile/order/order_detail?id='.$order['order_id']);
            $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        }

    }

    /**
     * 发送模板消息（供货商新订单提醒）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnSuppliersOrder($order)
    {

        $template_sn = 'OPENTM201743389';
        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);

        $suppliers_user_id = Db::name('suppliers')->where("suppliers_id=".$order['suppliers_id'])->value('user_id');
        if($suppliers_user_id > 0){
            $suppliers_user = Db::name('oauth_users')->where(['user_id' => $suppliers_user_id, 'oauth' => 'weixin', 'oauth_child' => 'mp'])->field('openid')->find();
            if($order && $this->getDefaultTemplateMsg($template_sn) && $tpl_msg && $tpl_msg->template_id && $suppliers_user && $suppliers_user['openid']){

                if($order['red'] == 1){
                    $unit = "米豆";
                    $good_table = "order_red_goods";
                }else{
                    $unit = "元";
                    $good_table = "order_goods";
                }

                //获取订单商品明细
                $goods_list = M($good_table)->field('goods_name')->where("order_id=".$order['order_id'])->select();
                $goods_str = "";
                foreach($goods_list as $vd){
                    if($goods_str == ''){
                        $goods_str = $vd['goods_name'];
                    }else{
                        $goods_str .= ','.$vd['goods_name'];
                    }
                }

                #获取购买会员信息
                $userinfo = M('users')->field('mobile,nickname')->where("user_id=".$order['user_id'])->find();

                //配送地址
                $province = $this->getRegionName($order['province']);
                $city = $this->getRegionName($order['city']);
                $district = $this->getRegionName($order['district']);
                $full_address = $province.' '.$city.' '.$district.' '. $order['address'];

                $data = [
                    'first' => ['value' => "您有新的订单，请注意查收！"],
                    'keyword1' => ['value' => $order['order_sn']],
                    'keyword2' => ['value' => $goods_str.",".$order['order_amount'].$unit],
                    'keyword3' => ['value' => $userinfo['nickname']],
                    'keyword4' => ['value' => $userinfo['mobile']],
                    'keyword5' => ['value' => $full_address],
                    'remark' => ['value' => $tpl_msg->remark ?: ''],
                ];

                $url = "";
                $return = self::$wechat_obj->sendTemplateMsg($suppliers_user['openid'], $tpl_msg->template_id, $url, $data);

            }

        }

    }

    /**
     * 根据id获取地区名字
     * @param $regionId id
     */
    public function getRegionName($regionId){
        $data = M('region')->where(array('id'=>$regionId))->field('name')->find();
        return $data['name'];
    }


    /**
     * 发送模板消息（线下在实体店扫码微信提醒实体店家）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnStoreOrderPay($order)
    {

        $template_sn = 'OPENTM411340842';
        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);

        $store_id = Db::name('staff')->where("id=".$order['staff_id'])->value('store_id');

        $mobile = Db::name("company")->where("cid=".$store_id)->value("mobile");
        $user = Db::name('bind_store_user')->field('openid')->where("mobile='".$mobile."'")->find();
        $nickname = Db::name('users')->where("user_id=".$order['user_id'])->value('nickname');

        if($order && $this->getDefaultTemplateMsg($template_sn) && $tpl_msg && $tpl_msg->template_id && $user && $user['openid']){
            $data = [
                'first' => ['value' => $nickname."的订单支付成功！"],
                'keyword1' => ['value' => $order['paid_sn']],
                'keyword2' => ['value' => date('Y-m-d H:i:s',$order['create_time'])],
                'keyword3' => ['value' => $order['money']."元"],
                'remark' => ['value' => $tpl_msg->remark ?: ''],
            ];

            $url = "";
            $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        }

    }

}