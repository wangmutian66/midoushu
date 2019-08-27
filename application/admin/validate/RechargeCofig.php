<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 */

namespace app\admin\validate;

use think\Validate;

/**
 * Description of Article
 *
 * @author Administrator
 */
class RechargeCofig extends Validate
{
    //验证规则
    protected $rule = [
        'account' => 'require|number',
        'orderby' => 'require|number',
        'rec_id'  => 'require',
    ];
    
    //错误消息
    protected $message = [
        'account.require'  => '金额不能为空',
        'account.number'   => '金额必须是数字',
        'orderby.require'  => '不能为空',
        'orderby.number'   => '排序必须是数字',
    ];

    //验证场景
    protected $scene = [
        'add'  => ['account', 'orderby'],
        'edit' => ['account', 'orderby'],
        'del'  => ['rec_id']
    ];
}
