<?php

/**
 * tpshop
 * ============================================================================
 * 版权所有 2017-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * author: lhb
 * Date: 2017-5-8
 */

namespace app\common\logic\wechat;

use think\Db;
use think\Cache;

/**
 * 说明：此类只进行微信公众号的接口封装，不实现业务逻辑！
 * 接口统一错误返回false，错误信息由getError()获取
 * 业务逻辑请前往 WechatLogic
 */
class WechatUtil extends WxCommon
{
    private $config = [];    //微信公众号配置
    private $send_template_on = 1;  //是否允许发送模板消息提醒

    public function __construct($config = null)
    {
        if ($config === null) {
            $config = Db::name('wx_user')->find();
        }
        $this->config = $config;
    }

    /**
     * 获取access_token
     * @return string
     */
    public function getAccessToken()
    {

        $jssdk = new \app\common\logic\JssdkLogic($this->config['appid'],$this->config['appsecret']);
        $accessToken = $jssdk->get_access_token(1);
        return $accessToken;

    }

    /**
     * 获取用户所有模板消息
     * @return bool|mixed|string
     */
    public function getAllTemplateMsg()
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $url ="https://api.weixin.qq.com/cgi-bin/material/get_all_private_template?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'GET');
        if ($return === false) {
            return false;
        }

        return $return;
    }

    /**
     * 添加消息模板
     * @param $template_sn string 模板编号
     * @return bool|string 模板id
     */
    public function addTemplateMsg($template_sn)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson(['template_id_short' => $template_sn]);
        $url ="https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }
        return $return['template_id'];
    }

    /**
     * 删除模板消息
     * @param $template_id string 模板id
     * @return bool
     */
    public function delTemplateMsg($template_id)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson(['template_id' => $template_id]);
        $url ="https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }
        return true;
    }

    public function sendTemplateMsg($openid, $template_id, $url, $data)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        if(!$this->send_template_on){
            return false;
        }

        $post = $this->toJson([
            "touser" => $openid,
            "template_id" => $template_id,
            "url" => $url, //模板跳转链接
            "data" => $data, //模板数据
//
        ]);
        //注：url和miniprogram都是非必填字段，若都不传则模板无跳转；若都传，会优先跳转至小程序。
        //开发者可根据实际需要选择其中一种跳转方式即可。当用户的微信客户端版本不支持跳小程序时，将会跳转至url

        $url ="https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }
        return true;
    }

}