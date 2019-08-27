<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use think\AjaxPage;
use think\Cache;
use think\Loader;
use think\Page;
use think\Db;
//use think\rquest;


class Goods extends Base {
    /**
     *  商品分类列表
     */
    public function categoryList(){    
        $cat_list = cache('cat_list');
        if(empty($cat_list)){
            $GoodsLogic = new GoodsLogic();               
            $cat_list = $GoodsLogic->goods_cat_list();
            cache('cat_list',$cat_list);
        }
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
        
            $GoodsLogic = new GoodsLogic();        
            if(IS_GET)
            {
                $goods_category_info = D('GoodsCategory')->where('id='.I('GET.id',0))->find();
                $level_cat = $GoodsLogic->find_parent_cat($goods_category_info['id']); // 获取分类默认选中的下拉框
                
                $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单                
                $this->assign('level_cat',$level_cat);                
                $this->assign('cat_list',$cat_list);                 
                $this->assign('goods_category_info',$goods_category_info);      
                return $this->fetch('_category');
                exit;
            }

            $GoodsCategory = D('GoodsCategory'); //

            $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新                        
            //ajax提交验证
            if(I('is_ajax') == 1)
            {
                // 数据验证            
                $validate = \think\Loader::validate('GoodsCategory');
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
                        $children = M('goods_category')->where($children_where)->max('level');
                        if (I('parent_id_1')) {
                            $parent_level = M('goods_category')->where(array('id' => I('parent_id_1')))->getField('level', false);
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
                            $parent_level = M('goods_category')->where(array('id' => I('parent_id_2')))->getField('level', false);
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
                    
                    if($type == 1){
                        //查找同级分类是否有重复分类
                        $par_id = ($GoodsCategory->parent_id > 0) ? $GoodsCategory->parent_id : 0;
                        $same_cate = M('GoodsCategory')->where(['parent_id'=>$par_id , 'name'=>$GoodsCategory['name']])->find();
                        if($same_cate){
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
                        adminLog('编辑商品分类(id:'.I('id').')');

                    }
                    else
                    {
                        $GoodsCategory->save(); // 写入数据到数据库
                        $insert_id = $GoodsCategory->getLastInsID();
                        $GoodsLogic->refresh_cat($insert_id);
                        adminLog('添加商品分类(id:'.$insert_id.')');

                    }
                    $return_arr = array(
                        'status' => 1,
                        'msg'   => '操作成功',
                        'data'  => array('url'=>U('Admin/Goods/categoryList')),
                    );
                    $this->ajaxReturn($return_arr);

                }  
            }
    }
    
    /**
     * 获取商品分类 的帅选规格 复选框
     */
    public function ajaxGetSpecList(){
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_spec');        
        $filter_spec_arr = explode(',',$filter_spec);        
        $str = $GoodsLogic->GetSpecCheckboxList($_REQUEST['type_id'],$filter_spec_arr);  
        $str = $str ? $str : '没有可帅选的商品规格';
        exit($str);        
    }
 
    /**
     * 获取商品分类 的帅选属性 复选框
     */
    public function ajaxGetAttrList(){
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_attr = M('GoodsCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_attr');        
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
        $count = Db::name("goods_category")->where("parent_id = {$ids}")->count("id");
        $count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下还有分类不得删除!']);
        // 判断是否存在商品
        $goods_count = Db::name('Goods')->where("cat_id = {$ids}")->count('1');
        $goods_count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下有商品不得删除!']);
        // 删除分类
        DB::name('goods_category')->where('id',$ids)->delete();
        adminLog('删除分类(id:'.$ids.')');
        $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/Goods/categoryList')]);
    }
    
    
    /**
     *  商品列表
     */
    public function goodsList(){      
        $GoodsLogic = new GoodsLogic();        
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getCategoryList();
        $suppliersList = $GoodsLogic->getSuppliers();
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        $this->assign('suppliersList',$suppliersList);
        return $this->fetch();
    }

    #获取商品下级分类
    #2018-12-11  张洪凯
    public function ajax_get_category(){
        $cat_id = I('cat_id/d',0);
        $GoodsLogic = new GoodsLogic();
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
        
        $where = ' 1 = 1 '; // 搜索条件                
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
            $grandson_ids = getCatGrandson($cat_id); 
            $where .= " and g.cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        I('suppliers_id') && $where .= " and g.suppliers_id = ".I('suppliers_id') ;

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " AND g.suppliers_id > 0";
        } else if($sp && $sp == 2){
            $where .= " AND g.suppliers_id = 0";
        }
        $count = M('Goods')
          ->alias('g')
          ->field('g.*')
          ->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','LEFT')
          ->where($where)
          ->count();
      
        $Page  = new AjaxPage($count,20);
        /**  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        */
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('goods')
          ->alias('g')
          ->field('g.*')
          ->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','LEFT')
          ->where($where)
          ->order($order_str)
          ->limit($Page->firstRow.','.$Page->listRows)
          ->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList',$catList);
        $this->assign('goodsList',$goodsList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    function export_goods(){
        $where = ' 1 = 1 '; // 搜索条件                
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
            $grandson_ids = getCatGrandson($cat_id); 
            $where .= " and g.cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        I('suppliers_id') && $where .= " and g.suppliers_id = ".I('suppliers_id') ;

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

        $goodsList = M('goods')
          ->alias('g')
          ->field('g.*')
          ->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','LEFT')
          ->where($where)
          ->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">序号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;">商品ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供货商</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">货号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">分类</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">本店售价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">市场价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品成本价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运营成本价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否参与大盘的可返米豆比率</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">可返米豆比率</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否包邮</td>';
        $strTable .= '</tr>';
        if(is_array($goodsList)){
            $n = 0;
            foreach($goodsList as $k=>$val){
                $n++;
                if($val['is_z_back'] == 1){
                    $is_z_back = '是';
                    $val['midou_back_percent'] = tpCache('shoppingred.midou_back_percent');
                } else { 
                    $is_z_back = '否'; 
                }

                if($val['is_free_shipping'] == 1) $is_free_shipping = "是"; else $is_free_shipping = "否";  

                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$n.'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['goods_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.get_suppliers_name($val['suppliers_id']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_sn'].' </td>'; 
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$catList[$val['cat_id']]['name'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shop_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['market_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_operating'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_z_back.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_back_percent'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_free_shipping.'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($goodsList);
        downloadExcel($strTable,'goods');
        adminLog('导出商品');
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
                $goods = M('goods')->field('goods_id,goods_name')->where(array('goods_id' => array('eq', $goods_id)))->select();
                $goods_info = M('goods')->where(array('goods_id' => array('eq', $goods_id)))->find();
            }
            $this->assign('goods_info',$goods_info);
        } else {
            $goods_id_array = I('get.goods_id_array');
            if (!empty($goods_id_array)) {
                $goods = M('goods')->field('goods_id,goods_name')->where(array('goods_id' => array('IN', $goods_id_array)))->select();
            }
        }
        $this->assign('goods',$goods);
        return $this->fetch();
    }

     /**
     *
     * @time 2018/11/13
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
            M('Goods')->save($data);
        } else {
            //个体消息
            if (!empty($goods)) {
                foreach ($goods as $key) {
                    M('goods')->where('goods_id = '.$key)->save($data);
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
        adminLog('现金商品审核操作(goods_id:'.$goods_ids.'；内容：'.$is_checkname.')');
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
                M('goods')->where('goods_id = '.$goods_id)->save($data);
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
            M('goods')->where('goods_id = '.$goods_id)->save($data);
            echo "1";
            exit();
        }
        
        
    }
     
    // 商品库存记录
    public function stock_list(){
    	$model = M('stock_log');
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
    		$map['stock.goods_name'] = array('like',"%$goods_name%");
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
   //     $map['is_red']  =   ['eq',$this->is_red];
    	$count = $model->alias('stock')->field('stock.id')->join('__GOODS__ goods','goods.goods_id = stock.goods_id','left')->where($map)->count();
    	$Page  = new Page($count,20);
    	$show = $Page->show();
    	$this->assign('pager',$Page);
    	$this->assign('page',$show);// 赋值分页输出
    	$stock_list = $model->alias('stock')->field('stock.*')->join('__GOODS__ goods','goods.goods_id = stock.goods_id')->where($map)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
    	$this->assign('stock_list',$stock_list);
    	return $this->fetch();
    }


    public function export_stock_list(){
        $model = M('stock_log');
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
        adminLog('导出库存');
        exit();
    }

    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {
        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\admin\model\Goods();
        $goods_id = I('goods_id');
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if ((I('is_ajax') == 1) && IS_POST) {
            // 数据验证
            $virtual_indate = input('post.virtual_indate');//虚拟商品有效期
            $return_url =  U('/admin/Goods/goodsList');

            $data = input('post.');
            $validate = \think\Loader::validate('Goods');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            }
            $data['virtual_indate'] = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            $data['exchange_integral'] = ($data['is_virtual'] == 1) ? 0 : $data['exchange_integral'];
            $Goods->data($data, true); // 收集数据
            //$Goods->on_time = time(); // 上架时间
            I('cat_id_2') && ($Goods->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($Goods->cat_id = I('cat_id_3'));

            I('extend_cat_id_2') && ($Goods->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($Goods->extend_cat_id = I('extend_cat_id_3'));
            $Goods->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
            $Goods->spec_type = $Goods->goods_type;
            $price_ladder = array();
            if ($Goods->ladder_amount[0] > 0) {
                foreach ($Goods->ladder_amount as $key => $value) {
                    $price_ladder[$key]['amount'] = intval($Goods->ladder_amount[$key]);
                    $price_ladder[$key]['price'] = floatval($Goods->ladder_price[$key]);
                }
                $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
                $price_ladder_max = count($price_ladder);
                if ($price_ladder[$price_ladder_max - 1]['price'] >= $Goods->shop_price) {
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
                $Goods->price_ladder = serialize($price_ladder);
            } else {
                $Goods->price_ladder = '';
            }

            if ($type == 2) {
                $Goods->isUpdate(true)->save(); // 写入数据到数据库
                // 修改商品后购物车的商品价格也修改一下
                M('cart')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                    'market_price'       => I('market_price'),   //市场价
                    'goods_price'        => I('shop_price'),     // 本店价
                    'member_goods_price' => I('shop_price'),     // 会员折扣价
                    'cost_price'         => I('cost_price'),     // 成本价
                    'cost_operating'     => I('cost_operating'), // 运营成本价
                ));
                adminLog('编辑商品(id:'.$goods_id.'；本店价:'.I('shop_price').'；市场价:'.I('market_price').'；商品成本价:'.I('cost_price').'；运营成本价:'.I('cost_operating').')');

            } else {
                $Goods->save(); // 写入数据到数据库
                $goods_id = $insert_id = $Goods->getLastInsID();
                db('goods')->where('goods_id',$goods_id)->setField('sort',$goods_id);
                adminLog('添加商品(id:'.$insert_id.'；本店价:'.$data['shop_price'].'；市场价:'.$data['market_price'].'；商品成本价:'.$data['cost_price'].'；运营成本价:'.$data['cost_operating'].')');
            }

            $Goods->afterSave($goods_id);
            $GoodsLogic->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => $return_url),
            );
            $this->ajaxReturn($return_arr);
        }

        $goodsInfo = M('Goods')->where('goods_id=' . I('GET.id', 0))->find();
        if ($goodsInfo['price_ladder']) {
            $goodsInfo['price_ladder'] = unserialize($goodsInfo['price_ladder']);
        }
        $level_cat = $GoodsLogic->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        if($goodsInfo['extend_cat_id'])
            $level_cat2 = $GoodsLogic->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $brandList = $GoodsLogic->getSortBrands();
        $goodsType = M("GoodsType")->select();
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
        $shipping_where['suppliers_id'] = array('eq', $suppliers_id);*/
        $plugin_shipping = M('plugin')->where("status=1 and type='shipping' and (suppliers_id=$suppliers_id or suppliers_id=0)")->select();//插件物流


        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        //$this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsImages")->where('goods_id =' . I('GET.id', 0))->select();

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
    /**
     * 通过税率计算可返米豆比率及运营成本价
     */
    public function get_midou(){
        $tax_rate = I('tax_rate');
        $shop_price = I('shop_price');
        $cost_price = I('cost_price');
        //计算毛利
        $profit = $shop_price-$cost_price;
        //计算增值税&综合成本
        if($tax_rate==3){
            $value_added_tax = round($shop_price/1.16*0.16,2);
            $comprehensive_cost = round($profit*0.371,2);
        }
        elseif($tax_rate==10){
            $value_added_tax = round(($shop_price/1.1-$cost_price/1.1)*0.1,2);
            $comprehensive_cost = round($profit*0.453,2);
        }
        else{
            $value_added_tax = round(($shop_price/1.16-$cost_price/1.16)*0.16,2);
            $comprehensive_cost = round($profit*0.4,2);
        }
        //计算附加税
        $additional_tax = round($value_added_tax*0.12,2);
        //计算分摊费用
        $share_money = $profit*tpCache("settlement.share_rate");
        //计算运营成本
        $operating_costs = $value_added_tax+$additional_tax+$share_money+$comprehensive_cost;
        $midou_back_percent = floor(100*($comprehensive_cost/$profit));
        $data["operating_costs"] = $operating_costs;
        $data["midou_back_percent"] = $midou_back_percent;
        $this->ajaxReturn($data);
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
        $model = M("GoodsType");                
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
        $model = M("GoodsType");
        if (IS_POST) {
            $data = $this->request->post();
            if ($id){
                DB::name('GoodsType')->update($data);
                adminLog('编辑商品类型(id:'.$id.')');

            }else{
                DB::name('GoodsType')->insert($data);
                $insert_id = DB::name('GoodsType')->getLastInsID();
                adminLog('添加商品类型(id:'.$insert_id.')');
            }
            $this->success("操作成功!!!", U('Admin/Goods/goodsTypeList'));
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
        $goodsTypeList = M("GoodsType")->select();
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
        //$where .= " and is_red= {$this->is_red}";                 
        // 关键词搜索               
        $model = M('GoodsAttribute');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
        $goodsTypeList = M("GoodsType")->getField('id,name');
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
                        
            $model = D("GoodsAttribute");                      
            $type = I('attr_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新         
            $attr_values = str_replace('_', '', I('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符            
            $attr_values = trim($attr_values);
            
            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;
       //     $post_data['is_red']    =   $this->is_red;
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
                    adminLog('操作商品属性('.$error_msg[0].')');
                    $this->ajaxReturn($return_arr);
                } else {     
                    $model->data($post_data,true); // 收集数据
                    
                    if ($type == 2)
                    {                                 
                        $model->isUpdate(true)->save(); // 写入数据到数据库       
                    adminLog('操作商品属性(id:'.$post_data['attr_id'].')');

                    }
                    else
                    {
                        $model->save(); // 写入数据到数据库
                        $insert_id = $model->getLastInsID();      
                    adminLog('添加商品属性(id:'.$insert_id.')');

                    }
                    $return_arr = array(
                        'status' => 1,
                        'msg'   => '操作成功',                        
                        'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
                    );
                    $this->ajaxReturn($return_arr);
                }  
            }                
           // 点击过来编辑时                 
           $attr_id = I('attr_id/d',0);  
           $goodsTypeList = M("GoodsType")->select();           
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
            'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
        );

        $this->ajaxReturn($return_arr);
    }

    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput(){
        $GoodsLogic = new GoodsLogic();
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
        $ordergoods_count = Db::name('OrderGoods')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($ordergoods_count)
        {
            $goods_count_ids = implode(',',$ordergoods_count);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$goods_count_ids}】的商品有订单,不得删除!",'data'  =>'']);
        }
         // 商品团购
        $groupBuy_goods = M('group_buy')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($groupBuy_goods)
        {
            $groupBuy_goods_ids = implode(',',$groupBuy_goods);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$groupBuy_goods_ids}】的商品有团购,不得删除!",'data'  =>'']);
        }
        //格式化商品名
        $goods_name=M("Goods")->whereIn('goods_id',$goods_ids)->field('goods_name')->select();  //商品表
        $goods_name=implode(',',array_column($goods_name, 'goods_name'));
        // 删除此商品
        M("Goods")->whereIn('goods_id',$goods_ids)->delete();  //商品表
        M("cart")->whereIn('goods_id',$goods_ids)->delete();  // 购物车
        M("comment")->whereIn('goods_id',$goods_ids)->delete();  //商品评论
        M("goods_consult")->whereIn('goods_id',$goods_ids)->delete();  //商品咨询
        M("goods_images")->whereIn('goods_id',$goods_ids)->delete();  //商品相册
        M("spec_goods_price")->whereIn('goods_id',$goods_ids)->delete();  //商品规格
        M("spec_image")->whereIn('goods_id',$goods_ids)->delete();  //商品规格图片
        M("goods_attr")->whereIn('goods_id',$goods_ids)->delete();  //商品属性
        M("goods_collect")->whereIn('goods_id',$goods_ids)->delete();  //商品收藏
        adminLog('删除商品(id:'.$goods_ids.' 商品名称:'.$goods_name.')');
        $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/goods/goodsList")]);
    }
    
    /**
     * 删除商品类型 
     */
    public function delGoodsType()
    {
        // 判断 商品规格
        $id = $this->request->param('id');
        $count = M("Spec")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品规格不得删除!',U('Admin/Goods/goodsTypeList'));
        // 判断 商品属性        
        $count = M("GoodsAttribute")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品属性不得删除!',U('Admin/Goods/goodsTypeList'));        
        // 删除分类
        M('GoodsType')->where("id = {$id}")->delete();
        adminLog('删除商品类型(id:'.$id.')');
        $this->success("操作成功!!!",U('Admin/Goods/goodsTypeList'));
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
        $count_ids = Db::name("GoodsAttr")->whereIn('attr_id',$attrBute_ids)->group('attr_id')->getField('attr_id',true);
        if($count_ids){
            $count_ids = implode(',',$count_ids);
            $this->ajaxReturn(['status' => -1,'msg' => "ID为【{$count_ids}】的属性有商品正在使用,不得删除!"]);
        }
        // 删除 属性
        M('GoodsAttribute')->whereIn('attr_id',$attrBute_ids)->delete();
        adminLog('删除商品属性(id:'.$attrBute_ids.')');
        $this->ajaxReturn(['status' => 1,'msg' => "操作成功!",'url'=>U('Admin/Goods/goodsAttributeList')]);
    }            
    
    /**
     * 删除商品规格
     */
/*    public function delGoodsSpec()
    {
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！"]);
        $aspec_ids = rtrim($ids,",");
        // 判断 商品规格项
        $count_ids = M("SpecItem")->whereIn('spec_id',$aspec_ids)->group('spec_id')->getField('spec_id',true);
        if($count_ids){
            $count_ids = implode(',',$count_ids);
            $this->ajaxReturn(['status' => -1,'msg' => "ID为【{$count_ids}】规格，清空规格项后才可以删除!"]);
        }
        // 删除分类
        M('Spec')->whereIn('id',$aspec_ids)->delete();
        $this->ajaxReturn(['status' => 1,'msg' => "操作成功!!!",'url'=>U('Admin/Goods/specList')]);
    } */
    /*删除商品规格
    原来的有BUG ，需要修改一下，判断是否为AJAX提交再进行输出
    */
    //Request::instance()->isAjax()
    public function delGoodsSpec()
    {
     //   Loader::import('Request');

        $ids = I('request.ids','');
        $msg_data = ['status' => -1,'msg' =>"非法操作！"];
        if(empty($ids)){
            if(\think\Request::instance()->isAjax()){
                $this->ajaxReturn($msg_data);
            }else{
                $this->error($msg_data['msg']);
            }
        } 

        $aspec_ids = rtrim($ids,",");
        // 判断 商品规格项
        $count_ids = M("SpecItem")->whereIn('spec_id',$aspec_ids)->group('spec_id')->getField('spec_id',true);
        if($count_ids){
            $count_ids = implode(',',$count_ids);
            $msg_data = ['status' => -1,'msg' => "ID为【{$count_ids}】规格，清空规格项后才可以删除!"];
            if(\think\Request::instance()->isAjax()){
                $this->ajaxReturn($msg_data);
            }else{

                $this->error($msg_data['msg']);

            }
        }
      
        // 删除分类
        M('Spec')->whereIn('id',$aspec_ids)->delete();
        $msg_data = ['status' => 1,'msg' => "操作成功!!!",'url'=>U('Admin/Goods/specList')];
        adminLog('删除商品规格(id:'.$aspec_ids.')');
        if(\think\Request::instance()->isAjax()){
            $this->ajaxReturn($msg_data);
        }else{
            $this->error($msg_data['msg']);
        }
    } 
    /**
     * 品牌列表
     */
    public function brandList(){  
        $model = M("Brand"); 
        $where = "";
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : "";
        $count = $model->where($where)->count();
        $Page = $pager = new Page($count,10);        
        $brandList = $model->where($where)->order("`sort` asc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $show  = $Page->show(); 
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
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
                	M("Brand")->update($data);
                    adminLog('操作商品品牌(id:'.$id.')');
                }else{
                	M("Brand")->insert($data);
                    $insert_id = M("Brand")->getLastInsID();  
                    adminLog('操作商品品牌(id:'.$insert_id.')');
                }
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
        $goods_count = Db::name('Goods')->whereIn("brand_id",$brind_ids)->group('brand_id')->getField('brand_id',true);
        $use_brind_ids = implode(',',$goods_count);
        if($goods_count)
        {
            $this->ajaxReturn(['status' => -1,'msg' => 'ID为【'.$use_brind_ids.'】的品牌有商品在用不得删除!','data'  =>'']);
        }
        $res=Db::name('Brand')->whereIn('id',$brind_ids)->delete();
        if($res){
            adminLog('删除品牌(id:'.$ids.')');
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/goods/brandList")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '操作失败','data'  =>'']);
    }      
    
    /**
     * 商品规格列表    
     */
    public function specList(){       
        $goodsTypeList = M("GoodsType")->select();
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
    //    $where .= " and is_red = {$this->is_red}";        
        // 关键词搜索               
        $model = D('spec');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $specList = $model->where($where)->order('`type_id` desc')->limit($Page->firstRow.','.$Page->listRows)->select();        
        $GoodsLogic = new GoodsLogic();        
        foreach($specList as $k => $v)
        {       // 获取规格项     
                $arr = $GoodsLogic->getSpecItem($v['id']);
                $specList[$k]['spec_item'] = implode(' , ', $arr);
        }
        $this->assign('specList',$specList);
        $this->assign('page',$show);// 赋值分页输出
        $goodsTypeList = M("GoodsType")->select(); // 规格分类
        $goodsTypeList = convert_arr_key($goodsTypeList, 'id');
        $this->assign('goodsTypeList',$goodsTypeList);        
        return $this->fetch();
    }

    /**
     * 添加修改编辑  商品规格
     */
    public  function addEditSpec(){

            $model = D("spec");
            $id = I('id/d',0);
            if((I('is_ajax') == 1) && IS_POST)//ajax提交验证
            {                
                // 数据验证
                $validate = \think\Loader::validate('Spec');
                $post_data = I('post.');
                $scene = $id>0 ? 'edit' :'add';
                if (!$validate->scene($scene)->batch()->check($post_data)) {  //验证数据
                    $error = $validate->getError();
                    $error_msg = array_values($error);
                    $this->ajaxReturn(['status' => -1,'msg' => $error_msg[0],'data' => $error]);
                }
                $model->data($post_data, true); // 收集数据
                if ($scene == 'edit') {
                    $model->isUpdate(true)->save(); // 写入数据到数据库
                    $model->afterSave(I('id'));
                adminLog('操作商品规格(id:'.$id.')');

                } else {
                    $model->save(); // 写入数据到数据库
                    $insert_id = $model->getLastInsID();
                    $model->afterSave($insert_id);
                adminLog('添加商品规格(id:'.$insert_id.')');

                }
                $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url' => U('Admin/Goods/specList')]);
            }                
           // 点击过来编辑时
           $spec = DB::name("spec")->find($id);
           $GoodsLogic = new GoodsLogic();  
           $items = $GoodsLogic->getSpecItem($id);
           $spec[items] = implode(PHP_EOL, $items); 
           $this->assign('spec',$spec);
           
           $goodsTypeList = M("GoodsType")->select();           
           $this->assign('goodsTypeList',$goodsTypeList);           
           return $this->fetch('_spec');
    }  
    
    
    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect(){
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;        
        $GoodsLogic = new GoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('Spec')->where("type_id = ".I('get.spec_type/d'))->order('`order` desc')->select();
        foreach($specList as $k => $v)        
            $specList[$k]['spec_item'] = M('SpecItem')->where("spec_id = ".$v['id'])->order('id')->getField('id,item'); // 获取规格项                
        
        $items_id = M('SpecGoodsPrice')->where('goods_id = '.$goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);       
        
        // 获取商品规格图片                
        if($goods_id)
        {
           $specImageList = M('SpecImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');                 
        }   
        $goodsinfo = M('goods')->where('goods_id',$goods_id)->find();     
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
         $GoodsLogic = new GoodsLogic();
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
        M('goods_images')->where("image_url = '$path'")->delete();
    }

    /**
     * 初始化商品关键词搜索
     */
    public function initGoodsSearchWord(){
        $searchWordLogic = new SearchWordLogic();
        $successNum = $searchWordLogic->initGoodsSearchWord();
        $this->success('成功初始化'.$successNum.'个搜索关键词');
    }

    /**
     * 初始化地址json文件
     */
    public function initLocationJsonJs()
    {
        $goodsLogic = new GoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        file_put_contents(ROOT_PATH."public/js/locationJson.js", "var locationJsonInfoDyr = ".json_encode($region_list, JSON_UNESCAPED_UNICODE).';');
        $this->success('初始化地区json.js成功。文件位置为'.ROOT_PATH."public/js/locationJson.js");
    }


    /**
     * [处理金蝶导出数据]
     * @author 王牧田
     * @date 2018-12-19
     */
    public function export_goods_jindie_data(){

        Loader::import('PHPExcel.PHPExcel');
//        Loader::import('PHPExcel.PHPExcel.IOFactory.PHPExcel_IOFactory');
        $condition=[];
        $begin = I('addtimebegin',date("Y-m-d",strtotime("-1 month")));
        $end = I('addtimeend',date("Y-m-d"));
        $begin = strtotime($begin);
        $end = strtotime($end);
        $condition['on_time'] = array('between',"$begin,$end");
        $catid1 = I('post.catid1',0);
        if($catid1!=0){
            $where["parent_id_path"] = ["like","0_".$catid1."%"];
            $where["level"] = 3;
            $goods_category_id = db('goods_category')->where($where)->column("id");
            $condition['cat_id']=["in",$goods_category_id];
        }
        //

        $limitpage = 30;
        $count = db('goods')
            ->alias("g")
            ->where($condition)
            ->join("goods_category gc","g.cat_id = gc.id","left")
            ->join("spec_goods_price sgp","sgp.goods_id = g.goods_id","inner")
            ->count();

        if($count > 1000){
            $msg["error"] = "1";
            $msg["msg"] = "当前导出数量为{$count}条，超出导出数量1000条";
            $this->ajaxReturn($msg);
        }

        $Page  = new Page($count,$limitpage);


        $goods= db('goods')
            ->limit($Page->firstRow,$Page->listRows)
            ->where($condition)
            ->field("gc.name,gc.id as gcid,g.goods_id,g.goods_name,sgp.key_name,sgp.item_id")
            ->alias("g")
            ->join("goods_category gc","g.cat_id = gc.id","left")
            ->join("spec_goods_price sgp","sgp.goods_id = g.goods_id","inner")
            ->order("gcid asc")
            ->select();


        //整理 分类 和 商品的数据
        $goodresult = [];
        foreach ($goods as $row){
            $goodresult[$row['gcid']]['gcid'] = $row['gcid'];
            $goodresult[$row['gcid']]['name'] = $row['name'];
            $goodresult[$row['gcid']]['data'][] = array(
                "goods_id"=>$row['goods_id'],
                "goods_name"=>$row['goods_name'],
                "key_name"=>$row['key_name'],
                'item_id'=>$row['item_id']
            );
        }




        $filename = "./public/jindie/yangli.xlsx";
        $objReader = new \PHPExcel_Reader_Excel2007();
        $objExcel = $objReader->load($filename);
        $currentSheet = $objExcel->getSheet(0);

        $k=0;
        $excelDatas = [];
        foreach($currentSheet->getRowIterator() as $key => $row){
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach($cellIterator as $num=>$cell){
                $excelDatas[$k][$num] = $cell->getValue();//获取excel里的数据转成数组
            }
            $k++;
        }

        unset($excelDatas[0]);


        $orderList = [];
        $num=1;
        foreach ($goodresult as $val){
            $num++;

            //分类
            foreach ($excelDatas[1] as $k=>$row){
                $row=(gettype($row) == "boolean")?(($row)?"TRUE":"FALSE"):$row;
                switch ($k){
                    case "A":
                        $orderList[]=array("num"=>"A","value"=>$val['gcid']);
                        break;
                    case "B":
                        $orderList[]=array("num"=>"B","value"=>$val['name']);
                        break;
                    default:
                        $orderList[]=array("num"=>$k,"value"=>$row);
                        break;
                }

            }


            //商品
            foreach ($val["data"] as $v){
                $num++;
                $guid = $this->guid();
                foreach ($excelDatas[2] as $k=>$row){
                    $row=(gettype($row) == "boolean")?(($row)?"TRUE":"FALSE"):$row;
                    switch ($k){
                        case "A":
                            $orderList[]=array("num"=>"A","value"=>$val['gcid'].".".$v['goods_id'].$v['item_id']);
                            break;
                        case "B":
                            $orderList[]=array("num"=>"B","value"=>$v['goods_name']);
                            break;
                        case "E":
                            $orderList[]=array("num"=>"E","value"=>$val['name']."_".$v['goods_name']);
                            break;
                        case "G":
                            $orderList[]=array("num"=>"G","value"=>$v['key_name']);
                            break;
                        case "HN":
                            $orderList[]=array("num"=>"HN","value"=>$guid);
                            break;
                        default:
                            $orderList[]=array("num"=>$k,"value"=>$row);
                            break;
                    }

                    $data[]=[
                        "code"=>$val['gcid'].".".$v['goods_id'].$v['item_id'],
                        "guid"=>$guid,
                        "goods_id"=>$v['goods_id'],
                        "item_id"=>$v['item_id'],
                    ];
                }
            }



        }
        //$objExcel->getActiveSheet()->setCellValueExplicit("HN{$num}", "{$this->guid()}", \PHPExcel_Cell_DataType::TYPE_STRING);
        $user_id = $_SESSION['think']['user']['user_id'];
        $dir_url = "./public/data/exportgoods_".$user_id."/";

        if(!is_dir($dir_url)) {
            mkdir($dir_url, 0777, true);
        }
        if($Page->nowPage <= $Page->totalPages){
            Cache::set('jindie'.$Page->nowPage,json_encode($orderList),3600);
            file_put_contents($dir_url."/jindie_data_".$Page->nowPage.".txt",json_encode($orderList));
            return ceil($Page->nowPage/$Page->totalPages * 100);
        }

    }



    public function export_datajindie_order(){
        set_time_limit(0);
//        ini_set('memory_limit','700M');
        Loader::import('PHPExcel.PHPExcel');
        $user_id = $_SESSION["think"]["user"]["user_id"];
        $dir_url = "./public/data/exportgoods_".$user_id."/";
        $files = scandir($dir_url);
        unset($files[0]);
        unset($files[1]);
        $filelenght = count($files);
        $orderList = [];
        for ($i = 1; $i <= $filelenght; $i++) {
            $data = file_get_contents($dir_url . "jindie_data_" . $i . ".txt");
            $row = json_decode($data,true);
            $orderList = array_merge($orderList, $row);
        }

        $num = 1;
        $filename = "./public/jindie/wuliao.xlsx";
        $objReader = new \PHPExcel_Reader_Excel2007();
        $objExcel = $objReader->load($filename);
        foreach ($orderList as $row){
           if($row["num"] == "A"){
               $num++;
           }
           $objExcel->getActiveSheet()->setCellValueExplicit("{$row["num"]}{$num}", "{$row["value"]}", \PHPExcel_Cell_DataType::TYPE_STRING);
        }


        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name=$fileName.xls');
        header("Content-Disposition:attachment;filename=金蝶数据".date("Y-m-d H:i:s").".xls");//attachment新窗口打印inline本窗口打印
        $objWriter = \PHPExcel_IOFactory::createWriter($objExcel, 'Excel2007');
        $objWriter->save('php://output');
        $this->removeDir($dir_url);
    }



    /**
     * [金蝶数据导出]
     */
    public function export_goods_huadie(){
        $goods= db('goods')
            ->field("gc.name,gc.id as gcid,g.goods_id,g.goods_name,sgp.key_name")
            ->alias("g")
            ->join("goods_category gc","g.cat_id = gc.id","left")
            ->join("spec_goods_price sgp","sgp.goods_id = g.goods_id","inner")
            ->order("gcid asc")
            ->limit(10)
            ->select();

        $goodresult = [];
        foreach ($goods as $row){
            $goodresult[$row['gcid']]['gcid'] = $row['gcid'];
            $goodresult[$row['gcid']]['name'] = $row['name'];
            $goodresult[$row['gcid']]['data'][] = array("goods_id"=>$row['goods_id'],"goods_name"=>$row['goods_name'],"key_name"=>$row['key_name']);
        }

        Loader::import('PHPExcel.PHPExcel');
        Loader::import('PHPExcel.PHPExcel.IOFactory.PHPExcel_IOFactory');

        $filename = "./public/jindie/wuliao.xlsx";
        $objReader = new \PHPExcel_Reader_Excel2007();
        $objExcel = $objReader->load($filename);
        $currentSheet = $objExcel->getSheet(0);

        $k=0;
        $excelDatas = [];
        foreach($currentSheet->getRowIterator() as $key => $row){
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach($cellIterator as $num=>$cell){
                $excelDatas[$k][$num] = $cell->getValue();//获取excel里的数据转成数组
            }
            $k++;
        }

        unset($excelDatas[0]);

        /*
        $num=1;
        foreach ($goods as $k => $val){
            foreach ($excelDatas as $k=>$row){
                $num++;
                $flag=$row["C"];

                foreach ($row as $s=>$v){

                    $v=(gettype($v) == "boolean")?(($v)?"TRUE":"FALSE"):$v;

                    $objExcel->getActiveSheet()->setCellValueExplicit("{$s}{$num}", "{$v}", \PHPExcel_Cell_DataType::TYPE_STRING);

                    if($flag){//商品
                        $objExcel->getActiveSheet()->setCellValueExplicit("A{$num}", "{$val['gcid']}.{$val['goods_id']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                        $objExcel->getActiveSheet()->setCellValueExplicit("B{$num}", "{$val['goods_name']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                        $objExcel->getActiveSheet()->setCellValueExplicit("E{$num}", "{$val['name']}_{$val['goods_name']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                        $objExcel->getActiveSheet()->setCellValueExplicit("G{$num}", "{$val['key_name']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                    }else{    // 分类
                        $objExcel->getActiveSheet()->setCellValueExplicit("A{$num}", "{$val['gcid']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                        $objExcel->getActiveSheet()->setCellValueExplicit("B{$num}", "{$val['name']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                    }

                    $objExcel->getActiveSheet()->setCellValueExplicit("HN{$num}", "{$this->guid()}", \PHPExcel_Cell_DataType::TYPE_STRING);

                }
            }
        }

        */

        $num=1;
        foreach ($goodresult as $val){
            $num++;

            //分类
            foreach ($excelDatas[1] as $k=>$row){
                $row=(gettype($row) == "boolean")?(($row)?"TRUE":"FALSE"):$row;
                $objExcel->getActiveSheet()->setCellValueExplicit("{$k}{$num}", "{$row}", \PHPExcel_Cell_DataType::TYPE_STRING);
            }

            $objExcel->getActiveSheet()->setCellValueExplicit("A{$num}", "{$val['gcid']}", \PHPExcel_Cell_DataType::TYPE_STRING);
            $objExcel->getActiveSheet()->setCellValueExplicit("B{$num}", "{$val['name']}", \PHPExcel_Cell_DataType::TYPE_STRING);
            //商品

            foreach ($val["data"] as $v){
                $num++;
                foreach ($excelDatas[2] as $k=>$row){
                    $row=(gettype($row) == "boolean")?(($row)?"TRUE":"FALSE"):$row;
                    $objExcel->getActiveSheet()->setCellValueExplicit("{$k}{$num}", "{$row}", \PHPExcel_Cell_DataType::TYPE_STRING);
                }

                $objExcel->getActiveSheet()->setCellValueExplicit("A{$num}", "{$val['gcid']}.{$v['goods_id']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                $objExcel->getActiveSheet()->setCellValueExplicit("B{$num}", "{$v['goods_name']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                $objExcel->getActiveSheet()->setCellValueExplicit("E{$num}", "{$val['name']}_{$v['goods_name']}", \PHPExcel_Cell_DataType::TYPE_STRING);
                $objExcel->getActiveSheet()->setCellValueExplicit("G{$num}", "{$v['key_name']}", \PHPExcel_Cell_DataType::TYPE_STRING);


            }
        }





//        dump($goodresult);
//        exit();






//        dump($excelDatas);
//        exit();
        /*
        $num=1;
         foreach ($goods as $k => $val){
             $num++;
             $objExcel->getActiveSheet()->setCellValueExplicit("A{$num}", "{$val['gcid']}", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("B{$num}", "{$val['name']}", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("C{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("Y{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("AI{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("AJ{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("AK{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("AM{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("AO{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("AR{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("AW{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("AZ{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BC{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BF{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BK{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);

             $objExcel->getActiveSheet()->setCellValueExplicit("BL{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BM{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BN{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BO{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BP{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BQ{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BR{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BS{$num}", "FALSE", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BT{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BV{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BW{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BY{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("BZ{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);



             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);
             $objExcel->getActiveSheet()->setCellValueExplicit("CA{$num}", "0", \PHPExcel_Cell_DataType::TYPE_STRING);

         }

        */

        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name=$fileName.xls');
        header("Content-Disposition:attachment;filename=".time().".xls");//attachment新窗口打印inline本窗口打印
        $objWriter = \PHPExcel_IOFactory::createWriter($objExcel, 'Excel2007');
        $objWriter->save('php://output');


//        die("!");
    }



    function guid() {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime()*10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);
            $uuid   = chr(123)
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);
            return $uuid;
        }
    }



    //删除非空目录的解决方案
    public function removeDir($dirName)
    {
        if(! is_dir($dirName))
        {
            return false;
        }
        $handle = @opendir($dirName);
        while(($file = @readdir($handle)) !== false)
        {
            if($file != '.' && $file != '..')
            {
                $dir = $dirName . '/' . $file;
                is_dir($dir) ? removeDir($dir) : @unlink($dir);
            }
        }
        closedir($handle);

        return rmdir($dirName) ;
    }

}