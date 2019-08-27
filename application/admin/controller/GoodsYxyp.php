<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\admin\controller;
use app\admin\logic\YxypGoodsLogic;
use app\admin\logic\YxypSearchWordLogic;
use app\admin\logic\CompanyLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

class GoodsYxyp extends Base {

    /**
     *  商品分类列表
     */
    public function categoryList(){   
        //TK    2018年4月21日09:13:23             
        $GoodsLogicYxyp = new YxypGoodsLogic();      
        $cat_list = $GoodsLogicYxyp->goods_cat_list();
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
        
        $GoodsLogic = new YxypGoodsLogic();        
        if(IS_GET)
        {
            $goods_category_info = D('GoodsYxypCategory')->where('id='.I('GET.id',0))->find();
            $level_cat = $GoodsLogic->find_parent_cat($goods_category_info['id']); // 获取分类默认选中的下拉框
            
            $cat_list = M('goods_yxyp_category')->where("parent_id = 0")->select(); // 已经改成联动菜单                
            $this->assign('level_cat',$level_cat);                
            $this->assign('cat_list',$cat_list);                 
            $this->assign('goods_category_info',$goods_category_info);      
            return $this->fetch('_category');
            exit;
        }

        $GoodsCategory = D('GoodsYxypCategory'); //

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
                    $children = M('goods_yxyp_category')->where($children_where)->max('level');
                    if (I('parent_id_1')) {
                        $parent_level = M('goods_yxyp_category')->where(array('id' => I('parent_id_1')))->getField('level', false);
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
                        $parent_level = M('goods_yxyp_category')->where(array('id' => I('parent_id_2')))->getField('level', false);
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
                
                //查找同级分类是否有重复分类
                $par_id = ($GoodsCategory->parent_id > 0) ? $GoodsCategory->parent_id : 0;
                $same_cate = M('GoodsYxypCategory')->where(['parent_id'=>$par_id , 'name'=>$GoodsCategory['name']])->find();
                if($same_cate){
                    $return_arr = array(
                        'status' => 0,
                        'msg' => '同级已有相同分类存在',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
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
                    'data'  => array('url'=>U('Admin/GoodsYxyp/categoryList')),
                );

                $this->ajaxReturn($return_arr);

            }  
        }

    }
    
    /**
     * 获取商品分类 的帅选规格 复选框
     */
    public function ajaxGetSpecList(){
        $GoodsLogic = new YxypGoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsYxypCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_spec');        
        $filter_spec_arr = explode(',',$filter_spec);        
        $str = $GoodsLogic->GetSpecCheckboxList($_REQUEST['type_id'],$filter_spec_arr);  
        $str = $str ? $str : '没有可帅选的商品规格';
        exit($str);        
    }
 
    /**
     * 获取商品分类 的帅选属性 复选框
     */
    public function ajaxGetAttrList(){
        $GoodsLogic = new YxypGoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_attr = M('GoodsYxypCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_attr');        
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
        $count = Db::name("goods_yxyp_category")->where("parent_id = {$ids}")->count("id");
        $count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下还有分类不得删除!']);
        // 判断是否存在商品
        $goods_count = Db::name('goods_yxyp')->where("cat_id = {$ids}")->count('1');
        $goods_count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下有商品不得删除!']);
        // 删除分类
        DB::name('goods_yxyp_category')->where('id',$ids)->delete();
        $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/GoodsYxyp/categoryList')]);
    }
    
    
    /**
     *  商品列表
     */
    public function goodsList(){      
        $GoodsLogic = new YxypGoodsLogic();        
        // $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $suppliersList = $GoodsLogic->getSuppliers();
        $this->assign('categoryList',$categoryList);
        //$this->assign('brandList',$brandList);
        $this->assign('suppliersList',$suppliersList);
        return $this->fetch();
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
        $cat_id = I('cat_id');
        // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where .= " and (g.goods_name like '%$key_word%' or g.goods_sn like '%$key_word%' or s.suppliers_name like '%$key_word%')" ;
        }
        
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandsonYxyp($cat_id); 
            $where .= " and g.cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        I('suppliers_id') && $where .= " and g.suppliers_id = ".I('suppliers_id');

