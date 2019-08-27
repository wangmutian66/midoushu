<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\home\controller;
use app\common\logic\UsersLogic;
use think\Db;
use think\Session;
use think\Verify;
use think\Cookie;
use app\home\model\AccessLog;

class Api extends Base {
    public  $send_scene;
    public function _initialize() {
        parent::_initialize();        
    }
    /*
     * 获取地区
     */
    public function getRegion(){
        $parent_id = I('get.parent_id/d');
        $selected = I('get.selected',0);        
        $data = M('region')->where("parent_id",$parent_id)->select();
        $html = '';
        if($data){
            foreach($data as $h){
            	if($h['id'] == $selected){
            		$html .= "<option value='{$h['id']}' selected>{$h['name']}</option>";
            	}
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }
    
	/*
     * 模糊搜索 获取地区
	 * by 刘姝含
	 * 2018/10/25 星期四
     */
    public function getKeywordRegion(){
        $keyword = I('get.keyword/s');       
        $data = M('region')->where("`name` like '%{$keyword}%' and `level` < 4")->select();
        $html = '';
        if($data){
            foreach($data as $h){
                //市
				if($h['level'] == 2) {
					$province = M('region')->where("`id`='{$h['parent_id']}' and `level`=1")->field('name')->find();
					// $html .= $province['name'].$h['name'].'<br>';
					$html .= "<option value='{$h['parent_id']}|{$h['id']}'>{$province['name']}{$h['name']}</option>";
                //区
				} else if($h['level'] == 3) {
					$city = M('region')->where("`id`='{$h['parent_id']}' and `level`=2")->field('name, parent_id')->find();
					if($city) {
						$province = M('region')->where("`id`='{$city['parent_id']}' and `level`=1")->field('name')->find();
					}
					$html .= "<option value='{$city['parent_id']}|{$h['parent_id']}|{$h['id']}'>{$province['name']}{$city['name']}{$h['name']}</option>";
                //省
				} else {
                    // echo 1;
					// $html .= $h['name'].'<br>';
					$html .= "<option value='{$h['id']}'>{$h['name']}</option>";
				}
            }
        }

        echo $html;
    }




    

    public function check_login(){
        $user_info = session('user');
        if($user_info['user_id']){
            $logic = new UsersLogic();
            $user = $logic->get_info($user_info['user_id']);
            $out_data['waitReceive']    =   $user['result']['waitReceive'];
            $out_data['waitSend']    =   $user['result']['waitSend'];
            $out_data['waitPay']    =   $user['result']['waitPay'];
            $out_data['uncomment_count']    =   $user['result']['uncomment_count'];
            $out_data['head_pic']   =   $user_info['head_pic'] ? $user_info['head_pic'] : '/template/pc/chengxin/static/img/img5.jpg';
            $out_data['user_id']    =   $user_info['user_id'];
            $out_data['nickname']    =   $user_info['nickname'];
            $msg['status']  =   1;
            $msg['info']    =   $out_data;
        }else{
            $msg['status']  =   0;
            $msg['info']    =   false;
        }
        echo json_encode($msg);
    }

    public function getTwon(){
    	$parent_id = I('get.parent_id/d');
    	$data = M('region')->where("parent_id",$parent_id)->select();
    	$html = '';
    	if($data){
    		foreach($data as $h){
    			$html .= "<option value='{$h['id']}'>{$h['name']}</option>";
    		}
    	}
    	if(empty($html)){
    		echo '0';
    	}else{
    		echo $html;
    	}
    }

    /**
     * 获取省
     */
    public function getProvince()
    {
        $province = Db::name('region')->field('id,name')->where(array('level' => 1))->cache(true)->select();
        $res = array('status' => 1, 'msg' => '获取成功', 'result' => $province);
        exit(json_encode($res));
    }

    /**
     * 获取市或者区
     */
    public function getRegionByParentId()
    {
        $parent_id = input('parent_id');
        $res = array('status' => 0, 'msg' => '获取失败，参数错误', 'result' => '');
        if($parent_id){
            $region_list = Db::name('region')->field('id,name')->where(['parent_id'=>$parent_id])->select();
            $res = array('status' => 1, 'msg' => '获取成功', 'result' => $region_list);
        }
        exit(json_encode($res));
    }
    

    /*
     * 获取商品分类
     */
    public function ad_get_category(){

        $parent_id = I('get.parent_id/d'); // 商品分类 父id
        
        $list = db('ad_category')->where("parent_id", $parent_id)->select();
        
        $list_ad = db('ad_position')->where("cate_id", $parent_id)->select();
        $array = array();
        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['ad_category_name']}</option>"; 
        foreach($list_ad as $kk => $vv)
            $html_ad .= "<option value='{$vv['position_id']}'>{$vv['position_name']}</option>";  
            $array['list'] =$html;
            $array['list_ad'] =$html_ad;
            ajaxReturn($array);
        //exit(json_encode($array));
    }


    /*
     * 获取商品分类
     */
    public function get_category(){
       
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
            $list = db('goods_category')->where("parent_id", $parent_id)->select();
        
        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['name']}</option>";      
        exit($html);
    }  

