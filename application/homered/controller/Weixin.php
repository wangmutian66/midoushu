<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\homered\controller;

use app\common\logic\WechatLogic;

class Weixin
{
    /**
     * 处理接收推送消息
     */
    public function index()
    {
        $logic = new WechatLogic;
        $logic->handleMessage();
    }
    
}