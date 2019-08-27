<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\companymobile\controller; 
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

}