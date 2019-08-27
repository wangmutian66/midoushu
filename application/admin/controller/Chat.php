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

use app\admin\model\ChatMessageModel;
use app\admin\model\ChatQuestionModel;
use app\admin\model\ChatReplyModel;
use think\Db;
use think\Page;


class Chat extends Base {

    protected $cr;
    protected $cq;
    protected $default_img;

    public function __construct()
    {
        parent::__construct();
        $this->default_img = '/template/pc/chengxin/static/img/img5.jpg';
     
        $this->cr = new ChatReplyModel(); /*快捷回复*/
        $this->cq = new ChatQuestionModel();/*常见问题*/
    }

    public function index(){
        return $this->fetch();
    }


   
    function chat_group(){
        $where = [];
        $p    = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $list = M('chat_group')
                    ->where($where)
                    ->order('id desc')
                    ->page("$p,$size")
                    ->select();
        
        $count = M('chat_group')->where($where)->count();// 查询满足要求的总记录数
        $pager = new Page($count,$size);// 实例化分页类 传入总记录数和每页显示的记录数
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pager);// 赋值分页输出     
        // 渲染模板输出
        return $this->fetch('chat_group');
    }

    /*添加客服分组*/
    function add_group(){
        if(IS_POST){
            // 只有一个 1  其余的都是 0

            $data['name'] = I('post.name');

            $res =  Db::name('chat_group')->insert($data);
            if($res){
                adminLog('添加客服分组');
                $this->success('添加成功',U('Chat/chat_group'));exit;
            }else{
                $this->error('添加失败,'.$res['msg'],U('Chat/chat_group'));
            }
        }
        return $this->fetch('add_group');
    }

    /*编辑分组*/
    function edit_group(){
        $p = I('get.p');

        if(IS_POST){
            $data['name'] = I('post.name');
            $data = I('post.');
            $dasgha['id']=$data['ppid'];
            $res = Db::table('chat_group')->where($data)->update($dasgha);
            if($res){
                adminLog('编辑客服分组');
                $this->success('分组名称修改成功',U('Chat/chat_group'));exit;
            }else{
                $this->error('修改失败,'.$res['msg'],U('Chat/chat_group'));
            }
        }

        $postid['id']=I('get.pid'); 
        $chat_group = M('chat_group')->where($postid)->find();
        $this->assign('chat_group',$chat_group);
        $gid['chat_group_id']=I('get.pid');
        $res = M('users')->where($gid)->page($p.',10')->select();

        $this->assign('list',$res);
        $count = M('users')->where($gid)->count();
        $Page = new Page($count,10);
        $show = $Page->show();
        $this->assign('page',$show);
        return $this->fetch('edit_group');
    }
    /*强制下线*/
    function downline(){
        $user_id = I('post.user_id/d');
        if($user_id > 0){
            db('communication')->where("fromid = {$user_id} or toid= {$user_id}")->setField('is_chatting',0);
            db('users')->where('user_id',$user_id)->setField('is_line',0);
        }
    }

    /**
     * [设置成默认分组]
     * @author 王牧田
     * @date 2018年08月27日
     */
    public function default_group(){
        $data = I('get.');
        $dasgha['id']=$data['id'];
        $chat_group = Db::name('chat_group');
        //->fetchSql(true)
        $resultreturn = $chat_group->where(['is_default'=>'1'])->save(['is_default'=>'0']);
        $result = Db::name('chat_group')->where($dasgha)->update(['is_default'=>1]);
        if($resultreturn && $result){
            $this->success("设置为默认分组成功",U('chat_group'));
        }else{
            $this->error("未做任何操作",U('chat_group'));
        }

    }




    /*添加分组成员*/
    function add_edit_group(){
 
         $p = I('get.p/d',1);
         if(IS_POST){
            $gid['mobile']  = I('post.mobile');
            $res = M('users')->order('user_id')->where($gid)->page($p,20)->select();
         }else{
            // $gid['chat_group_id']  = array('neq',I('get.tid'));
            $gid['chat_group_id']  = array('eq',0);
            $res = M('users')->order('user_id')->where($gid)->page($p,20)->select();
         }
         if($res){
            foreach ($res as $val){
                $list[] = $val;
            }
         }
         $chat_group_name = db('chat_group')->where('id',I('get.tid/d',0))->getField('name');
         $this->assign('chat_group_name',$chat_group_name);
         $this->assign('jkl',I('get.tid'));
         $this->assign('list',$list);
         $count = M('users')->where($gid)->count();
         $Page = new Page($count,20);
         $show = $Page->show();
         $this->assign('page',$show);
         return $this->fetch('choice');
    }

     /*添加分组成员ajax*/
    function add_edit_ajax(){
        $data = I('post.');
        if($data['act'] == 'del'){
            $dasgha['user_id']=$data['ppid'];
            $where['chat_group_id']=$data['jkl'];
            $r = M('users')->where($dasgha)->update($where);

            if($r) exit(json_encode(1));
        }
        if($r){
            adminLog('添加分组成员');
            $this->success("添加成功",U('add_edit_group'));
        }else{
            $this->error("添加失败");
        }
    }


    /**
     * [删除用户分组]
     * @author 王牧田
     * @date 2018年8月27日
     */
    public function del_edit_ajax(){
        $data = I('post.');
        M('users')->where(['user_id'=>$data['id']])->update(['chat_group_id' => 0]);
        $data['status'] = 1;
        return json_encode($data);
    }

    /*删除分组*/
    function del_group(){
        $data = I('post.');

        if($data['act'] == 'del'){
            M('users')->where(['chat_group_id'=>$data['id']])->update(['chat_group_id' => 0]);
            $r = M('chat_group')->where(['id'=>$data['id']])->delete();

            if($r) exit(json_encode(1));
        }
        if($r){
            adminLog('删除分组');
            $this->success("删除成功",U('Chat/chat_group'));
        }else{
            $this->error("删除失败");
        }
    }

     /*聊天记录*/
    function record($formid){
        //id 大于一百万就是匿名
        $tousers = Db::name('users')->where(['user_id'=>$formid])->field("nickname,head_pic")->find();

        $comt = Db::name('communication')->where(['fromid'=>$formid])->order("time desc")->group('toid')->column('toid');
        $comf = Db::name('communication')->where(['toid'=>$formid])->order("time desc")->group('fromid')->column('fromid');

        $com = array_merge($comt,$comf);

        $com = array_unique($com);

        $users = [];
        foreach ($com as $v=>$val){
            if($val >10000000){
                $users[$v]['nickname'] = '匿名';
                $users[$v]['head_pic'] = $this->default_img;

            }else{
                $users[$v] = Db::name('users')->where(['user_id'=>$val])->field('nickname,head_pic')->find();
                $users[$v]['head_pic'] = empty($users[$v]['head_pic'])?$this->default_img:$users[$v]['head_pic'];
            }
            $communication=Db::name('communication')->where("fromid = $val or toid = $val")->order("id desc")->field("time,type")->find();
            $users[$v]['time'] = $communication['time'];
            $users[$v]['type'] = $communication['type'];
            $users[$v]['user_id'] = $val;

        }



        $users = $this->arraySort($users,'time','desc');
        $communication = [];
        foreach ($users as $k=>$val){
            $content = Db::name('communication')->where(['fromid'=>$val['user_id'],'toid'=>$formid])->order("id desc")->value("content");
            $users[$k]['content'] = $content;
        }

        $this->assign('formid',$formid);
        $this->assign('users',$users);
        $this->assign('tousers',$tousers);
        $this->assign('communication',$communication);
        return $this->fetch('record');
    }



    /**
     * @desc arraySort php二维数组排序 按照指定的key 对数组进行排序
     * @param array $arr 将要排序的数组
     * @param string $keys 指定排序的key
     * @param string $type 排序类型 asc | desc
     * @return array
     */
    function arraySort($arr, $keys, $type = 'asc') {
        $keysvalue = $new_array = array();
        foreach ($arr as $k => $v){
            $keysvalue[$k] = $v[$keys];
        }
        $type == 'asc' ? asort($keysvalue) : arsort($keysvalue);
        reset($keysvalue);
        foreach ($keysvalue as $k => $v) {
            $new_array[$k] = $arr[$k];
        }
        return $new_array;
    }






    /*查看所有分组会员*/
    function select_user(){

    }


    /**
     * [显示联系人聊天记录]
     * @author 王牧田
     * @date 2018年8月27日
     */
    public function chatlist(){
        $formid = I('post.formid');
        $toid = I('post.toid');

        $where = "(`fromid` = $formid  AND `toid` = $toid) OR (`fromid` = $toid  AND `toid` = $formid)";
        $communication = Db::name('communication')->where($where)->field("fromname,fromid,toid,content,type,time")->group("id")->select();
        foreach ($communication as $k=>$row){
            $communication[$k]['time']=date("Y-m-d H:i",$row['time']);
            $users = Db::name('users')->where(['user_id'=>$row['fromid']])->find();
            $communication[$k]['nickname'] = $users['nickname'];
            $communication[$k]['head_pic'] = empty($users['head_pic'])?$this->default_img:$users['head_pic'];
            if($row['fromid'] == $formid){
                $communication[$k]['classname'] = 'me';
            }else{
                $communication[$k]['classname'] = 'other';
            }

            if($row['type'] == 3){

                $contentArray = explode("|",$row['content']);
                $goods_id = $contentArray[0];
                $isRed = $contentArray[1];
                $isReturn = empty($contentArray[2])?0:$contentArray[2];
                if($isRed == 0){
                    $communication[$k]['goodsinfo'] = db('goods')->where(['goods_id'=>$goods_id,'is_allreturn'=>$isReturn])->field("goods_name,original_img,shop_price")->find();

                }else if($isRed == 1){
                    $communication[$k]['goodsinfo'] = db('goods_red')->where(['goods_id'=>$goods_id])->field("goods_name,original_img,shop_price")->find();
                }
            }


        }
        return json($communication);
    }

    /**
     * [显示该客服聊过天的人]
     * @author 王牧田
     * @date 2018年08月27日
     */
    public function findchatting(){
        $id = I('post.id');
        $communication = Db::name('communication')->where(['fromid'=>$id])->group('toid')->column('toid');
        $users = Db::name('users')->where(['user_id'=>['in',implode(",",$communication)]])->select();
        return json($users);
    }


    /**
     * [快捷回复]
     * @author 王牧田
     * @date 2018年08月28日
     */
    public function chat_reply(){
        $p = I('get.p',1);
        $chat_reply = $this->cr->order("orderby asc")->page($p,10)->select();
        $this->assign('chat_reply',$chat_reply);
        $count = $this->cr->count();
        $Page = new Page($count,10);
        $show = $Page->show();
        $this->assign('page',$show);
        return $this->fetch();
    }


    /**
     * [添加快捷回复]
     * @author 王牧田
     * @date 2018年8月28日
     * add_reply
     */
    public function add_reply(){
        if($this->request->isPost()){
            $data['content'] = I('post.content');
            $data['orderby'] = I('post.orderby');
            $isreply = $this->cr->where(["content"=>$data['content']])->find();
            if(!empty($isreply)){
                $this->error('该回复已存在');
                exit();
            }
            $res =  Db::name('chat_reply')->insert($data);
            if($res){
                adminLog('添加快捷回复');
                $this->success('添加成功',U('Chat/chat_reply'));exit;
            }else{
                $this->error('添加失败,'.$res['msg'],U('Chat/chat_reply'));
            }
        }else{
            $this->assign('url',U('Chat/add_reply'));
            $this->assign('title','添加');
            return $this->fetch('save_reply');
        }

    }

    /**
     * [修改快捷回复]
     * @author 王牧田
     * @date 2018年8月28日
     */
    public function edit_reply($id){

        if($this->request->isPost()){
            //此次做ajax 请求
            $data['content'] = I('post.content');
            $data['orderby'] = I('post.orderby');
            $isreply = $this->cr->where(["content"=>$data['content'],'id'=>['neq',$id]])->find();
            if(!empty($isreply)){
                $this->error('该回复已存在');
                exit();
            }
            $res =  $this->cr->where(['id'=>$id])->update($data);
            if($res){
                adminLog('修改快捷回复');
                $this->success('修改成功',U('Chat/chat_reply'));exit;
            }else{
                $this->error('未做任何修改,'.$res['msg']);
            }
        }else{
            $this->assign('title','修改');
            $chat_reply = $this->cr->where(['id'=>$id])->find();
            $this->assign('chat_reply',$chat_reply);
            $this->assign('url',U('Chat/edit_reply',array('id'=>$id)));
            return $this->fetch('save_reply');
        }
    }


    /**
     * [删除快捷回复]
     * @author 王牧田
     * @date 2018年8月28日
     */
    public function del_reply(){
        $data = I('post.');
        $r = $this->cr->where(['id'=>$data['id']])->delete();
        if($r) exit(json_encode(1));

    }


    /**
     * [用户留言]
     * @author 王牧田
     * @date 2018年8月28日
     */
    public function chat_message(){
        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
    //    $cm = new ChatMessageModel();
        $chat_message = db::name('ChatMessage')->page($p,$size)->order('id desc')->select();
        $count = db::name('ChatMessage')->count();
        $Page = new Page($count,10);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('chat_message',$chat_message);
        return $this->fetch();
    }

    /**
     * [查看留言内容]
     * @author 王牧田
     * @date 2018年8月28日
     */
    public function chat_show_message($id){
        if($this->request->isPost()) {
            $save_data['note'] =  I('post.note/s');
            $save_data['status']    =   I('post.status/d');
            $note = db::name('ChatMessage')->where(["id"=>$id])->save($save_data);
            if($note){
                $this->success("修改成功",U('/Admin/Chat/chat_message'));
            }else{
                $this->error("修改失败");
            }
        }else{
            $message = db::name('ChatMessage')->where(['id'=>$id])->find();
       
            $this->assign('message',$message);
            return $this->fetch();
        }
    }

    /**
     * [常见问题]
     * @author 王牧田
     * @date 2018年8月30日
     */
    public function chat_question(){

        $p     = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
        $chat_question = $this->cq->page($p,$size)->order("cq_sort asc")->select();

        $count = $this->cq->count();
        $Page = new Page($count,$size);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('chat_question',$chat_question);

        return $this->fetch();
    }


    /**
     * [(添加/修改)问题]
     * @author 王牧田
     * @date 2018年8月30
     */
    public function save_question(){
        $cq_id = I('get.cq_id');
        $title = "添加'";
        if($cq_id){
            $title = "修改";
            $chat_question = $this->cq->where(['cq_id'=>$cq_id])->find();
            $this->assign('chat_question',$chat_question);
        }

        if($this->request->isPost()) {
            $data['cq_question'] = I('post.cq_question');
            $data['cq_answer'] = I('post.cq_answer');
            $data['cq_sort'] = I('post.cq_sort');
            if($cq_id){
                $res = $this->cq->where(['cq_id'=>$cq_id])->save($data);
            }else{
                $res = $this->cq->add($data);
            }

            if($res){
                adminLog($title.'常见问题');
                $this->success($title.'成功',U('Chat/chat_question'));exit;
            }else{
                $this->error($title.'失败');
            }
        }else{
            $this->assign('title', $title);
            return $this->fetch();
        }
    }

    /**
     * [删除问题]
     * @author 王牧田
     * @date 2018年8月30日
     */
    public function del_question(){
        $data = I('post.');
        $r = $this->cq->where(['cq_id'=>$data['cq_id']])->delete();
        if($r) exit(json_encode(1));
    }

    /**
     * [自动回复]
     * @author 王牧田
     * @date 2018年9月26日
     */
    public function chat_autoreply(){
        if(IS_POST){
            $data = I('post.');
            foreach ($data as $k=>$row){
                $autoreply = db('chat_autoreply')->where(["ca_keyname"=>$k])->find();
                if($autoreply){
                    db('chat_autoreply')->where(["ca_keyname"=>$k])->save(["ca_value"=>$row]);
                }else{
                    db('chat_autoreply')->add(["ca_keyname"=>$k,"ca_value"=>$row]);
                }
            }

            $this->success('操作成功',U('Chat/chat_autoreply'));exit;
        }else{
            $autoreply = db('chat_autoreply')->select();
            $autoreplyArr = [];
            foreach ($autoreply as $k=>$row){
                $autoreplyArr[$row['ca_keyname']]=$row['ca_value'];
            }
            $this->assign("autoreply",$autoreplyArr);
            return $this->fetch();
        }

    }

}