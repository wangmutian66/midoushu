<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\applet\controller;
use app\common\logic\CartLogic;
use app\common\logic\MessageLogic;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\logic\CouponLogic;
use think\Page;
use think\Request;
use think\Verify;
use think\db;



class Bind extends MobileBase
{
    public function bind_store(){
        $cid = I('get.cid/d',0);
        $mobile = I('mobile','');
        $cname = M('company')->where("cid=".$cid)->value('cname');
        $this->assign('cid',$cid);
        $this->assign('cname',$cname);
        $this->assign('mobile',$mobile);
        return $this->fetch();
    }

    public function do_bind_store(){

        $mobile = I('mobile','');
        if($mobile == ''){
            return json(array('status'=>0,'info'=>'请输入手机号码'));
        }

        $bd = M('bind_store_user')->where("mobile='".$mobile."'")->find();
        if($bd){
            return json(array('status'=>0,'info'=>'该实体店已绑定！'));
        }

        $sr = M('company')->field('cid')->where("mobile='".$mobile."' and parent_id > 0")->find();
        if(!$sr){
            return json(array('status'=>0,'info'=>'手机号码不存在，只有实体店才可以绑定!'));
        }

        return json(array('status'=>1,'cid'=>$sr['cid'],'mobile'=>$mobile));

    }

    public function bind_store_ok(){
        $cid = I('cid/d',0);
        $mobile = I('mobile','');
        $openid = $this->GetOpenid();
        $addtime = time();

        $result = M('bind_store_user')->save(['cid'=>$cid,'mobile'=>$mobile,'openid'=>$openid,'addtime'=>$addtime]);
        if($result){
            return json(array('status'=>1,'info'=>'绑定成功！'));
        }else{
            return json(array('status'=>0,'info'=>'绑定失败！'));
        }



    }
}
