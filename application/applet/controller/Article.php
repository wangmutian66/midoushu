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
namespace app\applet\controller;
use think\AjaxPage;
use think\Page;
use think\Db;

class Article extends MobileBase {
    public $hot_article = array();

    public function _initialize() {
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
            $articleList['cat_name']=$parent['cat_name'];
            $articleList['article']=noticeimgurl($article);
        }
        $articleList['page']['totalPages']=$Page->totalPages;
        C('TOKEN_ON',false);
        return formt($articleList);
       
    } 

    /**
     * 文章内容页
     */
    public function detail(){
        $article_id = I('article_id/d',1);
        $article = Db::name('article')->where("article_id", $article_id)->find();
        if($article){
            $parent = Db::name('article_cat')->where("cat_id",$article['cat_id'])->find();
            $articleList['cat_name']=$parent['cat_name'];
            $articleList['article']=notimgurl($article);
        }
        return formt($articleList);
    } 


    /**
     * 文章内列表页
     */
    public function noticeList(){
        $count = M('article_notice')->where("(article_type = 0 OR article_type = 1) AND is_open = 1")->count();
        $Page   = new Page($count, 20); // 实例化分页类 传入总记录数和每页显示的记录数
        $article = Db::name('article_notice')->where("(article_type = 0 OR article_type = 1) AND is_open = 1")->order('add_time desc')->select();
        if($article){
            $noticeList['article']=noticeimgurl($article);
        }
        $noticeList['page']['totalPages']=$Page->totalPages;
        C('TOKEN_ON',false);
        return formt($noticeList);
    } 


    /**
     * 通知内容页
     */
    public function notice_detail(){
        $article_id = I('article_id/d');
        $article = Db::name('article_notice')->where("article_id", $article_id)->find();
        $typeArr = array('全部可见','仅会员可见','仅供货商可见','仅子公司可见','仅员工可见','仅推广员可见');
        if($article){
            $article['article_type'] = $typeArr[$article['article_type']];
            $notice_detail=notimgurl($article);
        }
        return formt($notice_detail);
    }
}