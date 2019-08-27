<?php
/**
 * Created by PhpStorm.
 * User: 王牧田
 * Date: 2018/10/10
 * Time: 10:57
 */
namespace app\appletred_app\controller;
use think\Request;
use app\common\logic\JssdkLogic;

class StoreIndex extends MobileBase{


    /**
     * [实体店铺]
     * @author 王牧田
     * @date 2018-10-08
     * @return mixed
     */
    public function index(){
        $store_id = session("store_id");       
        if(empty($store_id)){
            session("store_id","-1");
        }

        $weixin_config = M('wx_user')->find(); //获取微信配置
        $jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
        $signPackage = $jssdk->getSignPackage();
        $this->assign('signPackage',$signPackage);
        return $this->fetch();
    }


    /**
     * [实体店列表]
     * @author 王牧田
     * @date 2018-10-08
     */
    public function stroelist(){
        $post = I('post.');

        $p  = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
        if(isset($post['cid'])){
            $where['cat_id'] = $post['cid'];
        }
        if(isset($post['pro'])){
            $where['province_id'] = $post['pro'];
        }

        $lat = "45.752407";
        $lng = "126.710147";
        $search_text = $post['search_text'];
        $where['parent_id'] = ["neq",0];
        if($search_text!=""){
            $where['cname'] = ['like','%'.$search_text.'%'];
        }
        $sort = $post['sort'];

        if($sort == 1){
            $order = "cid desc";
        }else{
            $order = "juli asc";
        }

        $company = db('company')
            ->field("cid,cname,lng,lat,ROUND(6378.138*2*ASIN(SQRT(POW(SIN(($lat*PI()/180-lat*PI()/180)/2),2)+COS($lat*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng*PI()/180-lng*PI()/180)/2),2)))*1000) AS juli,litpic,address,cat_id")
            //->where($where)
            ->order($order)
            ->page($p,$size)
            // ->fetchsql(true)
            ->select();
        
        foreach ($company as $k=>$row){
            $cc_name = db('company_category')->where(['id'=>$row['cat_id']])->value("cc_name");
            $url = U('/Mobilered/StoreIndex/info',array('cid'=>$row['cid']));
            $company[$k]['url'] = $url;
            $company[$k]['cc_name'] = empty($cc_name)?"":$cc_name;
            $company[$k]['litpic'] = empty($row['litpic'])?'/public/images/icon_goods_thumb_empty_300.png':$row['litpic'];
            if($row['lng']=="" && $row['lat']==""){
                $company[$k]['julicon'] = "";
            }else if($row['juli'] < 1000){
                $company[$k]['julicon']=round($row['juli'],2)."m";
            }else{
                $company[$k]['julicon']=round($row['juli']/1000,2)."km";
            }
        }

        return json_encode($company);

    }

    /**
     * [实体店详情页]
     * @author 王牧田
     * @date 2018-10-09
     * @return mixed
     */
    public function info($cid){
        $company = db('company')->where(['cid'=>$cid])->find();
        $weixin_config = M('wx_user')->find(); //获取微信配置
        $jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
        $signPackage = $jssdk->getSignPackage();

        $p  = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
        //$store_id  = empty($_REQUEST['store_id']) ? 0 : $_REQUEST['store_id'];
        $session_store_id = session("store_id");
        session("store_id",$cid);
//        if(empty($session_store_id) || $session_store_id=="a"){
//
//        }
//        dump(session("store_id"));
//        exit();
        $goods_id = db('store_goods_stock')->where(['store_id'=>$cid])->column("goods_id");
        $where['goods_id']=['in',$goods_id];
        $where['is_on_sale']=1;
        $where['is_check']=1;
        $where['exchange_integral']=0;
        $good_list = db('goods_red')->where($where)->page($p,$size)->select();
        foreach ($good_list as $k => $val) {
            // 米豆换算
            $midouInfo = getMidou($val['goods_id']);
            $good_list[$k]['midou']       = $midouInfo['midou'];
            $good_list[$k]['midou_money'] = $midouInfo['midou_money'];
            $good_list[$k]['midou_index'] = $midouInfo['midou_index'];
        }


        $this->assign('goods_list',$good_list);

        $this->assign('cid',$cid);
        $this->assign('signPackage',$signPackage);
        $this->assign("company",$company);
        return $this->fetch();
    }


    /**
     * [通过经纬度获取当前店铺的距离]
     * @author 王牧田
     * @date 2018-10-10
     */
    public function getjuli(){
        CONTROLLER_NAME;
        $lat = I('post.latitude');
        $lng = I('post.longitude');
        $cid = I('post.cid');
        $where['cid'] = $cid;

        $company = db('company')
            ->field("cid,cname,lng,lat,ROUND(6378.138*2*ASIN(SQRT(POW(SIN(($lat*PI()/180-lat*PI()/180)/2),2)+COS($lat*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng*PI()/180-lng*PI()/180)/2),2)))*1000) AS juli,litpic,address")
            ->where($where)
            ->find();
        if($company['lng'] == "" && $company['lat'] == ""){
            $company['julicon'] = "";
        }else if($company['juli'] < 1000){
            $company['julicon']=round($company['juli'],2)."m";
        }else{
            $company['julicon']=round($company['juli']/1000,2)."km";
        }
        return json_encode($company);
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