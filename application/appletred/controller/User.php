<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\appletred\controller;

use app\common\logic\RedCartLogic;
use app\common\logic\MessageLogic;
use app\common\logic\RedUsersLogic;
use app\common\logic\RedOrderLogic;
use app\common\logic\RedCouponLogic;
use think\Page;
use think\Request;
use think\Verify;
use think\db;

class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        session('store_id',null);
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
        }
       

        $order_status_coment = array(
            'WAITPAY'      => '待付款', //订单查询状态 待支付
            'WAITSEND'     => '待发货',  //订单查询状态 待发货
            'WAITRECEIVE'  => '待收货',  //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价',  //订单查询状态 待评价
        );
        $this->user_id = $this->request->param('user_id');
        $this->token = $this->request->param('token');
         
        $isUsers = M('users')->where(['user_id'=>$this->user_id , 'token'=>$this->token])->find();
        if (ACTION_NAME != 'forget_pwd' && ACTION_NAME != 'find_pwd' && ACTION_NAME != 'set_pwd' && ACTION_NAME != 'yanzhengma' && ACTION_NAME != 'login') {
            if(!$isUsers){
                exit(formt('',201,'用户不存在'));
            }
        }
        $this->assign('order_status_coment', $order_status_coment);
    }

    /*
     * 用户中心首页
     */
    public function index()
    {
        $userLogic = new RedUsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $listData = $user_info['result'];
        $listData['head_pic'] = URL.$user_info['result']['head_pic'];
        $listData['rebate_money'] =  floor($user_info['result']['rebate_money'] * 100) / 100;
        $listData['rebate_money_all'] = floor($user_info['result']['rebate_money_all'] * 100) / 100;
        $listData['user_money'] = floor($user_info['result']['user_money'] * 100) / 100;
        $messageLogic       = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();
        $listData['user_message_count'] = $user_message_count;
        exit(formt($listData));
    }



    /*
     * 账户资金
     */
    public function account()
    {
        // $user = session('user');
        $user_id = I('user_id/d');
        // $user_id = '307';
        $user = M('users')->where('user_id='.$user_id)->find();
        //获取账户资金记录
        $logic = new RedUsersLogic();
        $data = $logic->get_account_log($user_id, I('get.type'));
        $account_log = $data['result'];
        $account['user']=$user;
        $account['account_log']=$account_log;
        exit(formt($account));
        
    }

    public function account_list()
    {
        $user_id=I('user_id/d');
        // $user_id='307';
    	$type = I('type','all');
    	$usersLogic = new RedUsersLogic;
    	$result = $usersLogic->account($user_id, $type);
        $account_list['account_log']=$result['account_log'];
        exit(formt($account_list));

    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
        exit(formt($detail));

      
    }
    
   

   
    /**
     * 登录
     */
    public function login()
    {
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
     
        $logic = new RedUsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = urldecode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new RedCartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new RedOrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
          exit(formt($res['result'],'200',$msg));
        }else{
            exit(formt('',201,$msg));
        }
    }

    /**
     *  注册
     */
    public function reg()
    {

       

        $reg_sms_enable  = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new RedUsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname  = I('post.nickname', '');
            $username  = I('post.username', '');
            $password  = I('post.password', '');
            $password2 = I('post.password2', '');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
           
            $invite = I('invite');
            if(!empty($invite)){
                $invite = get_user_info($invite,2);//根据手机号查找邀请人
            }else{
                $invite = array();
            }
            
            if($is_bind_account && session("third_oauth")){ //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic  = $thirdUser['head_pic'];
                $data = $logic->reg($username, $password, $password2, 0, $invite ,$nickname , $head_pic);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new RedUsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            }else{
                $data = $logic->reg($username, $password, $password2,0,$invite);
            }
             
            
            if ($data['status'] != 1) $this->ajaxReturn($data);
            
            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];
            
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new RedCartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            if ($data['status'] == 1) {
                exit(formt($data['result'],200,$data['msg']));
            }
            
        }
      
    }

  
    
    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        if(IS_POST){
            $data = I('post.');
            $userLogic = new RedUsersLogic();
            $user['mobile'] = $data['mobile'];
            $user['password'] = encrypt($data['password']);
            $res = $userLogic->oauth_bind_new($user);
            if ($res['status'] == 1) {
                //绑定成功, 重新关联上下级
                $map['first_leader'] = cookie('first_leader');  //推荐人id
                // 如果找到他老爸还要找他爷爷他祖父等
                if($map['first_leader']){
                    $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
                    if($first_leader){
                        $map['second_leader'] = $first_leader['first_leader'];
                        $map['third_leader'] = $first_leader['second_leader'];
                    }
                    //他上线分销的下线人数要加1
                    M('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
                }else
                {
                    $map['first_leader'] = 0;
                }
                $ruser = $res['result'];
                M('Users')->where('user_id' , $ruser['user_id'])->save($map);
                
                $res['url'] = urldecode(I('post.referurl'));
                $res['result']['nickname'] = empty($res['result']['nickname']) ? $res['result']['mobile'] : $res['result']['nickname'];
                setcookie('user_id', $res['result']['user_id'], null, '/');
                setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
                setcookie('uname', urlencode($res['result']['nickname']), null, '/');
                setcookie('head_pic', urlencode($res['result']['head_pic']), null, '/');
                setcookie('cn', 0, time() - 3600, '/');
                //获取公众号openid,并保持到session的user中
                $oauth_users = M('OauthUsers')->where(['user_id'=>$res['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
                $oauth_users && $res['result']['open_id'] = $oauth_users['open_id'];
                session('user', $res['result']);
                $cartLogic = new RedCartLogic();
                $cartLogic->setUserId($res['result']['user_id']);
                $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
                $userlogic = new RedOrderLogic();//登录后将超时未支付订单给取消掉
                $userlogic->setUserId($res['result']['user_id']);
                $userlogic->abolishOrder();
                return $this->success("绑定成功", U('Mobile/User/index'));
            }else{
                return $this->error("绑定失败,失败原因:".$res['msg']);
            }
        }else{
            return $this->fetch();
        }
    }
    public function express()
    {
        $order_id    = I('get.order_id/d', 195);
        $order_goods = M('order_red_goods')->where("order_id", $order_id)->select();
        $delivery    = M('delivery_red_doc')->where("order_id", $order_id)->find();
        $this->assign('order_red_goods', $order_goods);
        $this->assign('delivery', $delivery);
        return $this->fetch();
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
      
         $address_list['address_lists']=$address_lists;
        exit(formt($address_list,200,'成功'));
        return $this->fetch();
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        if (I('consignee')) {
            $post_data['consignee']=I('consignee');
            $post_data['mobile']=I('mobile');
            $post_data['province']=I('province');
            $post_data['city']=I('city');
            $post_data['district']=I('district');
            $post_data['address']=I('address');
            $post_data['is_default']=I('is_default');
            $user_id = I('user_id/d', 0);
            $logic     = new RedUsersLogic();
            $data      = $logic->add_address($user_id, 0, $post_data);
         $addlist['addressid']=$data['result'];
            if ($data['status']=='1') {
                exit(formt($data['result'],200,'添加成功'));
            }else{
                exit(formt('',201,$data['msg']));
            }
        }
    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
       $user_id = I('user_id');
        $id = I('address_id');
        if ($id) {
      
            $post_data['consignee']  = I('consignee');
            $post_data['province']   = I('province');
            $post_data['city']       = I('city');
            $post_data['district']   = I('district');
            $post_data['address']    = I('address');
            $post_data['mobile']     = I('mobile');
            $post_data['is_default'] = I('is_default');
            $logic        = new RedUsersLogic();
            $data         = $logic->add_address($user_id, $id, $post_data);
             if ($data['status']=='1') {
                exit(formt($data['result'],200,$data['msg']));
            }else{
                exit(formt('',201,$data['msg']));
            }
            
        }
    }

    

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('address_id');
        $user_id = I('user_id');
        $address = M('user_address')->where("address_id", $id)->find();
        $row     = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row){
            exit(formt('',201,'操作失败'));
        }else{
            exit(formt('',200,'操作成功'));
        }
    }


    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new RedUsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
        	if ($_FILES['head_pic']['tmp_name']) {
        		$file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
        		$validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
        		$dir = 'public/upload/head_pic/';
        		if (!($_exists = file_exists($dir))){
        			$isMk = mkdir($dir);
        		}
        		$parentDir = date('Ymd');
        		$info = $file->validate($validate)->move($dir, true);
        		if($info){
        			$post['head_pic'] = '/'.$dir.$parentDir.'/'.$info->getFilename();
        		}else{
        			$this->error($file->getError());//上传错误提示错误信息
        		}
        	}
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq')       ? $post['qq']       = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex')      ? $post['sex']      = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city')     ? $post['city']     = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email')    ? $post['email']    = I('post.email') : false; //邮箱
            I('post.mobile')   ? $post['mobile']   = I('post.mobile') : false; //手机

            $email  = I('post.email');
            $mobile = I('post.mobile');
            $code   = I('post.mobile_code', '');
            $scene  = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            setcookie('uname',urlencode($post['nickname']),null,'/');
            $this->success("操作成功");
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
        }
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile(){
         $userLogic = new RedUsersLogic();
        if (I('user_id/d', 0)) {
            $user_id = I('user_id/d', 0);
            $mobile = input('mobile');
            
            $validate = I('validate',0);
            $status = I('status',0);
            $c = Db::name('users')->where(['mobile' => mobile, 'user_id' => ['<>', $user_id]])->count();
             $c && exit(formt('',201,'手机已被使用'));
           
            if($check_code['status'] !=1){
                return formt('',201,$check_code['msg']);
            }
            if($validate == 1 & $status == 0){
                $res = Db::name('users')->where(['user_id' => $user_id])->update(['mobile'=>$mobile]);
                if($res){
                    $data['url']='User/userinfo';
                    return formt($data,200,'修改成功');
                }
                return formt('',201,'修改失败');
            }
        }
    }

   


   

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new RedUsersLogic();
        $data = $userLogic->wechat_get_goods_collect($this->user_id);
        $page= object_to_array($data['page']);
        $data['pages']['totalPages']=$page['totalPages'];
        unset($data['page']);
        unset($data['show']);
        return formt($data);
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
       $collect_id = I('collect_id');
        $user_id = I('user_id');
        // $collect_id = ''
        if (M('goods_red_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            return formt('',200,'取消收藏成功');
        } else {
            return formt('',201,'取消收藏失败');
        }
    }



   

    /*
    我的红包
    作者：TK
    2018年5月28日16:01:24
    */

    function red_envelope(){
        $type = I('type','all');
        $user_id = I('user_id/d');
        if($type == 'plus'){
            $where['money'] =   ['gt',0];
        }elseif($type == 'minus'){
            $where['money'] =   ['lt',0];
        }
        $p = I('p/d',1);
        $page_last = 10;

        $where['red.user_id']   =   ['eq',$user_id];
        $count = M('red_envelope')->alias('red')->where($where)->count();
        $page = new Page($count,$page_last);
        $page->rollPage = 2;
        $list = M('red_envelope')->alias('red')->where($where)->field('red.*,order.order_sn,FROM_UNIXTIME(create_time,"%Y-%m-%d") as create_time')
                                    ->join('order order','order.order_id = red.order_id','left')
                                    ->page("{$p},{$page_last}")
                                    ->order("id desc")
                                    ->select();
        
        $redenvelope['list']=$list;
        $page= object_to_array($page);
        $redenvelope['page']['totalPages']=$page['totalPages'];
        return formt($redenvelope);
    }


    

 
  
	/*我的返利*/
    

}
