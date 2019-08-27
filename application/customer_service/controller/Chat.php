<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\customer_service\controller; 
use think\Config;
use think\Controller;
use think\response\Json;
use think\Db;
use think\Session;
use think\Cookie;
#use think\Request;

use  app\mobile\controller\MobileBase;

use app\common\logic\UsersLogic;
use app\common\logic\CartLogic;
use app\common\logic\OrderLogic;

#https://www.midoushu.com/customer_service/Chat/login

class Chat extends MobileBase { 
    var $users;
    var $fromid;
    var $toid;
    var $fromip;
    var $myid;
    /*
     * 初始化操作
     */
    public function _initialize() 
    {
        parent::_initialize();
        //过滤不需要登陆的行为
        $this->fromip = GetIP();
        $user_id = Cookie('user_id');
        if(!session('?user') && $user_id){
            $res = db('users')->cache("user_{$user_id}")->find($user_id);
            if($res){
                session('user',$res);    
            }
        }

        $this->users = session('user');
        if($this->users){
            $this->fromid = $this->users['user_id'];
        }else{
            #如果未登陆
            $this->fromid = I('fromid') ? I('fromid') : $this->get_user_id();
        }
        $this->toid = I('toid/d');
        $this->assign('fromid',$this->fromid);
        $this->assign('toid',$this->toid);     
        $this->assign('fromip',$this->fromip); 
        $this->assign('user_id',$this->users['user_id']);

        /*以下是自动回复相关*/
        $autoreply = cache('autoreply');
        if(!$autoreply){
            $chat_autoreply = db('chat_autoreply')->select();
            foreach ($chat_autoreply as $key => $value) {
                $autoreply[$value['ca_keyname']]    =   $value['ca_value'];
            }
            cache('autoreply',$autoreply,600);
        }
        $this->assign('autoreply',$autoreply);

        $this->assign('version_number','1.1');
    #    $this->assign('socket_url','ws://127.0.0.1:8282');
        $this->assign('socket_url','wss://www.midoushu.com:8282');
    }

    public function index(){   
        /*$from_url = I('get.from_url/s'); 
        $this->assign('from_url',$from_url);*/
        return $this->fetch('index');

    }
  
    //PC端   客服聊天页面
    public function indexpc(){
        $reply_list = db('chat_reply')->order('orderby desc')->select();
        $this->assign('reply_list',$reply_list);
        //访问路径
    
        return $this->fetch('indexpc');
    }

    /*查看聊天记录*/
    function chat_logs(){
        $fromid = I('fromid/d');
        $toid = I('toid/d');
        $message = Db::name('communication')->where('(fromid=:fromid and toid=:toid) || (fromid=:toid1 and toid=:fromid1)',['fromid'=>$fromid,'toid'=>$toid,'toid1'=>$toid,'fromid1'=>$fromid])->order('id asc')->select();
        $r = db('users')->field('user_id,head_pic,nickname')->where('user_id','in',[$fromid,$toid])->select_key('user_id');
        $msg['head_pic']['from_head'] = $r[$fromid]['head_pic'] ? $r[$fromid]['head_pic'] : $this->default_img;
        $msg['head_pic']['to_head'] = $r[$toid]['head_pic'] ? $r[$toid]['head_pic'] : $this->default_img;
        /*如果中间有插入产品*/
        foreach ($message as $key => $value) {
            if($value['type'] == 3){
                $temp_array = explode('|', $value['content']);
                if($temp_array[1] == 1){
                    $type='red';
                    $table_name = 'goods_red';
                }else{
                    $type='';
                    $table_name = 'goods';
                }
                $goods_info = db($table_name)->field('goods_id,goods_name,shop_price')->cache(true,500)->find($temp_array[0]);
                $goods_info['goods_img']    =   goods_thum_images($goods_info['goods_id'],400,400,$type);
                if($temp_array[3] == 1){
                    $goods_info['url'] = U('Home/ReturnGoods/goodsInfo',['id'=>$goods_info['goods_id']]);
                }else{
                    if($temp_array[2] == 1){    //如果是米豆商品
                        $goods_info['url'] = U('Homered/Goods/goodsInfo',['id'=>$goods_info['goods_id']]);    
                    }else{
                        $goods_info['url'] = U('Home/Goods/goodsInfo',['id'=>$goods_info['goods_id']]);
                    }
                }
            #    dump($goods_info);die;
                $html = '<div class="sp">';
                $html .= '<div class="tu"><a href="'.$goods_info['url'].'" target="_blank"><img src="'.$goods_info['goods_img'].'"></a></div>';
                $html .= '<div class="wz">';
                $html .= '<div class="bt"><a href="'.$goods_info['url'].'" target="_blank">'.$goods_info['goods_name'].'</a></div>';
                $html .= '<div class="price"><a href="'.$goods_info['url'].'" target="_blank">¥</a><a target="_blank" href="'.$goods_info['url'].'">'.$goods_info['shop_price'].'</a></div>';
                $html .= '</div>';
                $html .= '</div>';
                $message[$key]['data']   =   $html;
            }
        }
        $msg['list'] = $message;

        $this->assign('msg',$msg);
        return $this->fetch('ltjl');
    }


