<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\customer_service\controller; 

use think\Controller;
use think\Db;
use think\Session;
use think\Request;

use app\common\model\Communication;

class Api extends Controller { 

 
   /**
     * 析构函数
     */
/*    function __construct() 
    {
        Session::start();

#        $this->company_id = session('company.cid');
        
   }  */  
    var $default_img;
    /*
     * 初始化操作
     */
    public function _initialize() 
    {
    //   parent::__construct();
        //过滤不需要登陆的行为
        $this->default_img = '/template/pc/chengxin/static/img/img5.jpg';
    }

     /**
     *文本消息的数据持久化
     */
    public function save_message(){
        if(Request()->isAjax()){
            $message = input("post.");
            $message = json_decode(htmlspecialchars_decode($message['data']),true);
            $datas['fromid']=$message['fromid'];
            $datas['fromname']= $this->getName($datas['fromid']);
            $datas['fromip']    =   $message['fromip'];
            $datas['toid']=$message['toid'];
            $datas['toname']= $this->getName($datas['toid']);
            $datas['content']=$message['data'];
            $datas['uuid']  =   $message['uuid'];
            $datas['time']=NOW_TIME;
            $datas['isread']=0;
            $datas['type'] = 1;
            $datas['is_chatting']   =   1;
            $msg['status'] =  M("communication")->insert($datas);
            if($message['uuid'] == 'connection_data'){
                $datas['toid']=$message['fromid'];
                $datas['toname']= $this->getName($datas['fromid']);
                $datas['fromid']=$message['toid'];
                $datas['fromname']= $this->getName($datas['toid']);
                M("communication")->insert($datas);
            }
            $msg['info']   = $msg['status'] !=0 ? ('添加记录成功') : '添加记录失败';
            echo json_encode($msg);
        }
    }

    function send_goods_save(){
        if(Request()->isAjax()){
            $message = input("post.");
            $message['data'] = htmlspecialchars_decode($message['data']);
            $datas['fromid']=$message['fromid'];
            $datas['fromname']= $this->getName($datas['fromid']);
            $datas['fromip']    =   $message['fromip'];
            $datas['toid']=$message['toid'];
            $datas['toname']= $this->getName($datas['toid']);
            $datas['content']= $message['data'];
            $datas['uuid']  =   $message['uuid'];
            $datas['time']=NOW_TIME;
            $datas['isread']=0;
            $datas['type'] = 3;
            $datas['is_chatting']   =   1;
            $msg['status'] =  M("communication")->insert($datas);
            $msg['info']   = $msg['status']!=0 ? ('添加记录成功') : '添加记录失败';
            echo json_encode($msg);
        }
    }

    #访问记录初始化
    function record_initialization(){
        $ip = I('ip');
        if(ip2long($ip)){
            $log = M('access_log')->where('al_ip',$ip)->order('al_id desc')->limit(10)->select();
            $this->ajaxReturn($log);
        }
    }

    /**
     * 根据用户id返回用户姓名
     */
    public function getName($uid){
        if($uid < 10000000){
            $userinfo = db("users")->cache(true)->field('nickname')->find($uid);
            $user_name = $this->cut_str($userinfo['nickname'], 200, 0, 'UTF-8');
            return isset($user_name) ? $user_name : $uid;
        }else{
            return $uid;
        }
    }

    public function cut_str($string, $sublen, $start = 0, $code = 'UTF-8'){
        if($code == 'UTF-8')
        {
            $pa = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
            preg_match_all($pa, $string, $t_string);
        
            if(count($t_string[0]) - $start > $sublen) return join('', array_slice($t_string[0], $start, $sublen))."...";
            return join('', array_slice($t_string[0], $start, $sublen));
        }
        else
        {
            $start = $start*2;
            $sublen = $sublen*2;
            $strlen = strlen($string);
            $tmpstr = '';
            for($i=0; $i< $strlen; $i++){
                if($i>=$start && $i< ($start+$sublen)){
                    if(ord(substr($string, $i, 1))>129){
                        $tmpstr.= substr($string, $i, 2);
                    }
                    else{
                        $tmpstr.= substr($string, $i, 1);
                    }
                }
                if(ord(substr($string, $i, 1))>129) $i++;
            }
            if(strlen($tmpstr)< $strlen ) $tmpstr.= "...";
            return $tmpstr;
        }
    }
    // $str = "abcd需要截取的字符串";
    // echo cut_str($str, 8, 0, 'gb2312');
  

