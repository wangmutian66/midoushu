<?php

/**
 * tpshop
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */
 
namespace app\admin\logic;

use think\Model;
use think\Db;

class StaffLogic extends Model
{    
    
    /**
     * 获取指定供货商信息
     * @param $cid int 用户CID
     * @param bool $relation 是否关联查询
     *
     * @return mixed 找到返回数组
     */
    public function detail($cid, $relation = true)
    {
        $Staff = M('Staff')->where(array('cid' => $cid))->find();
        return $Staff;
    }
    
    /**
     * 改变用户信息
     * @param int $cid
     * @param array $data
     * @return array
     */
    public function updateStaff($id = 0, $data = array())
    {
      /*  $Staff_count = db('staff')->where("phone = '{$data['phone']}' and id != {$id}")->count();
        if ($Staff_count > 0) {
            return array('status' => -1, 'msg' => '手机号码已存在');
        }
*/

        $data['update_time'] = NOW_TIME; // 申请时间
        $db_res = M('Staff')->where(array("id" => $id))->data($data)->save();
        
        if ($db_res) {
            $qrcode = self::qrcode($id,$data['invite_code']);
            M('Staff')->where('id','eq',$id)->setField('qrcode',$qrcode);
            return array('status'=>1,'msg'=>"修改成功");
        } else {
            return array('status'=>-1,'msg'=>"修改失败");
        }
    }
    
    
    /**
     * 添加供货商
     * @param $Supplier
     * @return array
     */
    public function addStaff($Staff)
    {
		/*$Staff_count = db('staff')->where("phone = '{$Staff['phone']}'")->count();
		if ($Staff_count > 0) {
			return array('status' => -1, 'msg' => '手机号码已存在');
		}*/
        $Staff['create_time'] = time(); // 申请时间
    	$result = M('Staff')->add($Staff);
        $id = M('Staff')->getLastInsID();
    	if(!$result){
    		return array('status'=>-1,'msg'=>'添加失败');
    	}else{
            $qrcode = self::qrcode($id,$Staff['invite_code']);
            M('Staff')->where('id','eq',$id)->setField('qrcode',$qrcode);
    		return array('status'=>1,'msg'=>'添加成功','id'=>$id);
    	}
    }  


    #二维码相关
    


    // 二维码
    public function qrcode($id=0,$invite_code=null)
    {
        if($id == 0){
            return ;
        }
        #       http://192.168.1.118/mobile/User/sweepCode/recommend_id/1
    //    $domain = 'http://' . $_SERVER['HTTP_HOST'];
        $domain = "https://www.midoushu.com";
        $savePath = APP_PATH . "/../public/qrcode/{$id}/";
        $webPath = "/qrcode/{$id}/";
        if($invite_code){
            $qrData = $domain.'/mobile/User/sweepCode/invite_code/' . $invite_code;
        }else{
            $qrData = $domain.'/mobile/User/sweepCode/recommend_id/' . $id;
        }
        
        $qrLevel = 'H';
        $qrSize = '8';
        if($filename = createQRcode($savePath, $qrData, $qrLevel, $qrSize)){
            $pic = $webPath . $filename;
        }
        return $pic;
    }




}