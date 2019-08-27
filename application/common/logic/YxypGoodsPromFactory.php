<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */
namespace app\common\logic;
/**
 * 商品活动工厂类
 * Class CatsLogic
 * @package admin\Logic
 */
class YxypGoodsPromFactory
{
    /**
     * @param $goods|商品实例
     * @param $spec_goods_price|规格实例
     * @return FlashSaleLogic|GroupBuyLogic|PromGoodsLogic
     */
    public function makeModule($goods, $spec_goods_price)
    {
        exit('正在开发,请联系TK');
        switch ($goods['prom_type']) {
            case 1:
                return new YxypFlashSaleLogic($goods, $spec_goods_price);
            case 2:
                return new YxypGroupBuyLogic($goods, $spec_goods_price);
            case 3:
                return new YxypPromGoodsLogic($goods, $spec_goods_price);
            case 6:
                return new YxypTeamActivityLogic($goods, $spec_goods_price);
        }
    }

    /**
     * 检测是否符合商品活动工厂类的使用
     * @param $promType |活动类型
     * @return bool
     */
    public function checkPromType($promType)
    {
        if (in_array($promType, array_values([1, 2, 3, 6]))) {
            return true;
        } else {
            return false;
        }
    }

}