    /*
    获取头像
    */
    public function get_head_pic(){
        if(Request()->isAjax()){
            $fromid = I('fromid/d');
            $toid = I('toid/d');
            $r = db('users')->field('user_id,head_pic')->where('user_id','in',[$fromid,$toid])->select_key('user_id');
            $msg['from_head'] = $r[$fromid]['head_pic'] ? $r[$fromid]['head_pic'] : $this->default_img;
            $msg['to_head'] = $r[$toid]['head_pic'] ? $r[$toid]['head_pic'] : $this->default_img;
            echo json_encode($msg);
        }
    }
    /*请求头像*/
    /*function get_head_pic(){
        $fromid = I('get.fromid/d',0);
        $toid = I('get.toid/d',0);
        if($toid && $fromid){
            if($fromid < 10000000){
                $from_head = db('users')->where('user_id',$fromid)->getField('head_pic');
                $res['from_head'] = $from_head ? $from_head : $this->default_img;
            }else{
                $res['from_head'] = $this->default_img;
            }
            if($toid < 10000000){
                $to_head = db('users')->where('user_id',$toid)->getField('head_pic');
                $res['to_head'] = $to_head ? $to_head : $this->default_img;
            }else{
                $res['to_head'] = $this->default_img;
            }
        }
        $this->ajaxReturn($res);
    }*/

    function massage_load(){
        if(Request()->isAjax()){
            $param = input('param.');
            $fromid = I('fromid/d',0);
            $toid = I('toid/d',0);
            
            $limit = 10; // 一次显示10 条聊天记录
            $offset = $param['page'] * $limit;

            $r = db('users')->field('user_id,head_pic,nickname')->where('user_id','in',[$fromid,$toid])->select_key('user_id');
         
            $msg['head_pic']['from_head'] = $r[$fromid]['head_pic'] ? $r[$fromid]['head_pic'] : $this->default_img;
            $msg['head_pic']['to_head'] = $r[$toid]['head_pic'] ? $r[$toid]['head_pic'] : $this->default_img;
            $field_list = 'id,fromid,fromname,toid,toname,content data,time,isread,type,uuid,fromip';

            $whereSql = "((fromid = {$fromid} and toid = {$toid}) or (fromid = {$toid} and toid = {$fromid})) and delete_time is null";
            $message = M('communication')->field($field_list)->where($whereSql)->limit($offset,10)->order('id desc')->select();
            if($message){
                $fromip = $message[0]['fromip'];
                $message = array_reverse($message);
            }
            $msg['this_count']  =   count($message);
            /*如果中间有插入产品*/
            $msg['list'] = $message;
            if($toid < 10000000){
                $msg['record_log'] = db('access_log')->where('user_id',$toid)->whereOr('al_ip',$fromip)->limit(10)->order('al_id desc')->select();
            }else{
                $msg['record_log'] = M('access_log')->where('al_ip',$fromip)->limit(10)->order('al_id desc')->select();

            }
            echo json_encode($msg);
        }
    }

    function delete_massage(){
        $data = I('post.data');
        $data = json_decode(htmlspecialchars_decode($data),true);
        $Communication =new Communication;
        $where['fromid']    =   ['eq',$data['fromid']];
        $where['toid']    =   ['eq',$data['toid']];
        $where['uuid']  =   ['eq',$data['id']];
        $delete_time = NOW_TIME - 180;
        if($Communication->where($where)->whereTime('time','>',$delete_time)->find()){
            $Communication->destroy(function ($query) use ($where){
                $query->where($where);
            });
            $res['status']  =   1;
            $res['info']    =   '删除成功！';
        }else{
            $res['status']  =   0;
            $res['info']    =   '发出消息已经超过120秒，无法撤回';
        }
        echo json_encode($res);
    }

    /*剪切过来的图片*/
    function uploadimgbase64(){
        $fromid = I('post.fromid/d');
        $toid = I('post.toid');
        $img_src = I('post.img_src');
        $imageName = NOW_TIME."_".rand(1111,9999).'.png';
        if (strstr($img_src,",")){
            $img_src = explode(',',$img_src);
            $img_src = $img_src[1];
        }

        $path = '/public/chat_img/'.date("Ymd");
        $path_nods = 'public/chat_img/'.date("Ymd");
        !is_dir($path_nods) && mkdir($path_nods, 0700, true);

        $save_path = ROOT_PATH . $path;

        $imageSrc =  $path."/". $imageName;  //图片名字

        $r = file_put_contents(ROOT_PATH . $imageSrc, base64_decode($img_src)); //返回的是字节数
        if (!$r) {
            $msg['status']  =   0;
            $msg['info']    =   '上传图片失败！请重试';
        }else{
            $data['content'] = $imageSrc;
            $data['fromid'] = $fromid;
            $data['toid'] = $toid;
            $data['uuid']   =   I('uuid');
            $data['fromname'] = $this->getName($data['fromid']);
            $data['toname'] = $this->getName($data['toid']);
            $data['time'] = NOW_TIME;
            $data['isread'] = 0;
            $data['type'] = 2;
            $data['is_chatting']    =   1;
            db('communication')->insert($data);
            $msg['status']  =   1;
            $msg['info']    =   $imageSrc;
        }
        echo json_encode($msg);
    }
    /*获取文件后缀*/
    function getExt($filename)
    {
       $arr = explode('.',$filename);
       return array_pop($arr);
    }

