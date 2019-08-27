<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 个人学习免费, 如果商业用途务必到TPshop官网购买授权.
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 *
 */ 
namespace app\home\controller;
use app\common\logic\GoodsPromFactory;
use app\common\model\SpecGoodsPrice;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\ActivityLogic;
use think\Page;
use think\Verify;
use think\Image;
use think\Db;
class Index extends Base {
    

    public function _initialize() {

        parent::_initialize();
    }

    public function index(){
        // 如果是手机跳转到 手机模块
        if(isMobile()){
            redirect(U('Mobile/Index/index'));
        }

        // 热销产品 推荐分类 费嘞列表 推荐商品
        $hot_goods = $hot_cate = $cateList = $recommend_goods = array();
        $sql = "select a.goods_name,a.goods_id,a.suppliers_id,a.shop_price,a.market_price,a.cat_id,b.parent_id_path,b.name from ".C('database.prefix')."goods as a left join ";
        $sql .= C('database.prefix')."goods_category as b on a.cat_id=b.id where a.is_allreturn=0 and a.is_hot=1 and a.is_on_sale=1 and a.is_check=1 and a.is_tgy_good=0 order by a.sort";//二级分类下热卖商品
        $index_hot_goods = S('index_hot_goods'); 
        if(empty($index_hot_goods))
        {
            $index_hot_goods = Db::query($sql);//首页热卖商品
            S('index_hot_goods',$index_hot_goods,TPSHOP_CACHE_TIME);
        }
       
        if($index_hot_goods){
            foreach($index_hot_goods as $val){
                $cat_path = explode('_', $val['parent_id_path']);
                $hot_goods[$cat_path[1]][] = $val;
            }
        }
        
        $sql2 = "select a.goods_name,a.goods_id,a.suppliers_id,a.shop_price,a.market_price,a.cat_id,b.parent_id_path,b.name from ".C('database.prefix')."goods as a left join ";
        $sql2 .= C('database.prefix')."goods_category as b on a.cat_id=b.id where a.is_allreturn=0 and a.is_recommend=1 and a.is_on_sale=1 and a.is_check=1 and a.is_tgy_good=0 order by a.sort";//二级分类下热卖商品
        $index_recommend_goods = S('index_recommend_goods'); 
        if(empty($index_recommend_goods))
        {
        	$index_recommend_goods = Db::query($sql2);//首页推荐商品
        	S('index_recommend_goods',$index_recommend_goods,TPSHOP_CACHE_TIME);
        }
         
        if($index_recommend_goods){
        	foreach($index_recommend_goods as $k => $va){
        		$cat_path2 = explode('_', $va['parent_id_path']);
        		$recommend_goods[$cat_path2[1]][] = $va;
        	}
        }

        $hot_category = M('goods_category')->where("is_allreturn=0 and is_hot=1 and level=3 and is_show=1")->cache(true,TPSHOP_CACHE_TIME)->select();//热门三级分类
        foreach ($hot_category as $v){
        	$cat_path = explode('_', $v['parent_id_path']);
        	$hot_cate[$cat_path[1]][] = $v;
        }
        
        foreach ($this->cateTrre as $k=>$v){
            if($v['is_hot']==1){
        		$v['hot_goods']       = empty($hot_goods[$k])       ? '' : $hot_goods[$k];
        		$v['recommend_goods'] = empty($recommend_goods[$k]) ? '' : $recommend_goods[$k];
        		$v['hot_cate']        = empty($hot_cate[$k])        ? '' : $hot_cate[$k];
        		$cateList[]           = $v;
        	}
        }

        // 首页促销产品
        $pro_goods_where['p.start_time']  = array('lt',time());
        $pro_goods_where['p.end_time']  = array('gt',time());
        $pro_goods_where['p.is_end']  = 0;
        $pro_goods_where['g.prom_type']  = 3;
        $pro_goods_where['g.is_on_sale'] = 1;
        $pro_goods_where['g.is_check']   = 1;
        $pro_goods_where['g.is_allreturn'] = 0;
        //$pro_goods_where['g.is_tgy_good'] = 0;
        $procount = Db::name('goods')
            ->field('g.*,p.end_time,s.item_id')
            ->alias('g')
            ->join('__PROM_GOODS__ p', 'g.prom_id = p.id')
            ->join('__SPEC_GOODS_PRICE__ s','g.prom_id = s.prom_id AND s.goods_id = g.goods_id','LEFT')
            ->group('g.goods_id')
            ->where($pro_goods_where)
            ->cache(true)
            ->count();
        $max_num = $procount-6;
        if($max_num < 0)$max_num = 0;
        $startnum = rand(0,$max_num);

        $progoodsList = Db::name('goods')
            ->field('g.*,p.end_time,s.item_id')
            ->alias('g')
            ->join('__PROM_GOODS__ p', 'g.prom_id = p.id')
            ->join('__SPEC_GOODS_PRICE__ s','g.prom_id = s.prom_id AND s.goods_id = g.goods_id','LEFT')
            ->group('g.goods_id')
            ->where($pro_goods_where)
            ->cache(true)
            ->limit($startnum.',6')
            ->select();

        $goodsPromFactory = new GoodsPromFactory();
        foreach ($progoodsList as $k => $val) {
            if ($goodsPromFactory->checkPromType($val['prom_type'])) {
                $goodsPromLogic = $goodsPromFactory->makeModule($val,null);
                if($goodsPromLogic->checkActivityIsAble()){            // 判断活动是否进行中
                    $val = $goodsPromLogic->getActivityGoodsInfo();  // 获取商品转换活动商品的数据
                }
            }
            $progoodsList[$k] = $val;
        }

        // 首页新品推荐
        $new_goods_where['is_on_sale'] = 1;
        $new_goods_where['is_check']   = 1;
        $new_goods_where['is_new']     = 1;
        $new_goods_where['is_allreturn'] = 0;
        $newcount = Db::name('goods')
            ->where($new_goods_where)
            ->limit(20)
            ->cache(true)
            ->count();
        $max_num_new = $newcount-6;
        if($max_num_new < 0)$max_num_new = 0;
        $startnum_new = rand(0,$max_num_new);

        $newgoodsList = Db::name('goods')
            ->where($new_goods_where)
            ->order("new_sort desc")
            ->cache("pc_home_new_goods_list")
            ->limit(4)
            ->select();

        foreach ($newgoodsList as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $newgoodsList[$k] = $val;
        }

        // 首页热销商品
        $hot_goods_where['is_on_sale'] = 1;
        $hot_goods_where['is_check']   = 1;
        $hot_goods_where['is_hot']     = 1;
        $hot_goods_where['is_allreturn'] = 0;
        //$hot_goods_where['is_tgy_good'] = 0;

        $hotgoodsList = Db::name('goods')
            ->where($hot_goods_where)
            ->cache("pc_home_hot_goods_list")
            ->limit(4)
            ->order('hot_sort desc')
            ->select();

        foreach ($hotgoodsList as $k => $val) {
            // 可返米豆
            $midouInfo = returnMidou($val['goods_id']);
            $val['back_midou'] = $midouInfo['midou'];
            $hotgoodsList[$k] = $val;
        }


        // 首页活动专区推荐
        $return_goods_where['is_on_sale']   = 1;
        $return_goods_where['is_check']     = 1;
        $return_goods_where['is_recommend'] = 1;
        $return_goods_where['is_allreturn'] = 1;
        //$return_goods_where['is_tgy_good'] = 0;
        $returncount = Db::name('goods')
            ->where($return_goods_where)
            ->limit(20)
            ->count();
        $max_num_return = $returncount-6;
        if($max_num_return < 0)$max_num_return = 0;
        $startnum_return = rand(0,$max_num_return);

        $returngoodsList = Db::name('goods')
            ->where($return_goods_where)
            ->cache(true)
            ->limit($startnum_return.',6')
            ->select();


        $noticeList = M('article_notice')->where('(article_type = 0 OR article_type = 1) AND is_open = 1')->limit(5)->select();
        $this->assign('noticeList',$noticeList);

        $this->assign('cateList',$cateList);
        $this->assign('progoodsList',$progoodsList);
        $this->assign('newgoodsList',$newgoodsList);
        $this->assign('hotgoodsList',$hotgoodsList);
        $this->assign('returngoodsList',$returngoodsList);


        return $this->fetch();
    }
 
    
    // 二维码
    public function qr_code_raw(){        
        ob_end_clean();
        // 导入Vendor类库包 Library/Vendor/Zend/Server.class.php
        //http://www.tp-shop.cn/Home/Index/erweima/data/www.99soubao.com
        //require_once 'vendor/phpqrcode/phpqrcode.php';
        vendor('phpqrcode.phpqrcode'); 
        //import('Vendor.phpqrcode.phpqrcode');
        error_reporting(E_ERROR);            
        $url = urldecode($_GET["data"]);
        \QRcode::png($url);
		exit;        
    }
    
