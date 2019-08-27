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

class CompanyLogic extends Model
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
        $company = M('company')->where(array('cid' => $cid))->relation($relation)->find();
        return $company;
    }
    
    /**
     * 改变用户信息
     * @param int $cid
     * @param array $data
     * @return array
     */
    public function updateCompany($cid = 0, $data = array())
    {
        $data['update_date'] = time(); // 申请时间
        $db_res = M('company')->where(array("cid" => $cid))->data($data)->save();
        if ($db_res) {
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
    public function addCompany($company)
    {
		$company_count = Db::name('company')
				->where(function($query) use ($company){
					if ($company['mobile']) {
						$query->whereOr('mobile',$company['mobile']);
					}
				})
				->count();
		if ($company_count > 0) {
			return array('status' => -1, 'msg' => '账号已存在');
		}

    	$company['password'] = encrypt($company['password']); //md5
        $company['update_date'] = time(); // 申请时间
    	$cid = M('company')->add($company);
    	if(!$cid){
    		return array('status'=>-1,'msg'=>'添加失败');
    	}else{
    		return array('status'=>1,'msg'=>'添加成功');
    	}
    } 


    /**
     * 改变或者添加公司时 需要修改他下面的 parent_id_path  和 level 
     * @global type 
     * @param  type $parent_id_path 指定的id
     * @return 返回数组 Description
     */
    public function refresh_cat($id)
    {            
        $Company = M("company"); // 实例化User对象
        $cat = $Company->where("cid = $id")->find(); // 找出他自己
        // 刚新增的分类先把它的值重置一下
        if($cat['parent_id_path'] == '')
        {
            ($cat['parent_id'] == 0) && Db::execute("UPDATE __PREFIX__company set  parent_id_path = '0_$id', level = 1 where cid = $id"); // 如果是一级分类               
            Db::execute("UPDATE __PREFIX__company AS a ,__PREFIX__company AS b SET a.parent_id_path = CONCAT_WS('_',b.parent_id_path,'$id'),a.level = (b.level+1) WHERE a.parent_id=b.cid AND a.cid = $id");                
            $cat = $Company->where("cid = $id")->find(); // 从新找出他自己
        }        
        
        if($cat['parent_id'] == 0) //有可能是顶级分类 他没有老爸
        {
            $parent_cat['parent_id_path'] = '0';   
            $parent_cat['level'] = 0;
        }
        else{
            $parent_cat = $Company->where("cid = {$cat['parent_id']}")->find(); // 找出他老爸的parent_id_path
        }        
        $replace_level = $cat['level'] - ($parent_cat['level'] + 1); // 看看他 相比原来的等级 升级了多少  ($parent_cat['level'] + 1) 他老爸等级加一 就是他现在要改的等级
        $replace_str   = $parent_cat['parent_id_path'].'_'.$id;                
        Db::execute("UPDATE `__PREFIX__company` SET parent_id_path = REPLACE(parent_id_path,'{$cat['parent_id_path']}','$replace_str'), level = (level - $replace_level) WHERE  parent_id_path LIKE '{$cat['parent_id_path']}%'");        
    }  


    /**
     *  获取选中的下拉框 获取父级
     * @param type $cid
     */
    function find_parent_cat($cid)
    {
        if($cid == null) return array();
        
        $cat_list =  M('company')->getField('cid,parent_id,level');
        $cat_level_arr[$cat_list[$cid]['level']] = $cid;

        // 找出他老爸
        $parent_id = $cat_list[$cid]['parent_id'];
        if($parent_id > 0)
             $cat_level_arr[$cat_list[$parent_id]['level']] = $parent_id;
        // 找出他爷爷
        $grandpa_id = $cat_list[$parent_id]['parent_id'];
        if($grandpa_id > 0)
             $cat_level_arr[$cat_list[$grandpa_id]['level']] = $grandpa_id;
        
        return $cat_level_arr;      
    } 


    /**
     *  获取排好序的子公司实体店列表     
     */
    function getSortCompany($level="",$parent_id=0)
    {
        exit('出错了');
        $companyList = S('companyList');
        if(empty($companyList))
        {
            $map = array();
            if($level) $map['level'] = ['eq',$level];
            $map['parent_id']   =   ['eq',$parent_id];
            $companyList =  M("Company")->where($map)->order('cid desc')->getField('cid,cname,parent_id,level');
            $nameList = array();
            foreach($companyList as $k => $v)
            {
                $name = getFirstCharter($v['cname']) .' '. $v['cname']; // 前面加上拼音首字母
                $nameList[] = $v['cname'] = $name;
                $companyList[$k] = $v;
            }
            array_multisort($nameList,SORT_ASC,SORT_STRING,$companyList);
            S('companyList',$companyList);
        }
        return $companyList;
    }   

    /**
     * 添加成员
     * @param $Company_member
     * @return array
     */
    public function addCompany_member($Company_member)
    {
        $Company_member_count = db('company_member')->where('phone','eq',$Company_member['phone'])->count();
        if ($Company_member_count > 0) {
            return array('status' => -1, 'msg' => '手机号已存在');
        }
        $Company_member['update_time'] = $Company_member['create_time'] = time(); // 申请时间
        $id = M('Company_member')->add($Company_member);
        if(!$id){
            return array('status'=>-1,'msg'=>'添加失败');
        }else{
            return array('status'=>1,'msg'=>'添加成功');
        }
    } 


    /**
     * 添加成员
     * @param $Company_member
     * @return array
     */
    public function updateCompany_member($Company_member)
    {
        $map['phone'] = ['eq',$Company_member['phone']];
        $map['id'] = ['neq',$Company_member['id']];
        $Company_member_count = db('company_member')->where($map)->count();
        if ($Company_member_count > 0) {
            return array('status' => -1, 'msg' => '手机号已存在');
        }
        $pk = $Company_member['id'];
        if($Company_member['psw']){
            $Company_member['psw'] = encrypt($Company_member['psw']); //md5
        }else{
            unset($Company_member['psw']);
        }
        
        $Company_member['update_time'] = time(); 
        unset($Company_member['id']);
        $id = M('Company_member')->where('id','eq',$pk)->update($Company_member);
     #   echo  M('Company_member')->getlastsql();die;
        if(!$id){
            return array('status'=>-1,'msg'=>'更新失败');
        }else{
            return array('status'=>1,'msg'=>'更新成功');
        }
    } 



    /**
     * 改变或者添加公司时 需要修改他下面的 parent_id_path  和 level 
     * @global type 
     * @param  type $parent_id_path 指定的id
     * @return 返回数组 Description
     */
    /*public function refresh_company_member($id)
    {            
        $Company = M("company"); // 实例化User对象
        $cat = $Company->where("cid = $id")->find(); // 找出他自己
        // 刚新增的分类先把它的值重置一下
        if($cat['parent_id_path'] == '')
        {
            ($cat['parent_id'] == 0) && Db::execute("UPDATE __PREFIX__company_member set  parent_id_path = '0_$id', level = 1 where parent_id = $id"); // 如果是一级分类               
            Db::execute("UPDATE __PREFIX__company_member AS a ,__PREFIX__company AS b SET a.parent_id_path = CONCAT_WS('_',b.parent_id_path,'$id'),a.level = (b.level+1) WHERE a.parent_id=b.cid AND a.parent_id = $id");                
            $cat = $Company->where("cid = $id")->find(); // 从新找出他自己
        }        
        
        if($cat['parent_id'] == 0) //有可能是顶级分类 他没有老爸
        {
            $parent_cat['parent_id_path'] = '0';   
            $parent_cat['level'] = 0;
        }
        else{
            $parent_cat = $Company->where("cid = {$cat['parent_id']}")->find(); // 找出他老爸的parent_id_path
        }        
        $replace_level = $cat['level'] - ($parent_cat['level'] + 1); // 看看他 相比原来的等级 升级了多少  ($parent_cat['level'] + 1) 他老爸等级加一 就是他现在要改的等级
        $replace_str   = $parent_cat['parent_id_path'].'_'.$id;                
        Db::execute("UPDATE `__PREFIX__company_member` SET parent_id_path = REPLACE(parent_id_path,'{$cat['parent_id_path']}','$replace_str'), level = (level - $replace_level) WHERE  parent_id_path LIKE '{$cat['parent_id_path']}%'");        
    } */ 



}