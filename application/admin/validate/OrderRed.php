<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */
namespace app\admin\validate;
use think\Validate;
class OrderRed extends Validate {
    
    // 验证规则
    protected $rule = [
        ['consignee','require','收货人称必须填写'],
        ['address', 'require', '地址必须填写'],      
    ];    
}