    // 二维码
    public function qr_code()
    {
        ob_end_clean();
        vendor('topthink.think-image.src.Image');
        vendor('phpqrcode.phpqrcode');

        error_reporting(E_ERROR);
        $url = isset($_GET['data']) ? $_GET['data'] : '';
        $url = urldecode($url);
        $head_pic = input('get.head_pic', '');
        $back_img = input('get.back_img', '');
        $valid_date = input('get.valid_date', 0);
        
        $qr_code_path = './public/upload/qr_code/';
        if (!file_exists($qr_code_path)) {
            mkdir($qr_code_path);
        }
        
        /* 生成二维码 */
        $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';
        \QRcode::png($url, $qr_code_file, QR_ECLEVEL_M);
        
        /* 二维码叠加水印 */
        $QR = Image::open($qr_code_file);
        $QR_width = $QR->width();
        $QR_height = $QR->height();

        /* 添加背景图 */
        if ($back_img && file_exists($back_img)) {
            $back =Image::open($back_img);
            $back->thumb($QR_width, $QR_height, \think\Image::THUMB_CENTER)
             ->water($qr_code_file, \think\Image::WATER_NORTHWEST, 60);//->save($qr_code_file);
            $QR = $back;
        }
        
        /* 添加头像 */
        if ($head_pic) {
            //如果是网络头像
            if (strpos($head_pic, 'http') === 0) {
                //下载头像
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $head_pic); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                $file_content = curl_exec($ch);
                curl_close($ch);
                //保存头像
                if ($file_content) {
                    $head_pic_path = $qr_code_path.time().rand(1, 10000).'.png';
                    file_put_contents($head_pic_path, $file_content);
                    $head_pic = $head_pic_path;
                }
            }
            //如果是本地头像
            if (file_exists($head_pic)) {
                $logo = Image::open($head_pic);
                $logo_width = $logo->height();
                $logo_height = $logo->width();
                $logo_qr_width = $QR_width / 5;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;
                $logo_file = $qr_code_path.time().rand(1, 10000);
                $logo->thumb($logo_qr_width, $logo_qr_height)->save($logo_file, null, 100);
                $QR = $QR->thumb($QR_width, $QR_height)->water($logo_file, \think\Image::WATER_CENTER);     
                unlink($logo_file);
            }
            if ($head_pic_path) {
                unlink($head_pic_path);
            }
        }
        
