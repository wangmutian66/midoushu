<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\mobileyxyp\controller;
use app\common\logic\JssdkLogic;
use app\common\logic\SearchWordLogic;
use Think\Db;
use app\home\model\AccessLog;
use app\common\model\YxypFlashSale;

class Index extends MobileBase {
    public function __construct()
    {
        parent::__construct();
    }

    public function index(){
        //返回排序
        $sort     = I('sort','yxyp_sort');     // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        $price1    = I('price1','');        // 价钱
        $price2    = I('price2','');        // 价钱

        $this->assign('sort', $sort);
        $this->assign('sort_asc', $sort_asc);
        $this->assign('price1', $price1);
        $this->assign('price2', $price2);
        return $this->fetch('index');
    }


    
    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }
    /**
     * 商品列表页
     */
    public function goodsList(){
        $id = I('get.id/d',0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    
    public function ajaxGetMore(){
    	$p = I('p/d',1);
        $sort  = I('sort','yxyp_sort');     // 排序
        $sort_asc = I('sort_asc','asc');  // 排序
        $price1 = I('price1');        // 价钱
        $price2 = I('price2'); 
         # LX  
        if ($price1 && $price2) {
            $where['shop_price'] =  ['between',"{$price1},{$price2}"];
        }
        $where['is_on_sale'] = ['eq',1];
        $where['is_check'] = ['eq',1];
        $where['is_yxyp'] = ['eq',1];
        
        // $where = ['is_allreturn'=>0, 'is_on_sale'=>1, 'is_tgy_good'=>0, 'is_check'=>1];
    	$favourite_goods = Db::name('goods')
                         ->where($where)
                         ->order("$sort $sort_asc")
                         ->page($p,C('PAGESIZE'))
                         ->cache(true,TPSHOP_CACHE_TIME)
                         ->select();//首页推荐商品
        foreach ($favourite_goods as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $favourite_goods[$k] = $val;
        }
        
    	$this->assign('favourite_goods',$favourite_goods);
    	return $this->fetch();
    }

     
    //微信Jssdk 操作类 用分享朋友圈 JS
    public function ajaxGetWxConfig(){
    	$askUrl = I('askUrl');//分享URL
    	$weixin_config = M('wx_user')->find(); //获取微信配置
    	$jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
    	$signPackage = $jssdk->GetSignPackage(urldecode($askUrl));
    	if($signPackage){
    		$this->ajaxReturn($signPackage,'JSON');
    	}else{
    		return false;
    	}
    }

    /**
     * [手机端访问记录]
     * @author 王牧田
     * @date 2018年8月29日
     * @return mixed
     */
    public function mpublic_log(){
        $url = $_SERVER['HTTP_REFERER'];
        $ip = GetIP();
        $user_id = session('user.user_id');
        $user_id = empty($user_id)?0:$user_id;
        $al_project = new AccessLog();
        $lastal_url = $al_project->where(['al_ip'=>$ip,'user_id'=>$user_id])->order("al_id desc")->value('al_url');
        //->fetchSql(true)
        //判断是否登录 和 刷新后不重复添加数据库
        if($lastal_url !== $url){
            $tolowerurl = strtolower($url);
            //对商品进行处理
            $parram = "/id\/(.*?)\.html/is";
            preg_match_all($parram,$tolowerurl,$result);
            if(!empty($result[1][0])){
                if(strpos($tolowerurl,'mobile/goods') !== false){
                    //现金
                    $alData['al_status'] = 1;
                }else if(strpos($tolowerurl,'mobile/returngoods') !== false){
                    //福利商品
                    $alData['al_status'] = 2;
                }else if(strpos($tolowerurl,'mobilered/goods') !== false) {
                    //米豆
                    $alData['al_status'] = 3;
                }
                $alData['goods_id'] = $result[1][0];
            }

            //搜索内容处理
            $q = I('get.q');
            if(!empty($q)){
                $alData['al_keyword'] = $q;

            }
            $alData['user_id'] = $user_id;
            $alData['al_url'] = $url;
            $alData['create_time'] = time();
            $alData['al_type'] = 1;
            $alData['al_ip'] = $ip;
            $al_project->add($alData);
        }

    }

  
}