    //PC端 用户列表
    public function indexpcuser(){
        $keyword_list = $midou_goods_list = $goods_list = array();
        if($this->users){
            $goods_ids = db('order')->where("user_id = {$this->users['user_id']}")->order('order_id desc')->limit(5)->column('order_id');
            if($goods_ids){
                $goods_list = db('order_goods')
                                ->alias('order_goods')
                                ->field('order_goods.goods_id,goods_name,order_goods.suppliers_id,order_goods.goods_price,order_goods.is_allreturn,order_sn')
                                ->where('order_goods.order_id','in',$goods_ids)
                                ->join('order order',"order.order_id = order_goods.order_id")
                                ->select();
                $this->assign('goods_list',$goods_list);
            }
            $midou_order_ids = db('order_red')->where("user_id = {$this->users['user_id']}")->order('order_id desc')->limit(5)->column('order_id');
            if($midou_order_ids){
                $midou_goods_list = db('order_red_goods')
                        ->alias('order_goods')
                        ->field('order_sn,order_goods.goods_id,order_goods.goods_name,order_goods.suppliers_id,order_goods.goods_price')
                        ->where('order_goods.order_id','in',$midou_order_ids)
                        ->join('order_red order',"order.order_id = order_goods.order_id")
                        ->select();
                $this->assign('midou_goods_list',$midou_goods_list);
            }
        }else{

            /*判断这个人的IP地址 并查询出访问过的相应路径*/
            #goods_id,al_status,shop_price,suppliers_id,goods_name,is_allreturn
            $where['al_ip'] =   ['eq',$this->fromip];
            $where['log.goods_id'] =   ['neq',0];
            $where['session_id']    =   ['eq',$this->session_id];
            $log_list = M('access_log')->field('log.user_id,log.goods_id,log.create_time,log.al_status,log.al_type,log.al_ip')
                                            ->alias('log')
                                            ->where($where)
                                            ->order('al_id desc')
                                            ->limit(10)
                                            ->select();
            #al_status   1.现金 2.福利商品 3.米豆
            foreach ($log_list as $key => $value) {
                switch ($value['al_status']) {
                    case 1:
                        $goods_ids[]    =   $value['goods_id'];
                        break;
                    case 2:
                        $goods_ids[]    =   $value['goods_id'];
                        break;
                    case 3:
                        $goods_red_ids[]    =   $value['goods_id'];
                        break;
                }
            }

            $goods_cash_list = db('goods')->where('goods_id','in',$goods_ids)->field('goods_id,goods_name,shop_price,suppliers_id')->limit(10)->select_key('goods_id');
            $goods_red_list = db('goods_red')->where('goods_id','in',$goods_red_ids)->field('goods_id,goods_name,shop_price,suppliers_id')->limit(10)->select_key('goods_id');
           
           
            foreach ($log_list as $key => $value) {
                $goods_list[$key]['goods_id']   =   $value['goods_id'];
                $goods_list[$key]['suppliers_id']   =   $value['suppliers_id'];
                if($value['al_status'] == 3){
                    $goods_list[$key]['goods_name']   =   $goods_red_list[$value['goods_id']]['goods_name'];
                    $goods_list[$key]['shop_price']   =   $goods_red_list[$value['goods_id']]['shop_price'];
                    $goods_list[$key]['is_red'] =  1;
                }else{
                    $goods_list[$key]['goods_name']   =   $goods_cash_list[$value['goods_id']]['goods_name'];
                    $goods_list[$key]['shop_price']   =   $goods_cash_list[$value['goods_id']]['shop_price'];
                    $goods_list[$key]['is_red'] =  0;
                }
            }

            if(empty($goods_list)){
                //如果没有轨迹，没有记录 ， 则显示推荐商品 
                $goods_list = M('goods')->where('is_recommend = 1 and is_on_sale = 1')->order('sort asc,goods_id desc')->cache(true,120)->limit(10)->select();
            }
        }

        /* url 处理 */
       /* $from_url = I('get.from_url/s');
        $from_url = parse_url(strtolower(urldecode($from_url)));
        if(isset($from_url['path'])){
            $from_url = explode('/',$from_url['path']);    
            if($from_url[3] == 'goodsinfo'){
                $table_name = 'goods';
                $is_red    =   0;
                $default_goods_id  =   intval($from_url[5]);
                if($from_url[1] == 'homered' || $from_url[1] == 'mobilered'){
                    $table_name = 'goods_red';
                    $is_red    =   1;
                }
                    
            }
        }*/
        if($table_name){
            $default_send_goods = db($table_name)->cache(true)->find($default_goods_id);
            $default_send_goods['is_red']   =   $is_red;
            $default_send_goods['goods_url']    = $is_red == 1 ? U('Homered/Goods/goodsInfo',['id'=>$default_send_goods['goods_id']]) : U('Home/Goods/goodsInfo',['id'=>$default_send_goods['goods_id']]);
            $this->assign('send_goods',$default_send_goods);
        }
        
     //   dump($goods);die;

        $this->assign('goods_list',$goods_list);
        #查询常见问题

        $question_list = db('chat_question')->cache('chat_question',6000)->order('cq_sort asc')->select();
        $this->assign('question_list',$question_list);

        return $this->fetch('indexpcuser');
    }
    