        if ($valid_date && strpos($url, 'weixin.qq.com') !== false) {
            $QR = $QR->text('有效时间 '.$valid_date, "./vendor/topthink/think-captcha/assets/zhttfs/1.ttf", 7, '#00000000', Image::WATER_SOUTH);
        }
        $QR->save($qr_code_file, null, 100);
        
        $qrHandle = imagecreatefromstring(file_get_contents($qr_code_file));
        unlink($qr_code_file); //删除二维码文件
        header("Content-type: image/png");
        imagepng($qrHandle);
        imagedestroy($qrHandle);
        exit;
    }

    // 验证码
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : '';
        $fontSize = I('get.fontSize') ? I('get.fontSize') : '40';
        $length = I('get.length') ? I('get.length') : '4';
        
        $config = array(
            'fontSize' => $fontSize,
            'length' => $length,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);    
		exit();    
    }

    function truncate_tables (){
        $tables = DB::query("show tables");
        $table = array('tp_admin','tp_config','tp_region','tp_system_module','tp_admin_role','tp_system_menu','tp_article_cat','tp_wx_user');
        foreach($tables as $key => $val)
        {                                    
            if(!in_array($val['Tables_in_tpshop2.0'], $table))                             
                echo "truncate table ".$val['Tables_in_tpshop2.0'].' ; ';
                echo "<br/>";         
        }                
    }

    /**
     * 猜你喜欢
     * @author lxl
     * @time 17-2-15
     */
    public function ajax_favorite(){
        $p = I('p/d',1);
        $i = I('i/d',5); //显示条数
        $where = ['is_recommend'=>1,'is_on_sale'=>1, 'is_check'=>1, 'virtual_indate'=>['exp',' = 0 OR virtual_indate > '.time()]];
        $favourite_goods = Db::name('goods')->where($where)->order('goods_id DESC')->page($p,$i)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('favourite_goods',$favourite_goods);
        return $this->fetch();
    }

    public function abc(){

        $order_id_list = M('order_red')->where(["is_store"=>1])->column("order_id");

        $list = M('order_red_goods')->where('order_id','in',$order_id_list)->select_key('rec_id');    //rec_id

        foreach ($list as $k=>$row){

            if($row['item_id']==0){
                $getmidou = getMidou($row["goods_id"]);
            }else{
                $getmidou = getMidou($row["goods_id"],$row['item_id']);
            }
            $list[$k]["midou1"] = $getmidou["midou"];
            
        }
    }






}