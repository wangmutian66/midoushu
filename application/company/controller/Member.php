<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\company\controller; 
use app\admin\logic\GoodsLogic;
use app\admin\model\GoodsRed;
use think\AjaxPage;
use think\Controller;
use think\Config;
use think\Page;
use think\Db;

/*
    成员管理
*/
class Member extends Base {

	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
   } 

    public function index(){
        /* 查询本公司成员 */
        //搜索关键词
        $key_word = I('get.key_word');
        //层级
        $where['parent_id'] = ['eq',$this->company_id];

        $count = M('CompanyMember')->where($where)->where(function ($query) use ($key_word) {
                                        if($key_word){
                                            $query->where('real_name', 'like',"%{$key_word}%")->whereor('phone','eq',$key_word);
                                        }
                                    })->count();
        $Page = $pager = new Page($count,14);
        $show  = $Page->show();
        $list = M('CompanyMember')->alias('cm')
                                    ->field('cm.*,cl.lv_name')
                                    ->join('CompanyLevel cl','cm.company_level = cl.id','left')
                                    ->where($where)
                                    ->where(function ($query) use ($key_word) {
                                        if($key_word){
                                            $query->where('real_name', 'like',"%{$key_word}%")->whereor('phone','eq',$key_word);
                                        }
                                    })
                                    ->order("id desc")
                                    ->limit($Page->firstRow.','.$Page->listRows)
                                    ->select();
        /*处理隶属关系*/
        foreach ($list as $key => $value) {
            $temp_arr = explode('_', $value['parent_id_path']);
            $list[$key]['relation'] = get_company_name($temp_arr[2]);
        }

        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('list',$list);
        return $this->fetch('index');
    }

    /*查看成员详细*/
    public function view(){
        if($id = I('get.id/d')){
            $item = M('CompanyMember')->alias('cm')
                                    ->field('cm.*,cl.lv_name')
                                    ->join('CompanyLevel cl','cm.company_level = cl.id','left')
                                    ->cache(true)
                                    ->find($id);
            /*$temp_arr = explode('_', $item['parent_id_path']);
            $item['relation'] = get_company_name($temp_arr[1]) .' / '. get_company_name($temp_arr[2]);*/
            $this->assign('item',$item);
        //    dump($item);
            return $this->fetch();
        }else{
            $this->error('参数错误');
        }
        
    }

    /**
     * [申请供货]
     * @author 王牧田
     * @date 2018-10-31
     */
    public function apply(){
    //    dump($_SESSION);die;
        $store_id = I('get.store_id');
        $company = M('company')->where(["cid"=>$store_id])->find();
        //,"sg.is_com"=>["neq","1"]
        $supply_goods = M('supply_goods sg')
            ->join("tp_goods_red gr","sg.goods_id=gr.goods_id")
            ->join("tp_store_goods_supplices sgs","sg.sgs_id=sgs.id","left")
            ->field("sg.sgs_id,sgs.id,gr.goods_name,sg.time,sg.is_com,sg.reason,sg.is_supply,sgs.is_examine,sgs.is_confirm,sgs.id,sgs.logistics_id,sg.stock")
            ->where(["sg.store_id"=>$store_id])->order("sg.id desc")->select();
//        dump($supply_goods);
//        exit();
        $this->assign('supply_goods',$supply_goods);
        $this->assign('company',$company);
        // dump($company);die();
        return $this->fetch();
    }

    /**
     * [添加供货商商品]
     * @author 王牧田
     * @date 2018-10-31
     */
    public function doapply(){
        $post = I('post.');
        $content = I('post.content');
        $goods = $post['goods'];

        $content = htmlspecialchars_decode($content);
        $data = array();
        if(empty($goods)){
            $data["error"] = 1;
            $data["msg"] = "商品不能为空！";
            exit(json_encode($data));
        }

        foreach ($goods as $row){
            $data[]  = [
                "goods_id"=>$row['goodid'],
                "item_id"=>$row['itemid'],
                "stock"=>$row['stock'],
                "store_id"=>$post['store_id'],
                "time" => time()
            ] ;

            $GoodRed = new GoodsRed();
            $suppliers_id = $GoodRed->where(["goods_id"=>$row['goodid']])->value("suppliers_id");
            $company = db('company')->where(["cid"=>$post['store_id']])->find();

            $this->doMessage($company["cname"]." 发起一条新的供货申请",$suppliers_id);
        }

        db('company')->where(["cid"=>$post['store_id']])->save(['strore_supply_content'=>$content,'gonghuo_examine'=>'1']);
        $supply_goods = db('supply_goods')->insertAll($data);
        $data["error"] = 0;
        if(!$supply_goods){
            $data["error"] = 1;
            $data["msg"] = "申请失败";
        }
        exit(json_encode($data));

    }


    /**
     * [给供货商发送消息]
     * @author 王牧田
     * @date 2018-10-31
     * @param $text 发送的内容
     * @param $suppliers_id 供货上id
     */
    public function doMessage($text,$suppliers_id){

        $message = array(
            'admin_id'  => 0,
            'message'   => $text,
            'category'  => 0,
            'send_time' => time(),
            'object'    => 'suppliers'
        );

        //个体消息
        $message['type'] = 0;
        $create_message_id = M('message')->add($message);
        M('suppliers_message')->add(array('suppliers_id' => $suppliers_id, 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));

    }

    /**
     * [供货商申请选择商品]
     * @author 王牧田
     * @date 2018-10-31
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function search_goods()
    {
        $goods_id = input('goods_id');
        $cat_id   = input('cat_id');
        $brand_id = input('brand_id');
        $keywords = input('keywords');
        $where    = ['is_on_sale' => 1, 'store_count' => ['gt', 0],'is_virtual'=>0,'exchange_integral'=>0];
        $prom_type = input('prom_type/d');

        if($keywords){
            $where['goods_name|keywords'] = ['like','%'.$keywords.'%'];
        }
        $Goods = new GoodsRed();
        $count = $Goods->where($where)->count();
        $Page = new Page($count, 10);
        $goodsList = $Goods->with('specGoodsPrice')->where($where)->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);
        return $this->fetch();
    }

    /**
     * [查看物流]
     * @author 王牧田
     * @date 2018-11-08
     */
    public function showwuliu(){

        $id = I('get.id');
        $supplices = db('store_goods_supplices')->where(["id"=>$id])->field("logistics_id,Logistics_single_number")->find();
        $code = explode("_",$supplices["logistics_id"]);
        $supplices["logistics_id"] = $code[0];

        $shipping_code = $supplices["logistics_id"];
        $invoice_no = $supplices["Logistics_single_number"];
        //$shipping_code = 'zhongtong';
        //$invoice_no = '285413535650';
        //参数设置
//        $post_data = array();
//        $post_data["customer"] = 'DF32DE3F16EDCCC68C701048A18A8AA8';
//        $key= 'MGUIHCmb6562' ;
//        $post_data["param"] = '{"com":"'.$shipping_code.'","num":"'.$invoice_no.'"}';
//
//        $url='http://poll.kuaidi100.com/poll/query.do';
//        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
//        $post_data["sign"] = strtoupper($post_data["sign"]);
//        $o="";
//        foreach ($post_data as $k=>$v)
//        {
//            $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
//        }
//        $post_data=substr($o,0,-1);
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_HEADER, 0);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($ch, CURLOPT_URL,$url);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
//        $result = curl_exec($ch);
//        $data = str_replace("\"",'"',$result );
//        $data = json_decode($data,true);
//
//        $wuliudata = array();
//        if($data['result'] !== false){
//            $wuliudata = $data['data'];
//        }


        $wuliudata = file_get_contents("https://www.midoushu.com/auto/kuaidi/doquery/num/".$invoice_no);
        $wuliudata = json_decode($wuliudata,true);
        $this->assign('invoice_no',$invoice_no);
        $this->assign('wuliudata',$wuliudata['log']);
        return $this->fetch();
    }


    /**
     * [确认收货]
     * @author 王牧田
     * @date 2018-11-08
     */
    public function confirmgood(){
        $id = I('post.id');
        db('store_goods_supplices')->where(["id"=>$id])->save(["is_confirm"=>"1"]);
        $stockinit = db('store_goods_supplices')->where(["id"=>$id])->find();
        $where=[];
        $data = [];
        $where['store_id'] = $data['store_id'] = $stockinit['store_id'];
        $where['goods_id'] = $data['goods_id'] = $stockinit['goods_id'];
        $where['item_id'] = $data['item_id'] = $stockinit['item_id'];
        $data['supplier_id'] = $stockinit['supplier_id'];
        $data['stock'] = $stockinit['stock'];
        $data['is_examine'] = 1;
        $data['create_time'] = time();
        $storestock = M('store_goods_stock')->where($where)->find();
        //检查正常使用的实体店库存是否存在
        if(empty($storestock)){
            //如果不存在就去添加
            $a = db('store_goods_stock')->insert($data);
        }else{
            //如果存在就在原来的库存上做累加
            db('store_goods_stock')->where($where)->setInc('stock',$data['stock']);
        }

        return json(["code"=>200,"msg"=>"操作成功"]);
    }


    /**
     * [获取实体店供货明细view]
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function stock_goods_list(){

        $where['a.cid'] = I('store_id');
        $company = Db::name('company')->alias('a')->where($where)->find();
        $this->assign('company',$company);
        return $this->fetch();

    }


    /**
     * [获取实体店供货明细ajax]
     * @author 王牧田
     * @date 2018-11-20
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ajaxStockgoodsList(){

        //ajax请求
        $store_id = I('post.store_id');     //实体店id
        $key_word = I('post.key_word','');  //关键词


        $condition['a.store_id'] = $store_id;
        if($key_word!=""){
            $condition['b.cname'] = ['like',"%".$key_word."%"];
        }

        $count = Db::name('store_goods_stock')->alias('a')
            ->where($condition)
            ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
            ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
            ->count();

        $Page  = new AjaxPage($count,10);
        $show = $Page->show();

        $goods_store = Db::name('store_goods_stock')->alias('a')
            ->where($condition)
            ->field('a.*,c.key_name,g.goods_name,g.suppliers_id')

            ->join('spec_red_goods_price c','a.item_id = c.item_id','LEFT')
            ->join('goods_red g','g.goods_id = a.goods_id','LEFT')
            ->limit("$Page->firstRow,$Page->listRows")
            ->order("a.id desc")
            ->select();
        $this->assign('StockgoodsList',$goods_store);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }

}