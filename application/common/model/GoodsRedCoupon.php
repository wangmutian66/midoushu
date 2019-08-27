<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */
namespace app\common\model;

use think\Db;
use think\Model;

class GoodsRedCoupon extends Model
{
    public function goods()
    {
        return $this->hasOne('GoodsRed','goods_id','goods_id');
    }
    public function goodsCategory()
    {
        return $this->hasOne('GoodsRedCategory','id','goods_category_id');
    }
}