    /*
     * 获取商品分类
     */
    public function get_category_yxyp(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
            $list = db('goods_yxyp_category')->where("parent_id", $parent_id)->select();
        
        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['name']}</option>";        
        exit($html);
    }  
    /*
        LW 2018年4月21日11:21:09
     *  获取红包商城商品分类
     */
    public function get_category_red(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
            $list = M('goods_red_category')->where("parent_id", $parent_id)->select();
        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['name']}</option>";        
        exit($html);
    }

    /*
     * 获取子公司，实体店
     */
    public function get_company(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
            $list = M('company')->where("parent_id", $parent_id)->select();
        foreach($list as $k => $v)
            $html .= "<option value='{$v['cid']}'>{$v['cname']}</option>";        
        exit($html);
    }


    /*
     * 获取子公司，实体店 员工等级
     */
    public function get_company_level(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
        $type      = I('get.t/d');  // 类型

        $map['c_parent_id'] = $parent_id;  // 父ID
        if( $type ) $map['is_staff'] = 1; else $map['is_staff'] = 0;

        $list = M('company_level')->where($map)->select();
        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['lv_name']}</option>";        
        exit($html);
    } 


    /*
     * 获取子公司，实体店 员工
     */
    public function get_company_staff(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id

        $map['store_id']    = $parent_id;  // 父ID
        $map['type'] = 0;

        $list = M('staff')->where($map)->select();
        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['uname']}</option>";        
        exit($html);
    } 
    
    
    /**
     * 前端发送短信方法: APP/WAP/PC 共用发送方法
     */
    public function send_validate_code(){
        $this->send_scene = C('SEND_SCENE');

        $type   = I('type');
        $scene  = I('scene');    //发送短信验证码使用场景
        $mobile = I('mobile');
        $sender = I('send');
        $verify_code = I('verify_code');
        $mobile = !empty($mobile) ?  $mobile : $sender ;
        $session_id = I('unique_id' , session_id());
        session("scene" , $scene);
        
        if($type == 'email'){
            //发送邮件验证码
            $logic = new UsersLogic();
            $res = $logic->send_email_code($sender);
            ajaxReturn($res);
        }else{
            //发送短信验证码
            $res = checkEnableSendSms($scene);
            if($res['status'] != 1){
                ajaxReturn($res);
            }
            //判断是否存在验证码
            $data = M('sms_log')->where(array('mobile'=>$mobile,'session_id'=>$session_id, 'status'=>1))->order('id DESC')->find();

            //获取时间配置
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 120;
            //120秒以内不可重复发送
            if($data && (time() - $data['add_time']) < $sms_time_out){
                $return_arr = array('status'=>-1,'msg'=>$sms_time_out.'秒内不允许重复发送');
                ajaxReturn($return_arr);
            }
            //随机一个验证码
            $code = rand(100000, 999999); 
            $params['code'] =$code;
            //发送短信
            $resp = sendSms($scene , $mobile , $params, $session_id);

            if($resp['status'] == 1){
                //发送成功, 修改发送状态位成功
                M('sms_log')->where(array('mobile'=>$mobile,'code'=>$code,'session_id'=>$session_id , 'status' => 0))->save(array('status' => 1));

                $return_arr = array('status'=>1,'msg'=>'发送成功,请注意查收');
            }else{
                $return_arr = array('status'=>-1,'msg'=>'发送失败'.$resp['msg']);
            }
            ajaxReturn($return_arr);
        }
    }
    
    /**
     * 验证短信验证码: APP/WAP/PC 共用发送方法
     */
    public function check_validate_code(){
          
        $code   = I('post.code');
        $mobile = I('mobile');
        $send   = I('send');
        $sender = empty($mobile) ? $send : $mobile; 
        $type   = I('type');
        $session_id = I('unique_id', session_id());
        $scene  = I('scene', -1);

        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $sender, $type ,$session_id, $scene);
        ajaxReturn($res);
    }
    
    
    
    /**
     * 检测手机号是否已经存在
     */
    public function issetMobile()
    {
        $mobile = I("mobile",'0');  
        $user_id = I("user_id/d",0);
        if($user_id){
            $users = M('users')->where('mobile',$mobile)->where('user_id','neq',$user_id)->find();
        }else{
            $users = M('users')->where('mobile',$mobile)->find();
        }
      
        if($users){
            exit ('1');
        }else{
            exit ('0');  
        }      
    }

    public function issetMobileOrEmail()
    {
        $mobile = I("mobile",'0');        
        $users = M('users')->where("email",$mobile)->whereOr('mobile',$mobile)->find();
        if($users)
            exit ('1');
        else
            exit ('0');
    }
    /**
     * 查询物流
     */
    public function queryExpress()
    {
        $shipping_code = input('shipping_code/s');
        $invoice_no = input('invoice_no/s');
        if(empty($shipping_code) || empty($invoice_no)){
            return json(['status'=>0,'message'=>'参数有误','result'=>'']);
        }
        return json(queryExpress($shipping_code,$invoice_no));
    }
    
    /**
     * 检查订单状态
     */
    public function check_order_pay_status()
    {
        $order_id = I('order_id/d');
        if(empty($order_id)){
            $res = ['message'=>'参数错误','status'=>-1,'result'=>''];
            $this->AjaxReturn($res);
        }
        $order = M('order')->field('pay_status')->where(['order_id'=>$order_id])->find();
        if($order['pay_status'] != 0){
            //2018-9-25 王牧田修改  订单提交支付后直接确认
            M('order')->where(['order_id'=>$order_id])->save(['order_status'=>1]);
            $res = ['message'=>'已支付','status'=>1,'result'=>$order];
        }else{
            $res = ['message'=>'未支付','status'=>0,'result'=>$order];
        }
        $this->AjaxReturn($res);
    }

    /**
     * 广告位js
     */
    public function ad_show()
    {
        $pid = I('pid/d',1);
        $where = array(
            'pid'=>$pid,
            'enable'=>1,
            'start_time'=>array('lt',strtotime(date('Y-m-d H:00:00'))),
            'end_time'=>array('gt',strtotime(date('Y-m-d H:00:00'))),
        );
        $ad = D("ad")->where($where)->order("orderby desc")->cache(true,TPSHOP_CACHE_TIME)->find();
        $this->assign('ad',$ad);
        return $this->fetch();
    }

    /**
     *  搜索关键字
     * @return array
     */
    public function searchKey(){
        $searchKey = input('key');
        $searchKeyList = Db::name('search_word')
            ->where('keywords','like',$searchKey.'%')
            ->whereOr('pinyin_full','like',$searchKey.'%')
            ->whereOr('pinyin_simple','like',$searchKey.'%')
            ->limit(10)
            ->select();
        if($searchKeyList){
            return json(['status'=>1,'msg'=>'搜索成功','result'=>$searchKeyList]);
        }else{
            return json(['status'=>0,'msg'=>'没记录','result'=>$searchKeyList]);
        }
    }

    /**
     * 根据ip设置获取的地区来设置地区缓存
     */
    public function doCookieArea()
    {
//        $ip = '183.147.30.238';//测试ip
        $address = input('address/a',[]);
        if(empty($address) || empty($address['province'])){
            $this->setCookieArea();
            return;
        }
        $province_id = Db::name('region')->where(['level' => 1, 'name' => ['like', '%' . $address['province'] . '%']])->limit('1')->value('id');
        if(empty($province_id)){
            $this->setCookieArea();
            return;
        }
        if (empty($address['city'])) {
            $city_id = Db::name('region')->where(['level' => 2, 'parent_id' => $province_id])->limit('1')->order('id')->value('id');
        } else {
            $city_id = Db::name('region')->where(['level' => 2, 'parent_id' => $province_id, 'name' => ['like', '%' . $address['city'] . '%']])->limit('1')->value('id');
        }
        if (empty($address['district'])) {
            $district_id = Db::name('region')->where(['level' => 3, 'parent_id' => $city_id])->limit('1')->order('id')->value('id');
        } else {
            $district_id = Db::name('region')->where(['level' => 3, 'parent_id' => $city_id, 'name' => ['like', '%' . $address['district'] . '%']])->limit('1')->value('id');
        }
        $this->setCookieArea($province_id, $city_id, $district_id);
    }

    /**
     * 设置地区缓存
     * @param $province_id
     * @param $city_id
     * @param $district_id
     */
    private function setCookieArea($province_id = 1, $city_id = 2, $district_id = 3)
    {
        Cookie::set('province_id', $province_id);
        Cookie::set('city_id', $city_id);
        Cookie::set('district_id', $district_id);
    }


    /**
     * [访问记录]
     * @author 王牧田
     * @date 2018年8月29日
     * @return mixed
     */
    public function public_log(){
        $from_id = I('get.from_id/d',0);
        $to_id = I('get.to_id/d',0);

        $al_project = new AccessLog();
        $url = $_SERVER['HTTP_REFERER'];
        $ip = GetIP();
        $user_id = session('user.user_id');
        $user_id = $user_id ? $user_id : 0;
        $lastal_url = $al_project->where(['al_ip'=>$ip,'user_id'=>$user_id])->order("al_id desc")->value('al_url');
        //刷新后不重复添加数据库
        $tolowerurl = strtolower($url);
        //对商品进行处理
        $parram = "/id\/(.*?)\.html/is";
        preg_match_all($parram,$tolowerurl,$result);
        $goods_id = $result[1][0];
        if(!empty($result[1][0])){
            if(strpos($tolowerurl,'home/goods') !== false){
                //现金
                $alData['al_status'] = 1;
            }else if(strpos($tolowerurl,'home/returngoods') !== false){
                //福利商品
                $alData['al_status'] = 2;
            }else if(strpos($tolowerurl,'homered/goods') !== false) {
                //米豆
                $alData['al_status'] = 3;
            }
            $alData['goods_id'] = $goods_id;
        }
 
        if($lastal_url !== $url){
            //搜索内容处理
            $param = "/search\.html\?q\=(.*?)$/is";
            preg_match_all($param,$tolowerurl,$search);
            if(!empty($search[1][0])){
                $alData['al_keyword'] = urldecode($search[1][0]);
            }
            $alData['user_id'] = $user_id;
            $alData['al_url'] = $url ? $url : 'https://www.midoushu.com';
            $alData['create_time'] = time();
            $alData['al_ip'] = $ip;
            $alData['session_id']   =   session_id();
         //   $al_project->add($alData);
        }
        $where['is_line'] = ['eq',1];
        #查询默认客服分组,寻找出客服
        $chat_group_id = db('chat_group')->cache('default_chat_group',3600)->where('is_default = 1')->value('id');
        $where['chat_group_id'] = ['eq',$chat_group_id];
        $user_list = db('users')->where($where)->cache("chat_{$user_id}",55)->column('user_id');

        if(in_array($to_id,$user_list)){
            $this->assign('to_id',$to_id);
        }
        if($from_id != 0){
            $this->assign('from_id',$from_id);
        }
        //获取商品的供应商id
        if(is_numeric($goods_id) && $goods_id > 0){
            if($alData['al_status'] == 3){
                $suppliers_id = db('goods_red')
                                    ->where(['goods_id'=>$goods_id])
                                    ->cache("chat_goods_red_{$goods_id}")
                                    ->value('suppliers_id');
            }else{
                $suppliers_id = db('goods')
                                    ->where(['goods_id'=>$goods_id])
                                    ->cache("chat_goods_{$goods_id}")
                                    ->value('suppliers_id');
            }
            if($suppliers_id && $suppliers_id>0){
                $supplier_chat_group_id = db('suppliers')->where(['suppliers_id'=>$suppliers_id])->value('chat_group_id');
                if($supplier_chat_group_id > 0){
                    $where['chat_group_id'] = ['eq',$supplier_chat_group_id];
                    $suppliers_list = M('users')->where($where)->column('user_id');
                    #未完善，需要继续完善才可以给供货商开户
                }
            }
        }
        //获取供应商分组id
        $this->assign('chat_group_id',$chat_group_id);
        $this->assign('isHaveGroup', (empty($user_list)?0:1) );
        return $this->fetch();
    }

    /**
     * [用户留言]
     * @author 王牧田
     * @date 2018年9月3日
     */
    public function sendChatMessage(){
        $user = session('user');
        $data = I('post.');
        if(!empty($user)){
            $data['user_id'] = $user['user_id'];
        }else{

            $data['user_id'] = $this->get_rand_id();
        }
        $data['create_time']=time();
        $data['is_read']=0;

        $result = db('chat_message')->insert($data);

        return json_encode((empty($result)?0:1));


    }

    function get_rand_id(){
        $id = rand(10000000,99999999);
        $r = db('communication')->where("fromid = {$id}")->cache(true,600)->find();

        if($r){
            return $this->get_rand_id();
        }else{
            return $id;
        }
    }
    /*
     * 模糊搜索 获取子公司
     * by wuchaoqun
     */
    public function getKeywordChildcompany(){

        $keyword = I('get.keyword/s');
        $company_list = M('company')->where("`parent_id` = 0  and `cname` like '%{$keyword}%'")->select();
        $html = '';
        foreach($company_list as $h){
                $html .= $h['cname'].'<br>';
                $html .= "<option value='{$h['cid']}'>{$h['cname']}</option>";
        }
        echo $html;
    }

}