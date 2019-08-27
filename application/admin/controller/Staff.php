<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\admin\controller; 
use app\admin\logic\StaffLogic;
use app\admin\logic\CompanyLogic;
use think\AjaxPage;
use think\Image;
use think\Page;
use think\Db;
use think\Request;
use app\admin\model\StaffModel;

class Staff extends Base {

    var $table_name;
    var $model;
    var $pk;
    var $indexUrl;
    var $company_model;
    var $company_level_model;
    public function _initialize() {
        parent::_initialize();   
        $this->table_name = 'staff';
        $this->pk ='id';
        $this->model = M($this->table_name);
        $this->indexUrl = U('Admin/Staff/Index');
        $this->company_model = M('company');
        $this->company_level_model = M('company_level');
    }

    public function index(){
        $t = I('t'); 
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $level_id = I('level_id') ? I('level_id') : '';
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id){
            $store_list = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
            $where['company_id'] = ['eq',$company_id];
        }
        if($store_id){
            $where['store_id']  =   ['eq',$store_id];
        }
        if(($t || $t == 0) && $t!=''){
            $where['type']  =   ['eq',$t];
        }
        if($level_id){
            $where['company_level'] = ['eq',$level_id];
        }
        if($key_word = I('key_word')){
            $where['real_name|phone'] = ['eq',$key_word];
        }
        $count = M('staff')->alias('staff')->where($where)
                        ->join('__COMPANY__ c','staff.company_id = cid','left')
                        ->count();
        $Page  = new Page($count,20);

