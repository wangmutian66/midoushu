<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 *  子公司管理
 */
namespace app\admin\controller;
use app\admin\logic\CompanyLogic;
use think\AjaxPage;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
use think\Loader;
use think\Cache;

use app\admin\model\CompanyModel;

class Company extends Base
{

    public $table_name;
    public $pk;
    public $indexUrl;

    public function _initialize()
    {
        parent::_initialize();
        $this->table_name = 'Company';
        $this->pk = 'cid';
        $this->indexUrl = U('Admin/Company/index');
        $this->catUrl = U('Admin/Company/category');
    }

    // 子公司
    public function index()
    {
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $map['parent_id'] = 0;
        if ($company_id = I('get.company_id/d')) {
            $map['parent_id'] = ['eq', $company_id];
            $company_info = db('company')->cache("company_{$company_id}")->find($company_id);
            $this->assign('company_info', $company_info);
        }

        #搜索
        $searchtype = I('get.searchtype');
        $searchkey = I('get.searchkey');
        $keyword = I('get.keyword');
        if ($keyword != "") {
            $map['cname|mobile|contact']    =   ['like',"%$keyword%"];

        }

        if ($searchtype == 88) {
            $map['parent_id'] = 0;
        } elseif ($searchtype == 99) {
            $map['parent_id'] = array('gt', 0);
        }

        $this->assign('searchtype',$searchtype);
        $this->assign('searchkey',$searchkey);


        $list = M('company')->where($map)->order("{$this->pk} desc")->page("$p,$size")->select();
        foreach ($list as $k => $v) {
            if ($v['parent_id'] > 0) {
                $list[$k]['store_name'] = M('company')->where("cid=" . $v['parent_id'])->value("cname");
            } else {
                $list[$k]['store_name'] = "";
            }

        }

        $count = M('company')->where($map)->count();
        $pager = new Page($count, $size);
        $this->assign('list', $list);
        $this->assign('pager', $pager);
        return $this->fetch('index');
    }

