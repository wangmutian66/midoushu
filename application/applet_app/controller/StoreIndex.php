<?php
/**
 * Created by PhpStorm.
 * User: 王牧田
 * Date: 2018/10/10
 * Time: 10:57
 */
namespace app\applet_app\controller;
use think\Request;
use app\common\logic\JssdkLogic;

class StoreIndex extends MobileBase{


    /**
     * [实体店铺]
     * @author 李鑫
     * @date 2019-02-20
     * @return mixed
     */
    public function index(){
//        $store_id = session("store_id");
//        if(empty($store_id)){
//            session("store_id","-1");
//        }

//        $weixin_config = M('wx_user')->find(); //获取微信配置
//        $jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
//        $signPackage = $jssdk->getSignPackage();
//        $this->assign('signPackage',$signPackage);
//        return $this->fetch();
    }


    /**
     * [实体店列表]
     * @author 李鑫
     * @date 2019-02-20
     */
    public function stroelist(){
        $post = I('post.');
        $p  = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
        $where['is_show'] = 1;
        if(isset($post['cat_id']) && $post['cat_id'] != ''){
            $where['cat_id'] = $post['cat_id'];
        }
        if(isset($post['pro']) && $post['pro'] != ''){
            $where['province_id'] = $post['pro'];
        }
        //默认值为东北亚公司
        $lat = input('post.latitude/f','45.752407');
        $lng = input('post.longitude/f','126.710147');
        $where['parent_id'] = ["neq",0];
        if($search_text = input('search_text/s')){
            $where['cname'] = ['like',"%{$search_text}%"];
        }
        $order = "juli asc";
        $where['is_show'] = 1;
        
        //如果加上缓存会导致搜索不好使
        $company = db('company')
            ->field("cid,cname,lng,lat,ROUND(6378.138*2*ASIN(SQRT(POW(SIN(($lat*PI()/180-lat*PI()/180)/2),2)+COS($lat*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng*PI()/180-lng*PI()/180)/2),2)))*1000) AS juli,litpic,address,cat_id")
            ->where($where)
            ->order($order)
        //    ->cache("StoreIndexCache{$user_id}{$p}{$size}")
            ->page($p,$size)
            ->select();
        foreach ($company as $k=>$row){
            $cc_name = db('company_category')->where(['id'=>$row['cat_id']])->value("cc_name");
//          $url = U('/Mobilered/StoreIndex/info',array('cid'=>$row['cid']));
            $company[$k]['cc_name'] = empty($cc_name)?"":$cc_name;
            $company[$k]['litpic'] = empty($row['litpic'])?'/public/images/icon_store_thumb_empty_150.png':$row['litpic'];
            if($row['lng']=="" && $row['lat']==""){
                $company[$k]['julicon'] = "";
            }else if($row['juli'] < 1000){
                $company[$k]['julicon']=round($row['juli'],2)."m";
            }else{
                $company[$k]['julicon']=round($row['juli']/1000,2)."km";
            }
        }
        $advwhere = 'start_time <= '.time().' and end_time >='.time();
        $advmodel= db('ad');
        $advfield = ('ad_id,ad_link,ad_code,pid,start_time,end_time');
        //banner图片
        $banner = $advmodel->where('pid=85')->where($advwhere)->limit(5)->field($advfield)->select();
        $pages['nowPages']=$p;
        exit(formt(['listData'=>$company,'page'=>$pages,'banner'=>$banner]));

    }

    /**
     * [实体店详情页]
     * @author 王牧田
     * @date 2018-10-09
     * @return mixed
     */
    public function info($cid){
        $lat = input('get.latitude/f','45.752407');
        $lng = input('get.longitude/f','126.710147');
        $company = db('company')->field("cid,cname,lng,lat,strore_content,mobile,ROUND(6378.138*2*ASIN(SQRT(POW(SIN(($lat*PI()/180-lat*PI()/180)/2),2)+COS($lat*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng*PI()/180-lng*PI()/180)/2),2)))*1000) AS juli,litpic,address")->where(['cid'=>$cid])->find();
        if($company['lng']=="" && $company['lat']==""){
            $company['julicon'] = "";
        }else if($company['juli'] < 1000){
            $company['julicon']=round($company['juli'],2)."m";
        }else{
            $company['julicon']=round($company['juli']/1000,2)."km";
        }

        $p  = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];

        $store_goods_stock = db('store_goods_stock')->where(['store_id'=>$cid])->field("goods_id,item_id")->select();
        $goods_id=[];
        $result=[];
        foreach ($store_goods_stock as $row){
            $goods_id[]=$row["goods_id"];
            $result[$row["goods_id"]] = $row["item_id"];
        }

        $where['goods_id']=['in',$goods_id];
        $where['is_on_sale']=1;
        $where['is_check']=1;
        $where['exchange_integral']=0;
        $good_list = db('goods_red')->where($where)->page($p,$size)->select();

        foreach ($good_list as $k => $val) {

            // 米豆换算
            $midouInfo = getMidou($val['goods_id'],$result[$val['goods_id']]);
            $good_list[$k]['midou']       = $midouInfo['midou'];
            $good_list[$k]['midou_money'] = $midouInfo['midou_money'];
            $good_list[$k]['midou_index'] = $midouInfo['midou_index'];
        }
        $list ['good_list'] = $good_list;
        $list ['cid'] = $cid;
        $list ['company'] = $company;
        exit(formt($list,200,'操作成功'));
    }




    /**
     * [商品类目]
     * @author 王牧田
     * @date 2018-10-11
     */
    public function categoryList(){
        $sheng = $this->getShen();
        $address = explode("|",$sheng['address']);
        $p = M('region')->where(array('level' => 1,'name'=>['like',"%".$address[1]."%"]))->find();
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province',$province);
        $this->assign('p',$p);
        $companCategory = db('company_category')->select();
        $this->assign('companCategory',$companCategory);
        return $this->fetch();
    }

    /**
     * [商品类目店铺]
     * @author 王牧田
     * @date 2018-10-11
     */
    public function categoryStore(){
        $cid = I('get.cid');
        $pro = I('get.pro');
        $where['cat_id'] = $cid;
        $where['province_id'] = $pro;
        $cc_name = db('company_category')->where(['id'=>$cid])->value('cc_name');
        $company = db('company')->where($where)->select();
        $reginName = M('region')->where(array('parent_id' => 0, 'level' => 1,'id'=>$pro))->value('name');
        $weixin_config = M('wx_user')->find(); //获取微信配置
        $jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
        $signPackage = $jssdk->getSignPackage();
        $this->assign('reginName',empty($reginName)?'':$reginName);
        $this->assign('signPackage',$signPackage);
        $this->assign('cc_name',$cc_name);
        $this->assign("company",$company);
        return $this->fetch();
    }


    public function getShen(){
        $getIp=$_SERVER["REMOTE_ADDR"];
        $content=file_get_contents("http://api.map.baidu.com/location/ip?ak=lnEXnENwerw7FOR9otv8o1AwTUKr3IcS&ip={$getIp}&coor=bd09ll");
        $json=json_decode($content,true);
        return $json;
    }


}