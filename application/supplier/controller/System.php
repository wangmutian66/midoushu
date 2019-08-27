<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\supplier\controller;
use app\supplier\logic\GoodsLogic;
use think\db;
use think\Cache;
class System extends Base
{

    /**
     * 清空静态商品页面缓存
     */
    public function ClearGoodsHtml(){
        $goods_id = I('goods_id');            
        if(unlink("./Application/Runtime/Html/Home_Goods_goodsInfo_{$goods_id}.html"))
        {
            // 删除静态文件                
            $html_arr = glob("./Application/Runtime/Html/Home_Goods*.html");
            foreach ($html_arr as $key => $val)
            {            
                strstr($val,"Home_Goods_ajax_consult_{$goods_id}") && unlink($val); // 商品咨询缓存
                strstr($val,"Home_Goods_ajaxComment_{$goods_id}") && unlink($val); // 商品评论缓存
            }
            $json_arr = array('status'=>1,'msg'=>'清除成功','result'=>'');
        }
        else 
        {
            $json_arr = array('status'=>-1,'msg'=>'未能清除缓存','result'=>'' );
        }                                                    
        $json_str = json_encode($json_arr);            
        exit($json_str);            
    } 


    public function ClearGoodsRedHtml(){
        $goods_id = I('goods_id');            
        if(unlink("./Application/Runtime/Html/Homered_Goods_goodsInfo_{$goods_id}.html"))
        {
            // 删除静态文件                
            $html_arr = glob("./Application/Runtime/Html/Homered_Goods*.html");
            foreach ($html_arr as $key => $val)
            {            
                strstr($val,"Homered_Goods_ajax_consult_{$goods_id}") && unlink($val); // 商品咨询缓存
                strstr($val,"Homered_Goods_ajaxComment_{$goods_id}") && unlink($val); // 商品评论缓存
            }
            $json_arr = array('status'=>1,'msg'=>'清除成功','result'=>'');
        }
        else 
        {
            $json_arr = array('status'=>-1,'msg'=>'未能清除缓存','result'=>'' );
        }                                                    
        $json_str = json_encode($json_arr);            
        exit($json_str);            
    } 

    /**
     * 商品静态页面缓存清理
     */
      public function ClearGoodsThumb(){
            $goods_id = I('goods_id');
            delFile(UPLOAD_PATH."goods/thumb/".$goods_id); // 删除缩略图
            $json_arr = array('status'=>1,'msg'=>'清除成功,请清除对应的静态页面','result'=>'');
            $json_str = json_encode($json_arr);            
            exit($json_str);            
      } 

    /**
     * 商品静态页面缓存清理
     */
      public function ClearGoodsRedThumb(){
            $goods_id = I('goods_id');
            delFile(UPLOAD_PATH."goods_red/thumb/".$goods_id); // 删除缩略图
            $json_arr = array('status'=>1,'msg'=>'清除成功,请清除对应的静态页面','result'=>'');
            $json_str = json_encode($json_arr);            
            exit($json_str);            
      } 

}