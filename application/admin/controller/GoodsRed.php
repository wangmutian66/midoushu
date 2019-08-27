<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\admin\controller;
use app\admin\logic\RedGoodsLogic;
use app\admin\logic\RedSearchWordLogic;
use app\admin\logic\CompanyLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

class GoodsRed extends Base {

    /**
     *  商品分类列表
     */
    public function categoryList(){   
        //TK    2018年4月21日09:13:23             
        $GoodsLogicRed = new RedGoodsLogic();      
        $cat_list = $GoodsLogicRed->goods_cat_list();
        $this->assign('cat_list',$cat_list);        
        return $this->fetch();
    }
    
    /**
     * 添加修改商品分类
     * 手动拷贝分类正则 ([\u4e00-\u9fa5/\w]+)  ('393','$1'), 
     * select * from tp_goods_category where id = 393
        select * from tp_goods_category where parent_id = 393
        update tp_goods_category  set parent_id_path = concat_ws('_','0_76_393',id),`level` = 3 where parent_id = 393
        insert into `tp_goods_category` (`parent_id`,`name`) values 
        ('393','时尚饰品'),
     */
    public function addEditCategory(){
        
        $GoodsLogic = new RedGoodsLogic();        
        if(IS_GET)
        {
            $goods_category_info = D('GoodsRedCategory')->where('id='.I('GET.id',0))->find();
            $level_cat = $GoodsLogic->find_parent_cat($goods_category_info['id']); // 获取分类默认选中的下拉框
            
            $cat_list = M('goods_red_category')->where("parent_id = 0")->select(); // 已经改成联动菜单                
            $this->assign('level_cat',$level_cat);                
            $this->assign('cat_list',$cat_list);                 
            $this->assign('goods_category_info',$goods_category_info);      
            return $this->fetch('_category');
            exit;
        }

        $GoodsCategory = D('GoodsRedCategory'); //

        $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新                        
        //ajax提交验证
        if(I('is_ajax') == 1)
        {
            // 数据验证            
            $validate = \think\Loader::validate('GoodsRedCategory');
            if(!$validate->batch()->check(input('post.')))
            {                          
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            } else {
                 
                $GoodsCategory->data(input('post.'),true); // 收集数据
                $GoodsCategory->parent_id = I('parent_id_1');
                input('parent_id_2') && ($GoodsCategory->parent_id = input('parent_id_2'));
                //编辑判断
                if($type == 2){
                    $children_where = array(
                        'parent_id_path'=>array('like','%_'.I('id')."_%")
                    );
                    $children = M('goods_red_category')->where($children_where)->max('level');
                    if (I('parent_id_1')) {
                        $parent_level = M('goods_red_category')->where(array('id' => I('parent_id_1')))->getField('level', false);
                        if (($parent_level + $children) > 4) {
                            $return_arr = array(
                                'status' => -1,
                                'msg'   => $parent_level.'商品分类最多为三级'.$children,
                                'data'  => '',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                    if (I('parent_id_2')) {
                        $parent_level = M('goods_red_category')->where(array('id' => I('parent_id_2')))->getField('level', false);
                        if (($parent_level + $children) > 4) {
                            $return_arr = array(
                                'status' => -1,
                                'msg'   => '商品分类最多为三级',
                                'data'  => '',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                }

                if($type == 1) {
                    //查找同级分类是否有重复分类
                    $par_id = ($GoodsCategory->parent_id > 0) ? $GoodsCategory->parent_id : 0;
                    $same_cate = M('GoodsRedCategory')->where(['parent_id' => $par_id, 'name' => $GoodsCategory['name']])->find();
                    if ($same_cate) {
                        $return_arr = array(
                            'status' => 0,
                            'msg' => '同级已有相同分类存在',
                            'data' => '',
                        );
                        $this->ajaxReturn($return_arr);
                    }
                }
                
                if ($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id) {
                    //  编辑
                    $return_arr = array(
                        'status' => 0,
                        'msg' => '上级分类不能为自己',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }
                
                if($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id)
                {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg'   => '上级分类不能为自己',
                        'data'  => '',
                    );
                    $this->ajaxReturn($return_arr);                        
                }
                if($GoodsCategory->commission_rate > 100)
                {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg'   => '分佣比例不得超过100%',
                        'data'  => '',
                    );
                    $this->ajaxReturn($return_arr);                        
                }   
               
                if ($type == 2)
                {
                    $GoodsCategory->isUpdate(true)->save(); // 写入数据到数据库
                    $GoodsLogic->refresh_cat(I('id'));
                }
                else
                {
                    $GoodsCategory->save(); // 写入数据到数据库
                    $insert_id = $GoodsCategory->getLastInsID();
                    $GoodsLogic->refresh_cat($insert_id);
                }
                $return_arr = array(
                    'status' => 1,
                    'msg'   => '操作成功',
                    'data'  => array('url'=>U('Admin/GoodsRed/categoryList')),
                );
                $this->ajaxReturn($return_arr);

            }  
        }

    }
    
    /**
     * 获取商品分类 的帅选规格 复选框
     */
    public function ajaxGetSpecList(){
        $GoodsLogic = new RedGoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsRedCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_spec');        
        $filter_spec_arr = explode(',',$filter_spec);        
        $str = $GoodsLogic->GetSpecCheckboxList($_REQUEST['type_id'],$filter_spec_arr);  
        $str = $str ? $str : '没有可帅选的商品规格';
        exit($str);        
    }
 
    /**
     * 获取商品分类 的帅选属性 复选框
     */
    public function ajaxGetAttrList(){
        $GoodsLogic = new RedGoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_attr = M('GoodsRedCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_attr');        
        $filter_attr_arr = explode(',',$filter_attr);        
        $str = $GoodsLogic->GetAttrCheckboxList($_REQUEST['type_id'],$filter_attr_arr);          
        $str = $str ? $str : '没有可帅选的商品属性';
        exit($str);        
    }    
    
    /**
     * 删除分类
     */
    public function delGoodsCategory(){
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        // 判断子分类
        $count = Db::name("goods_red_category")->where("parent_id = {$ids}")->count("id");
        $count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下还有分类不得删除!']);
        // 判断是否存在商品
        $goods_count = Db::name('goods_red')->where("cat_id = {$ids}")->count('1');
        $goods_count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下有商品不得删除!']);
        // 删除分类
        DB::name('goods_red_category')->where('id',$ids)->delete();
        $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/GoodsRed/categoryList')]);
    }
    
    
    /**
     *  商品列表
     */
    public function goodsList(){      
        $GoodsLogic = new RedGoodsLogic();        
        // $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getCategoryList();
        $suppliersList = $GoodsLogic->getSuppliers();
        $this->assign('categoryList',$categoryList);
        //$this->assign('brandList',$brandList);
        $this->assign('suppliersList',$suppliersList);
        return $this->fetch();
    }

    #获取商品下级分类
    #2018-12-11  张洪凯
    public function ajax_get_category(){
        $cat_id = I('cat_id/d',0);
        $GoodsLogic = new RedGoodsLogic();
        $cat_list = $GoodsLogic->getCategoryList($cat_id);
        if ($cat_list) {
            $data['status'] = 1;
            $data['list'] = $cat_list;
        } else {
            $data['status'] = 0;
            $data['list'] = '';
        }
        $this->ajaxReturn($data);
    }
    
    /**
     *  商品列表
     */
    public function ajaxGoodsList(){            
        
        $where = "1=1";
        I('intro')    && $where .= " and g.".I('intro')." = 1" ;        
        (I('is_on_sale') !== '') && $where .= " and g.is_on_sale = ".I('is_on_sale') ;   
        (I('is_check') !== '') && $where .= " and g.is_check = ".I('is_check') ;       
        (I('is_allreturn') !== '') && $where .= " and g.is_allreturn = ".I('is_allreturn');

        $cat_id1 = I('cat_id1/d',0);
        $cat_id2 = I('cat_id2/d',0);
        $cat_id3 = I('cat_id3/d',0);
        $cat_id = $cat_id3 > 0 ? $cat_id3 : ($cat_id2 > 0 ? $cat_id2 : $cat_id1);
        // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where .= " and (g.goods_name like '%$key_word%' or g.goods_sn like '%$key_word%' or s.suppliers_name like '%$key_word%')" ;
        }
        
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandsonRed($cat_id); 
            $where .= " and g.cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        I('suppliers_id') && $where .= " and g.suppliers_id = ".I('suppliers_id');

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " AND g.suppliers_id > 0";
        } else if($sp && $sp == 2){
            $where .= " AND g.suppliers_id = 0";
        }
        $count = M('GoodsRed')
          ->alias('g')
          ->field('g.*')
          ->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','LEFT')
          ->where($where)
          ->count();
        $Page  = new AjaxPage($count,20);

        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $order_str = "`goods_id` desc";
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('GoodsRed')
              ->alias('g')
              ->field('g.*')
              ->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','LEFT')
              ->where($where)
              ->order($order_str)
              ->limit($Page->firstRow.','.$Page->listRows)
              ->select();

        $midou_use_percent = $this->tpshop_config['shoppingred_midou_use_percent']; // 购买商品 使用米豆 比率
        $midou_rate        = $this->tpshop_config['shoppingred_midou_rate'];        // 米豆兑换比
        foreach ($goodsList as $k => $val) {
            $midouInfo = getMidou($val['goods_id']);
            $goodsList[$k]['midou']       = $midouInfo['midou'];
            $goodsList[$k]['midou_money'] = $midouInfo['midou_money'];
        }

        $catList = D('GoodsRedCategory')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList',$catList);
        $this->assign('goodsList',$goodsList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    function export_goods(){
        $where = "1=1";
        I('intro')    && $where .= " and g.".I('intro')." = 1" ;        
        (I('is_on_sale') !== '') && $where .= " and g.is_on_sale = ".I('is_on_sale') ;   
        (I('is_check') !== '') && $where .= " and g.is_check = ".I('is_check') ;       
        (I('is_allreturn') !== '') && $where .= " and g.is_allreturn = ".I('is_allreturn');

        $cat_id1 = I('cat_id1/d',0);
        $cat_id2 = I('cat_id2/d',0);
        $cat_id3 = I('cat_id3/d',0);
        $cat_id = $cat_id3 > 0 ? $cat_id3 : ($cat_id2 > 0 ? $cat_id2 : $cat_id1);
        
        // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where .= " and (g.goods_name like '%$key_word%' or g.goods_sn like '%$key_word%' or s.suppliers_name like '%$key_word%')" ;
        }
        
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandsonRed($cat_id); 
            $where .= " and g.cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        I('suppliers_id') && $where .= " and g.suppliers_id = ".I('suppliers_id');

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " AND g.suppliers_id > 0";
        } else if($sp && $sp == 2){
            $where .= " AND g.suppliers_id = 0";
        }

        $goods_ids = I('goods_ids');
        if($goods_ids){
            $where .= " AND goods_id IN ($goods_ids)";
        }

        $goodsList = M('GoodsRed')
          ->alias('g')
          ->field('g.*')
          ->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','LEFT')
          ->where($where)
          ->select();

        $catList = D('GoodsRedCategory')->select();
        $catList = convert_arr_key($catList, 'id');

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;">商品ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供货商</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">货号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">分类</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">本店售价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">米豆价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">市场价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品成本价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运营成本价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否参与大盘的最多可使用米豆比率</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最多可使用米豆比率</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否参与大盘的可返米豆比率</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">可返米豆比率</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否包邮</td>';
        $strTable .= '</tr>';
        if(is_array($goodsList)){
            foreach($goodsList as $k=>$val){

                $midouInfo = getMidou($val['goods_id']);
                $val['midou']       = $midouInfo['midou'];
                $val['midou_money'] = $midouInfo['midou_money'];

                if($val['is_z_change'] == 1){
                    $is_z_change = '是';
                    $val['midou_use_percent'] = tpCache('shoppingred.midou_use_percent');
                } else { 
                    $is_z_change = '否'; 
                }

                if($val['is_z_back'] == 1){
                    $is_z_back = '是';
                    $val['midou_back_percent'] = tpCache('shoppingred.midou_back_percent');
                } else { 
                    $is_z_back = '否'; 
                }

                if($val['is_free_shipping'] == 1) $is_free_shipping = "是"; else $is_free_shipping = "否";  

                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['goods_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.get_suppliers_name($val['suppliers_id']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_sn'].' </td>'; 
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$catList[$val['cat_id']]['name'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shop_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou'].'米豆+'.$val['midou_money'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['market_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_operating'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_z_change.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_use_percent'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_z_back.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_back_percent'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_free_shipping.'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($goodsList);
        downloadExcel($strTable,'goodsred');
        exit();
    }

    /**
     *
     * @time 2018/04/19
     * @author liyi
     * 审核商品
     */
    public function is_check()
    {
        $t = I('get.t');
        $goods = array();
        if($t == 1){
            $goods_id = I('get.goods_id');
            if (!empty($goods_id)) {
                $goods = M('goodsRed')->field('goods_id,goods_name')->where(array('goods_id' => array('eq', $goods_id)))->select();
                $goods_info = M('goodsRed')->where(array('goods_id' => array('eq', $goods_id)))->find();
            }
            $this->assign('goods_info',$goods_info);
        } else {
            $goods_id_array = I('get.goods_id_array');
            if (!empty($goods_id_array)) {
                $goods = M('goods_red')->field('goods_id,goods_name')->where(array('goods_id' => array('IN', $goods_id_array)))->select();
            }
        }
        $this->assign('goods',$goods);
        return $this->fetch();
    }


    /**
     *
     * @time 2018/11/14
     * @author wuchaoqun
     * 商品解锁密码
     */
    public function is_check_password()
    {
        $t = I('get.t');
        $goods_id = I('get.goods_id');
        $this->assign('goods_id',$goods_id);
        $this->assign('type',$t);
        return $this->fetch();
    }

    /**
     * 更改商品审核
     * @author dyr
     * @time  2018/04/19
     */
    public function dois_check()
    {
        $call_back = I('call_back');      //回调方法
        $no_remark = I('post.no_remark'); //内容
        $is_check  = I('post.is_check'); //内容
        $type      = I('post.type', 0);   //个体or全体
        $goods     = I('post.goods/a');   //个体id
        $data = array(
            'no_remark'   => $no_remark,
            'is_check'    => $is_check,
            'last_update' => time()
        );

        if ($type == 1) {
            //全体用户系统消息
            M('goods_red')->save($data);
        } else {
            //个体消息
            if (!empty($goods)) {
                foreach ($goods as $key) {
                    M('goods_red')->where('goods_id = '.$key)->save($data);
                }
            }
        }
        if ($type=='1') {
            $typename='全体';
        }else{
            $typename='个体';
        }
        if ($is_check=='1') {
            $is_checkname='通过';
        }else{
            $is_checkname='未通过';
        }
        $goods_ids = implode(',', $goods);
        adminLog('米豆商品审核操作(goods_id:'.$goods_ids.'；内容：'.$is_checkname.')');
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }
    
     /**
     * 更改商品解锁密码
     * @author wuchaoqun
     * @time  2018/11/13
     */
    public function dois_check_password()
    {
        $type = I('type');
        $call_back = I('call_back');      //回调方法
        $goods_id = I('goods_id');
        if($type != 1){
            $password = I('post.password'); 
            $config = M('config')->where("name = 'lockPassword' and inc_type = 'basic'")->find();
            if($config['value'] == md5($password)){
                if($type ==1){
                    $data = array(
                        'is_lock'   => 0,
                    );
                }else{
                    $data = array(
                        'is_lock'   => 1,
                    );
                }
                M('goods_red')->where('goods_id = '.$goods_id)->save($data);
                echo "<script>parent.{$call_back}(1);</script>";
                exit();
            }else{
                echo "<script>parent.{$call_back}(2);</script>";
                exit();
            }
        }else{
            $data = array(
                'is_lock'   => 0,
            );
            M('goods_red')->where('goods_id = '.$goods_id)->save($data);
            echo "1";
            exit();
        }
        
    }
     
    // 商品库存记录
    public function stock_list(){
    	$model = M('stock_red_log');
    	$map = array();
    	$mtype = I('mtype');
    	if($mtype == 1){
    		$map['stock'] = array('gt',0);
    	}
    	if($mtype == -1){
    		$map['stock'] = array('lt',0);
    	}
    	$goods_name = I('goods_name');
    	if($goods_name){
    		$map['goods_name'] = array('like',"%$goods_name%");
    	}
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $map['suppliers_id'] = array('gt', 0); 
        } else if($sp && $sp == 2){
            $map['suppliers_id'] = array('eq', 0);
        }
    	$ctime = urldecode(I('ctime'));
    	if($ctime){
    		$gap = explode(' - ', $ctime);
    		$this->assign('start_time',$gap[0]);
    		$this->assign('end_time',$gap[1]);
    		$this->assign('ctime',$gap[0].' - '.$gap[1]);
    		$map['ctime'] = array(array('gt',strtotime($gap[0])),array('lt',strtotime($gap[1])));
    	}

    	$count = $model->where($map)->count();
    	$Page  = new Page($count,20);
    	$show = $Page->show();
    	$this->assign('pager',$Page);
    	$this->assign('page',$show);// 赋值分页输出
 
    	$stock_list = $model->where($map)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
    //    echo $model->getlastsql();die;
    	$this->assign('stock_list',$stock_list);
    	return $this->fetch();
    }


    public function export_stock_list(){
        $model = M('stock_red_log');
        $map = array();
        $mtype = I('mtype');
        if($mtype == 1){
            $map['stock'] = array('gt',0);
        }
        if($mtype == -1){
            $map['stock'] = array('lt',0);
        }
        $goods_name = I('goods_name');
        if($goods_name){
            $map['goods_name'] = array('like',"%$goods_name%");
        }
        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $map['stock.suppliers_id'] = array('gt', 0); 
        } else if($sp && $sp == 2){
            $map['stock.suppliers_id'] = array('eq', 0);
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['ctime'] = array(array('gt',strtotime($gap[0])),array('lt',strtotime($gap[1])));
        }

        $ids = I('ids');
        if($ids){
            $map['id'] = array('in',$ids);
        }

        $stock_list = $model->alias('stock')->field('stock.*')->join('__GOODS__ goods','goods.goods_id = stock.goods_id')->where($map)->order('id desc')->select();

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="600">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品规格</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">库存</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">库存类型</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">操作人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">日志时间</td>';
        $strTable .= '</tr>';
        if(is_array($stock_list)){
            foreach($stock_list as $k=>$val){
                if(empty($val['order_sn'])) $val['type'] = "货品库存"; else  $val['type'] = "商品库存";
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_spec'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_sn'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['stock'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['type'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['muid'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['add_time']).'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($order_list);
        downloadExcel($strTable,'saleList');
        exit();
    }
    //商品规格
    public function storestock(){
        $type_id = I('get.type_id/d'); // 商品分类 父id
        $data = M('spec_red')->where("type_id", $type_id)->select();
        $html = '';
        if($data){
            $html .= '<select  class="form-control">';
                   $html .= '<option value="0">请选择商品规格</option>';
            foreach($data as $h){
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";

            }
            $html .= "</select>";
        }
      
        exit($html);
    }

    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {

        $GoodsLogicRed = new RedGoodsLogic();
        $GoodsRed = new \app\admin\model\GoodsRed();
        $goods_id = I('goods_id');
        $goods_ids = I('id');
        ///实体店商品库存
        $store_id = $_POST['shitiid'];
        $stock = $_POST['stock'];
        $item_id = $_POST['storegoodstype'];
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新

        if ($goods_ids) {
            $res=M('store_goods_stock')->where('goods_id='.$goods_ids)->select();
            $sgoodslist = array(); //想要的结果
            foreach ($res as $k => $v) {
              $sgoodslist[$v['store_id']][] = $v;
              $comid = M('Company')->where('cid='.$v['store_id'])->find();
            }

        }

        //判断规格是不是空
        if ($goods_id) {
            $guige = M("spec_red_goods_price")->where('goods_id='.$goods_id)->select();
            if (!empty($guige)) {
                if (!empty($item_id)) {
                    $itid = array_count_values($item_id);
                    if ($itid['0']) {
                        $return_url =  U('/admin/GoodsRed/goodsList/');
                        $return_arr = array(
                                'msg' => '您没有选择有效的规格！',
                                'status' => -0,
                                'data' => array('url' => $return_url)
                        );
                        $this->ajaxReturn($return_arr);
                    }
                }
            }
        }
        //ajax提交验证
        if ((I('is_ajax') == 1) && IS_POST) {
            if ($stock) {
               //添加实体店家商品库存
                $a = array("store_id"=>$store_id);
                $b = array("stock"=>$stock);
                $c = array("item_id"=>$item_id);
                $test = array("a"=>"store_id","b"=>"stock","c"=>"item_id");
                $result = array();
//                M('store_goods_stock')->where('goods_id',$goods_id)->delete();
                for($i=0;$i<count($a["store_id"]);$i++){
                    foreach($test as $key=>$value){
                        $result[$i]['goods_id'] = $goods_id;
                        $result[$i][$value] = ${$key}[$value][$i];
                    }
                    $add['is_examine'] = 1;
                    $add['goods_id']=$data['goods_id']=$result[$i]['goods_id'];
                    $add['store_id']=$data['store_id']=$result[$i]['store_id'];
                    $add['stock']=$stock=$result[$i]['stock'];
                    $add['item_id']=$data['item_id']=empty($result[$i]['item_id'])?0:$result[$i]['item_id'];
                    $storegoodstock = db('store_goods_stock')->where($data)->find();


                    if(empty($storegoodstock)){
                        //去添加
                        db('store_goods_stock')->insert($add);
                    }else{
                        //去修改
                         db('store_goods_stock')->where($data)->setInc('stock',$stock);
                    }
                    $add['create_time'] = time();
                    db('store_goods_supplices')->insert($add);
                }




            }
            
           
            // 数据验证
         
            $virtual_indate = input('post.virtual_indate');//虚拟商品有效期
            $return_url =  U('/admin/GoodsRed/goodsList/');

            $data = input('post.');
            if($_POST['shitiid']) {
                $data['suppliers_ids'] = implode(',',$_POST['shitiid']);
            }
            $validate = \think\Loader::validate('GoodsRed');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg'    => $error_msg[0],
                    'data'   => $error,
                );
                $this->ajaxReturn($return_arr);
            }

            $data['virtual_indate'] = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            $data['exchange_integral'] = ($data['is_virtual'] == 1) ? 0 : $data['exchange_integral'];
			
            $GoodsRed->data($data, true); // 收集数据

            $GoodsRed->on_time = time();  // 上架时间
            I('cat_id_2') && ($GoodsRed->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($GoodsRed->cat_id = I('cat_id_3'));

            I('extend_cat_id_2') && ($GoodsRed->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($GoodsRed->extend_cat_id = I('extend_cat_id_3'));
            $GoodsRed->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $GoodsRed->shipping_area_ids = $GoodsRed->shipping_area_ids ? $GoodsRed->shipping_area_ids : '';
            $GoodsRed->spec_type = $GoodsRed->goods_type;

            $price_ladder = array();
            if ($GoodsRed->ladder_amount[0] > 0) {
                foreach ($GoodsRed->ladder_amount as $key => $value) {
                    $price_ladder[$key]['amount'] = intval($GoodsRed->ladder_amount[$key]);
                    $price_ladder[$key]['price'] = floatval($GoodsRed->ladder_price[$key]);
                }
                $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
                $price_ladder_max = count($price_ladder);
                if ($price_ladder[$price_ladder_max - 1]['price'] >= $GoodsRed->shop_price) {
                    $return_arr = array(
                        'msg' => '价格阶梯最大金额不能大于商品原价！',
                        'status' => -0,
                        'data' => array('url' => $return_url)
                    );
                    $this->ajaxReturn($return_arr);
                }
                if ($price_ladder[0]['amount'] <= 0 || $price_ladder[0]['price'] <= 0) {
                    $return_arr = array(
                        'msg' => '您没有输入有效的价格阶梯！',
                        'status' => -0,
                        'data' => array('url' => $return_url)
                    );
                    $this->ajaxReturn($return_arr);
                }
                $GoodsRed->price_ladder = serialize($price_ladder);
            } else {
                $GoodsRed->price_ladder = '';
            }

            if ($type == 2) {
                $ab = $GoodsRed->isUpdate(true)->save(); // 写入数据到数据库

                // 修改商品后购物车的商品价格也修改一下  米豆部分需要修改
                M('cart_red')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                    'market_price'       => I('market_price'), //市场价
                    'goods_price'        => I('shop_price'), // 本店价
                    'member_goods_price' => I('shop_price'), // 会员折扣价
                    'cost_price'         => I('cost_price'),     // 成本价
                    'cost_operating'     => I('cost_operating'), // 运营成本价
                ));
                adminLog('编辑米豆商品(id:'.$goods_id.'；本店价:'.I('shop_price').'；市场价:'.I('market_price').'；商品成本价:'.I('cost_price').'；运营成本价:'.I('cost_operating').')');

            } else {
                $GoodsRed->save(); // 写入数据到数据库
                $goods_id = $insert_id = $GoodsRed->getLastInsID();
                // db('goods_red')->where('goods_id',$goods_id)->setField('sort',$goods_id);
                db('goods_red')->where('goods_id',$goods_id)->setField('sort','5000');
                adminLog('添加米豆商品(id:'.$insert_id.'；本店价:'.$data['shop_price'].'；市场价:'.$data['market_price'].'；商品成本价:'.$data['cost_price'].'；运营成本价:'.$data['cost_operating'].')');

            }

            $GoodsRed->afterSave($goods_id);
            $GoodsLogicRed->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => $return_url),
            );
            $this->ajaxReturn($return_arr);
        }

        $goodsInfo = M('GoodsRed')->where('goods_id=' . I('GET.id', 0))->find();

        if ($goodsInfo['price_ladder']) {
            $goodsInfo['price_ladder'] = unserialize($goodsInfo['price_ladder']);
        }
        $level_cat = $GoodsLogicRed->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        if($goodsInfo['extend_cat_id'])
            $level_cat2 = $GoodsLogicRed->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list = M('goods_red_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        // $brandList = $GoodsLogicRed->getSortBrands();
        $goodsType = M("GoodsRedType")->select();
        if ($goods_ids) {
            $stockgoodsType = M("spec_red_goods_price")->where('goods_id='.$goods_ids)->select();
        }

        $suppliersList = M("suppliers")->where('')->select();
            foreach ($suppliersList as $k => $val) {
            $name=getFirstCharter(mb_substr($val['suppliers_name'],0,1,'utf-8')) .' '. $val['suppliers_name'];
            $str = "";
            switch ($val['is_check']) {
                case '0':
                    $str = "(未审核)";
                    break;
                case '1':
                    $str = "(审核中)";
                    break;
                case '2':
                    $str = "(审核未通过)";
                    break;
                case '3':
                    if($val['status'] == 0)
                        $str = "(已冻结)";
                    if($val['status'] == 1)
                        $str = "(营业)";
                    else
                        $str = "(审核通过)";
                    break;
                default:
                    $str = "(未审核)";
                    break;
            }
             $nameList[] =$val['suppliers_name'] = $name.$str;
            $suppliersList[$k] = $val;
        }
        array_multisort($nameList,SORT_STRING,SORT_ASC,$suppliersList);
        if($goodsInfo) $suppliers_id = I('suppliers_id') ? I('suppliers_id') : $goodsInfo['suppliers_id'];
        else $suppliers_id = 0;
        /*$shipping_where['status'] = 1;
        $shipping_where['is_default'] = 1;
        $shipping_where['type'] = array('eq', 'shipping');
        $shipping_where['suppliers_id'] = array('eq', $suppliers_id);
        $plugin_shipping = M('plugin')->where($shipping_where)->select();//插件物流*/
        $plugin_shipping = M('plugin')->where("status=1 and type='shipping' and (suppliers_id=$suppliers_id or suppliers_id=0)")->select();//插件物流

        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域

        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        //查询实体店数据
        $companys =M('Company')->where('parent_id=0')->select();
        foreach ($companys as $key => $value) {
            $companys[$key]['child']= M('Company')->where('parent_id='.$value['cid'])->select();
        }

        //已申请实体店显示
        $storeStock =M('store_goods_stock sgs')
            ->join('company c','c.cid = sgs.store_id','left')
            ->join('goods_red g','g.goods_id = sgs.goods_id','left')
            ->join('spec_red_goods_price sgp','sgs.item_id = sgp.item_id','left')
            ->field("sgs.*,c.cname,g.goods_name,sgp.key_name")
            ->where(["sgs.goods_id"=>$goods_ids,"sgs.is_examine"=>"1"])
            ->group("sgs.item_id,c.cid,g.goods_id")
            ->select();



        $this->assign('storeStock', $storeStock);
        $this->assign('shiti', $companys);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        // $this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        //////s
        $this->assign('guige', $guige);
        $this->assign('sgoodslist', $sgoodslist);
        // $this->assign('sgoodsliste', $sgoodsliste);
        $store_id = db('store_goods_stock')->where(['goods_id'=>$goods_ids])->column("store_id");

        $this->assign('sids', $store_id);
        $this->assign('stockgoodsType', $stockgoodsType);
        if ($stockgoodsType) {
            $stockstr="1";
        }else{
            $stockstr="2";
        }
        $this->assign('stockstr', $stockstr);
        //////e
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsRedImages")->where('goods_id =' . I('GET.id', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册

        return $this->fetch('_goods');
    } 

    /**
     * 模糊查询供货商
     */
    public function get_suppliers(){
        $suppliers_name = I('suppliers_name/s');
        $where = "";
        $where = $suppliers_name ? "suppliers_name like '%$suppliers_name%' " : "";
        $suppliersList = M("suppliers")->where($where)->select();
        if(!$suppliersList){
            echo "error";
        }else{
        foreach ($suppliersList as $k => $val) {
        $name=getFirstCharter(mb_substr($val['suppliers_name'],0,1,'utf-8')) .' '. $val['suppliers_name'];
        $str = "";
        switch ($val['is_check']) {
            case '0':
                $str = "(未审核)";
                break;
            case '1':
                $str = "(审核中)";
                break;
            case '2':
                $str = "(审核未通过)";
                break;
            case '3':
                if($val['status'] == 0)
                    $str = "(已冻结)";
                if($val['status'] == 1)
                    $str = "(营业)";
                else
                    $str = "(审核通过)";
                break;
                default:
                    $str = "(未审核)";
                break;
            }
            $nameList[] =$val['suppliers_name'] = $name.$str;
            $suppliersList[$k] = $val;
        }
        array_multisort($nameList,SORT_STRING,SORT_ASC,$suppliersList);
        $this->ajaxReturn($suppliersList);
        }
    }
    
    public function get_plugin_shipping(){
        $suppliers_id = I('suppliers_id') ? I('suppliers_id') : 0;
        $shipping_where['status'] = 1;
        $shipping_where['is_default'] = 1;
        $shipping_where['type'] = array('eq', 'shipping');
        $shipping_where['suppliers_id'] = array('eq', $suppliers_id);
        $plugin_shipping = M('plugin')->where($shipping_where)->select();//插件物流
        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        return $this->fetch();
    }
          
    /**
     * 商品类型  用于设置商品的属性
     */
    public function goodsTypeList(){
        //modify
        $model = M("GoodsRedType");                
        $count = $model->count();        
        $Page = $pager = new Page($count,14);
        $show  = $Page->show();
        $goodsTypeList = $model->order("id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch('goodsTypeList');
    }

    /**
     * 添加修改编辑  商品属性类型
     */
    public function addEditGoodsType()
    {
        $id = $this->request->param('id', 0);

        $model = M("GoodsRedType");
        if (IS_POST) {
            $data = $this->request->post();

            //modify
            if ($id){
                DB::name('GoodsRedType')->update($data);
                adminLog('编辑米豆商品属性类型(id:'.$id.')');
            }else{
                DB::name('GoodsRedType')->insert($data);
                $insert_id = DB::name('GoodsRedType')->getLastInsID();
                adminLog('添加米豆商品属性类型(id:'.$insert_id.')');
            }
            $this->success("操作成功!!!", U('Admin/GoodsRed/goodsTypeList'));
            exit;
        }
        $goodsType = $model->find($id);
        $this->assign('goodsType', $goodsType);
        return $this->fetch('_goodsType');
    }
    
    /**
     * 商品属性列表
     */
    public function goodsAttributeList(){  
    //modify     
        $goodsTypeList = M("GoodsRedType")->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch();
    }   
    
    /**
     *  商品属性列表
     */
    public function ajaxGoodsAttributeList(){            
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件                        
        I('type_id')   && $where = "$where and type_id = ".I('type_id') ;
        //modify            
        // 关键词搜索               
        $model = M('GoodsRedAttribute');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
        $goodsTypeList = M("GoodsRedType")->getField('id,name');
        $attr_input_type = array(0=>'手工录入',1=>' 从列表中选择',2=>' 多行文本框');
        $this->assign('attr_input_type',$attr_input_type);
        $this->assign('goodsTypeList',$goodsTypeList);        
        $this->assign('goodsAttributeList',$goodsAttributeList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }   
    
    /**
     * 添加修改编辑  商品属性
     */
    public  function addEditGoodsAttribute(){
                        
            $model = D("GoodsRedAttribute");                      
            $type = I('attr_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新         
            $attr_values = str_replace('_', '', I('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符            
            $attr_values = trim($attr_values);
            
            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;
            if((I('is_ajax') == 1) && IS_POST)//ajax提交验证
            {                                
                    // 数据验证            
                    $validate = \think\Loader::validate('GoodsAttribute');
                    if(!$validate->batch()->check($post_data))
                    {                          
                        $error = $validate->getError();
                        $error_msg = array_values($error);
                        $return_arr = array(
                            'status' => -1,
                            'msg' => $error_msg[0],
                            'data' => $error,
                        );
                        adminLog('操作米豆商品属性('.$error_msg[0].')');
                        $this->ajaxReturn($return_arr);
                    } else {     
                         $model->data($post_data,true); // 收集数据

                         if ($type == 2)
                         {
                             $model->isUpdate(true)->save(); // 写入数据到数据库
                             adminLog('操作米豆商品属性(id:'.$post_data['attr_id'].')');
                         }
                         else
                         {
                             $model->save(); // 写入数据到数据库
                             $insert_id = $model->getLastInsID();
                             adminLog('添加米豆商品属性(id:'.$insert_id.')');
                         }
                         $return_arr = array(
                             'status' => 1,
                             'msg'   => '操作成功',
                             'data'  => array('url'=>U('Admin/GoodsRed/goodsAttributeList')),
                         );
                         $this->ajaxReturn($return_arr);
                }  
            }                
           // 点击过来编辑时                 
           $attr_id = I('attr_id/d',0);  
           //modify
           $goodsTypeList = M("GoodsRedType")->select();           
           $goodsAttribute = $model->find($attr_id);           
           $this->assign('goodsTypeList',$goodsTypeList);                   
           $this->assign('goodsAttribute',$goodsAttribute);
           return $this->fetch('_goodsAttribute');
    }  
    
    /**
     * 更改指定表的指定字段
     */
    public function updateField(){
        $primary = array(
                'goods' => 'goods_id',
                'goods_category' => 'id',
                'brand' => 'id',            
                'goods_attribute' => 'attr_id',
        		'ad' =>'ad_id',            
        );        
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];        
        $model->save();   
        $return_arr = array(
            'status' => 1,
            'msg'   => '操作成功',                        
            'data'  => array('url'=>U('Admin/GoodsRed/goodsAttributeList')),
        );
        $this->ajaxReturn($return_arr);
    }

    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput(){
        $GoodsLogic = new RedGoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'],$_REQUEST['type_id']);
        exit($str);
    }
        
    /**
     * 删除商品
     */
    public function delGoods()
    {
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        $goods_ids = rtrim($ids,",");
        // 判断此商品是否有订单
        $ordergoods_count = Db::name('order_red_goods')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($ordergoods_count)
        {
            $goods_count_ids = implode(',',$ordergoods_count);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$goods_count_ids}】的商品有订单,不得删除!",'data'  =>'']);
        }
         // 商品团购
        $groupBuy_goods = M('group_red_buy')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($groupBuy_goods)
        {
            $groupBuy_goods_ids = implode(',',$groupBuy_goods);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$groupBuy_goods_ids}】的商品有团购,不得删除!",'data'  =>'']);
        }
        //格式化商品名
        $goods_name=M("goods_red")->whereIn('goods_id',$goods_ids)->field('goods_name')->select();  //商品表
        $goods_name=implode(',',array_column($goods_name, 'goods_name'));
        // 删除此商品        
        M("goods_red")->whereIn('goods_id',$goods_ids)->delete();  //商品表
        M("cart_red")->whereIn('goods_id',$goods_ids)->delete();  // 购物车
        M("comment_red")->whereIn('goods_id',$goods_ids)->delete();  //商品评论
        M("goods_red_consult")->whereIn('goods_id',$goods_ids)->delete();  //商品咨询
        M("goods_red_images")->whereIn('goods_id',$goods_ids)->delete();  //商品相册
        M("spec_red_goods_price")->whereIn('goods_id',$goods_ids)->delete();  //商品规格
        M("spec_red_image")->whereIn('goods_id',$goods_ids)->delete();  //商品规格图片
        M("goods_red_attr")->whereIn('goods_id',$goods_ids)->delete();  //商品属性
        M("goods_red_collect")->whereIn('goods_id',$goods_ids)->delete();  //商品收藏
        adminLog('删除米豆商品(id:'.$goods_ids.'商品名称:'.$goods_name.')');
        $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/GoodsRed/goodsList")]);
    }
    
    /**
     * 删除商品类型 
     */
    public function delGoodsType()
    {
        // 判断 商品规格
        $id = $this->request->param('id');
        $count = M("SpecRed")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品规格不得删除!',U('Admin/GoodsRed/goodsTypeList'));
        // 判断 商品属性        
        $count = M("GoodsRedAttribute")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品属性不得删除!',U('Admin/GoodsRed/goodsTypeList'));        
        // 删除分类
        M('GoodsRedType')->where("id = {$id}")->delete();
        adminLog('删除米豆商品类型(id:'.$id.') ');
        $this->success("操作成功!!!",U('Admin/GoodsRed/goodsTypeList'));
    }    

    /**
     * 删除商品属性
     */
    public function delGoodsAttribute()
    {
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！"]);
        $attrBute_ids = rtrim($ids,",");
        // 判断 有无商品使用该属性
        $count_ids = Db::name("GoodsRedAttr")->whereIn('attr_id',$attrBute_ids)->group('attr_id')->getField('attr_id',true);
        if($count_ids){
            $count_ids = implode(',',$count_ids);
            $this->ajaxReturn(['status' => -1,'msg' => "ID为【{$count_ids}】的属性有商品正在使用,不得删除!"]);
        }
        // 删除 属性
        M('GoodsRedAttribute')->whereIn('attr_id',$attrBute_ids)->delete();
        adminLog('删除米豆商品属性(id:'.$ids.')');
        $this->ajaxReturn(['status' => 1,'msg' => "操作成功!",'url'=>U('Admin/GoodsRed/goodsAttributeList')]);
    }            
    
    /**
     * 删除商品规格
     */
    public function delGoodsSpec()
    {
        $ids = I('post.ids','');
        $is_ajax = \think\Request::instance()->isAjax();
        if($is_ajax){
            empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！"]);
        }else{
             empty($ids) &&  $this->error('非法操作！');
        }
        
        $aspec_ids = rtrim($ids,",");
        // 判断 商品规格项
        $count_ids = M("SpecRedItem")->whereIn('spec_id',$aspec_ids)->group('spec_id')->getField('spec_id',true);
        if($count_ids){
            $count_ids = implode(',',$count_ids);
            if($is_ajax){
                $this->ajaxReturn(['status' => -1,'msg' => "ID为【{$count_ids}】规格，清空规格项后才可以删除!"]);
            }else{
                $this->error("ID为【{$count_ids}】规格，清空规格项后才可以删除!");
            }
        }
        // 删除分类
        M('SpecRed')->whereIn('id',$aspec_ids)->delete();
        if($is_ajax){
            adminLog('删除米豆商品删除分类(id:'.$ids.')');
            $this->ajaxReturn(['status' => 1,'msg' => "操作成功!!!",'url'=>U('Admin/GoodsRed/specList')]);
        }else{
            adminLog('删除米豆商品规格(id:'.$aspec_ids.')');
            $this->success("操作成功!!!");
        }
       
    } 
    
    /**
     * 品牌列表
     */
    public function brandList(){  
        exit('尚未开放');
        $model = M("BrandRed"); 
        $where = "";
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : "";
        $count = $model->where($where)->count();
        $Page = $pager = new Page($count,10);        
        $brandList = $model->where($where)->order("`sort` asc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $show  = $Page->show(); 
        $cat_list = M('goods_red_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
        $this->assign('cat_list',$cat_list);       
        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('brandList',$brandList);
        return $this->fetch('brandList');
    }
    
    /**
     * 添加修改编辑  商品品牌
     */
    public  function addEditBrand(){
            $id = I('id');            
            if(IS_POST)
            {
               	$data = I('post.');
                $brandVilidate = Loader::validate('Brand');
                if(!$brandVilidate->batch()->check($data)){
                    $return = ['status'=>0,'msg'=>'操作失败','result'=>$brandVilidate->getError()];
                    $this->ajaxReturn($return);
                }
                if($id){
                	M("BrandRed")->update($data);
                }else{
                	M("BrandRed")->insert($data);
                }
                adminLog('操作米豆商品品牌(id:'.$id.')');
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功','result'=>'']);
            }           
           $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
           $this->assign('cat_list',$cat_list);           
           $brand = M("Brand")->find($id);             
           $this->assign('brand',$brand);
           return $this->fetch('_brand');
    }    
    
    /**
     * 删除品牌
     */
    public function delBrand()
    {
        $ids = I('post.ids','');
        empty($ids) && $this->ajaxReturn(['status' => -1,'msg' => '非法操作！']);
        $brind_ids = rtrim($ids,",");
        // 判断此品牌是否有商品在使用
        $goods_count = Db::name('GoodsRed')->whereIn("brand_id",$brind_ids)->group('brand_id')->getField('brand_id',true);
        $use_brind_ids = implode(',',$goods_count);
        if($goods_count)
        {
            $this->ajaxReturn(['status' => -1,'msg' => 'ID为【'.$use_brind_ids.'】的品牌有商品在用不得删除!','data'  =>'']);
        }
        $res=Db::name('BrandRed')->whereIn('id',$brind_ids)->delete();
        if($res){
            adminLog('删除米豆商品品牌(id:'.$ids.')');
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/GoodsRed/brandList")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '操作失败','data'  =>'']);
    }      
    
    /**
     * 商品规格列表    
     */
    public function specList(){     
        //modify  
        $goodsTypeList = M("GoodsRedType")->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch();
    }
    
    
    /**
     *  商品规格列表
     */
    public function ajaxSpecList(){ 
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件                        
        I('type_id')   && $where = "$where and type_id = ".I('type_id') ;  
        // 关键词搜索               
        $model = D('SpecRed');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $specList = $model->where($where)->order('`type_id` desc')->limit($Page->firstRow.','.$Page->listRows)->select();        
        $GoodsLogicRed = new RedGoodsLogic();        
        foreach($specList as $k => $v)
        {       // 获取规格项     
                $arr = $GoodsLogicRed->getSpecItem($v['id']);
                $specList[$k]['spec_item'] = implode(' , ', $arr);
        }
        
        $this->assign('specList',$specList);
        $this->assign('page',$show);// 赋值分页输出
        $goodsTypeList = M("GoodsRedType")->select(); // 规格分类
        $goodsTypeList = convert_arr_key($goodsTypeList, 'id');
        $this->assign('goodsTypeList',$goodsTypeList);        
        return $this->fetch();
    }

    /**
     * 添加修改编辑  商品规格
     */
    public  function addEditSpec(){

            $model = D("SpecRed");
            $id = I('id/d',0);
            if((I('is_ajax') == 1) && IS_POST)//ajax提交验证
            {                
                // 数据验证
                $validate = \think\Loader::validate('Spec');
                $post_data = I('post.');
                //modify
                $scene = $id>0 ? 'edit' :'add';
                if (!$validate->scene($scene)->batch()->check($post_data)) {  //验证数据
                    $error = $validate->getError();
                    $error_msg = array_values($error);
                    $this->ajaxReturn(['status' => -1,'msg' => $error_msg[0],'data' => $error]);
                }
              /*  dump($post_data);
                die;*/
                $model->data($post_data, true); // 收集数据
                if ($scene == 'edit') {
                    $model->isUpdate(true)->save(); // 写入数据到数据库
                    $model->afterSave(I('id'));
                    adminLog('操作米豆商品规格(id:'.$id.')');
                } else {
                    $model->save(); // 写入数据到数据库
                    $insert_id = $model->getLastInsID();
                    $model->afterSave($insert_id);
                    adminLog('添加米豆商品规格(id:'.$insert_id.')');
                }
                // adminLog('操作米豆商品规格');
                $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url' => U('Admin/GoodsRed/specList')]);
            }                
           // 点击过来编辑时
           $spec = DB::name("SpecRed")->find($id);
           $GoodsLogic = new RedGoodsLogic();  
           $items = $GoodsLogic->getSpecItem($id);
           $spec[items] = implode(PHP_EOL, $items); 
           $this->assign('spec',$spec);
           $goodsTypeList = M("GoodsRedType")->select();           
           $this->assign('goodsTypeList',$goodsTypeList);           
           return $this->fetch('_spec');
    }  
    
    
    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect(){
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;        
        $GoodsLogic = new RedGoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('SpecRed')->where("type_id = ".I('get.spec_type/d'))->order('`order` desc')->select();
        foreach($specList as $k => $v)        
            $specList[$k]['spec_item'] = M('SpecRedItem')->where("spec_id = ".$v['id'])->order('id')->getField('id,item'); // 获取规格项                
        
        $items_id = M('SpecRedGoodsPrice')->where('goods_id = '.$goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);
        
        // 获取商品规格图片                
        if($goods_id)
        {
           $specImageList = M('SpecRedImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');                 
        }     
        $goodsinfo = M('goodsRed')->where('goods_id',$goods_id)->find();        
        $this->assign('specImageList',$specImageList);
        $this->assign('items_ids',$items_ids);
        $this->assign('specList',$specList);
        $this->assign('goodsinfo',$goodsinfo);
        return $this->fetch('ajax_spec_select');
    }    
    
    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */    
    public function ajaxGetSpecInput(){     
         $GoodsLogic = new RedGoodsLogic();
         $goods_id = I('goods_id/d') ? I('goods_id/d') : 0;
         $str = $GoodsLogic->getSpecInput($goods_id ,I('post.spec_arr/a',[[]]));
         exit($str);   
    }
    
    /**
     * 删除商品相册图
     */
    public function del_goods_images()
    {
        $path = I('filename','');

        M('goods_red_images')->where("image_url = '$path'")->delete();
        adminLog('删除米豆商品相册图');
    }

    /**
     * 初始化商品关键词搜索
     */
    public function initGoodsSearchWord(){
        $searchWordLogic = new RedSearchWordLogic();
        $successNum = $searchWordLogic->initGoodsSearchWord();
        $this->success('成功初始化'.$successNum.'个搜索关键词');
    }

    /**
     * 初始化地址json文件
     */
    public function initLocationJsonJs()
    {
        $goodsLogic = new RedGoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        file_put_contents(ROOT_PATH."public/js/locationJson.js", "var locationJsonInfoDyr = ".json_encode($region_list, JSON_UNESCAPED_UNICODE).';');
        $this->success('初始化地区json.js成功。文件位置为'.ROOT_PATH."public/js/locationJson.js");
    }


      /**
     * 判断
     */    
    public function ajaxGetstoreSpecInput(){   
        $goods_id = I('goods_id/d');  
        $stockgoodsType = M("spec_red_goods_price")->where('goods_id='.$goods_id)->select();
        if ($stockgoodsType) {
            $str="1";
        }else{
            $str="2";
        }
        exit($str);   
    }
     
    /**
     * 实体店搜索
    */    
    public function ajaxGetshitidian(){   
        $cname = I('cname');  
        if (!empty($cname)) {
        $where = array(
            'cname'=>array('like','%'.$cname."%"),
            'parent_id'=>array('neq','0')
        );
        $comid = M('Company')->where($where)->select();
        foreach ($comid as $key => $value) {
           $str[$key]['cid']=$value['cid'];
           $str[$key]['cname']=$value['cname'];
        }
        }
        exit(json_encode($str));
        
       
    } 
    /**
     * 子公司
    */    
    public function ajaxGetzigongsi(){   
        $cid = I('cid');  
        if (!empty($cid)) {
            $where = array(
                'parent_id'=>array('eq',$cid),
            );
            $comid = M('Company')->where($where)->field('cid,cname,ispush,isallow')->order("ispush desc,cid desc")->select();
        }
        exit(json_encode($comid));
       
    }  
     

}