    public function lists(){
        if(!$this->check_user()){
            $this->error('您未被分配客服权限','/');
        }

        return $this->fetch('list');
    }
   

    public function listspc(){

        if(!$this->check_user()){
            $this->error('您未被分配客服权限',U('/customer_service/Chat/login/'));
        }
        $user_id = $this->users['user_id'];
        $is_line = db('users')->where('user_id',$user_id)->getField('is_line');
        $this->assign('is_line',$is_line);

        $chat_group = db('chat_group')->cache('chat_group_list')->select();
    //    dump($chat_group);die;
        $this->assign('chat_group',$chat_group);        
        return $this->fetch('listspc');
    }

    public function login(){
        if($this->check_user()){
            $this->redirect(U("/customer_service/Chat/listspc/"));
        }
        return $this->fetch('login');
    }
    /*检测是否为客服组客服*/
    function check_user(){
        $chat_group_id =$this->users['chat_group_id']; 
        if($chat_group_id){
            $r = db('chat_group')->cache(true)->find($chat_group_id);
            if($r){
                return true; 
            }
        }
        return false;
    }
    public function doLogin(){
        $user_name = I('user_name/s');
        $password = I('password/s');
        if(empty($user_name) || empty($password)){
            $msg['status']  =   0;
            $msg['msg']    =   '用户名或密码不能为空！';
        }else{
            $logic = new UsersLogic();
            $res   = $logic->login($user_name,$password);
            if($res['status'] == 1){
                //登录成功  判断是否为客服
                if($chat_group_id = $res['result']['chat_group_id']){
                    $r = db('chat_group')->where("id = {$chat_group_id}")->find();
                    if(!$r){
                        $msg['status']  =   0;
                        $msg['msg'] =   '您没有被分配为客服！请联系系统管理员';
                        exit(json_encode($msg));
                    }
                }
                session('user',$res['result']);
                $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
                setcookie('user_id',$res['result']['user_id'],null,'/');
                setcookie('is_distribut',$res['result']['is_distribut'],null,'/');
                setcookie('uname',urlencode($nickname),null,'/');
                setcookie('cn',0,time()-3600,'/'); 
                setcookie('cnreturn',0,time()-3600,'/');
                setcookie('cnred',0,time()-3600,'/'); 
                $user_data['last_login'] = $res['result']['last_login'];
                $user_data['last_ip']    = GetIP();
                $user_data['is_line']   =   1;
                M('users')->where("user_id = ".$res['result']['user_id'])->save($user_data);

                session('session_login_time', $user_data['last_login']); //记录登陆时间
                session('session_user_time', 24*3600); 
                $cartLogic = new CartLogic();
                $cartLogic->setUserId($res['result']['user_id']);
                $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
                $orderLogic = new OrderLogic();
                $orderLogic->setUserId($res['result']['user_id']); //登录后将超时未支付订单给取消掉
                $orderLogic->abolishOrder();
                $msg['status']  =   1;
            //    M('users')->where('id',$res['result']['user_id'])->setField('is_line',1);
            }else{
                $msg = $res;
            }
        }
        exit(json_encode($msg));
    }
 