    // 添加子公司
    public function add()
    {

        $this->assign('acts', 'doAdd');
        $this->assign('bank_list', $this->bank_list());
        $this->assign('pk', $this->pk);
        $is_hide = 0;   //利润比是否隐藏
        $searchtype = I('searchtype/d');
        if ($company_id = I('company_id/d')) {
            $company_info = db('company')->cache("company_{$company_id}")->find($company_id);
            $this->assign('company_info', $company_info);
        } else {
            $is_hide = 1;
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province',$p);

        $companyCategroy = M('company_category')->select();
        $this->assign('companyCategroy', $companyCategroy);
        $company_list = get_company_list();
        $this->assign('company_list', $company_list);
        $this->assign('is_hide', $is_hide);
        $this->assign('searchtype', $searchtype);
        return $this->fetch('form');
    }

    public function doAdd()
    {

        $new_model = new CompanyModel($_POST);
        $_POST['password'] = encrypt($_POST['password']);
        $_POST['level'] = 1;
//        $verify_r = M('company')->where("mobile = '{$_POST['mobile']}'")->find();
//        if ($verify_r) {
//            $res['status'] = 0;
//            $res['info'] = '手机号码重复，请重新填写';
//            $this->ajaxReturn($res);
//        }
        //    $company_obj       = new CompanyLogic();
        if ($new_model->allowField(true)->save($_POST)) {
            $company_id = $new_model->getLastInsID();
            //   $insert_id =    M('company')->getLastInsID();
            //    $company_obj->refresh_cat($insert_id);
            adminLog('添加子公司');
            cache::rm('company_list');

            #2018-10-15 存储推荐子公司的相关信息
            #start
            $parent_id = I('parent_id',0);
            $t_company_id = I('t_company_id/s','');
            $company_level = I('company_level/s','');
            if($t_company_id != ''){
                $arr_t_company_id = explode(',',$t_company_id);
                $arr_company_level = explode(',',$company_level);
                $clen = count($arr_t_company_id);
                for($i=0;$i<$clen;++$i){
                    $arr_level = explode(':',$arr_company_level[$i])[1];
                    if($parent_id > 0){
                        $t_data['company_id'] = $parent_id;
                        $t_data['store_id'] = $company_id;
                    }else{
                        $t_data['company_id'] = $company_id;
                    }
                    $t_data['t_company_id'] = $arr_t_company_id[$i];
                    $t_data['company_level'] = $arr_level;
                    $t_data['addtime'] = time();
                    M('company_sign')->save($t_data);
                }
            }
            #end

            $res['parent_id'] = $parent_id;
            $res['status'] = 1;
            $res['info'] = '新增数据成功';
            $this->ajaxReturn($res);
            #  $this->success('新增数据成功！',$this->indexUrl);
        } else {
            $res['parent_id'] = 0;
            $res['status'] = 0;
            $res['info'] = '新增失败';
            $this->ajaxReturn($res);
        }

    }

    // 修改实体店
    function modify()
    {

        if ($id = I('get.id/d')) {

            $this->assign('bank_list', $this->bank_list());
            $item = M('company')->find($id);

            $company_list = get_company_list();
            $companyCategroy = M('company_category')->select();
            $this->assign('companyCategroy', $companyCategroy);
            $this->assign('company_list', $company_list);
            $this->assign('acts', 'doModify');
            $this->assign('pk', $this->pk);
            $this->assign('item', $item);
            if ($item['parent_id'] == 0) {
                $is_hide = 1;
            } else {
                $is_hide = 0;
            }

            #提取推荐子公司的列表
            #2018-10-15 张洪凯
            #start

            if($item['parent_id'] == 0){
                $t_company_list = M('company_sign')->where("company_id=".$id." and store_id=0")->select();
            }else{
                $t_company_list = M('company_sign')->where("store_id=".$id)->select();
            }

            foreach($t_company_list as $k=>$tl){
                $t_company_list[$k]['cname'] = M('company')->where("cid=".$tl['t_company_id'])->value('cname');
                $r = M('company_level')->field('profit,lv_name')->find($tl['company_level']);
                if($r){
                    $t_company_list[$k]['lv_name'] = $r['lv_name'];
                    $t_company_list[$k]['profit'] = $r['profit'];
                }
            }
            $this->assign('t_company_list',$t_company_list);
            #end

            $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
            $c = M('region')->where(array('parent_id'=>$item['province_id'],'level'=> 2))->select();
            $d = M('region')->where(array('parent_id'=>$item['city_id'],'level'=> 3))->select();
            $this->assign('province',$p);
            $this->assign('city',$c);
            $this->assign('district',$d);
            $this->assign('is_hide', $is_hide);
            return $this->fetch('form');
        } else {
            $this->error('参数错误!');
        }

    }

    function doModify()
    {
        $new_model = new CompanyModel();
        $password = I('post.password');
        if ($password) {
            $_POST['password'] = encrypt($_POST['password']);
        } else {
            unset($_POST['password']);
        }
        $cid = I('post.cid');
//        $verify_r = M('company')->where("mobile = '{$_POST['mobile']}' and cid != $cid")->find();
//        if ($verify_r) {
//            $res['status'] = 0;
//            $res['info'] = '手机号码重复，请重新填写';
//            $this->ajaxReturn($res);
//        }

        // 过滤post数组中的非数据表字段数据

        if ($new_model->allowField(true)->save($_POST, [$this->pk => $cid])) {
            if ($password) {
                db('company')->where(["mobile" => $_POST['mobile']])->save(["password" => $_POST['password']]);
            }
            //    $company_obj->refresh_cat($_POST[$this->pk]);
          
            $log_str = "修改子公司:收款手机号码：{$_POST['hidden_mobile']}->:{$_POST['receivable_mobile']}";
            $log_str .= "收款方用户名：{$_POST['hidden_name']}->:{$_POST['enc_true_name']}";
            $log_str .= "收款方银行卡号：{$_POST['hidden_bank_no']}->:{$_POST['enc_bank_no']}";
            adminLog($log_str);
            cache::rm('company_list');
            cache::rm("company_store_list_{$cid}");
            
            #2018-10-15 存储推荐子公司的相关信息
            #start
            $parent_id = I('parent_id/d',0);
            $t_company_id = I('t_company_id','');
            $company_level = I('company_level','');
            if($parent_id == 0){
                $sign_where['company_id'] = ['eq',$cid];
                $sign_where['store_id'] = ['eq',0];
            }else{
                $sign_where['store_id'] = ['eq',$cid];
            }
            if($t_company_id != ''){
                $arr_t_company_id = explode(',',$t_company_id);
                $arr_company_level = explode(',',$company_level);
                $tlist = M('company_sign')->where($sign_where)->field('id,company_id,t_company_id')->select();
                $clen = count($arr_t_company_id);

                for($i=0;$i<$clen;++$i){
                    $arr_level = explode(':',$arr_company_level[$i])[1];
                    $flag = false;
                    foreach($tlist as $key=>$value){
                        if($arr_t_company_id[$i] == $value['t_company_id']){
                            if($arr_level > 0){
                                M('company_sign')->where("id=".$value['id'])->update(['company_level'=>$arr_level]);
                            }
                            $flag = true;
                            break;
                        }
                    }

                    if($flag == false){
                        if($parent_id > 0){
                            $t_data['company_id'] = $parent_id;
                            $t_data['store_id'] = $cid;
                        }else{
                            $t_data['company_id'] = $cid;
                        }
                        $t_data['t_company_id'] = $arr_t_company_id[$i];
                        $t_data['company_level'] = $arr_level;
                        $t_data['addtime'] = time();
                        M('company_sign')->save($t_data);
                    }

                }

                M('company_sign')->where($sign_where)->where("t_company_id not in (".$t_company_id.")")->delete();

            }else{
                M('company_sign')->where($sign_where)->delete();
            }
            #end


            $res['status'] = 1;
            $res['info'] = '更新数据成功！';
            $this->ajaxReturn($res);
        } else {
            $res['status'] = 0;
            $res['info'] = '数据无改动，请重新修改';
            $this->ajaxReturn($res);
        }
    }

    /**
     * [搜索子公司列表]
     * @author 王牧田
     * @date 2018-12-14
     */
    public function search_company_list(){
        $cname = I('post.cname');
        // if(empty($cname)){
        //    $cname = "0";
        // }
        $where['cname'] = ["like","%".$cname."%"];
        $where['parent_id'] = 0;
        $company_list = M('company')->where($where)->select();
        $res['status'] = 1;
        $res['data'] = $company_list;
        $this->ajaxReturn($res);
    }




    #获取推荐子公司返利等级
    #老张
    function search_company_level(){


        $cid = I('cid/d',0);
        if($cid == 0) return;

        $levelwhere['is_elite'] = ['eq',1];
        $levelwhere['c_parent_id'] = ['eq',$cid];

        $level_list = M('company_level')->where($levelwhere)->select();

        return json_encode($level_list);

    }

    function get_company_level(){

        $level_id = I('level_id/d');
        $rst = M('company_level')->field('lv_name,profit')->find($level_id);
        if($rst){
            $res['status'] = 0;
            $res['info'] = $rst;
        }else{
            $res['status'] = 1;
            $res['info'] = '获取等级信息失败';
        }
        $this->ajaxReturn($res);

    }

    /*导出所有数据*/
    function export_company()
    {
        $where['parent_id'] = ['eq', 0];
        $r = db('company') ->where('parent_id = 0')->order('cid desc')->select();
        $strTable = '<table width="1000" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">级别</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属子公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属实体店</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">联系电话</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">成员数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">员工数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否参与大盘反米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">可反米豆比率</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店利润比</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推广员、线下比例</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">设定员工，实体店，子公司分红比例</td>';
        $strTable .= '</tr>';
        foreach ($r as $key => $r) {
            $is_z_back = $r['is_z_back'] == 1 ? ('是') : ('否');
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;">子公司</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">' . $r['cname'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> -- </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ?? </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $r['contact'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $r['mobile'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . chengyuan($r['cid']) . '</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . yuangong($r['cid']) .  ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $is_z_back . ' </td>';
            if($r['parent_id'] == 0){
                $midou_back_percent = 0;
            }else{
                $midou_back_percent = $r['midou_back_percent'];
            }
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $midou_back_percent . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $r['proportion'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $r['tgyxxbl'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $r['yscbl'] . ' </td>';
            $strTable .= '</tr>';
            $strTable .= $this->return_store($r);
        }
        $strTable .= '</table>';
        /*        header("Content-type:text/html;charset=utf-8");
                echo $strTable;die;*/
        downloadExcel($strTable, '子公司列表');
        adminLog('导出子公司列表');
        exit();
        dump($company_list);
    }

 
    function return_store($r)
    {

        $list = db('company')->where('parent_id', $r['cid'])->select();
        foreach ($list as $key => $value) {
            $is_z_back = $value['is_z_back'] == 1 ? ('是') : ('否');
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;">实体店</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">' . $r['cname'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $value['cname'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ?? </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $value['contact'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $value['mobile'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . chengyuan($value['cid']) . '</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . yuangong($value['cid']) .  ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $is_z_back . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $value['midou_back_percent'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $value['proportion'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $value['tgyxxbl'] . ' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;"> ' . $value['yscbl'] . ' </td>';
            $strTable .= '</tr>';
        }
        return $strTable;
    }


    // 子公司，实体店删除
    function del()
    {
        if ($id = I('post.id/d')) {
            if (M('company')->delete($id)) {
                M('company_sign')->where("company_id=".$id)->delete();
                adminLog('删除子公司/实体店');
                $this->success('删除成功！', $this->indexUrl);
            } else {
                $this->error('删除失败！');
            }
        } else {
            $this->error('非法操作');
        }
    }

    #张洪凯  2018-11-6
    function ajax_del(){
        $DelInfoLogic = new \app\admin\logic\DelInfoLogic();
        $res = $DelInfoLogic->delete_info();
        $this->ajaxReturn($res);
    }




    /*获取某公司下方所有实体店*/
    function ajax_get_store()
    {
        $company_id = I('get.company_id');
        $store_list = TK_get_company_store($company_id);
        if ($store_list) {
            $data['status'] = 1;
            $data['list'] = $store_list;
        } else {
            $data['status'] = 0;
            $data['list'] = '';
        }
        $this->ajaxReturn($data);
    }
	
	/**
	 * 模糊搜索实体店
	 * by 刘姝含
	 * 2018/10/25 星期四
	**/
    function ajax_get_keystore()
    {
        $name = I('get.name/s');
		$where = "`cname` LIKE '%{$name}%' AND `parent_id` >0";
        $store = M('company')->field('cid,cname,parent_id')->where($where)->select();
		$html = '<option value="">请选择</option>';
        if ($store) {
			foreach($store as $k => $val) {
				if($val['parent_id']) {
					$parent = M('company')->field('cid,cname')->where("`cid`='{$val['parent_id']}'")->find();
				}
				$html .= "<option value={$val['cid']}>{$parent['cname']}-{$val['cname']}</option>";
			}
				
        }
        echo $html;
    }

    function ajax_get_level()
    {
        $company_id = I('get.company_id');
        $store_id = I('get.store_id');
        $is_staff = I('get.is_staff/s');
        if ($is_staff == 'no') {
            $where['is_staff'] = ['eq', 0];
        } elseif ($is_staff == 'yes') {
            $where['is_staff'] = ['eq', 1];
        }
        if ($store_id) {
            $where['c_parent_id'] = ['eq', $store_id];
            $level_list = M('CompanyLevel')->where($where)->cache(true)->select();
        } elseif ($company_id) {
            $where['c_parent_id'] = ['eq', $company_id];
            $level_list = M('CompanyLevel')->where($where)->cache(true)->select();
        } else {
            $level_list = M('CompanyLevel')->where($where)->cache(true)->select();
        }
        if ($level_list) {
            $data['status'] = 1;
            $data['list'] = $level_list;
        } else {
            $data['status'] = 0;
            $data['list'] = '';
        }
        $this->ajaxReturn($data);
    }

    // 子公司 实体店 成员
    public function company_member()
    {
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $map = array();

        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $level_id = I('level_id') ? I('level_id') : '';

        if ($company_id) $map['a.parent_id'] = $company_id;
        if ($store_id) $map['a.parent_id'] = $store_id;
        if ($level_id) $map["company_level"] = $level_id;

        #初始化搜索条件
        $company_list = get_company_list();
        $this->assign('company_list', $company_list);
        /*查询所有实体店*/
        if ($company_id != 0) {
            $store_list = M('company')->where('parent_id', 'eq', $company_id)->select();
            $this->assign('store_list', $store_list);
        }

        #初始化搜索条件结束
        $key_word = I('key_word') ? I('key_word/s') : '';
        $list = M('company_member')
            ->alias('a')
            ->field('a.*,l.lv_name,c.cname company_name,c.parent_id pid')
            ->join('company_level l', 'a.company_level = l.id', 'left')
            ->join('company c', 'a.parent_id = c.cid')
            ->where($map)
            ->where(function ($query) use ($key_word) {
                if ($key_word) {
                    $query->where('a.phone', $key_word)->whereOr('a.real_name', 'like', "%{$key_word}%");
                }
            })
            ->order('id desc')
            ->page("$p,$size")
            ->select();

        foreach($list as $key=>$value){
            if($value['pid'] == 0){
                $list[$key]['company_name'] = $value['company_name'];
                $list[$key]['store_name'] = '';
            }else{
                $list[$key]['company_name'] = M('company')->where('cid='.$value['pid'])->value('cname');
                $list[$key]['store_name'] = $value['company_name'];
            }
        }

        $count = M('company_member')->alias('a')->where(function ($query) use ($key_word) {
            if ($key_word) {
                $query->where('a.phone', $key_word)->whereOr('a.real_name', 'like', "%{$key_word}%");
            }
        })->where($map)->count();
        $pager = new Page($count, $size);
        $this->assign('list', $list);
        $this->assign('pager', $pager);
        return $this->fetch();
    }


    // 添加成员
    public function company_member_add()
    {
        if ($company_id = I('company_id/d')) {
            $map['cid'] = ['eq', $company_id];
            $company_level = M('company_level')->where("c_parent_id = {$company_id} AND is_staff = 0 and is_elite=0")->select();
            $item['parent_id'] = $company_id;
            $item['cname'] = M('company')->cache("company_{$company_id}")->find($company_id)['cname'];
        }
        if ($store_id = I('store_id/d')) {
            $map['cid'] = ['eq', $store_id];
            $company_level = M('company_level')->where("c_parent_id = {$store_id} AND is_staff = 0 and is_elite=0")->select();
            $item['parent_id'] = $store_id;
            $item['cname'] = M('company')->cache("company_{$store_id}")->find($store_id)['cname'];
        }
        $this->assign('member', $item);
        $company_list = get_company_list();
        $this->assign('company_list', $company_list);


        if (IS_POST) {
            $data = I('post.');
            #    $data['parent_id_path'] = $company['parent_id_path'];
            $data['psw'] = encrypt($data['psw']);
            $CompanyLogic = new CompanyLogic();
            $res = $CompanyLogic->addCompany_member($data);
            if ($res['status'] == 1) {
                adminLog('添加子公司成员');
                $msg['status'] = 1;
            } else {
                $msg['status'] = 0;
                $msg['info'] = $res['msg'];
            }
            $this->ajaxReturn($msg);
        }
        $this->assign('company_level', $company_level);     //等级列表，下拉用的
        return $this->fetch('company_member_form');
    }


    // 修改成员
    public function company_member_edit()
    {
        $id = I('get.id');
        $company_id = I('get.company_id/d', 0);
        $member = M('company_member')->alias('m')->field('m.*,profit,c.cname')
            ->join('company c', 'c.cid = m.parent_id')
            ->join('company_level lv', 'lv.id = m.company_level')
            ->where("m.id = {$id}")
            ->find();
        #    dump($member );die;
        if (!$member)
            exit($this->error('成员不存在'));

        $company_level = M('company_level')->where("c_parent_id = {$member['parent_id']} AND is_staff = 0 and is_elite=0")->select();


        if (IS_POST) {
            $data = I('post.');
            $company_obj = new CompanyLogic();
            $res = $company_obj->updateCompany_member($data);
            if ($res['status'] == 1) {
                adminLog('修改子公司成员');
                $msg['status'] = 1;
            } else {
                $msg['status'] = 0;
                $msg['info'] = $res['msg'];
            }
            $this->ajaxReturn($msg);
        }
        $this->assign('pk', 'id');
        $this->assign('member', $member);
        $this->assign('company_level', $company_level);     //等级列表，下拉用的
        $company_list = get_company_list();
        $this->assign('company_list', $company_list);
        return $this->fetch('company_member_form');
    }

    function company_member_del()
    {
        if ($id = I('post.id/d')) {
            if (M('company_member')->delete($id)) {
                adminLog('删除子公司成员');
                $this->success('删除成功！', U('/Admin/Company/company_member', ['company_id' => I('company_id'), 'store_id' => I('store_id')]));
            } else {
                $this->error('删除失败！');
            }
        } else {
            $this->error('非法操作');
        }
    }


    function tree()
    {
        return $this->fetch('tree');
    }

    function ajax_tree_json()
    {
        /*$tree_data = S('level_tree_all_data');
        if($tree_data){
            $this->ajaxReturn($tree_data);
        }*/
        //查询出所有子公司
        $data['name'] = '米豆薯';
        $data['children'] = M('company')->field('cname name,cid')->where('parent_id', 0)->select();
        #查询出所有的实体店
        $store_list = M('company')->field('cname name,cid,parent_id_path,parent_id')->select();
        #查询出所有的成员
        $company_member_list = M('CompanyMember')->field('parent_id_path,parent_id,id cmid,real_name name')->select();

        #查询出所有员工
        $staff_list = M('staff')->field('id staff_id,real_name name,uname,parent_id,company_id,store_id,type t')->select();
        foreach ($data['children'] as $key => $value) {
            $data['children'][$key][0]['name'] = '实体店';
            $data['children'][$key][1]['name'] = '成员';
        }
        foreach ($data['children'] as $key => $value) {
            $data['children'][$key]['children'][0]['name'] = '实体店';
            #添加实体店
            foreach ($store_list as $k => $v) {
                if ($value['cid'] == $v['parent_id']) {
                    #在节点添加实体店之前，先将员工和成员加入实体店
                    $v['children'][0]['name'] = '员工';
                    $v['children'][1]['name'] = '成员';
                    foreach ($staff_list as $k1 => $v1) {
                        if ($v1['store_id'] == $v['cid'] && $v1['t'] == 0) {
                            #添加员工之前将推广员添加到员工下方
                            foreach ($staff_list as $k2 => $v2) {
                                if ($v2['parent_id'] == $v1['staff_id']) {
                                    $v1['children'][] = $v2;
                                }
                            }
                            #添加员工
                            $v1['name'] = ($v1['name']) ? ($v1['name']) : ($v1['uname']);
                            $v['children'][0]['children'][] = $v1;
                            unset($staff_list[$k1]);
                        }
                    }
                    #将实体店的成员加进去
                    foreach ($company_member_list as $k1 => $v1) {
                        if ($v['cid'] == $v1['parent_id']) {
                            $v['children'][1]['children'][] = $v1;
                            unset($company_member_list[$k1]);
                        }
                    }
                    /*判断该节点是否有有下级，没有删除，不然太乱*/
                    $data['children'][$key]['children'][0]['children'][] = $v;
                    unset($staff_list[$k]);


                }
            }
            #实体店结束
            #添加子公司成员
            $data['children'][$key]['children'][1]['name'] = '成员';
            foreach ($company_member_list as $k => $v) {
                if ($value['cid'] == $v['parent_id']) {
                    $data['children'][$key]['children'][1]['children'][] = $v;
                    unset($company_member_list[$k]);
                }
            }
            #添加子公司成员结束

        }
        $this->ajaxReturn($data);
    }

    function tree_unset(&$array)
    {
        if ($array) {
            if (empty($array['children'])) {
                unset($array);
            }
        }
    }

    #发送站内消息
    function sendSms()
    {
        if (\think\Request::instance()->isPost()) {
            $ids = I('post.ids');
            $text = I('post.text');
            if (empty($ids) || empty($text)) {
                $msg['status'] = 0;
                $msg['info'] = '发送的消息不能为空';
            } else {
                $id_array = explode(',', $ids);
                foreach ($id_array as $key => $value) {
                    if (empty($value)) {
                        continue;
                    }
                    $list[] = ['company_id' => $value, 'message' => $text, 'admin_id' => session('admin_id'), 'create_time' => NOW_TIME, 'status' => 0, 'info' => '系统通知'];
                }
                $r = db('company_msg')->insertAll($list);
                if ($r) {
                    adminLog('发送站内消息');
                    $msg['status'] = 1;
                } else {
                    $msg['status'] = 0;
                    $msg['info'] = '发送失败！';
                }
            }
            $this->ajaxReturn($msg);
        } else {
            return $this->fetch();
        }
    }

    /*企业收款记录*/
    function transfer_log()
    {
        #  $_GET['is_pay'] =   1;
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $company_list = get_company_list();
        $this->assign('company_list', $company_list);

        $tz = I('get.tz/d');
        if ($tz == 2) {
            $where['paid_sn'] = ['like', "mypays_%"];
        } else {
            $where['paid_sn'] = ['like', "staff_paid%"];
        }


        if ($company_id = I('get.company_id/d')) {
            $where['store_id'] = ['eq', $company_id];
            $store_list = db('company')->where("parent_id = {$company_id}")->select();
            $this->assign('store_list', $store_list);
        }

        if ($store_id = I('get.store_id/d')) {
            $where['store_id'] = ['eq', $store_id];
        };

        $where['pay_status'] = ['eq', 1];

        if ($key_word = I('get.key_word/s')) {
            $where['paid_sn'] = ['like', "%{$key_word}%"];
        }


        $list = M('transfer_log')->where($where)->order("id desc")->page("$p,$size")->select();
        $count = M('transfer_log')->where($where)->count();
        foreach ($list as $key => $value) {
            if (strstr($value['paid_sn'], 'staff_paid_')) {
                $list[$key]['money'] = db('staff_paid')->where("paid_sn = '{$value['paid_sn']}'")->getField('money');
            } else {
                $list[$key]['money'] = db('staff_mypays')->where("paid_sn = '{$value['paid_sn']}'")->getField('money');
            }
        }
        $pager = new Page($count, $size);
        $this->assign('list', $list);
        $this->assign('pager', $pager);
        return $this->fetch('transfer_log');
    }

    function view_transfer_log()
    {
        $id = I('get.id/d');
        $where['id'] = ['eq', $id];
        $paid_sn = I('get.paid_sn/s');
        if ($paid_sn) {
            $where['paid_sn'] = ['eq', $paid_sn];
            unset($where['id']);
        }
        $item = db('transfer_log')->where($where)->find();
        if (strstr($item['paid_sn'], 'staff_paid_')) {
            $item['r'] = db('staff_paid')->where("paid_sn = '{$item['paid_sn']}'")->find();
        } else {
            $item['r'] = db('staff_mypays')->where("paid_sn = '{$item['paid_sn']}'")->find();
        }

        $this->assign('item', $item);
        return $this->fetch('view_transfer_log');
    }


    /*线下付款流水明细*/
    function offline_detail()
    {
        //echo 1;die;
		$from = I('from/s');
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];

        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $company_list = get_company_list();
        $this->assign('company_list', $company_list);
        $export = I('export');
        if ($export == 1) {
            $p = 0;
            $size = 1000;
        }
        $tz = I('get.tz/d');
        if ($tz == 2) {
            //扫码自定义
            $table_name = 'staff_mypays';
        } else {
            $table_name = 'staff_paid';
        }
        if ($company_id = I('get.company_id/d')) {
            $where['staff.store_id'] = ['eq', $company_id];
            $store_list = db('company')->where("parent_id = {$company_id}")->select();
            $this->assign('store_list', $store_list);
        }

        if ($store_id = I('get.store_id/d')) {
            $where['staff.store_id'] = ['eq', $store_id];
        };
        if ($is_pay = I('get.is_pay/d')) {
            if ($is_pay == 2) {
                $where['a.pay_status'] = ['eq', 0];
            } elseif ($is_pay == 1) {
                $where['a.pay_status'] = ['eq', 1];
            }
        }
        if (I('add_time_begin')) {
            $begin = strtotime(str_replace('+',' ',I('add_time_begin')));
            $end   = strtotime(str_replace('+',' ',I('add_time_end')));
        }
    //    echo $begin.'<br>'.$end;
        if($begin && $end){
            $where['a.create_time'] = array('between',"$begin,$end");
        }
		$key_word_type = I('get.key_word_type/d');
        if ($key_word = I('get.key_word/s')) {
			if($key_word_type == 1) {
				$where['a.paid_sn'] = ['like', "%{$key_word}%"];
			} else if($key_word_type == 2) {
				$where['a.user_id'] = $key_word;
			} else {
				$where['a.staff_id'] = $key_word;
			}
        }
    
        if ($export == 1) {
            $time = date("Y-m-d H:i:s", strtotime("-7 day"));
        } else {
            $time = date("Y-m-d H:i:s", strtotime("-30 day"));
        }

        $list = M($table_name)->where($where)->alias('a')
            ->field('a.*,staff.store_id,staff.company_id,staff.store_id,store.cname store_name,company.cname company_name,staff.real_name staff_name,staff.cumulative_money cumulative_money,tl.paid_sn is_store_collection,store.dbystore,re.money midou')
            ->join('staff staff', 'staff.id = a.staff_id')
            ->join('company store', 'store.cid = staff.store_id')
            ->join('company company', 'company.cid = staff.company_id')
            ->join('transfer_log tl', 'tl.paid_sn = a.paid_sn', 'left')
            ->join('red_envelope re', 're.order_sn = a.paid_sn', 'left')
            ->order("id desc")->page("$p,$size")->select();
    //    print_r($list);die;
        foreach ($list as $key => $value) {
            if ($value['pay_status'] == 1 && $value['rebate_status'] == 1) {
                //如果已经支付
                if ($table_name == 'staff_mypays') {
                    $map['pay_id'] = ['eq', $value['id']];
                } else {
                    $map['paid_id'] = ['eq', $value['id']];
                }
				
                #推广员
                $tgy_map = $map;
                $tgy_map['is_tj'] = ['eq', 1];
                $list[$key]['tgy_name'] = db('users u')->where("user_id = {$value['user_id']}")
                                            ->join('staff staff', 'staff.id = u.staff_id')
                                            ->cache("tgy_name_{$value['user_id']}")
                                            ->getField('staff.real_name');
                $list[$key]['tgy_money'] = db('staff_commission')->where($tgy_map)->cache(true)->sum('money');
                #员工
                $staff_map = $map;
                $staff_map['is_tj'] = ['eq', 0];
                $list[$key]['staff_money'] = db('staff_commission')->where($staff_map)->cache(true)->sum('money');
                #实体店
                if ($value['store_id']) {
                    $store_map = $map;
                    $store_map['member_id'] = ['exp', " in (select id from tp_company_member where parent_id = {$value['store_id']})"];
                    $list[$key]['fh_store_money'] = db('member_commission')->cache(true)->where($store_map)->sum('money');     //实体店成员的钱
                }
                #子公司
                if ($value['company_id']) {
                    $company_map = $map;
                    $company_map['member_id'] = ['exp', " in (select id from tp_company_member where parent_id = {$value['company_id']})"];
                    $list[$key]['company_money'] = db('member_commission')->cache(true)->where($company_map)->sum('money');     //子公司成员的钱
                }
                #推荐子公司
                $staff_id = $value['staff_id'];
                $company_id = $value['company_id'];
                $store_id = $value['store_id'];
                #提取推荐子公司分红比例
                #首先判断该实体店是不是被推荐的
                $r1 = M('company_sign')->where("company_id={$company_id} and store_id={$store_id}")->cache(true)->select();
                #如果该实体店是被推荐的，则上级子公司的返利从实体店里分出
                if($r1){
                    $is_flag = 'store';
                    $rlist = $r1;
                }else{
                    #如果该实体店不是被推荐的，判断其子公司是不是被推荐的
                    $r2 = M('company_sign')->where("company_id={$company_id} and store_id=0")->cache(true)->select();
                    #如果其子公司是被推荐的，则上级子公司的返利从该子公司中分出
                    if($r2){
                        $is_flag = 'company';
                        $rlist = $r2;
                    }else{
                        $is_flag = 'no';
                    }
                }
               
                #如果该实体店或其子公司是被推荐的，查询计算出分配给其上级子公司的分红比例
                if($is_flag != 'no'){
                    $profit_sum = 0;
                    foreach($rlist as $rp){
                        if($rp['company_level']){
                            $r = M('company_level')->field('profit')->find($rp['company_level']);
                            if($r){
                                $profit_sum += $r['profit'];
                            }
                        }
                    }
                    #利润比
                    $pz = M('company')->field('proportion,tgyxxbl,yscbl')->find($store_id);
                    $promoney = bcmul($value['money'],$pz['proportion'],9);
                    //推广员，线下比例：
                    $tgyxxbl = explode('|',$pz['tgyxxbl']);
                    //线下返利总额
                    $xianmoney = bcmul($promoney,$tgyxxbl[1],9);
                    //推广员，实体店，子公司推广分红比例
                    $yscbl = explode('|',$pz['yscbl']);
                    #实体店返利总额

                    $store_money = bcmul($xianmoney,$yscbl[1],9);
                    #子公司返利总额
                    $company_money = bcmul($xianmoney,$yscbl[2],9);

                    if($is_flag == "store"){
                        $list[$key]['elite_money'] = bcmul($store_money,$profit_sum,9);
                    }else{
                        $list[$key]['elite_money'] = bcmul($company_money,$profit_sum,9);
                    }
                }else{
                    $list[$key]['elite_money'] = 0;
                }
                $syjg = bcsub($value['dby_money'], $list[$key]['tgy_money'], 9);
                $syjg = bcsub($syjg, $list[$key]['staff_money'], 9);
                $syjg = bcsub($syjg, $list[$key]['fh_store_money'], 9);
                $syjg = bcsub($syjg, $list[$key]['company_money'], 9);
                $list[$key]['syjg'] = bcsub($syjg, $list[$key]['elite_money'], 9);
            }
        }
        if ($export == 1) {
            $this->company_export($list);
        }

        $count = M($table_name)->where($where)->alias('a')
//            ->whereTime('a.create_time', '>', $time)
            ->field('a.*,staff.store_id,staff.company_id,store.cname store_name,company.cname company_name,staff.real_name staff_name')
            ->join('staff staff', 'staff.id = a.staff_id')
            ->join('company store', 'store.cid = staff.store_id')
            ->join('company company', 'company.cid = staff.company_id')
            ->join('transfer_log tl', 'tl.paid_sn = a.paid_sn', 'left')
            ->join('red_envelope re', 're.order_sn = a.paid_sn', 'left')
            ->count();
        $resultUrl = $_SERVER['REQUEST_URI'];
        preg_match_all('/' . ACTION_NAME . '(.*?)(p\/\d+)?$/is', $resultUrl, $res1);
        $this->assign('ajaxdata', empty($res1[1][0]) ? "" : $res1[1][0]);

        $pager = new Page($count, $size);
        $this->assign('list', $list);
        $this->assign('pager', $pager);
		$tmp = ($from == 'qrcode')? 'offline_detail_qrcode' : 'offline_detail';
		return $this->fetch($tmp);
    }

    function company_export($list){
        $strTable = '<table width="1000" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">员工姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单编码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推广员金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推广员</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">员工</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">子公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">剩余</td>';

        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店结余</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">公司结余</td>';

        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">隶属公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">下单时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否支付</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">企业收款</td>';
        $strTable .= '</tr>';

        if (is_array($list)) {
            foreach ($list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['staff_id'] . ':' . $val['staff_name'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['paid_sn'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . tk_money_format($val['tgy_money'],9) . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['tgy_name'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . tk_money_format($val['staff_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['store_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['company_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['syjg'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['store_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['dby_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['store_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . date("Y-m-d H:i:s", $val['create_time']) . '</td>';
                $pay_status = ($val['pay_status'] == 1) ? ('是') : ('否');
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $pay_status . '</td>';
                $str = $val['is_store_collection'] ? ('是') : ('否');
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $str . '</td>';
                $strTable .= '</tr>';
            }

        }
        $strTable .= '</table>';
        downloadExcel($strTable, '线下流水明细');
        adminLog('导出线下流水明细');
        exit();
    }

    function get_staff_list()
    {

        $paid_sn = I('get.paid_sn');
        $id = I('get.id/d');
        if (stripos($paid_sn, 'staff_paid') !== false) {
            $where['paid_id'] = ['eq', $id];
        } else {
            $where['pay_id'] = ['eq', $id];
        }
        $where['is_tj'] = ['eq', 0];
        $list = db('staff_commission a')->field('a.*,staff.real_name')->join('staff staff', 'staff.id = a.staff_id', 'left')->where($where)->select();
        $this->assign('list', $list);
        return $this->fetch('tongji/staff_list');
    }

    /*获取某笔订单谁吃返利了 成员*/
    function get_store_list()
    {
        $paid_sn = I('get.paid_sn');
        $id = I('get.id/d');
        $store_where['a.id'] = ['eq', $id];
        if (stripos($paid_sn, 'staff_paid') !== false) {
            $table_name = 'staff_paid';
            $where['paid_id'] = ['eq', $id];
        } else {
            $table_name = 'staff_mypays';
            $where['pay_id'] = ['eq', $id];
        }

        $store_id = db($table_name)->alias('a')->where($store_where)
            ->join('staff staff', 'staff.id = a.staff_id')
            ->getField('a.store_id');
        if ($store_id) {
            #   $where['a.paid_sn']   =   ['eq',$paid_sn];
            $where['member_id'] = ['exp', " in (select id from tp_company_member where parent_id = {$store_id})"];
            $list = db('member_commission a')->field('a.*,member.real_name')
                ->join('company_member member', 'member.id = a.member_id', 'left')
                ->where($where)
                ->select();
        }

        $this->assign('list', $list);
        return $this->fetch('tongji/member_list');
    }

    /*获取某笔订单谁吃返利了 成员*/
    function get_company_list()
    {
        $paid_sn = I('get.paid_sn');
        $id = I('get.id/d');
        $company_where['a.id'] = ['eq', $id];
        if (stripos($paid_sn, 'staff_paid') !== false) {
            $table_name = 'staff_paid';
            $where['paid_id'] = ['eq', $id];
        } else {
            $table_name = 'staff_mypays';
            $where['pay_id'] = ['eq', $id];
        }
        $company_id = M($table_name)->alias('a')->where($company_where)
            ->join('staff staff', 'staff.id = a.staff_id', 'left')
            ->getField('a.company_id');
        if ($company_id) {
            #   $where['a.paid_sn']   =   ['eq',$paid_sn];
            $where['member_id'] = ['exp', " in (select id from tp_company_member where parent_id = {$company_id})"];
            $list = db('member_commission a')->field('a.*,member.real_name')
                ->join('company_member member', 'member.id = a.member_id', 'left')
                ->where($where)
                ->select();
        }

        $this->assign('list', $list);
        return $this->fetch('tongji/member_list');
    }

    /*获取推荐子公司成员获取返利情况*/
    function get_elite_company_list()
    {
        $paid_sn = I('get.paid_sn');
        $id = I('get.id/d');
        $company_where['id'] = ['eq', $id];
        if (stripos($paid_sn, 'staff_paid') !== false) {
            $table_name = 'staff_paid';
            $where['paid_id'] = ['eq', $id];
        } else {
            $table_name = 'staff_mypays';
            $where['pay_id'] = ['eq', $id];
        }

        $rst = M($table_name)
            ->field('store_id,company_id')
            ->where($company_where)
            ->find();

        if($rst){
            $company_id = $rst['company_id'];
            $store_id = $rst['store_id'];

            $company_sign_sql   =   " (`company_id` = {$company_id}  AND `store_id` = {$store_id}) OR (`company_id` = {$company_id} and `store_id` = 0)";

            $t_company = M('company_sign')->where($company_sign_sql)
                ->field('t_company_id')
                ->find();

            if ($t_company) {
                $t_company_id = $t_company['t_company_id'];
                $where['member_id'] = ['exp', " in (select id from tp_company_member where parent_id = {$t_company_id})"];
                $list = db('member_commission a')->field('a.*,member.real_name')
                    ->join('company_member member', 'member.id = a.member_id', 'left')
                    ->where($where)
                    ->select();
            }
        }

        $this->assign('list', $list);
        return $this->fetch('tongji/member_list');
    }


    function view_offline_log()
    {
        $id = I('get.id/d');
        $tz = I('get.tz/d');
        if ($tz == 2) {
            $table_name = 'staff_mypays';
        } else {
            $table_name = 'staff_paid';
        }

        $item = M($table_name)->where($where)->alias('a')
            ->field('a.*,staff.store_id,staff.company_id,store.cname store_name,company.cname company_name,staff.real_name staff_name,tl.paid_sn is_store_collection')
            ->join('staff staff', 'staff.id = a.staff_id')
            ->join('company store', 'store.cid = staff.store_id')
            ->join('company company', 'company.cid = staff.company_id')
            ->join('transfer_log tl', 'tl.paid_sn = a.paid_sn', 'left')
            ->find($id);
        $this->assign('item', $item);
        return $this->fetch('view_offline_log');
    }


    function bank_list()
    {
        $bank_list['1002'] = '工商银行';
        $bank_list['1005'] = '农业银行';
        $bank_list['1026'] = '中国银行';
        $bank_list['1003'] = '建设银行';
        $bank_list['1001'] = '招商银行';
        $bank_list['1066'] = '邮储银行';
        $bank_list['1020'] = '交通银行';
        $bank_list['1004'] = '浦发银行';
        $bank_list['1006'] = '民生银行';
        $bank_list['1009'] = '兴业银行';
        $bank_list['1010'] = '平安银行';
        $bank_list['1021'] = '中信银行';
        $bank_list['1025'] = '华夏银行';
        $bank_list['1027'] = '广发银行';
        $bank_list['1022'] = '光大银行';
        $bank_list['1032'] = '北京银行';
        $bank_list['1056'] = '宁波银行';
        return $bank_list;
    }

    public function return_percentage_fileput()
    {
//        Config::set('APP_DEBUG',true);
//        Config::set('show_error_msg',true);
		\think\Config::set('show_error_msg',true);
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 15 : $_REQUEST['size'];
        $company_list = get_company_list();
        $this->assign('company_list', $company_list);
        $export = I('export');
        if ($export == 1) {
            $p = 0;
            $size = 1000;
        }
        $tz = I('get.tz/d');
        if ($tz == 2) {
            //扫码自定义
            $table_name = 'staff_mypays';
        } else {
            $table_name = 'staff_paid';
        }
        if ($company_id = I('get.company_id/d')) {
            $where['staff.store_id'] = ['eq', $company_id];
            $store_list = db('company')->where("parent_id = {$company_id}")->select();
            $this->assign('store_list', $store_list);
        }

        if ($store_id = I('get.store_id/d')) {
            $where['staff.store_id'] = ['eq', $store_id];
        };
        if ($is_pay = I('get.is_pay/d')) {
            if ($is_pay == 2) {
                $where['a.pay_status'] = ['eq', 0];
            } elseif ($is_pay == 1) {
                $where['a.pay_status'] = ['eq', 1];
            }
        }
        
        $key_word_type = I('get.key_word_type/d');
        if ($key_word = I('get.key_word/s')) {
            if($key_word_type == 1) {
                $where['a.paid_sn'] = ['like', "%{$key_word}%"];
            } else if($key_word_type == 2) {
                $where['a.user_id'] = $key_word;
            } else {
                $where['a.staff_id'] = $key_word;
            }
        }
        if (I('get.add_time_begin')) {
            $begin = strtotime(I('get.add_time_begin'));
            $end   = strtotime(I('get.add_time_end'));
        }
        if($begin && $end){
            $where['a.create_time'] = array('between',"$begin,$end");
        }
        if ($export == 1) {
            $time = date("Y-m-d H:i:s", strtotime("-7 day"));
        } else {
            $time = date("Y-m-d H:i:s", strtotime("-30 day"));
        }

        $list = M($table_name)->where($where)->alias('a')
//            ->whereTime('a.create_time', '>', $time)
            ->field('a.*,staff.store_id,staff.company_id,store.cname store_name,company.cname company_name,staff.real_name staff_name,tl.paid_sn is_store_collection,store.dbystore,re.money midou')
            ->join('staff staff', 'staff.id = a.staff_id')
            ->join('company store', 'store.cid = staff.store_id')
            ->join('company company', 'company.cid = staff.company_id')
            ->join('transfer_log tl', 'tl.paid_sn = a.paid_sn', 'left')
            ->join('red_envelope re', 're.order_sn = a.paid_sn', 'left')
            ->order("id desc")->page("$p,$size")->select();
        $count = M($table_name)->where($where)->alias('a')
            ->field('a.*,staff.store_id,staff.company_id,store.cname store_name,company.cname company_name,staff.real_name staff_name,re.money midou')
            ->join('staff staff', 'staff.id = a.staff_id')
            ->join('company store', 'store.cid = staff.store_id')
            ->join('red_envelope re', 're.order_sn = a.paid_sn', 'left')
            ->join('company company', 'company.cid = staff.company_id')->count();

        foreach ($list as $key => $value) {
            if ($value['pay_status'] == 1) {
                //如果已经支付
                if ($table_name == 'staff_mypays') {
                    $map['pay_id'] = ['eq', $value['id']];
                } else {
                    $map['paid_id'] = ['eq', $value['id']];
                }
                #推广员
                $tgy_map = $map;
                $tgy_map['is_tj'] = ['eq', 1];
                $list[$key]['tgy_name'] = tgy_name($value['user_id']);
                $list[$key]['tgy_money'] = tgy_money($tgy_map);
                #员工
                $staff_map = $map;
                $staff_map['is_tj'] = ['eq', 0];
                $list[$key]['staff_money'] = staff_money($staff_map);
                #实体店
                if ($value['store_id']) {
                    $store_map = $map;
                    $store_map['member_id'] = ['exp', " in (select id from tp_company_member where parent_id = {$value['store_id']})"];
                    $list[$key]['fh_store_money'] = fh_store_money($store_map);     //实体店成员的钱
                }
                #子公司
                if ($value['company_id']) {
                    $company_map = $map;
                    $company_map['member_id'] = ['exp', " in (select id from tp_company_member where parent_id = {$value['company_id']})"];
                    $list[$key]['company_money'] = company_money($company_map);     //子公司成员的钱
                }
                #推荐子公司
                $staff_id = $value['staff_id'];
                $company_id = $value['company_id'];
                $store_id = $value['store_id'];
                #提取推荐子公司分红比例
                $r1 = storebili($company_id,$store_id);

                if($r1){
                    $is_flag = 'store';
                    $rlist = $r1;
                }else{
                    $r2 = companybili($company_id);
                    if($r2){
                        $is_flag = 'company';
                        $rlist = $r2;
                    }else{
                        $is_flag = 'no';
                    }
                }

                //dump($rlist);
                if($is_flag != 'no'){
                    $profit_sum = 0;
                    foreach($rlist as $rp){
                        $profit_sum += profit_sum($rp['company_level']);
                    }

                    #利润比
                    $pz = profitratio($store_id);
                    $promoney = bcmul($value['money'],$pz['proportion'],9);

                    //推广员，线下比例：
                    $tgyxxbl = explode('|',$pz['tgyxxbl']);
                    //线下返利总额
                    $xianmoney = bcmul($promoney,$tgyxxbl[1],9);
                    //推广员，实体店，子公司推广分红比例
                    $yscbl = explode('|',$pz['yscbl']);
                    #实体店返利总额

                    $store_money = bcmul($xianmoney,$yscbl[1],9);
                    #子公司返利总额
                    $company_money = bcmul($xianmoney,$yscbl[2],9);

                    if($is_flag == "store"){
                        $list[$key]['elite_money'] = bcmul($store_money,$profit_sum,9);
                    }else{
                        $list[$key]['elite_money'] = bcmul($company_money,$profit_sum,9);
                    }



                }else{
                    $list[$key]['elite_money'] = 0;
                }

                $syjg = bcsub($value['dby_money'], $list[$key]['tgy_money'], 9);
                $syjg = bcsub($syjg, $list[$key]['staff_money'], 9);
                $syjg = bcsub($syjg, $list[$key]['fh_store_money'], 9);
                $syjg = bcsub($syjg, $list[$key]['company_money'], 9);
                $list[$key]['syjg'] = bcsub($syjg, $list[$key]['elite_money'], 9);


            }
        }


        $user_id = $_SESSION["think"]["user"]["user_id"];

        $dir_url = "./public/datacom/data_" . $user_id . "/";

        if (!is_dir($dir_url)) {
            mkdir($dir_url, 0777, true);
        }
        $Page = new Page($count, $size);

        if ($Page->nowPage <= $Page->totalPages) {
            file_put_contents($dir_url . "/return_com_" . $Page->nowPage . ".txt", json_encode($list));
            return ceil($Page->nowPage / $Page->totalPages * 100);
        }

    }


    /**
     * [进度条完事将全返的txt文件导出excel]
     * @author 王牧田
     * @date 2018-09-07
     */
    public function return_percetage_downExcel()
    {

//        Config::set('APP_DEBUG',true);
//        Config::set('show_error_msg',true);
        $user_id = $_SESSION["think"]["user"]["user_id"];

        $dir_url = "./public/datacom/data_" . $user_id . "/";
        $files = scandir($dir_url);

        unset($files[0]);
        unset($files[1]);
        $filelenght = count($files);
        $orderList = [];
        for ($i = 1; $i <= $filelenght; $i++) {
            $data = file_get_contents($dir_url . "return_com_" . $i . ".txt");
            $row = json_decode($data, true);
            $orderList = array_merge($orderList, $row);
        }

        $strTable = '<table width="1000" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">员工姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:140px;">用户姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单编码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推广员金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推广员</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">员工</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">子公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">推荐子公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">剩余</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">米豆返点</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实体店结余</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">公司结余</td>';

        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">隶属公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属子公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">下单时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">是否支付</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">企业收款</td>';
        $strTable .= '</tr>';

        if (is_array($orderList)) {
            foreach ($orderList as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['staff_id'] . ':' . $val['staff_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . ':&nbsp;' . yonghu($val['user_id']) . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['paid_sn'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . tk_money_format($val['tgy_money'],9) . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['tgy_name'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . tk_money_format($val['staff_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['fh_store_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['company_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['elite_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['syjg'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['midou'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['store_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . tk_money_format($val['dby_money'],9) . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['store_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['company_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . date("Y-m-d H:i:s", $val['create_time']) . '</td>';
                $pay_status = ($val['pay_status'] == 1) ? ('是') : ('否');
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $pay_status . '</td>';
                $str = $val['is_store_collection'] ? ('是') : ('否');
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $str . '</td>';
                $strTable .= '</tr>';
            }

        }
        $strTable .= '</table>';
        downloadExcel($strTable, '线下流水明细');
        $this->removeDir($dir_url);
        adminLog('导出线下流水明细');
        exit();

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

    /**
     * [公司类目]
     * @author 王牧田
     * @date 2018-10-11
     */
    public function category()
    {
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $list = M('company_category')->where($map)->order("id desc")->page("$p,$size")->select();
        // foreach($list as $k=>$v){
        //     if($v['parent_id'] > 0){
        //         $list[$k]['store_name'] = M('company')->where("cid=".$v['parent_id'])->value("cname");
        //     }else{
        //         $list[$k]['store_name'] = "";
        //     }

        // }
        $count = M('company_category')->where($map)->count();
        $pager = new Page($count, $size);
        $this->assign('list', $list);
        $this->assign('pager', $pager);
        // dump($pager->show());die();
        return $this->fetch();
    }


    /**
     * [添加类目]
     * @author 王牧田
     * @date 2018-10-11category
     */
    public function addcategory()
    {

        if (I('get.id/d')) {
            $id = I('get.id/d');
            $item = M('company_category')->where(['id' => $id])->find();
            $this->assign('item', $item);
        }

        if (IS_POST) {
            if (!$id) {
                $verify_r = M('company_category')->where("cc_name = '{$_POST['cc_name']}'")->find();
                if ($verify_r) {
                    $res['status'] = 0;
                    $res['info'] = '公司类目重复，请重新填写';
                    $this->ajaxReturn($res);
                }

                if (M('company_category')->save($_POST)) {
                    $res['status'] = 1;
                    $res['info'] = '新增类目成功';
                    adminLog('新增公司类目');
                    $this->ajaxReturn($res);
                } else {
                    $res['status'] = 0;
                    $res['info'] = '新增失败';
                    $this->ajaxReturn($res);
                }
            } else {
                if (M('company_category')->update($_POST)) {
                    $res['status'] = 1;
                    $res['info'] = '修改类目成功';
                    adminLog('修改公司类目');
                    $this->ajaxReturn($res);
                } else {
                    $res['status'] = 0;
                    $res['info'] = '修改失败';
                    $this->ajaxReturn($res);
                }

            }

        }
        return $this->fetch("addcategory");
    }


    // 公司类目删除
    function catdel()
    {
        if ($id = I('post.id/d')) {
            if (M('company_category')->delete($id)) {
                adminLog('删除公司类目');
                $this->success('删除成功！', $this->catUrl);
            } else {
                $this->error('删除失败！');
            }
        } else {
            $this->error('非法操作');
        }
    }

    //获取用户信息
    function get_user_info(){
        $mobile = I('mobile','');
        $userinfo = M('users')->field('nickname,reg_time')->where("mobile='{$mobile}'")->find();
        if($userinfo){
            $userinfo['reg_time'] = date('Y-m-d H:i:s',$userinfo['reg_time']);
            $res['status'] = 1;
            $res['info'] = $userinfo;
            $this->ajaxReturn($res);
        }else{
            $res['status'] = 0;
            $res['info'] = '此手机号没有注册会员';
            $this->ajaxReturn($res);
        }
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
        $cid = I('cid');
        if($type != 1){
            $password = I('post.password'); 
            $config = M('config')->where("name = 'lockPassword' and inc_type = 'basic'")->find();
            if($config['value'] == md5($password)){
                if($type ==1){
                    $data = array(
                        'is_locks'   => 1,
                    );
                }else{
                    $data = array(
                        'is_locks'   => 0,
                    );
                }
                M('company')->where('cid',$cid)->save($data);
                echo "<script>parent.{$call_back}(1);</script>";
                exit();
            }else{
                echo "<script>parent.{$call_back}(2);</script>";
                exit();
            }
        }else{
            $data = array(
                'is_locks'   => 1,
            );
            M('company')->where('cid',$cid)->save($data);
            echo "1";
            exit();
        }
        
        
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
        $cid = I('get.cid');
        $this->assign('cid',$cid);
        $this->assign('type',$t);
        return $this->fetch();
    }


    public function get_city_area(){
        $regin_val = I('post.regin_val');
        $regin = explode("|", $regin_val);
        $data['province'] = M('region')->where(['parent_id'=>0])->select();  // 搜索省份
        $data['city'] = M('region')->where(['parent_id'=>$regin[0]])->select();  // 搜索省份
        $data['area'] = M('region')->where(['parent_id'=>$regin[1]])->select();  // 搜索省份

        return json_encode($data);

    }

}