    /*手机端上传过来的文件*/
    function uploadimgbase(){
        $file = request()->file('file');
        $path = '/public/chat_img/'.date("Ymd");
        $save_path = ROOT_PATH . $path;
        $images_name = get_rand_str() . '.png';
        !is_dir($save_path) && mkdir($save_path, 0700, true);
        $info = $file->validate(['type'=>'image/jpeg,image/png,image/gif,image/jpeg'])->move($save_path, $images_name);
        if (!$info) {
            $msg['status']  =   0;
            $msg['info']    =   '上传图片失败！请重试';
        }else{
            $imageSrc = $path . '/' . $images_name;
            $data['content'] = $imageSrc;
            $data['fromid'] = I('fromid/d');
            $data['toid'] = I('toid/d');
            $data['fromname'] = $this->getName($data['fromid']);
            $data['toname'] = $this->getName($data['toid']);
            $data['time'] = NOW_TIME;
            $data['isread'] = 0;
            $data['type'] = 2;
            $data['uuid']   =   I("uuid");
            $data['is_chatting']    =   1;
            db('communication')->insert($data);
            $msg['status']  =   1;
            $msg['info']    =   $imageSrc;
        }
        echo json_encode($msg);
    }

    /**
     * PC端上传过来的文件
     */
    public function uploadimg(){
        $fromid = input('fromid');
        $toid = input('toid');
        $online = input('online');
        $uuid = input('uuid');
        $fromip = input('fromip');
        $file = request()->file('file');
         // 移动到框架应用根目录/public/chat/ 目录下
        
        $ext = $this->getExt($_FILES['file']['name']);
        $img_type = ['jpg', 'jpeg', 'gif', 'bmp', 'png'];
        $file_type = ['xls','xlsx','doc','docx'];
        if($file){
            if (in_array($ext, $img_type)){
                $path = 'public' . DS . 'chat_img';
                $save_path = ROOT_PATH . $path;
                $info = $file->validate(['size'=>6666666,'ext'=>'jpg,png,gif,jpeg,bmp'])->move($save_path);
                $file_type = 2;
            }elseif(in_array($ext, $file_type)){
                $path = 'public' . DS . 'chat_file';
                $save_path = ROOT_PATH . $path;
                $info = $file->validate(['size'=>6666666,'ext'=>'xls,xlsx,doc,docx'])->move($save_path);
                $file_type = 4;
            }
            if($info){
                if($file_type == 2){
                    $image = \think\Image::open($path . DS .$info->getSaveName());
                    $image->thumb(1200,1200)->save($path . DS .$info->getSaveName());
                }
                $name = DS . $path . DS . $info->getSaveName();
                $name = strtr($name,'\\','/');
                $data['content'] = $name;
                $data['fromid'] = $fromid;
                $data['toid'] = $toid;
                $data['fromname'] = $this->getName($data['fromid']);
                $data['toname'] = $this->getName($data['toid']);
                $data['uuid']   =   $uuid;
                $data['time'] = NOW_TIME;
                $data['isread'] = 0;
                $data['type'] = $file_type;
                $data['is_chatting']    =   1;
                $data['fromip'] =   $fromip;
                $r = db('communication')->insert($data);
                if($r){
                    echo json_encode(['status'=>'ok','img_name'=>$name]);
                }else{
                    echo json_encode(['status'=>'false']);
                }
            }else{
                // 上传失败获取错误信息
                echo json_encode(['status'=>$file->getError()]);
            }
        }

    }
    