    public function switch_logon(){
        $id = I('get.id/d',0);
        $user_id = $this->users['user_id'];
        if(db('users')->where('user_id',$user_id)->setField('is_line',$id)){
            $res['status']  =   1;
            $res['info']    =   '修改成功！';
        }else{
            $res['status']  =   0;
            $res['info']    =   '系统繁忙，请刷新重试';
        }
        exit(json_encode($res));
    }
    /*分配客服*/
    public function randkf(){
        //如果已经有过聊天记录,并且客服也在线
        if($this->fromid && $this->toid){
            $chat_user = db('users')->where('is_line = 1')->find($this->toid);
            #/customer_service/Chat/indexpcuser/fromid/73877258/toid/10
            $default_url['Chat'] = isMobile() ? 'index' : 'indexpcuser';
            $default_url['fromid']  =   $this->fromid;
            $default_url['toid']    =   $this->toid;
            /* 增加该客服接待人数 */
            $save_reception_data['create_time'] =   NOW_TIME;
            $save_reception_data['user_id'] =   $this->toid;
            db::name('chat_reception')->save($save_reception_data);
            /*跳转至聊天界面*/
            $this->redirect(U("/customer_service",$default_url));
            return ;
        }
        //查询toid 
        $url_arr['Chat']    =   'index';
        $chat_group_id = I('chat_group_id/d',0);
        $url_arr['fromid'] =    $this->fromid;
        if(!$chat_group_id){
            $default_chat_id = $chat_group_id = db('chat_group')->where('is_default = 1')->value('id');
        }
        $where['chat_group_id'] = ['eq',$chat_group_id];
        $where['is_line'] = ['eq',1];
        $user_list = M('users')->where($where)->column('user_id');
        $kf_count = count($user_list);

        if ($kf_count > 1){
            $where = "user_id in (" .implode(',',$user_list). ")";
            $start_time = NOW_TIME - 86400;
            $end_time = NOW_TIME + 3600;
            $where .= " and create_time between {$start_time} and {$end_time}";
            $sql = "select user_id,count(id) count_sum from ".PREFIX."chat_reception where {$where} group by user_id;";
            $r = Db::query($sql);
            if($r){
                foreach ($user_list as $key => $value) {
                    foreach ($r as $k => $v) {
                        if($v['user_id'] == $value){
                            $arr[$value] = $v['count_sum'];
                        }
                    }
                    if(!isset($arr[$value])){
                        $arr[$value] = 0;
                    }
                }
                $this->toid = array_search(min($arr),$arr);
            }else{
                $this->toid = $user_list[rand(0,($kf_count-1))];
            }
        }elseif($kf_count == 1){
            $this->toid = current($user_list);
        }else{
            if(!$default_chat_id){ //如果设置的是供货商
                $this->redirect(U('/customer_service/Chat/randkf'));
            }else{
                $this->redirect(U('/Mobile/chat/jump'));
            }
        }
        if($this->toid){
            $save_reception_data['create_time'] =   NOW_TIME;
            $save_reception_data['user_id'] =   $this->toid;
            db::name('chat_reception')->save($save_reception_data);
        }
        $url_arr['toid'] =    $this->toid;
        if(!isMobile()){    
            $url_arr['Chat']    =  'indexpcuser'; //indexpc
        }
        if($this->fromid == $this->toid){
            $this->error('您不能和自己聊天','/customer_service/Chat/listspc');
        }
    //    $url_arr['from_url']    =  urlencode($_SERVER['HTTP_REFERER']);
        $url = U("/customer_service",$url_arr);
        $this->redirect($url);
    //    header("Location:{$url}");
    }
    /**转接其他客服*/
   function other_chat(){
        $chat_group = db('chat_group')->cache('chat_group')->select();
        $this->assign('chat_group',$chat_group);
        return $this->fetch('other_chat');
   }
   /*获取供货商列表*/
   function get_users(){
        $chat_group_id = I('get.group_id/d');
        if($chat_group_id){
            $user_list = db('users')->field('user_id,mobile,is_line,nickname')->where("chat_group_id = {$chat_group_id}")->cache("chat_group_{$chat_group_id}",120)->select();
            echo json_encode($user_list);
        }
   }

    function get_user_id(){
        return self::get_rand_id();
    }

    function get_rand_id(){
        $id = rand(10000000,99999999);
        $r = db('communication')->where("fromid = {$id}")->cache(true,600)->find();
        if($r){
            return self::get_rand_id();
        }else{
            return $id;
        }
    }
    /**
     * [聊天快捷搜索]
     * @author 王牧田
     * @date 2018-9-21
     */
    public function searchReply(){
        $nrinput = I('post.nrinput/s');
        $reply_list = db('chat_reply')->where(['content'=>['like',"%".$nrinput."%"]])->select();
        return json_encode($reply_list);
    }

    /*清空该聊天对象*/
    function loginOut(){
        $user_id = $this->users['user_id'];
        M('communication')->where("fromid = {$user_id} or toid= {$user_id}")->setField('is_chatting',0);
        M('users')->where("user_id = {$user_id}")->setField('is_line',0);
        session_unset();
        session_destroy();
        setcookie('uname','',time()-3600,'/');
        setcookie('cn','',time()-3600,'/');
        setcookie('cnred','',time()-3600,'/');
        setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        $res['status']  =   1;
        $res['info']    =   '退出成功！';
        return json($res);
    }


}