<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\homered\controller;
use think\Controller;
use think\Db;
use think\Page;

class Article extends Base {

    public $hot_article = array();

    public function _initialize() {
        $this->hot_article = Db::name('article')->where("is_recommend = 1")->limit(0,20)->select();
    }
    
    /**
     * 文章内列表页
     */
    public function articleList(){
        $cat_id = I('cat_id/d',1);
        $count = M('article')->where("cat_id", $cat_id)->count();
        $Page = new Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show_cx();// 分页显示输出
        $this->assign('page', $show);// 赋值分页输出
        $article = Db::name('article')->where("cat_id", $cat_id)->select();
        if($article){
            $parent = Db::name('article_cat')->where("cat_id",$cat_id)->find();
            $this->assign('cat_name',$parent['cat_name']);
            $this->assign('article',$article);
        }
        $this->assign('hot_article',$this->hot_article);
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

}