<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 *  推广员管理
 */
namespace app\admin\controller; 
use think\Page;
use think\Db;
use think\AjaxPage;

class Promoters extends Base {

    public function index(){
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function ajaxindex(){
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;

        $sort_order = I('order_by').' '.I('sort');
        
        $condition2['promoters_uid'] = ['neq',0];

        $model = M('users');
        $count = $model->where($condition)->where($condition2)->count();
        $Page  = new AjaxPage($count,10);
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        
        
        $userList = $model->alias('user')
                            ->field('user.*,staff.uname,staff.real_name,company.cname')
                            ->join('__STAFF__ staff','staff.id = user.promoters_uid')
                            ->join('__COMPANY__ company','user.promoters_top_id = company.cid')
                            ->where($condition)
                            ->where($condition2)
                            ->order($sort_order)
                            ->limit($Page->firstRow.','.$Page->listRows)
                            ->select();
                                       
        $show = $Page->show();
        $this->assign('userList',$userList);
        $this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }






}