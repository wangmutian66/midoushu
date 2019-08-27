<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\home\controller;
use think\Controller;
use think\Db;
use think\Page;

class Article extends Base {

    public $hot_article = array();

    public function _initialize() {
        $this->hot_article = Db::name('article')->where("is_recommend = 1")->limit(0,20)->select();
        
        $tpshop_config = array();
        $tp_config = M('config')->cache(true,TPSHOP_CACHE_TIME)->select();       
        foreach($tp_config as $k => $v)
        {
            if($v['name'] == 'hot_keywords'){
                $tpshop_config['hot_keywords'] = explode('|', $v['value']);
            }             
            $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
        } 
        $this->assign('tpshop_config', $tpshop_config);
    }

    // 官网
    public function special(){
        return $this->fetch();
    }
    
    /**
     * 文章内列表页
     */
    public function articleList(){
        $cat_id = I('cat_id/d',1);
        $count = M('article')->where("cat_id = ".$cat_id." AND is_open = 1")->count();
        $Page = new Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show_cx();// 分页显示输出
        $this->assign('page', $show);// 赋值分页输出
        $article = Db::name('article')->where("cat_id = ".$cat_id." AND is_open = 1")->select();
        if($article){
            $parent = Db::name('article_cat')->where("cat_id", $cat_id)->find();
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


    /**
     * 文章内列表页
     */
    public function noticeList(){
        $count = M('article_notice')->where("(article_type = 0 OR article_type = 1) AND is_open = 1")->count();
        $Page   = new Page($count, 20); // 实例化分页类 传入总记录数和每页显示的记录数
        $show   = $Page->show_cx();     // 分页显示输出
        $this->assign('page', $show);   // 赋值分页输出
        $article = Db::name('article_notice')->where("(article_type = 0 OR article_type = 1) AND is_open = 1")->select();
        if($article){
            $this->assign('article',$article);
        }
        $this->assign('hot_article',$this->hot_article);
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
        return $this->fetch('detail');
    } 

}