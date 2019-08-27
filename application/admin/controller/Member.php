<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 *  子公司管理
 */
namespace app\admin\controller;
use app\admin\logic\CompanyLogic;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use think\Loader;
use think\Cache;
use app\admin\model\CompanyModel;

class Member extends Base
{

    public function _initialize()
    {
        parent::_initialize();

    }


    public function index()
    {
       $this->redirect(U('Company/company_member'));
    }

}