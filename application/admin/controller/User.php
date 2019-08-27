<?php
/**
 * tpshop
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\admin\controller;
use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use think\Loader;

class User extends Base {

    public function index(){
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function ajaxindex(){
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;
        I('user_id') ? $condition['user_id'] = I('user_id') : false;

        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by').' '.I('sort');
               
        $model = M('users');
        $count = $model->where($condition)->count();
        $Page  = new AjaxPage($count,10);
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        
        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();
                               
        $show = $Page->show();
        $this->assign('userList',$userList);
        $this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    /*
    查看会员红包
    作者：王文凯
    2018年4月16日09:19:43
    */
    function red_envelope(){

        $p    = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        if($user_id = I('post.user_id/d')){
            $where['r.user_id'] = array('eq',$user_id);
            $user_info = db("users")->find($user_id);
            $this->assign('user_info',$user_info);
        }
        if($search_key = I('get.search_key/s')){
            $where['u.mobile'] = array('eq',$search_key);
        }
        $list = M('red_envelope')
                    ->alias('r')
                    ->field('r.*,u.nickname,u.user_id,u.mobile')
                    ->join('__USERS__ u ','r.user_id = u.user_id','left')
                    ->where($where)
                    ->order('id desc')
                    ->page("$p,$size")
                    ->select();
        
        $count = M('red_envelope')->alias('r')->where($where)->join('__USERS__ u ','r.user_id = u.user_id','left')->count();// 查询满足要求的总记录数
        $pager = new Page($count,$size);// 实例化分页类 传入总记录数和每页显示的记录数
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pager);// 赋值分页输出     
        // 渲染模板输出
        return $this->fetch();
    }
    /*
    会员卡分组列表
    */
    function club_card_group(){
        $list = db('club_card_group')->order('id desc')->select();
        $this->assign('list',$list);// 赋值数据集
        return $this->fetch('club_card_group');
    }
    /*添加会员卡分组*/
    function club_card_group_form(){
        if (\think\Request::instance()->isPost()){
            $data = I('post.');
            if(isset($data['id'])){
                $id = $data['id'];
                unset($data['id']);
                $r = db('club_card_group')->where('id',$id)->save($data);
                $this->success('修改成功',U('/admin/user/club_card_group'));
            }else{
                if(intval($data['total_number']) <= 0 || intval($data['total_number']) > 100){
                    $this->error('发行数量不能小于0,且不能大于100');
                }
                $data['create_time']    =   NOW_TIME;
                //get_rand_str(10,0,1)

                $group_id = db('club_card_group')->insertGetId($data);

                for($i=0;$i<$data['total_number'];$i++){
                    
                    $rand_str = $this->create_rand_club_card();
                //    cache('club_card_rand_str',)
                    $url  = "https://www.midoushu.com/Mobile/User/club_card/group_id/{$group_id}/card_number/{$rand_str}";
                    $save_path = APP_PATH . "/../public/club_card/{$group_id}/";
                    $qrcode_src = "/public/club_card/{$group_id}/".createQRcode($save_path,$url,'H',8);
                    $insert_data[] =    ['encryption_code'=>$rand_str,
                                'user_id'=>0,
                                'use_status'=>0,
                                'group_id'=>$group_id,
                                'qrcode_src'=>$qrcode_src,
                                ];
                }
                db('club_card_qrcode_list')->insertAll($insert_data);
                \think\cache::rm('club_card_rand_str');
                $this->success('添加成功',U('/admin/user/club_card_group'));
            }
        }else{
           
            $id = I('get.id/d');
            if($id){
                $item = db('club_card_group')->find($id);
                $this->assign('item',$item);
            }
            return $this->fetch('club_card_group_form');
        }
    }

    #去除重复的随机充值码
    function create_rand_club_card(){
        $rand_str = get_rand_str(10,0,1);
        $club_card_cache_data = cache('club_card_rand_str');
        if(empty($club_card_cache_data)){
            $club_card_cache_data[] =   $rand_str;
            cache('club_card_rand_str',$club_card_cache_data);
        }else{
            if(in_array($rand_str, $club_card_cache_data)){
                $rand_str = $this->create_rand_club_card();
            }else{
                $club_card_cache_data[] =   $rand_str;
                cache('club_card_rand_str',$club_card_cache_data);
            }
        }
        return $rand_str;
    }


    /*二维码列表*/
    function club_card_qrcode_list(){
        $group_id = I('get.id/d');
        $group_item = db('club_card_group')->find($id);
        $this->assign('group_item',$group_item);
        $list = db('club_card_qrcode_list')->where('group_id',$group_id)->order('use_status desc')->select();
        $this->assign('list',$list);// 赋值数据集
        return $this->fetch('club_card_qrcode_list');
    }

    /*
    冻结红包
    作者：王文凯
    2018年4月16日10:47:28
    */
    function frozen(){

        if($rid = I('get.rid/d')){
            $status = I('get.rstatus/d');
            switch ($status) {
                case 0:
                    $save_data['status'] = 1;
                    break;
                case 1:
                    $save_data['status'] = 0;
            }
            $save_data['admin_id'] = $_SESSION['admin_id'];
            $r = db('red_envelope')->where("rid = {$rid}")->save($save_data);
            if($r){
                $data['status']      = 1;
                $data['save_status'] = $save_data['status'];
                $data['msg']         = '冻结成功！';
            }else{
                $data['status']      = 1;
                $data['msg']         = '冻结失败！';
            }
            $this->ajaxReturn($data);
        }
    }


    /**
     * 会员详细信息查看
     */
    public function detail(){
        
        $uid = I('get.id');
        $user = D('users')->where(array('user_id'=>$uid))->find();
        if(!$user)
            exit($this->error('会员不存在'));
        if(IS_POST){
            //  会员信息编辑
            $password = I('post.password');
            $password2 = I('post.password2');
            if($password != '' && $password != $password2){
                exit($this->error('两次输入密码不同'));
            }
            if($password == '' && $password2 == ''){
                unset($_POST['password']);
            }else{
                $_POST['password'] = encrypt($_POST['password']);
            }

            if(!empty($_POST['email']))
            {   $email = trim($_POST['email']);
                $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }            
            
            if(!empty($_POST['mobile']))
            {   $mobile = trim($_POST['mobile']);
                $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }            
            
            $row = M('users')->where(array('user_id'=>$uid))->save($_POST);
            adminLog('修改会员详细信息');
            if($row)
                exit($this->success('修改成功'));
            exit($this->error('未作内容修改或修改失败'));
        }
        
        $user['first_lower']  = M('users')->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = M('users')->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower']  = M('users')->where("third_leader = {$user['user_id']}")->count();
        $openid =M('oauth_users')->where(["user_id"=>$user['user_id']])->value("openid");
        $this->assign('openid',$openid);
        $this->assign('user',$user);
        return $this->fetch();
    }
    
    public function add_user(){
    	if(IS_POST){
    		$data = I('post.');
			$user_obj = new UsersLogic();
			$res = $user_obj->addUser($data);
			if($res['status'] == 1){
                adminLog('添加角色');
				$this->success('添加成功',U('User/index'));exit;
			}else{
				$this->error('添加失败,'.$res['msg'],U('User/index'));
			}
    	}
    	return $this->fetch();
    }
    
    public function export_user(){
    	$strTable ='<table width="500" border="1">';
    	$strTable .= '<tr>';
    	$strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获得米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">使用米豆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">米豆结余</td>';
    	$strTable .= '</tr>';
    	$count = M('users')->count();
    	$p = ceil($count/5000);
    	for($i=0;$i<$p;$i++){
    		$start = $i*5000;
    		$end = ($i+1)*5000;
    		$userList = M('users')->order('user_id')->limit($start.','.$end)->select();
    		if(is_array($userList)){
    			foreach($userList as $k=>$val){
    				$strTable .= '<tr>';
    				$strTable .= '<td style="text-align:center;font-size:12px;">'.$val['user_id'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['level'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['email'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['reg_time']).'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['last_login']).'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['user_money'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_points'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou_all'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['midou_all']-$val['midou']).' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['midou'].' </td>';

    				$strTable .= '</tr>';
    			}
    			unset($userList);
    		}
    	}
    	$strTable .='</table>';
        header("Content-type: text/html; charset=utf-8");

        adminLog('导出会员');
    	downloadExcel($strTable,'users_'.$i);
    	exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address(){
        $uid = I('get.id');
        $lists = D('user_address')->where(array('user_id'=>$uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList',$regionList);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete(){
        $uid = I('get.id');
        $row = M('users')->where(array('user_id'=>$uid))->delete();
        if($row){
            adminLog('删除会员');
            $this->success('成功删除会员');
        }else{
            $this->error('操作失败');
        }
    }
    /**
     * 删除会员
     */
    public function ajax_delete(){
        if($_SESSION['admin_id'] != 1){
            return $this->ajaxReturn(array('status' => 0, 'msg' => '无权限删除', 'data' => ''));
        }
        $uid = I('id');
        if($uid){
            $row = M('users')->where(array('user_id'=>$uid))->delete();
            if($row !== false){
                adminLog('删除会员');
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            }else{
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        }else{
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log(){
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('account_log')->where(array('user_id'=>$user_id))->count();
        $page = new Page($count);
        $lists  = M('account_log')->where(array('user_id'=>$user_id))->order('change_time desc')->limit($page->firstRow.','.$page->listRows)->select();

        $this->assign('user_id',$user_id);
        $this->assign('page',$page->show());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit(){
        $user_id = I('user_id');
        if(!$user_id > 0) $this->ajaxReturn(['status'=>0,'msg'=>"参数有误"]);
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock,midou')->where('user_id',$user_id)->find();
        if(IS_POST){
            $desc = I('post.desc');
            if(!$desc)
                $this->ajaxReturn(['status'=>0,'msg'=>"请填写操作说明"]);
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money =  $m_op_type ? $user_money : 0-$user_money;
            
            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points =  $p_op_type ? $pay_points : 0-$pay_points;
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');

            $midou_act_type = I('post.midou_act_type');
            $pay_midou = I('post.pay_midou/f');
            $pay_midou =  $midou_act_type ? $pay_midou : 0-$pay_midou;

            if( $revision_frozen_money != 0){    //有加减冻结资金的时候
                $frozen_money =  $f_op_type ? $revision_frozen_money : 0-$revision_frozen_money;
                $frozen_money = $user['frozen_money']+$frozen_money;    //计算用户被冻结的资金
                if($f_op_type==1 and $revision_frozen_money > $user['user_money'])
                {
                    $this->ajaxReturn(['status'=>0,'msg'=>"用户剩余资金不足！！"]);
                }
                if($f_op_type==0 and $revision_frozen_money > $user['frozen_money'])
                {
                    $this->ajaxReturn(['status'=>0,'msg'=>"冻结的资金不足！！"]);
                }
                $user_money = $f_op_type ? 0-$revision_frozen_money : $revision_frozen_money ;    //计算用户剩余资金
                M('users')->where('user_id',$user_id)->update(['frozen_money' => $frozen_money]);
            }
            if(accountLog($user_id,$user_money,$pay_midou,$pay_points,$desc,$pay_midou))
            {
                adminLog('账户资金调节');
                $this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U("Admin/User/account_log",array('id'=>$user_id))]);
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败"]);
            }
            exit;
        }
        $this->assign('user_id',$user_id);
        $this->assign('user',$user);
        return $this->fetch();
    }

    // 会员充值额度
    public function rechargecofig(){
        $Ad =  M('rechargecofig');
        $p = $this->request->param('p');
        $list = $Ad->order('orderby')->page($p.',10')->select();
        $this->assign('list',$list);// 赋值数据集
        $count = $Ad->count();// 查询满足要求的总记录数
        $Page = new Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show();// 分页显示输出
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }

    // 添加编辑会员充值额度
    public function rechargecofig_detail(){
        $act = I('GET.act','add');
        $this->assign('act',$act);
        $rec_id = I('GET.rec_id');
        $rec_info = array();
        if($rec_id){
            $rec_info = M('rechargecofig')->where('rec_id='.$rec_id)->find();
            $this->assign('info',$rec_info);
        }
        return $this->fetch();
    }

    public function rechargecofig_Handle(){
        $data = I('post.');
        if($data['act'] == 'del'){
            $r = M('rechargecofig')->where(['rec_id'=>$data['rec_id']])->delete();
            if($r) exit(json_encode(1));
        }
        $result = $this->validate($data,'RechargeCofig.'.$data['act'], [], true);
        if(true !== $result){
            // 验证失败 输出错误信息
            $validate_error = '';
            foreach ($result as $key =>$value){
                $validate_error .=$value.',';
            }
            $this->error($validate_error);
        }

        if($data['act'] == 'add'){
            $r = M('rechargecofig')->insert($data);
        }
        if($data['act'] == 'edit'){
            $r = M('rechargecofig')->where('rec_id='.$data['rec_id'])->save($data);
        }
        if($r){
            $this->success("操作成功",U('Admin/User/rechargecofig'));
        }else{
            $this->error("操作失败");
        }
    }
    
    // 充值
    public function recharge(){
    	$timegap = urldecode(I('timegap'));
    	$nickname = I('nickname');
    	$map = array();
    	if($timegap){
    		$gap = explode(',', $timegap);
    		$begin = $gap[0];
    		$end = $gap[1];
    		$map['ctime'] = array('between',array(strtotime($begin),strtotime($end)));
    	}
    	if($nickname){
    		$map['nickname'] = array('like',"%$nickname%");
    	}  	
    	$count = M('recharge')->where($map)->count();
    	$page = new Page($count);
    	$lists  = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow.','.$page->listRows)->select();
    	$this->assign('page',$page->show());
        $this->assign('pager',$page);
    	$this->assign('lists',$lists);
    	return $this->fetch();
    }
    
    public function level(){
    	$act = I('get.act','add');
    	$this->assign('act',$act);
    	$level_id = I('get.level_id');
    	if($level_id){
    		$level_info = D('user_level')->where('level_id='.$level_id)->find();
    		$this->assign('info',$level_info);
    	}
    	return $this->fetch();
    }
    
    public function levelList(){
    	$Ad =  M('user_level');
        $p = $this->request->param('p');
    	$res = $Ad->order('level_id')->page($p.',10')->select();
    	if($res){
    		foreach ($res as $val){
    			$list[] = $val;
    		}
    	}
    	$this->assign('list',$list);
    	$count = $Ad->count();
    	$Page = new Page($count,10);
    	$show = $Page->show();
    	$this->assign('page',$show);
    	return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->add($data);
                if ($r !== false) {
                    adminLog('添加会员等级');
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->where('level_id=' . $data['level_id'])->save($data);
                if ($r !== false) {
                    adminLog('编辑会员等级');
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('user_level')->where('level_id=' . $data['level_id'])->delete();
            if ($r !== false) {
                adminLog('删除会员等级');
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));        
        if(strstr($search_key,'@'))    
        {
            $list = M('users')->where(" email like '%$search_key%' ")->select();        
            foreach($list as $key => $val)
            {
                echo "<option value='{$val['user_id']}'>{$val['email']}</option>";
            }                        
        }
        else
        {
            $list = M('users')->where(" mobile like '%$search_key%' ")->select();        
            foreach($list as $key => $val)
            {
                echo "<option value='{$val['user_id']}'>{$val['mobile']}</option>";
            }            
        } 
        exit;
    }
    
    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
          $list = M('users')->where("first_leader = 1")->select();
          return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users',$users);
        return $this->fetch();
    }

    /**
     * 发送系统消息
     * @author dyr
     * @time  2016/09/01
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $text= I('post.text');//内容
        $type = I('post.type', 0);//个体or全体
        $admin_id = session('admin_id');
        $users = I('post.user/a');//个体id
        $message = array(
            'admin_id'  => $admin_id,
            'message'   => $text,
            'category'  => 0,
            'send_time' => time(),
            'object'    => 'users'
        );

        if ($type == 1) {
            //全体用户系统消息
            $message['type'] = 1;
            M('Message')->add($message);
        } else {
            //个体消息
            $message['type'] = 0;
            if (!empty($users)) {
                $create_message_id = M('Message')->add($message);
                foreach ($users as $key) {
                    M('user_message')->add(array('user_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));
                }
            }
        }
        adminLog('发送站内信');
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email= I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if($email){
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $status = I('status/d');
    	$this->get_withdrawals_list($status);
        return $this->fetch();
    }
    
    public function get_withdrawals_list($status){
    	$user_id      = I('user_id/d');
    	$realname     = I('realname');
    	$bank_card    = I('bank_card');
    	$create_time  = I('create_time');
        $start_time   = I('start_time');
        $end_time     = I('end_time');

        $create_time  = str_replace("+"," ",$create_time);
        $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);

        $this->assign('start_time',$create_time3[0]);
        $this->assign('end_time',$create_time3[1]);
        if($create_time){
            $where['w.create_time'] =  array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));            
        }
        
        if($start_time && $end_time){
            $where['w.create_time'] =  array(array('gt', strtotime($start_time)), array('lt', strtotime($end_time)));
        }

    	if($status < 0 ){
    		$where['w.status'] = array('lt',0);
    	}
        if($status == 0 ){
            $where['w.status'] = 0;
        }
    	if($status > 0) {
    		$where['w.status'] = $status;
    	}

    	$user_id && $where['u.user_id'] = $user_id;
    	$realname && $where['w.realname'] = array('like','%'.$realname.'%');
    	$bank_card && $where['w.bank_card'] = array('like','%'.$bank_card.'%');

    	$export = I('export');
    	if($export == 1){
    		$strTable ='<table width="500" border="1">';
    		$strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">会员ID</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
    		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
    		$strTable .= '</tr>';
    		$remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
    		if(is_array($remittanceList)){
    			foreach($remittanceList as $k=>$val){
    				$strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['user_id'].'</td>';
    				$strTable .= '<td style="text-align:center;font-size:12px;">'.$val['nickname'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['money'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['bank_name'].'</td>';
    				$strTable .= '<td style="vnd.ms-excel.numberformat:@">'.$val['bank_card'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['realname'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['create_time']).'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['remark'].'</td>';
    				$strTable .= '</tr>';
    			}
    		}
    		$strTable .='</table>';
    		unset($remittanceList);
    		downloadExcel($strTable,'remittance');
    		exit();
    	}
    	$count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
    	$Page  = new Page($count,20);

    	$list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow.','.$Page->listRows)->select();

    	$this->assign('create_time',$create_time2);
    	$show  = $Page->show();
    	$this->assign('show',$show);
    	$this->assign('list',$list);
    	$this->assign('pager',$Page);
    	C('TOKEN_ON',false);
    }
    
    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $model = M("withdrawals");
        $model->where('id ='.$_GET['id'])->delete();
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        adminLog('删除申请记录');
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public  function editWithdrawals(){        
        $id = I('id');
        $model = M("withdrawals");
        $withdrawals = $model->find($id);
        $user = M('users')->where("user_id = {$withdrawals[user_id]}")->find();     
        if($user['nickname'])        
            $withdrawals['user_name'] = $user['nickname'];
        elseif($user['email'])        
            $withdrawals['user_name'] = $user['email'];
        elseif($user['mobile'])        
            $withdrawals['user_name'] = $user['mobile'];            
       
        $this->assign('user',$user);
        $this->assign('data',$withdrawals);

        if(IS_POST){
            $data['remark'] = I('remark');
            $r    = M('withdrawals')->where('id ='.$id)->update($data);
            adminLog('修改申请提现');
            if($r) $this->success('修改成功！');
        }

       return $this->fetch();
    }  

    /**
     *  处理会员提现申请
     */
    public function withdrawals_update(){
    	$id = I('id/a');
        $data['status'] = $status = I('status');
    	$data['remark'] = I('remark');
        if($status == 1) $data['check_time'] = time();
        if($status != 1) $data['refuse_time'] = time();

        $lists = M('withdrawals')->where('id in ('.implode(',', $id).')')->select();
        $r     = M('withdrawals')->where('id in ('.implode(',', $id).')')->update($data);
    	if($r){
            if($status == 3){
                foreach ($lists as $k => $val) {
                    $user = M('users')->where('user_id ='.$val['user_id'])->find();
                    $user_id = $val['user_id'];
                    $money   = $val['money']+$val['taxfee'];
                    accountLog($user_id, $money, 0, 0, '管理员拒绝会员提现申请');
                    $up_data['frozen_money'] = -1*$money+$user['frozen_money'];
                    M('users')->where('user_id ='.$user_id)->update($up_data);
                }
            }
            adminLog('处理会员提现申请');
    		$this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"),'JSON');
    	}else{
    		$this->ajaxReturn(array('status'=>0,'msg'=>"操作失败"),'JSON');
    	}  	
    }


    // 用户申请提现
    public function transfer(){
    	$id = I('selected/a'); // 选择的处理项目
    	if(empty($id))$this->error('请至少选择一条记录');
    	$atype = I('atype');   // 处理类型
    	if(is_array($id)){
    		$withdrawals = M('withdrawals')->where('id in ('.implode(',', $id).')')->select();
    	}else{
    		$withdrawals = M('withdrawals')->where(array('id'=>$id))->select();
    	}
    	$alipay['batch_num'] = 0;
    	$alipay['batch_fee'] = 0;
    	foreach($withdrawals as $val){
    		$user = M('users')->where(array('user_id'=>$val['user_id']))->find();
            $total = bcadd($val['money'],$val['taxfee'],2); // 总

    		if($user['frozen_money'] < $total)
    		{
    			//$data = array('status'=>-2,'remark'=>'账户冻结金额不足');
    			//M('withdrawals')->where(array('id'=>$val['id']))->save($data);
    			$this->error('账户冻结金额不足'); 
                exit();
    		} else {
    			$rdata = array('type'=>1,'money'=>$val['money'],'log_type_id'=>$val['id'],'user_id'=>$val['user_id']);
    			if($atype == 'online'){
			        header("Content-type: text/html; charset=utf-8");
                    exit("暂不支持此功能");
    			}else{
    				//accountLog($val['user_id'], ($val['money'] * -1), 0, 0,"管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $up_data['frozen_money'] = -1*$total+$user['frozen_money'];
                    M('users')->where('user_id ='.$val['user_id'])->update($up_data);
    				$r = M('withdrawals')->where(array('id'=>$val['id']))->save(array('status'=>2,'pay_time'=>time()));
    				expenseLog($rdata);//支出记录日志
    			}
    		}
    	}
    	if($alipay['batch_num']>0){
    		//支付宝在线批量付款
    		include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
    		$alipay_obj = new \alipay();
    		$alipay_obj->transfer($alipay);
            adminLog('用户申请提现');
    	}
    	$this->success("操作成功!",U('remittance'),3);
    }
    
    /**
     *  转账汇款记录
     */
    public function remittance(){
        $status = I('status/d');
        $status = empty($status) ? 1 : $status;
    	$this->assign('status',$status);
    	$this->get_withdrawals_list($status);
        return $this->fetch();
    }

        /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList() {       
        header("Content-type: text/html; charset=utf-8");
        exit("请联系TPshop官网客服购买高级版支持此功能");
    }
    
    
    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList() {
        header("Content-type: text/html; charset=utf-8");
        exit("请联系TPshop官网客服购买高级版支持此功能");
    }
    
    /**
     * 签到规则设置 
     * @date 2017/09/28
     */
    public function signRule() {
        header("Content-type: text/html; charset=utf-8");
        exit("请联系TPshop官网客服购买高级版支持此功能");
    }

    

    function tocash(){

        $user_id     = I('user_id/d');
        $create_time = I('create_time');
        $start_time  = I('start_time');
        $end_time    = I('end_time');
        $status      = I('status');

        $create_time  = str_replace("+"," ",$create_time);
        $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);

        $this->assign('start_time',$create_time3[0]);
        $this->assign('end_time',$create_time3[1]);

        // 查询条件
        $where = array();
        if($create_time){
            $where['cash.create_time'] =  array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));            
        }
        if($start_time && $end_time){
            $where['cash.create_time'] =  array(array('gt', strtotime($start_time)), array('lt', strtotime($end_time)));
        }
        if($status > -2) {
            $where['cash.status'] = $status;
        }
        $user_id && $where['u.user_id'] = $user_id;


        $count = Db::name('tocash')->alias('cash')->join('__USERS__ u', 'u.user_id = cash.user_id', 'left')->where($where)->count();
        $Page  = new Page($count,20);

        $list = Db::name('tocash')
                ->alias('cash')
                ->field('cash.*,u.nickname,mobile')
                ->join('__USERS__ u', 'u.user_id = cash.user_id', 'left')
                ->where($where)->order("cash.id desc")
                ->limit($Page->firstRow.','.$Page->listRows)
                ->select();
        $show  = $Page->show();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);

        return $this->fetch();
    }


    function tocash_update(){
        $id = I('id/a');
        $save_data['status'] = $status = I('status');
        $save_data['remark'] = I('remark');
        if($status == 1) $save_data['check_time'] = time();
        if($status == -1) $save_data['refuse_time'] = time();

        $lists = M('tocash')->where('id in ('.implode(',', $id).')')->select();
        if($lists){
            $error_msg    = '';
            $error_status = false;
            foreach ($lists as $k => $val) {
                $r = db('tocash')->alias('cash')->field('cash.*,rebate_money,dj_rebate')->join('users u','u.user_id = cash.user_id')->where("cash.id = {$val['id']}")->find();
                if($status == -1){
                    $save_data['remark'] = $data['remark'];
                    if($r['dj_rebate'] < $r['total']){
                        $error_status = true;
                        $error_msg   .= "会员(ID：".$r['user_id'].")因冻结余额不足，审核失败！";
                        continue;
                    }else{
                        $user_data['dj_rebate']     = ['exp',"dj_rebate - {$r['total']}"];
                        $user_data['rebate_money']  = ['exp',"rebate_money + {$r['total']}"];
                        db('users')->where("user_id = {$r['user_id']}")->update($user_data);
                    }
                }

                $save_data['status']    = $status;
                if($status==1){

                    if($r['dj_rebate'] < $r['total']){
                        $error_status = true;
                        $error_msg   .= "会员(ID：".$r['user_id'].")因冻结余额不足，审核失败！\n";
                        continue;
                    }else{   
                        $back_money = $r['money'];
                        $back_midou = $r['midou'];
                        $user_data['dj_rebate']  = ['exp',"dj_rebate - {$r['total']}"];
                        $user_data['midou']      = ['exp',"midou + {$back_midou}"];
                        $user_data['midou_all']  = ['exp',"midou_all + {$back_midou}"];
                        $user_data['user_money'] = ['exp',"user_money + {$back_money}"];
                        $s = db('users')->where("user_id = {$r['user_id']}")->update($user_data);
                        if($s){
                            $red_envelope_data  =   ['red_name'=>'返利提现','create_time'=>NOW_TIME,'source'=>'返利提现反米豆','money'=>$back_midou,'user_id'=>$r['user_id']];
                            db('red_envelope')->insert($red_envelope_data);

                            $account_log = array(
                                'user_id'       => $r['user_id'],
                                'user_money'    => $back_money,
                                'midou'         => $back_midou,
                                'midou_all'     => $back_midou,
                                'pay_points'    => 0,
                                'change_time'   => NOW_TIME,
                                'desc'          => '返利提现、米豆',
                                'order_id'      => 0,
                                'is_red'        => 0,
                            );    
                            M('account_log')->add($account_log);   
                                     
                        }else{
                            $error_status = true;
                            $error_msg   .= "会员(ID：".$r['user_id'].")因系统繁忙，请稍后再试！\n";
                            continue;
                        }
                    }
                }
                db("tocash")->where("id = {$val['id']}")->save($save_data);
            }

            if($error_status)
                $this->ajaxReturn(array('status'=>0,'msg'=>$error_msg),'JSON');
            else
                $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"),'JSON');
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>"操作失败"),'JSON');
        }   

    }

    function doCash(){
        $status  = I('post.status/d');
        $id      = I('post.id/d');
        $remark  = I('post.remark/s');
        $user_id = I('post.user_id/d');
        if($id){
            $r = db('tocash')->alias('cash')->field('cash.*,rebate_money,dj_rebate')->join('users u','u.user_id = cash.user_id')->where("cash.id = {$id}")->find();
            if($status == -1){
                $save_data['remark'] = $remark;
                if($r['dj_rebate'] < $r['total']){
                    $res['status'] = 0;
                    $res['info']   = '冻结余额不足，审核失败！';
                    $this->ajaxReturn($res);
                }else{
                    $user_data['dj_rebate']     = ['exp',"dj_rebate - {$r['total']}"];
                    $user_data['rebate_money']  = ['exp',"rebate_money + {$r['total']}"];
                    db('users')->where("user_id = {$r['user_id']}")->update($user_data);
                }
            }
            $save_data['status']    = $status;
            if($status==1){
                # $rebate_money = db('users')->where("user_id = {$user_id}")->getField('rebate_money');
                
                if($r['dj_rebate'] < $r['total']){
                    $res['status'] = 0;
                    $res['info']   = '冻结余额不足，审核失败！';
                    $this->ajaxReturn($res);
                }else{   
                    $back_money = $r['money'];
                    $back_midou = $r['midou'];
                    $user_data['dj_rebate']  = ['exp',"dj_rebate - {$r['total']}"];
                    $user_data['midou']      = ['exp',"midou + {$back_midou}"];
                    $user_data['midou_all']  = ['exp',"midou_all + {$back_midou}"];
                    $user_data['user_money'] = ['exp',"user_money + {$back_money}"];
                    $s = db('users')->where("user_id = {$r['user_id']}")->update($user_data);
                    if($s){
                        $red_envelope_data  =   ['red_name'=>'返利提现','create_time'=>NOW_TIME,'source'=>'返利提现反米豆','money'=>$back_midou,'user_id'=>$r['user_id']];
                        db('red_envelope')->insert($red_envelope_data);
                        $account_log = array(
                            'user_id'       => $r['user_id'],
                            'user_money'    => $back_money,
                            'midou'         => $back_midou,
                            'midou_all'     => $back_midou,
                            'pay_points'    => 0,
                            'change_time'   => NOW_TIME,
                            'desc'          => '返利提现、米豆',
                            'order_id'      => 0,
                            'is_red'        => 0,
                        );    
                        M('account_log')->add($account_log);   
                                 
                    }else{
                        $res['status']  =   0;
                        $res['info']    =   '系统繁忙，请稍后再试！';  #更新出错，直接退出
                        $this->ajaxReturn($res);
                    }

                }
            }
            if(db("tocash")->where("id = {$id}")->save($save_data)){
                $res['status']  =   1;
            }else{
                $res['status']  =   0;
                $res['info']    =   '系统繁忙，请稍后再试！';
            }       
            $this->ajaxReturn($res);
        }
    }

}