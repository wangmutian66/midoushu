<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */
namespace app\mobile\controller;
use think\AjaxPage;
use think\Page;
use think\Db;

class Article extends MobileBase {
    public $hot_article = array();

    public function _initialize() {
        parent::_initialize();
        $this->hot_article = Db::name('article')->where("is_recommend = 1")->limit(0,20)->select();
    }
    
    /**
     * 文章内列表页
     */
    public function articleList(){
        $cat_id = I('cat_id/d',1);
        $count = M('article')->where("cat_id = ".$cat_id." AND is_open = 1")->count();
        $Page = new Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数
        $article = Db::name('article')->where("cat_id = ".$cat_id." AND is_open = 1")->select();
        if($article){
            $parent = Db::name('article_cat')->where("cat_id", $cat_id)->find();
            $this->assign('cat_name',$parent['cat_name']);
            $this->assign('article',$article);
        }
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('hot_article',$this->hot_article);
        C('TOKEN_ON',false);
        if(input('is_ajax'))
            return $this->fetch('ajaxArticleList');
        else
            return $this->fetch();
    } 

    /**
     * 文章内容页
     */
    public function detail(){
        $article_id = I('article_id/d',1);
        $article = Db::name('article')->where("article_id", $article_id)->find();
        if($article){
            $parent = Db::name('article_cat')->where("cat_id",$article['cat_id'])->find();
            $this->assign('cat_name',$parent['cat_name']);
            $this->assign('article',$article);
        }
        $this->assign('hot_article',$this->hot_article);
        return $this->fetch();
    } 


    /**
     * 文章内列表页
     */
    public function noticeList(){
        $p  = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size  = empty($_REQUEST['size']) ? 10 : $_REQUEST['size'];
        $count = M('article_notice')->where("(article_type = 0 OR article_type = 1) AND is_open = 1")->count();
        $page   = new Page($count, 20); // 实例化分页类 传入总记录数和每页显示的记录数
        $article = Db::name('article_notice')->where("(article_type = 0 OR article_type = 1) AND is_open = 1")->page($p,$size)->order('is_top DESC,add_time desc')->select();
        if($article){
            $this->assign('article',$article);
        }
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('hot_article',$this->hot_article);
        C('TOKEN_ON',false);
        if(input('is_ajax'))
        
            return $this->fetch('ajaxNoticeList');
        else
            return $this->fetch();
    } 


    /**
     * 通知内容页
     */
    public function notice_detail(){
        $article_id = I('article_id/d',1);
        $article = Db::name('article_notice')->where("article_id", $article_id)->find();
        $typeArr = array('全部可见','仅会员可见','仅供货商可见','仅子公司可见','仅员工可见','仅推广员可见');
        if($article){
            $article['article_type'] = $typeArr[$article['article_type']];
            $this->assign('article',$article);
        }
        $this->assign('hot_article',$this->hot_article);
        return $this->fetch();
    } 

    public function notice_jinji(){
        return $this->fetch();
    }

}