        $list = M('staff')
                    ->alias('staff')
                    ->where($where)
                    ->field('staff.*,lv.lv_name,c.cname as company_name,d.cname as store_name')
                    ->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
                    ->join('__COMPANY__ c','staff.company_id = c.cid','left')
                    ->join('__COMPANY__ d','staff.store_id = d.cid','left')
                    ->order("{$this->pk} desc")
                    ->limit($Page->firstRow.','.$Page->listRows)
                    ->select(); 
        $show = $Page->show();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch('index');
    }

    public function staff_user(){
        $staff_id = I('staff_id/d');
        $where = array();
        $where['staff_id'] = $staff_id;

        if($key_word = I('key_word')){
            $where['real_name|mobile'] = ['eq',$key_word];
        }

        $count = M('users')->where($where)->count();
        $Page  = new Page($count,20);

        $list = M('users')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    

    // 统一后台效果
    #下面的这个函数  是用于在用户界面(detail.html)选择相应的推广员，现在用不到了，以后如果再用到直接复制粘贴就行，就不删除了
    public function search_promotders(){
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id = I('get.company_id/d')){
            $store_list = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
         #   dump($store_list);die;
            $this->assign('store_list',$store_list);
            $map['company_id'] = ['eq',$company_id];
        }
        if($store_id = I('get.store_id/d')){
            $map['store_id'] = ['eq',$store_id];
        }
        if($key_word = I('get.key_word/s')){
            $map['phone|real_name'] = ['like',"%{$key_word}%"];
        }
    //    $map['type']    =   ['eq',1];
        $list = M('staff')
                    ->alias('staff')
                    ->where($map)
                    ->field('staff.*,lv_name,company.cname as company_name,store.cname store_name')
                    ->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
                    ->join('__COMPANY__ company',"company.cid = staff.company_id",'left')
                    ->join('__COMPANY__ store',"store.cid = staff.store_id",'left')
                    ->order("{$this->pk} desc")
                    ->page("$p,$size")
                    ->select();
      #  echo M('staff')->getlastsql();
        $count = M('staff')->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);        
        return $this->fetch();
    }

    /*视图查看,排序*/
    function view(){
        $p    = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        if($company_id = I('get.company_id/d')){
            $map['company_id'] = ['eq',$company_id];
            $join_str = 'staff.company_id = company.cid';
        }
        if($store_id = I('get.store_id/d')){
            $map['store_id'] = ['eq',$store_id];
            $join_str = 'staff.store_id = company.cid';
        }
        $list = $this->model
                    ->alias('staff')
                    ->where($map)
                    ->field('staff.*,lv_name,company.cname as company_name')
                    ->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
                    ->join('__COMPANY__ company',$join_str,'left')
                    ->order("{$this->pk} desc")
                    ->page("$p,$size")
                    ->select();
        $count = $this->model->count();
        $pager = new Page($count,$size);
        $this->assign('list',$list);
        $this->assign('pager',$pager);        
        return $this->fetch();
    }

    function view_son(){
        $rid = I('get.id/d');
        if($top_id = I('get.top_id/d')){
            $map['top_id']    =   ['eq',$top_id];
            $map['rid']   =   ['eq',$rid];
        }
        $list = $this->model
                    ->alias('staff')
                    ->where($map)
                    ->field('staff.*,lv_name,company.cname as company_name')
                    ->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
                    ->join('__COMPANY__ company','staff.top_id = company.cid','left')
                    ->order("{$this->pk} desc")
                    ->select();

        if($list){
            foreach ($list as $key => $value) {
                $list[$key]['money']    =   tk_money_format($value['money']);
                $list[$key]['frozen']    =   tk_money_format($value['frozen']);
            }
            $data['status'] =   1;
            $data['list']   =   $list;
        }else{
            $data['status'] =   0;
            $data['list']   =   $this->model->getlastsql();
        }
        if(Request::instance()->isAjax()){
            $this->ajaxReturn($data);
        }else{
            return $data;
        }

    }

    // 添加 员工
    public function add(){
        /*查询实体店列表*/
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $level_id = I('level_id') ? I('level_id') : '';
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($store_id){
            $company_id = M('company')->field('parent_id')->find($store_id)['parent_id'];
            $this->assign('company_id',$company_id);
            $this->assign('store_id',$store_id);
        }
        $store_list = M('company')->where('parent_id','eq',$company_id)->select();
        $this->assign('store_list',$store_list);
        /*查询所有层级*/
        $id = ($store_id) ? ($store_id) : ($company_id);

        $level_list = M('company_level')->where(['c_parent_id'=>$id,'is_staff'=>1,'is_elite'=>0])->select();


        $this->assign('level_list',$level_list);

        if(IS_POST){
            $data                = I('post.');
            $data['tkpsw']         = encrypt($data['psw']);
            $staff_obj           = new StaffLogic();
            $res                 = $staff_obj->addStaff($data);
            if($res['status'] == 1){
                $msg['status'] = 1;
				$msg['id']		= $res['id'];
                $msg['info']    =   U('/Admin/Staff/index',array('company_id'=>$company_id,'store_id'=>$store_id,'t'=>$data['type']));
            }else{
                $msg['status']  =   0;
                $msg['info']    =   $res['msg'];
            }
            $this->ajaxReturn($msg);
        }

        //初始化某些值
        $item = ['money'=>0,
                'frozen'=>0,
                'is_lock'=>1,
                'service_charge'=>0,
                'present_money'=>0,
                'service_charge'=>0,
                'present_time_start'=>7,
                'present_time_end'=>20,
                ];
        $this->assign('item',$item);
        return $this->fetch('form');
    }
	
	/**
	 * 上传二维码
	 * by 刘姝含
	 * 2018/10/19 星期五
	**/
	public function toQcdoe() {


		//二维码
		$data = $_FILES['fileData'];
		$id = I('post.');
		$id = $id['id'];
		$tmp = $data['tmp_name'];
		$savePath = APP_PATH . "/../public/qrcode/".$id;
		$filename = $savePath.'/bg.png';
		if(!is_dir($savePath)) {mkdir($savePath, 644);}

		if($id && $res = move_uploaded_file($tmp, $filename)){
			echo "1";
		}

		//生成缩略图
		if($res && $data['type'] == "image/png"){
            $bigImg = $filename;
            $thumbImg = $savePath.'/bg-small-thumb.png';
            $img = \think\Image::open($bigImg);#打开大图片
            $img->thumb(50, 50);#生成缩略图
            $thumb = $savePath.'/bg-thumb.png';
            $img->save($thumbImg);
	    }
	  //二维码带pay
		$data = $_FILES['blobPay'];
		$tmp = $data['tmp_name'];
		$filename = $savePath.'/bg_pay.png';
		$res = move_uploaded_file($tmp, $filename);
		//生成缩略图
		if($res && $data['type'] == "image/png"){
            $bigPayImg = $filename;
            $thumbPayImg = $savePath.'/bg-pay-small-thumb.png';
            $img = \think\Image::open($bigPayImg);#打开大图片
            $img->thumb(50, 50);#生成缩略图
            $thumb = $savePath.'/bg-pay-thumb.png';
            $img->save($thumbPayImg);
	    }
        //二维码带weixin背景
        $data = $_FILES['blobwxPay'];
        $tmp = $data['tmp_name'];
        $filename = $savePath.'/bg_wx_pay.png';
        $res = move_uploaded_file($tmp, $filename);
        //生成缩略图
        if($res && $data['type'] == "image/png"){
            $bigPayImg = $filename;
            $thumbPayImg = $savePath.'/bg-wx-pay-small-thumb.png';
            $img = \think\Image::open($bigPayImg);#打开大图片
            $img->thumb(50, 50);#生成缩略图
            $thumb = $savePath.'/bg-wx-pay-thumb.png';
            $img->save($thumbPayImg);
        }

        //二维码带支付背景
        $data = $_FILES['blobwxAlipay'];
        $tmp = $data['tmp_name'];
        $filename = $savePath.'/bg_wxAlipay.png';
        $res = move_uploaded_file($tmp, $filename);
        //生成缩略图
        if($res && $data['type'] == "image/png"){
            $bigPayImg = $filename;
            $thumbPayImg = $savePath.'/bg-wxAlipay-small-thumb.png';
            $img = \think\Image::open($bigPayImg);#打开大图片
            $img->thumb(100, 100);#生成缩略图
            $thumb = $savePath.'/bg-wxAlipay-thumb.png';
            $img->save($thumbPayImg);
        }


	}



    public function edit(){
        $id   = I('get.id');
        $staff = M('staff')->where(array('id'=>$id))->find();

        if(!$staff)
            exit($this->error('员工不存在'));

        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
        /*查询所有实体店*/
        if($company_id = $staff['company_id']){
            $store_list = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
            $this->assign('store_list',$store_list);
        }
		$this->assign('qrcodeBg', "/public/qrcode/".$id.'/bg.png');
		$this->assign('thumbImg', "/public/qrcode/".$id.'/bg-small-thumb.png');
		$this->assign('qrcodePayBg', "/public/qrcode/".$id.'/bg_pay.png');
		$this->assign('thumbBGPayImg', "/public/qrcode/".$id.'/bg-pay-small-thumb.png');

        $this->assign('qrcodeWxPayBg', "/public/qrcode/".$id.'/bg_wx_pay.png');
        $this->assign('thumbWXPayImg', "/public/qrcode/".$id.'/bg-wx-pay-small-thumb.png');


        $this->assign('qrcodeWeixinPayBg', "/public/qrcode/".$id.'/bg_weixin_pay.png');
        $this->assign('thumbWixinPayImg', "/public/qrcode/".$id.'/bg-weixin-pay-small-thumb.png');

        $this->assign('qrcodeAlipayBg', "/public/qrcode/".$id.'/bg_Alipay.png');
        $this->assign('thumbAlipayImg', "/public/qrcode/".$id.'/bg-Alipay-small-thumb.png');

        $this->assign('qrcodewxAlipayBg', "/public/qrcode/".$id.'/bg_wxAlipay.png');
        $this->assign('thumbwxAlipayImg', "/public/qrcode/".$id.'/bg-wxAlipay-small-thumb.png');

        if( $staff['store_id'] ){
            $level_list = M('company_level')->where('c_parent_id = '.$staff['store_id'].' AND is_staff = 1 and is_elite=0')->select();
            $this->assign('level_list', $level_list);
            $stafflist = M('staff')->where("store_id = {$staff['store_id']} AND type = 0 and id !={$staff['id']}")->select();
            $this->assign('stafflist', $stafflist); 
        }

        if(IS_POST){
            $data     = I('post.');
            $password = I('post.psw');
            unset($data['psw']);
            if($password != ''){
                $data['tkpsw'] = encrypt($password);
            }
            
            $store_id  = $data['store_id'];
            $staff_obj = new StaffLogic();
            unset($data["search_content"]);
            $res       = $staff_obj->updateStaff($data['id'],$data);

            if($res['status'] == 1){;
                $msg['status'] = 1;
				$msg['id']		= $id;
                $msg['info']    =   U('/Admin/Staff/index',['company_id'=>$data['company_id'],'store_id'=>$store_id]);
            }else{
                $msg['status'] = 0;
                $msg['info']    =  $res['msg'];
            }
            $this->ajaxReturn($msg);
        }

        $this->assign('acts','updata');
        $this->assign('pk',$this->pk);
        $this->assign('staff', $staff);
        $this->assign('company_id', $staff['company_id']);
        $this->assign('store_id', $staff['store_id']);
        return $this->fetch('form');
    }



    function del(){
        if($id = I('id/d')){
            if(db('staff')->delete($id)){
                $this->success('删除成功！',$this->indexUrl);
            }else{
                $this->error('删除失败！');
            }
        }else{
            $this->error('非法操作');
        }
    }

    #张洪凯  2018-11-6
    function ajax_del(){
        $DelInfoLogic = new \app\admin\logic\DelInfoLogic();
        $res = $DelInfoLogic->delete_info();
        $this->ajaxReturn($res);
    }




    /*根据公司ID获取该公司下所有用户*/
    function getTop(){
        if($top_id = I('get.top_id/d')){
            $map['top_id'] = ['eq',$top_id];
            if($id = I('get.id/d')){
                $map['id']  =   ['neq',$id];
            } 
            $map['rid'] =   ['eq',0];
            if($list = $this->model->field('id,uname,real_name')->where($map)->select()){
                $data['status'] =   1;
                $data['list']   =   $list;
            }else{
                $data['status'] =   0;
            }
            if(Request::instance()->isAjax()){
                $this->ajaxReturn($data);
            }else{
                return $data;
            }
        }
    }

   


    function ajax_get_staff(){
        $store_id = I('get.store_id');
        $staff_id = I('get.staff_id');
        if($store_id){
            $where['store_id']  =   ['eq',$store_id];
            $where['type']  =   ['eq',0];
            if($staff_id){
                $where['id']  =   ['neq',$staff_id];
            }
            $where['parent_id'] = ['eq',''];
            $staff_list = db('staff')->field('id,uname')->where($where)->cache(true)->select();
            if($staff_list){
                $data['status'] =   1;
                $data['info']   =   $staff_list;
            }else{
                $data['status'] =   0;
            }
            $this->ajaxReturn($data);
        }
    }


    #更换域名重新生成二维码
    public function re_qrcode(){
        return $this->fetch();
    }

    public function create_qrcode(){
        extract($_GET); 
        $est1 = ExecTime();
        if(empty($sstime)) $sstime = time();

        foreach (I('get.') as $key => $value) {
            $query_string['query'][$key]  =   $value;
        }
        if(empty($query_string)){
            $query_string = [];
        }
        $totalnum = db('staff')->count();

        $list = Db::name('staff')->paginate(10,false,$query_string);
        $StaffLogic = new StaffLogic;
        foreach ($list as $key => $value) {
            $tjnum++;
            $save_data[]    =   ['id'=>$value['id'],'qrcode'=>$StaffLogic->qrcode($value['id'])];
        }
        if(empty($page)){
            $page = 2;
        }else{
            $page++;
        }
        $staff_mode = new staffModel;
        $staff_mode->saveAll($save_data);
        $t2 = ExecTime();
        $t2 = ($t2 - $est1);
        $ttime = time() - $sstime;
        $ttime = number_format(($ttime / 60),2);

        //返回提示信息
        $tjlen = $totalnum>0 ? ceil( ($tjnum/$totalnum) * 100 ) : 100;
        $tjsta = "<div style='width:200;height:15;border:1px solid #898989;text-align:left'><div style='width:{$tjlen}%;height:15;background-color:#829D83'></div></div>";
        $tjsta .= "<br/>本次用时：".number_format($t2,2)."，总用时：$ttime 分钟，到达位置：".($page)."<br/>完成创建文件总数的：$tjlen %，继续执行任务...";
        if($tjnum < $totalnum)
        {
            $nurl  = "/Admin/Staff/create_qrcode/page/{$page}";
            $nurl .= "?tjnum={$tjnum}&seltime=$seltime&sstime=$sstime&stime=".urlencode($stime);
            ShowMsg($tjsta,$nurl,0,1000);
            exit();
        }
        else
        {
            ShowMsg("完成所有更新任务！，生成二维码：$totalnum 总用时：{$ttime} 分钟。","javascript:;");
        }

    }


    /*推广员申请*/
    function tk_apply(){
        $count = M('apply_promoters')->alias('a')->where($where)
                        ->join('users user','a.user_id = user.user_id','left')
                        ->join('staff staff','a.staff_id = staff.id','left')
                        ->count();
        $Page  = new Page($count,20);

        $list = M('apply_promoters')
                    ->alias('a')
                    ->where($where)
                    ->field('a.*,user.mobile user_mobile,staff.real_name staff_name')
                    ->join('users user','a.user_id = user.user_id','left')
                    ->join('staff staff','a.staff_id = staff.id','left')
                    ->order("id desc")
                    ->limit($Page->firstRow.','.$Page->listRows)
                    ->select();

        $show = $Page->show();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    function get_buy_log(){
        $user_id = I('post.user_id/d',0);
        $apply_id = I('post.apply_id/d',0);
        if($user_id == 0){
            $res['status'] = 1;
            $res['msg'] = '没有要获取的记录';
            $this->ajaxReturn($res);
        }

        $apply_info = M('apply_promoters')->field('id,staff_id,contact')->find($apply_id);
        if($apply_info){

            $contact = $apply_info['contact'];

            //推广员审核限制配置信息  goods_unit 1 金额  2件数
            $goods_area = tpCache('basic.goods_area');
            $goods_unit = tpCache('basic.goods_unit');
            $goods_unit_con = tpCache('basic.goods_unit_con');

            //获取已完成订单的购买总金额或总件数
            $order_where['order_status'] = ['in', '2,4'];
            $order_where['o.user_id'] = ['eq', $user_id];
            $order_where['og.tg_ok'] = ['eq', 0];
            $order_where['og.is_tg'] = ['eq', 1];
            #提取现金区购买的商品记录
            $order_list1 = Db::name('order')
                ->alias('o')
                ->field('o.order_id,og.goods_id,og.goods_num,og.goods_price')
                ->join('order_goods og','o.order_id=og.order_id','left')
                ->where($order_where)
                ->select();
            #提取一乡一品区购买的商品记录
            $order_list2 = Db::name('order_yxyp')
                ->alias('o')
                ->field('o.order_id,og.goods_id,og.goods_num,og.goods_price')
                ->join('order_yxyp_goods og','o.order_id=og.order_id','left')
                ->where($order_where)
                ->select();
            #将两个区购买记录合并，计算推广员申请一共购买了多少
            $order_list = array_merge($order_list1,$order_list2);

            $total_moeny = 0;
            $total_num = 0;
            foreach ($order_list as $order) {
                if ($goods_unit == 1) {
                    //金额
                    $total_moeny += $order['goods_num'] * $order['goods_price'];
                } else {
                    //件数
                    $total_num += $order['goods_num'];
                }
            }

            if ($goods_unit == 1) {
                //如果设置的是金额
                if ($total_moeny >= $goods_unit_con || $apply_info['status'] == 3) {
                    $diff_money = 0;
                    //如果达标跳转到首页
                    //$this->redirect(U('Mobile/Index/index'));

                } else {
                    //如果不达标，计算还差多少金额达标
                    $diff_money = $goods_unit_con - $total_moeny;
                }

            } else {
                //如果设置的是件数
                if ($total_num >= $goods_unit_con || $apply_info['status'] == 3) {
                    $diff_num = 0;
                    //如果件数达标跳转到首页
                    //$this->redirect(U('Mobile/Index/index'));
                } else {
                    //如果不达标，计算还差多少件数达标
                    $diff_num = $goods_unit_con - $total_num;
                }

            }

            $unit_str =  $goods_unit == 1 ? '元' : '件';

            if($goods_unit == 1){
                $html_str = "已购买 ：".$total_moeny.$unit_str."<br/>还需购买：".$diff_money.$unit_str;
            }else{
                $html_str = "已购买 ：".$total_num.$unit_str."<br/>还需购买：".$diff_num.$unit_str;
            }

            $res['status'] = 0;
            $res['msg'] = array('contact'=>$contact,'html_str'=>$html_str);
            $this->ajaxReturn($res);
        }else{
            $res['status'] = 1;
            $res['msg'] = '无法获取到记录';
            $this->ajaxReturn($res);
        }


    }

    /*推广员申请状态*/
    function do_apply(){
        $status = I('get.status');
        $text = I('get.text/s');
        $id = I('get.id/d');
        if($status == -1 && empty($text)){
            $res['status']  =   0;
            $res['info']    =   '作废必须输入备注！';
            $this->ajaxReturn($res);
        }
        if($status == 3){
            $res = db('apply_promoters a')
                                ->field("a.*,u.password,s.store_id,s.company_id")
                                ->where("status = 2 and a.id ={$id}")
                                ->join("users u","u.user_id = a.user_id")
                                ->join("staff s","s.id = a.staff_id")
                                ->find();
                     
            if($res){
                $level_list = I('get.level_list');
                $data['company_level'] = $level_list;
                $data['uname']  =   $res['contact'];
                $data['tkpsw']  =   $res['psw'];
                $data['phone']  =   $res['mobile'];
                $data['create_time']  =   NOW_TIME;
                $data['real_name']  =   $res['contact'];
                $data['store_id']  =   $res['store_id'];
                $data['company_id']  =   $res['company_id'];
                $data['is_lock']  =   0;
                $data['type']  =   1;
                $data['parent_id']  =   $res['staff_id'];
                $data['invite_code']    =  judge_invite_code(get_rand_str(10,0,1)); 
                $staff_obj           = new StaffLogic();
                $r                 = $staff_obj->addStaff($data);
                if($r['status'] == 1){
                    db('apply_promoters')->where("id = {$id}")->update(['status'=>$status,'remark'=>$text,'update_time'=>NOW_TIME]);
                    $msg['status'] = 1;
                    $msg['info']    =   '添加推广员成功！';
                }else{
                    $msg['status']  =   0;
                    $msg['info']    =   $r['msg'];
                }

            }else{
                $msg['status']  =   0;
                $msg['info'] =  '员工信息被删除或会员信息被删除，自动成为推广员失败！';
            }
            $this->ajaxReturn($msg);
        }
        if(db('apply_promoters')->where("id = {$id}")->update(['status'=>$status,'remark'=>$text,'update_time'=>NOW_TIME])){
            $msg['status']  =   1;
            $msg['info']    =   '设置成功！';

        }else{
            $msg['status']  =   0;
            $msg['info']    =   '设置失败！';
        }
        $this->ajaxReturn($msg);
    }

    /*员工申请*/
    function staff_apply(){
        $count = M('apply_staff')->alias('a')->where($where)
            ->join('users user','a.user_id = user.user_id','left')
            ->join('staff staff','a.staff_id = staff.id','left')
            ->count();
        $Page  = new Page($count,20);

        $list = M('apply_staff')
            ->alias('a')
            ->where($where)
            ->field('a.*,user.mobile user_mobile,staff.real_name staff_name')
            ->join('users user','a.user_id = user.user_id','left')
            ->join('staff staff','a.staff_id = staff.id','left')
            ->order("id desc")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $show = $Page->show();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }


    /*员工申请状态*/
    function do_staff_apply(){
        $status = I('get.status');
        $text = I('get.text/s');
        $id = I('get.id/d');
        if($status == -1 && empty($text)){
            $res['status']  =   0;
            $res['info']    =   '作废必须输入备注！';
            $this->ajaxReturn($res);
        }
        if($status == 3){
            $res = db('apply_staff a')
                ->field("a.*,u.password,s.store_id,s.company_id")
                ->where("status = 2 and a.id ={$id}")
                ->join("users u","u.user_id = a.user_id")
                ->join("staff s","s.id = a.staff_id")
                ->find();

            if($res){
                $level_list = I('get.level_list');
                $data['company_level'] = $level_list;
                $data['uname']  =   $res['contact'];
                $data['tkpsw']  =   $res['psw'];
                $data['phone']  =   $res['mobile'];
                $data['create_time']  =   NOW_TIME;
                $data['real_name']  =   $res['contact'];
                $data['store_id']  =   $res['store_id'];
                $data['company_id']  =   $res['company_id'];
                $data['is_lock']  =   0;
                $data['type']  =   0;
                $data['parent_id']  =   $res['staff_id'];
                $data['invite_code']    =  judge_invite_code(get_rand_str(10,0,1));
                $staff_obj           = new StaffLogic();
                $r                 = $staff_obj->addStaff($data);
                if($r['status'] == 1){
                    db('apply_staff')->where("id = {$id}")->update(['status'=>$status,'remark'=>$text,'update_time'=>NOW_TIME]);
                    $msg['status'] = 1;
                    $msg['info']    =   '添加员工成功！';
                }else{
                    $msg['status']  =   0;
                    $msg['info']    =   $r['msg'];
                }

            }else{
                $msg['status']  =   0;
                $msg['info'] =  '会员信息被删除，自动成为员工失败！';
            }
            $this->ajaxReturn($msg);
        }
        if(db('apply_staff')->where("id = {$id}")->update(['status'=>$status,'remark'=>$text,'update_time'=>NOW_TIME])){
            $msg['status']  =   1;
            $msg['info']    =   '设置成功！';

        }else{
            $msg['status']  =   0;
            $msg['info']    =   '设置失败！';
        }
        $this->ajaxReturn($msg);
    }


    /**
     * [获取员工等级列表]
     * @author 王牧田
     * @date 2018-10-13
     */
    public function getlevellist(){

        $id = I('post.id');
        /*$res = db('apply_promoters a')
            ->field("a.*,s.store_id,s.company_id")
            ->where("status = 2 and a.id ={$id}")
            ->join("staff s","s.id = a.staff_id")
            ->find();*/

        $staff_id = db('apply_promoters')->where('id='.$id)->value('staff_id');
        $store_id = db('staff')->where('id='.$staff_id)->value('store_id');

        $level_list = M('company_level')->where('c_parent_id = '.$store_id.' AND is_staff = 1')->select();

        return json_encode($level_list);

    }

	/**
	 * 二维码查询
	 * @author 刘姝含
	 * @date 2018/10/19 
	**/
	public function qrcodeSearch() {
		$t = I('t'); 
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $level_id = I('level_id') ? I('level_id') : '';
		$key_word = I('key_word');
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
		if(!empty($company_id) || !empty($store_id) || !empty($level_id) || !empty($key_word)) {
			/*查询所有实体店*/
			if($company_id){
				$store_list = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
				$this->assign('store_list',$store_list);
				$where['company_id'] = ['eq',$company_id];
			}
			if($store_id){
				$where['store_id']  =   ['eq',$store_id];
			}
			if(($t || $t == 0) && $t!=''){
				$where['type']  =   ['eq',$t];
			}
			if($level_id){
				$where['company_level'] = ['eq',$level_id];
			}
			if($key_word){
				$where['real_name|phone'] = ['eq',$key_word];
			}
			$count = M('staff')->alias('staff')->where($where)
							->join('__COMPANY__ c','staff.company_id = cid','left')
							->count();
			$Page  = new Page($count,20);

			$list = M('staff')
						->alias('staff')
						->where($where)
						->field('staff.*,lv.lv_name,c.cname as company_name')
						->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
						->join('__COMPANY__ c','staff.company_id = cid','left')
						->order("{$this->pk} desc")
						->limit($Page->firstRow.','.$Page->listRows)
						->select(); 
			if(!empty($list)) {
				foreach($list as $k => $v) {
					$store = M('company')->field('cname')->where('cid','eq',$v['store_id'])->find();
					$list[$k]['story_name'] = $store['cname'];
				}
			}
			$show = $Page->show();
			$this->assign('list',$list);
			$this->assign('page',$show);// 赋值分页输出
			$this->assign('pager',$Page);
		}
		return $this->fetch('qrcode_search');
	}

	/**
	 * 批量生成带背景图的二维码
	 * @author 刘姝含
	 * @date 2018/10/23
	 * @param id:staff.id,invite_code:staff.invite_code,qrcodeImg: staff.qrcode
	**/
	public function batchqrcode() {
		$t = I('t'); 
        $company_id = I('company_id') ? trim(I('company_id')) : '';
        $store_id = I('store_id') ? trim(I('store_id')) : '';
        $level_id = I('level_id') ? I('level_id') : '';
		$key_word = I('key_word');
        $company_list = get_company_list();
        $this->assign('company_list',$company_list);
		/*查询所有实体店*/
		if($company_id){
			$store_list = M('company')->field('cid,cname')->where('parent_id','eq',$company_id)->select();
			$this->assign('store_list',$store_list);
			$where['company_id'] = ['eq',$company_id];
		}
		if($store_id){
			$where['store_id']  =   ['eq',$store_id];
		}
		if(($t || $t == 0) && $t!=''){
			$where['type']  =   ['eq',$t];
		}
		if($level_id){
			$where['company_level'] = ['eq',$level_id];
		}
		if($key_word){
			$where['real_name|phone'] = ['eq',$key_word];
		}
		$count = M('staff')->alias('staff')->where($where)
						->join('__COMPANY__ c','staff.company_id = cid','left')
						->count();
		$Page  = new Page($count,20);

		$list = M('staff')
					->alias('staff')
					->where($where)
					->field('staff.*,lv.lv_name,c.cname as company_name')
					->join('__COMPANY_LEVEL__ lv','staff.company_level = lv.id','left')
					->join('__COMPANY__ c','staff.company_id = cid','left')
					->order("{$this->pk} desc")
					->limit($Page->firstRow.','.$Page->listRows)
					->select(); 
		if(!empty($list)) {
			foreach($list as $k => $v) {
				$store = M('company')->field('cname')->where('cid','eq',$v['store_id'])->find();
				$list[$k]['story_name'] = $store['cname'];
			}
		}
		$show = $Page->show();
		$this->assign('list',$list);
		$this->assign('page',$show);// 赋值分页输出
		$this->assign('pager',$Page);
		return $this->fetch();
	}

    /**
     * 查询
     * @author 李鑫
     * @date 2018/11/05 
    **/
    public function search() {
        $key_word = I('key_word');
        if($key_word) {
            if($key_word){
                $where['nickname|mobile'] = ['like',"%{$key_word}%"];
            }
            $user = M('users')->where($where)->find();
            if(!$user)
                exit($this->error('会员不存在'));

            $this->assign('user',$user);
          
            // dump($staff);die();
        }
        return $this->fetch();
    }

    public function get_store_list(){
        $store_id = I('post.store_id');
        $res = M("company")->where(['cid'=>$store_id])->find();
        $data['company'] = M('company')->where(['parent_id'=>0])->select();  // 搜索子公司
        $data['store'] = M('company')->where(['parent_id'=>$res['parent_id']])->select();  // 搜索子公司
        $data["company_id"] = $res['parent_id'];
        return json_encode($data);
    }

}