<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\supplier\controller;
use app\supplier\logic\StoreLogic;
use app\supplier\logic\SearchWordLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;


class StoreRed extends Base {

    /**
     *  实体店供货明细
     */
    public function stock_goods_list(){      
        
        return $this->fetch();
    }

     /**
     *  明细列表
     */
    public function ajaxStockgoodsList(){  
        $condition = '';
        $StoreLogic = new StoreLogic();      
        $is_examine = I('is_check');
        if($is_examine !== '')
        {
                $condition['a.is_examine'] = ['eq',$is_examine];
        }
        // // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
                $condition['b.cname'] = ['like',"%".$key_word."%"];
        }

        $condition['g.suppliers_id'] = session('suppliers.suppliers_id');

        $count = $StoreLogic->count_store($condition);
        
        $Page  = new AjaxPage($count,10);
        $show = $Page->show();
        $sort_order = "id  desc";
        $StockgoodsList = $StoreLogic->goods_store_list($condition,$sort_order,$Page->firstRow,$Page->listRows);
        
        $this->assign('StockgoodsList',$StockgoodsList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }
    


    /**
     *  实体店申请记录
     */
    public function apply_list(){
        
        return $this->fetch();
    }

     /**
     *  实体店申请记录列表
     */
    public function ajaxApply_List(){  
        $StoreLogic = new StoreLogic();
        $is_examine = I('is_examine');

        switch ($is_examine){
            case 'is_new':
                $condition['a.is_examine'] = "1";
                break;
            case 'is_recommend':
                $condition['a.is_examine'] = "0";
                break;
        }

        $suppliers_id = session('suppliers.suppliers_id');

        // // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        
        if($key_word)
        {
            $condition['b.cname'] = ['like',"%".$key_word."%"];
        }

        $condition['g.suppliers_id'] = $suppliers_id;

        $count = $StoreLogic->count_store($condition);

        $Page  = new AjaxPage($count,10);
        $show = $Page->show();
        $sort_order = "id  desc";

        $StockapplysList = $StoreLogic->apply_store_list($condition,$sort_order,$Page->firstRow,$Page->listRows);
        $this->assign('StockapplysList',$StockapplysList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * [供货商申请列表]
     * @author 王牧田
     * @date 2018-10-31
     */
    public function supply_goods(){
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];

        $where["gr.suppliers_id"] = session("suppliers.suppliers_id");
//        $where["sg.is_com"] ='1';
        $where["sg.is_com"] = "1";
        $where["sg.status"] = "0";
        //$where["csp.coun"] = ["neq","0"];
        $supply = M('supply_goods sg')
            ->join("tp_goods_red gr","gr.goods_id = sg.goods_id","left")
            ->join("tp_company c","c.cid = sg.store_id","left")
            ->join("(select count(*) as coun,store_id as storeid from tp_supply_goods group by store_id) as csp","csp.storeid = sg.store_id","left")
            ->where($where)
            ->group("sg.store_id")
            ->page("$p,$size")
            ->select();
        $supplycount = M('supply_goods sg')
            ->join("tp_goods_red gr","gr.goods_id = sg.goods_id","left")
            ->join("tp_company c","c.cid = sg.store_id","left")
            ->join("(select count(*) as coun,store_id as storeid from tp_supply_goods  group by store_id) as csp","csp.storeid = sg.store_id","left")
            ->where($where)
            ->group("sg.store_id")
            ->count();

        $pager = new Page($supplycount, $size);
        $this->assign("page",$pager->show());
        $this->assign("supply",$supply);
        return $this->fetch();
    }

    /**
     * [供货商申请]
     * @author 王牧田
     * @date 2018-11-01
     */
    public function detail(){
        $suppliers_id  = session("suppliers.suppliers_id");
        $where["gr.suppliers_id"] = $suppliers_id; 
        $where["sg.store_id"] = I('get.store_id');
        $where["sg.is_com"] = "1";
        $where["sg.status"] = "0";
        //$where["sg.is_supply"] = 0;
        //$where["sg.is_examine"] = ["neq","2"];
        $supply = M('supply_goods sg')
            ->join("tp_goods_red gr","gr.goods_id = sg.goods_id","left")
            ->join('tp_spec_red_goods_price c','sg.item_id = c.item_id','LEFT')
            ->field("gr.*,sg.*")
            ->where($where)
            ->order("id desc")
            ->select();



        foreach ($supply as $key => $value) {
            $yuanyin = M('store_goods_supplices')->where(["item_id"=>$value['item_id'],"goods_id"=>$value['goods_id'],'store_id'=>$value['store_id']])->find();
            
            if ($yuanyin) {
                $supply[$key]['is_examine'] = $yuanyin['is_examine'];
                $supply[$key]['examine_reason'] = $yuanyin['examine_reason'];

                if ($supply[$key]['is_examine'] == '1') {
                   //unset($supply[$key]);
                }
            }
        }


        $company = M('company')->where(["cid"=>I('get.store_id')])->find();
        $this->assign("company",$company);
        $this->assign("supply",$supply);
        return $this->fetch();
    }

    /**
     * [同意去供货]
     * @author 王牧田
     * @date 2018-11-1
     */
    public function setsupply(){
        $good_id = I('post.good_id');
        $item_id = I('post.item_id');
        $stock = I('post.stock');
        $store_id = I('post.store_id');
        $id = I('post.id');
        $data=[
            "store_id"=>$store_id,
            "goods_id"=>$good_id,
            "stock"=>$stock,
            "item_id"=>$item_id,
            "is_examine"=>0,
            "create_time"=>time(),
            "supplier_id"=>session("suppliers.suppliers_id")
        ];

        $sgs_id = db('store_goods_supplices')->add($data);

// "is_supply"=>1,
        $supply_goods=db('supply_goods')->where(["id"=>$id])->save(["is_supply"=>1,"status"=>1,"sgs_id"=>$sgs_id]);

        $data["error"] = 0;
        if(!$sgs_id){
            $data["error"] = 1;
            $data["msg"] = "添加失败，请稍后重试";
        }
        exit(json_encode($data));
    }

   /**
     * 
     * @author wuchaoqun
     * @time  2018/11/8
     */

    public function is_check(){
        $id = I('id');
        $info = Db::name('store_goods_supplices')->where(['id'=>$id])->find();
        $plugin = Db::name('plugin')->where(['status'=>1,'type'=>'shipping'])->select();
        $this->assign("info",$info);
        $this->assign("plugin",$plugin);
        return $this->fetch();
    }

    /**
     * 
     * @author wuchaoqun
     * @time  2018/11/8
     */
    public function dois_check()
    {
        $call_back = I('call_back');      //回调方法
        $id = I('post.id'); 
        $logistics_id = I('post.logistics_id'); 
        $Logistics_single_number  = I('post.Logistics_single_number'); 
        $result = M('store_goods_supplices')->where(['id'=>$id])->save(['logistics_id'   => $logistics_id,'Logistics_single_number'=> $Logistics_single_number]);
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }
 /**
     * 
     * @author wuchaoqun
     * @time  2018/11/8
     */
    public function look (){
        
//         $num= '285413535650';
//         $code= 'zhongtong';
        $num= I('post.num');
//        $code = explode("_",I('post.code'));
//
//        $code=  $code[0];
//
//        //参数设置
//        $post_data = array();
//        $post_data["customer"] = 'DF32DE3F16EDCCC68C701048A18A8AA8';
//        $key= 'MGUIHCmb6562' ;
//        $post_data["param"] = '{"com":"'.$code.'","num":"'.$num.'"}';
//
//        $url='http://poll.kuaidi100.com/poll/query.do';
//        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
//        $post_data["sign"] = strtoupper($post_data["sign"]);
//
//        $o="";
//        foreach ($post_data as $k=>$v)
//        {
//            $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
//        }
//        $post_data=substr($o,0,-1);
//
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_HEADER, 0);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($ch, CURLOPT_URL,$url);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
//        $result = curl_exec($ch);
//        $data = str_replace("\"",'"',$result );
//        $data = json_decode($data,true);
//        if($data['result'] !== false){
//            $wuliudata = $data['data'];
//        }


        $wuliudata = file_get_contents("https://www.midoushu.com/auto/kuaidi/doquery/num/".$num);
        $wuliudata = json_decode($wuliudata,true);

        $this->assign('wuliudata',$wuliudata["log"]);
        return $this->fetch();
    }
   
}
