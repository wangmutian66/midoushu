<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */

namespace app\admin\logic;

use think\Model;
use think\Db;

class DelInfoLogic extends Model
{
    
    /**
     * 删除信息
     * @param type $id
     * @param type $table_name
     */
    public function delete_info()
    {
        $id = I('get.id/d');
        $table_name = I('post.table_name/s');

        if ($id && $table_name) {

            $verify = new \think\Verify();
            if (!$verify->check(I('post.verify'),'admin_login')) {
                $res['status'] = 1;
                $res['msg'] = '验证码错误，删除失败！';
                return $res;
            }
            $result = M($table_name)->delete($id);
            if ($result) {
                if($table_name == 'company'){
                    M('company_sign')->where("company_id=".$id)->delete();
                    adminLog('删除子公司/实体店');
                }
                $res['status'] = 0;
                $res['msg'] = '删除成功';
            } else {
                $res['status'] = 1;
                $res['msg'] = '删除失败';
            }
        } else {
            $res['status'] = 1;
            $res['msg'] = '非法操作';
        }

        return $res;
    }

}