    public function changeNoRead(){
        if(Request::instance()->isAjax()){
            $fromid = input('toid');
            $toid = input('fromid');
            Db::name('communication')->where(['fromid'=>$fromid,"toid"=>$toid])->update(['isread'=>1]);
        }

    }
    /**
     * @param $uid
     * 根据uid来获取它的头像
     */
    public function get_head_one($uid){

        $fromhead = Db::name('users')->where('user_id',$uid)->field('head_pic')->find();

        return $fromhead['head_pic'] ? $fromhead['head_pic'] : $this->default_img;
   }

    /**
     * @param $fromid
     * @param $toid
     * 根据fromid来获取fromid同toid发送的未读消息。
     */
    public function getCountNoread($fromid,$toid){

        return Db::name('communication')->where(['fromid'=>$fromid,'toid'=>$toid,'isread'=>0])->count('id');

    }

    /**
     * @param $fromid
     * @param $toid
     * 根据fromid和toid来获取他们聊天的最后一条数据
     */
    public function getLastMessage($fromid,$toid){

      /*  $info = Db::name('communication')
        ->where('(fromid=:fromid&&toid=:toid)||(fromid=:fromid2&&toid=:toid2)',['fromid'=>$fromid,'toid'=>$toid,'fromid2'=>$toid,'toid2'=>$fromid])
        ->order('id DESC')
        ->limit(1)->find();*/
        $whereSql = "(fromid = {$fromid} && toid = {$toid}) || (fromid = {$toid} && toid = {$fromid})";
        $info = db::name('communication')
                ->where($whereSql)
                ->order('id desc')
                ->limit(1)
                ->find();
        return $info;
    }



    /**
     * 根据fromid来获取当前用户聊天列表
     */
    public function get_list(){
        if(Request::instance()->isAjax() == 1){
            $fromid = input('id');
            $where['toid']  =   ['eq',$fromid];
            $where['is_chatting'] = ['eq',1];
            $info  = Db::name('communication')->field(['fromid','toid','fromname','fromip'])->where($where)->group('fromid')->select();

            $rows = array_map(function($res){
                $url = U('/customer_service/Chat/indexpc',['fromid'=>$res['toid'],'toid'=>$res['fromid']]);
                return [
                    'head_url'=>$this->get_head_one($res['fromid']),
                    'username'=>$res['fromname'],
                    'countNoread'=>$this->getCountNoread($res['fromid'],$res['toid']),
                    'last_message'=>$this->getLastMessage($res['fromid'],$res['toid']),
                    'chat_page'=>$url,
                ];

            },$info);
            $rows = $this->bubble_sort($rows);
            foreach ($rows as $key => $value) {
                $rows[$key]['last_message']['tkdate'] = date("m-d",$value['last_message']['time']);
                if($rows[$key]['last_message']['tkdate'] == date('m-d')){
                    $rows[$key]['last_message']['tkdate'] = '今天';
                }
                $rows[$key]['last_message']['tktime'] = date("H:i:s",$value['last_message']['time']);
            }
            $this->ajaxReturn($rows);
        }

    }

    public function get_list_mobile(){
        if(Request::instance()->isAjax() == 1){
            $fromid = input('id');
            $info  = Db::name('communication')->field(['fromid','toid','fromname','fromip'])->where('toid',$fromid)->group('fromid')->select();
            
            $rows = array_map(function($res){
                $url = U('/customer_service/Chat/index',['fromid'=>$res['toid'],'toid'=>$res['fromid']]);
                return [
                    'head_url'=>$this->get_head_one($res['fromid']),
                    'username'=>$res['fromname'],
                    'countNoread'=>$this->getCountNoread($res['fromid'],$res['toid']),
                    'last_message'=>$this->getLastMessage($res['fromid'],$res['toid']),
                    'chat_page'=>$url,
                ];

            },$info);
            $rows = $this->bubble_sort($rows);
            foreach ($rows as $key => $value) {
                $rows[$key]['last_message']['tkdate'] = date("m-d",$value['last_message']['time']);
                if($rows[$key]['last_message']['tkdate'] == date('m-d')){
                    $rows[$key]['last_message']['tkdate'] = '今天';
                }
                $rows[$key]['last_message']['tktime'] = date("H:i:s",$value['last_message']['time']);
            }
         //   dump($rows);die;
            $this->ajaxReturn($rows);
        }

    }

    /*对二维数组进行冒泡排序*/  // 到底有没用？？？待定
    function bubble_sort($list,$column='time'){
        foreach($list as $key => $value){
            $tims[] = $value['last_message'][$column];
        }
        if($list){
            array_multisort($tims, SORT_DESC,$list);    
        }
        
        return $list;
    }

    function ajaxReturn($str){
        exit(json_encode($str));
    }



}