        $sp = I('sp','','intval');
        if($sp && $sp == 1){
            $where .= " AND g.suppliers_id > 0";
        } else if($sp && $sp == 2){
            $where .= " AND g.suppliers_id = 0";
        }
        $count = M('GoodsYxyp')
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
        $goodsList = M('GoodsYxyp')
              ->alias('g')
              ->field('g.*')
              ->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','LEFT')
              ->where($where)
              ->order($order_str)
              ->limit($Page->firstRow.','.$Page->listRows)
              ->select();

        // $midou_use_percent = $this->tpshop_config['shoppingred_midou_use_percent']; // 购买商品 使用米豆 比率
        // $midou_rate        = $this->tpshop_config['shoppingred_midou_rate'];        // 米豆兑换比
        // foreach ($goodsList as $k => $val) {
        //     $midouInfo = getMidou($val['goods_id']);
        //     $goodsList[$k]['midou']       = $midouInfo['midou'];
        //     $goodsList[$k]['midou_money'] = $midouInfo['midou_money'];
        // }

        $catList = D('GoodsYxypCategory')->select();
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
        $cat_id = I('cat_id');
        // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where .= " and (g.goods_name like '%$key_word%' or g.goods_sn like '%$key_word%' or s.suppliers_name like '%$key_word%')" ;
        }
        
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandsonYxyp($cat_id); 
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

        $goodsList = M('GoodsYxyp')
          ->alias('g')
          ->field('g.*')
          ->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','LEFT')
          ->where($where)
          ->select();

        $catList = D('GoodsYxypCategory')->select();
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

                // $midouInfo = getMidou($val['goods_id']);
                // $val['midou']       = $midouInfo['midou'];
                // $val['midou_money'] = $midouInfo['midou_money'];

                // if($val['is_z_change'] == 1){
                //     $is_z_change = '是';
                //     $val['midou_use_percent'] = tpCache('shoppingred.midou_use_percent');
                // } else { 
                //     $is_z_change = '否'; 
                // }

                // if($val['is_z_back'] == 1){
                //     $is_z_back = '是';
                //     $val['midou_back_percent'] = tpCache('shoppingred.midou_back_percent');
                // } else { 
                //     $is_z_back = '否'; 
                // }

                if($val['is_free_shipping'] == 1) $is_free_shipping = "是"; else $is_free_shipping = "否";  

                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['goods_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].' </td>';               
                $strTable .= '<td style="text-align:left;font-size:12px;">'.get_suppliers_name($val['suppliers_id']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_sn'].' </td>'; 
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$catList[$val['cat_id']]['name'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shop_price'].'</td>';
                // $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou'].'米豆+'.$val['midou_money'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">0</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['market_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cost_operating'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_z_change.'</td>';
                // $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_use_percent'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">0</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_z_back.'</td>';
                // $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_back_percent'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">0</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$is_free_shipping.'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($goodsList);
        downloadExcel($strTable,'goodsyxyp');
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
                $goods = M('goodsYxyp')->field('goods_id,goods_name')->where(array('goods_id' => array('eq', $goods_id)))->select();
                $goods_info = M('goodsYxyp')->where(array('goods_id' => array('eq', $goods_id)))->find();
            }
            $this->assign('goods_info',$goods_info);
        } else {
            $goods_id_array = I('get.goods_id_array');
            if (!empty($goods_id_array)) {
                $goods = M('goods_yxyp')->field('goods_id,goods_name')->where(array('goods_id' => array('IN', $goods_id_array)))->select();
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
            M('goods_yxyp')->save($data);
        } else {
            //个体消息
            if (!empty($goods)) {
                foreach ($goods as $key) {
                    M('goods_yxyp')->where('goods_id = '.$key)->save($data);
                }
            }
        }
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
                M('goodsYxyp')->where('goods_id = '.$goods_id)->save($data);
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
            M('goodsYxyp')->where('goods_id = '.$goods_id)->save($data);
            echo "1";
            exit();
        }
        
    }
     
    // 商品库存记录
    public function stock_list(){
    	$model = M('stock_yxyp_log');
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
        $model = M('stock_yxyp_log');
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
        $data = M('spec_yxyp')->where("type_id", $type_id)->select();
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
        $GoodsLogicYxyp = new YxypGoodsLogic();
        $GoodsYxyp = new \app\admin\model\GoodsYxyp();
        $goods_id = I('goods_id');
        $goods_ids = I('id');
        ///实体店商品库存
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新

       
        //ajax提交验证
        if ((I('is_ajax') == 1) && IS_POST) {
            
           
            // 数据验证
         
            $virtual_indate = input('post.virtual_indate');//虚拟商品有效期
            $return_url =  U('/admin/GoodsYxyp/goodsList/');

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

            $GoodsYxyp->data($data, true); // 收集数据

            $GoodsYxyp->on_time = time();  // 上架时间
            I('cat_id_2') && ($GoodsYxyp->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($GoodsYxyp->cat_id = I('cat_id_3'));

            I('extend_cat_id_2') && ($GoodsYxyp->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($GoodsYxyp->extend_cat_id = I('extend_cat_id_3'));
            $GoodsYxyp->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $GoodsYxyp->shipping_area_ids = $GoodsYxyp->shipping_area_ids ? $GoodsYxyp->shipping_area_ids : '';
            $GoodsYxyp->spec_type = $GoodsYxyp->goods_type;

            $price_ladder = array();
            if ($GoodsYxyp->ladder_amount[0] > 0) {
                foreach ($GoodsYxyp->ladder_amount as $key => $value) {
                    $price_ladder[$key]['amount'] = intval($GoodsYxyp->ladder_amount[$key]);
                    $price_ladder[$key]['price'] = floatval($GoodsYxyp->ladder_price[$key]);
                }
                $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
                $price_ladder_max = count($price_ladder);
                if ($price_ladder[$price_ladder_max - 1]['price'] >= $GoodsYxyp->shop_price) {
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
                $GoodsYxyp->price_ladder = serialize($price_ladder);
            } else {
                $GoodsYxyp->price_ladder = '';
            }

            if ($type == 2) {
                $ab = $GoodsYxyp->isUpdate(true)->save(); // 写入数据到数据库

                // 修改商品后购物车的商品价格也修改一下  米豆部分需要修改
                M('cart_yxyp')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                    'market_price'       => I('market_price'), //市场价
                    'goods_price'        => I('shop_price'), // 本店价
                    'member_goods_price' => I('shop_price'), // 会员折扣价
                    'cost_price'         => I('cost_price'),     // 成本价
                    'cost_operating'     => I('cost_operating'), // 运营成本价
                ));
            } else {
                $GoodsYxyp->save(); // 写入数据到数据库
                $goods_id = $insert_id = $GoodsYxyp->getLastInsID();
                db('goods')->where('goods_id',$goods_id)->setField('sort',$goods_id);
            }

            $GoodsYxyp->afterSave($goods_id);
            $GoodsLogicYxyp->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => $return_url),
            );
            adminLog('操作一乡一品商品');
            $this->ajaxReturn($return_arr);
        }

        $goodsInfo = M('GoodsYxyp')->where('goods_id=' . I('GET.id', 0))->find();
        if ($goodsInfo['price_ladder']) {
            $goodsInfo['price_ladder'] = unserialize($goodsInfo['price_ladder']);
        }
        $level_cat = $GoodsLogicYxyp->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        if($goodsInfo['extend_cat_id'])
            $level_cat2 = $GoodsLogicYxyp->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list = M('goods_yxyp_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        // $brandList = $GoodsLogicRed->getSortBrands();
        $goodsType = M("GoodsYxypType")->select();
        if ($goods_ids) {
            $stockgoodsType = M("spec_yxyp_goods_price")->where('goods_id='.$goods_ids)->select();
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
        $shipping_where['status'] = 1;
        $shipping_where['is_default'] = 1;
        $shipping_where['type'] = array('eq', 'shipping');
        $shipping_where['suppliers_id'] = array('eq', $suppliers_id);
        $plugin_shipping = M('plugin')->where($shipping_where)->select();//插件物流

        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域

        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        //查询实体店数据
        $this->assign('shiti', M('Company')->where('parent_id!=0')->select());
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
        $store_id = db('store_goods_stock')->where(['goods_id'=>$goods_ids])->column("store_id");
//        file_put_contents("./public/sql.txt",json_encode($store_id));

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
        $goodsImages = M("GoodsYxypImages")->where('goods_id =' . I('GET.id', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册

        return $this->fetch('_goods');
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
     * 商品类型  用于设置商品的属性
     */
    public function goodsTypeList(){
        //modify
        $model = M("GoodsYxypType");                
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

        $model = M("GoodsYxypType");
        if (IS_POST) {
            $data = $this->request->post();

            //modify
            if ($id)
                DB::name('GoodsYxypType')->update($data);
            else
                DB::name('GoodsYxypType')->insert($data);

            adminLog('操作一乡一品商品属性类型');
            $this->success("操作成功!!!", U('Admin/GoodsYxyp/goodsTypeList'));
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
        $goodsTypeList = M("GoodsYxypType")->select();
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
        $model = M('GoodsYxypAttribute');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
        $goodsTypeList = M("GoodsYxypType")->getField('id,name');
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
                        
            $model = D("GoodsYxypAttribute");                      
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
                        adminLog('操作一乡一品商品属性');
                        $this->ajaxReturn($return_arr);
                    } else {     
                         $model->data($post_data,true); // 收集数据

                         if ($type == 2)
                         {
                             $model->isUpdate(true)->save(); // 写入数据到数据库
                         }
                         else
                         {
                             $model->save(); // 写入数据到数据库
                             $insert_id = $model->getLastInsID();
                         }
                         $return_arr = array(
                             'status' => 1,
                             'msg'   => '操作成功',
                             'data'  => array('url'=>U('Admin/GoodsYxyp/goodsAttributeList')),
                         );
                         adminLog('操作一乡一品商品属性');
                         $this->ajaxReturn($return_arr);
                }  
            }                
           // 点击过来编辑时                 
           $attr_id = I('attr_id/d',0);  
           //modify
           $goodsTypeList = M("GoodsYxypType")->select();           
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
            'data'  => array('url'=>U('Admin/GoodsYxyp/goodsAttributeList')),
        );
        $this->ajaxReturn($return_arr);
    }

    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput(){
        $GoodsLogic = new YxypGoodsLogic();
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
        $ordergoods_count = Db::name('order_yxyp_goods')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($ordergoods_count)
        {
            $goods_count_ids = implode(',',$ordergoods_count);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$goods_count_ids}】的商品有订单,不得删除!",'data'  =>'']);
        }
         // 商品团购
        $groupBuy_goods = M('group_yxyp_buy')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($groupBuy_goods)
        {
            $groupBuy_goods_ids = implode(',',$groupBuy_goods);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$groupBuy_goods_ids}】的商品有团购,不得删除!",'data'  =>'']);
        }
        // 删除此商品        
        M("goods_yxyp")->whereIn('goods_id',$goods_ids)->delete();  //商品表
        M("cart_yxyp")->whereIn('goods_id',$goods_ids)->delete();  // 购物车
        M("comment_yxyp")->whereIn('goods_id',$goods_ids)->delete();  //商品评论
        M("goods_yxyp_consult")->whereIn('goods_id',$goods_ids)->delete();  //商品咨询
        M("goods_yxyp_images")->whereIn('goods_id',$goods_ids)->delete();  //商品相册
        M("spec_yxyp_goods_price")->whereIn('goods_id',$goods_ids)->delete();  //商品规格
        M("spec_yxyp_image")->whereIn('goods_id',$goods_ids)->delete();  //商品规格图片
        M("goods_yxyp_attr")->whereIn('goods_id',$goods_ids)->delete();  //商品属性
        M("goods_yxyp_collect")->whereIn('goods_id',$goods_ids)->delete();  //商品收藏
        adminLog('删除一乡一品商品');
        $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/GoodsYxyp/goodsList")]);
    }
    
    /**
     * 删除商品类型 
     */
    public function delGoodsType()
    {
        // 判断 商品规格
        $id = $this->request->param('id');
        $count = M("SpecYxyp")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品规格不得删除!',U('Admin/GoodsYxyp/goodsTypeList'));
        // 判断 商品属性        
        $count = M("GoodsYxypAttribute")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品属性不得删除!',U('Admin/GoodsYxyp/goodsTypeList'));        
        // 删除分类
        M('GoodsYxypType')->where("id = {$id}")->delete();
        adminLog('删除一乡一品商品类型');
        $this->success("操作成功!!!",U('Admin/GoodsYxyp/goodsTypeList'));
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
        $count_ids = Db::name("GoodsYxypAttr")->whereIn('attr_id',$attrBute_ids)->group('attr_id')->getField('attr_id',true);
        if($count_ids){
            $count_ids = implode(',',$count_ids);
            $this->ajaxReturn(['status' => -1,'msg' => "ID为【{$count_ids}】的属性有商品正在使用,不得删除!"]);
        }
        // 删除 属性
        M('GoodsYxypAttribute')->whereIn('attr_id',$attrBute_ids)->delete();
        adminLog('删除一乡一品商品属性');
        $this->ajaxReturn(['status' => 1,'msg' => "操作成功!",'url'=>U('Admin/GoodsYxyp/goodsAttributeList')]);
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
        $count_ids = M("SpecYxypItem")->whereIn('spec_id',$aspec_ids)->group('spec_id')->getField('spec_id',true);
        if($count_ids){
            $count_ids = implode(',',$count_ids);
            if($is_ajax){
                $this->ajaxReturn(['status' => -1,'msg' => "ID为【{$count_ids}】规格，清空规格项后才可以删除!"]);
            }else{
                $this->error("ID为【{$count_ids}】规格，清空规格项后才可以删除!");
            }
        }
        // 删除分类
        M('SpecYxyp')->whereIn('id',$aspec_ids)->delete();
        if($is_ajax){
            adminLog('删除一乡一品商品分类');
            $this->ajaxReturn(['status' => 1,'msg' => "操作成功!!!",'url'=>U('Admin/GoodsYxyp/specList')]);
        }else{
            $this->success("操作成功!!!");
        }
       
    } 
    
    /**
     * 品牌列表
     */
    public function brandList(){  
        exit('尚未开放');
        $model = M("BrandYxyp"); 
        $where = "";
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : "";
        $count = $model->where($where)->count();
        $Page = $pager = new Page($count,10);        
        $brandList = $model->where($where)->order("`sort` asc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $show  = $Page->show(); 
        $cat_list = M('goods_yxyp_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
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
                	M("BrandYxyp")->update($data);
                }else{
                	M("BrandYxyp")->insert($data);
                }
                adminLog('操作一乡一品商品品牌');
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
        $goods_count = Db::name('GoodsYxyp')->whereIn("brand_id",$brind_ids)->group('brand_id')->getField('brand_id',true);
        $use_brind_ids = implode(',',$goods_count);
        if($goods_count)
        {
            $this->ajaxReturn(['status' => -1,'msg' => 'ID为【'.$use_brind_ids.'】的品牌有商品在用不得删除!','data'  =>'']);
        }
        $res=Db::name('BrandYxyp')->whereIn('id',$brind_ids)->delete();
        if($res){
            adminLog('删除一乡一品商品品牌');
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/GoodsYxyp/brandList")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '操作失败','data'  =>'']);
    }      
    
    /**
     * 商品规格列表    
     */
    public function specList(){     
        //modify  
        $goodsTypeList = M("GoodsYxypType")->select();
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
        $model = D('SpecYxyp');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $specList = $model->where($where)->order('`type_id` desc')->limit($Page->firstRow.','.$Page->listRows)->select();        
        $GoodsLogicYxyp = new YxypGoodsLogic();        
        foreach($specList as $k => $v)
        {       // 获取规格项     
                $arr = $GoodsLogicYxyp->getSpecItem($v['id']);
                $specList[$k]['spec_item'] = implode(' , ', $arr);
        }
        
        $this->assign('specList',$specList);
        $this->assign('page',$show);// 赋值分页输出
        $goodsTypeList = M("GoodsYxypType")->select(); // 规格分类
        $goodsTypeList = convert_arr_key($goodsTypeList, 'id');
        $this->assign('goodsTypeList',$goodsTypeList);        
        return $this->fetch();
    }

    /**
     * 添加修改编辑  商品规格
     */
    public  function addEditSpec(){

            $model = D("SpecYxyp");
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
                } else {
                    $model->save(); // 写入数据到数据库
                    $insert_id = $model->getLastInsID();
                    $model->afterSave($insert_id);
                }
                $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url' => U('Admin/GoodsYxyp/specList')]);
            }                
           // 点击过来编辑时
           $spec = DB::name("SpecYxyp")->find($id);
           $GoodsLogic = new YxypGoodsLogic();  
           $items = $GoodsLogic->getSpecItem($id);
           $spec[items] = implode(PHP_EOL, $items); 
           $this->assign('spec',$spec);
           $goodsTypeList = M("GoodsYxypType")->select();           
           $this->assign('goodsTypeList',$goodsTypeList);           
           return $this->fetch('_spec');
    }  
    
    
    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect(){
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;        
        $GoodsLogic = new YxypGoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('SpecYxyp')->where("type_id = ".I('get.spec_type/d'))->order('`order` desc')->select();
        foreach($specList as $k => $v)        
            $specList[$k]['spec_item'] = M('SpecYxypItem')->where("spec_id = ".$v['id'])->order('id')->getField('id,item'); // 获取规格项                
        
        $items_id = M('SpecYxypGoodsPrice')->where('goods_id = '.$goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);       
        
        // 获取商品规格图片                
        if($goods_id)
        {
           $specImageList = M('SpecYxypImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');                 
        }        
        $goodsinfo = M('goodsYxyp')->where('goods_id',$goods_id)->find();  
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
         $GoodsLogic = new YxypGoodsLogic();
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
        M('goods_yxyp_images')->where("image_url = '$path'")->delete();
        adminLog('删除一乡一品商品相册图');
    }

    /**
     * 初始化商品关键词搜索
     */
    public function initGoodsSearchWord(){
        $searchWordLogic = new YxypSearchWordLogic();
        $successNum = $searchWordLogic->initGoodsSearchWord();
        $this->success('成功初始化'.$successNum.'个搜索关键词');
    }

    /**
     * 初始化地址json文件
     */
    public function initLocationJsonJs()
    {
        $goodsLogic = new YxypGoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        file_put_contents(ROOT_PATH."public/js/locationJson.js", "var locationJsonInfoDyr = ".json_encode($region_list, JSON_UNESCAPED_UNICODE).';');
        $this->success('初始化地区json.js成功。文件位置为'.ROOT_PATH."public/js/locationJson.js");
    }


      /**
     * 判断
     */    
    public function ajaxGetstoreSpecInput(){   
        $goods_id = I('goods_id/d');  
        $stockgoodsType = M("spec_yxyp_goods_price")->where('goods_id='.$goods_id)->select();
        if ($stockgoodsType) {
            $str="1";
        }else{
            $str="2";
        }
        exit($str);   
    }

   
       
}