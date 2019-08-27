<?php
/**
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 */
namespace app\member\controller; 
use think\Controller;
class Index extends Base {
	/**
     * 析构函数
     */
    function _initialize() 
    {
        parent::_initialize();
   } 

    public function index(){
        $member_info = cache("member_{$this->member_id}");
        $member_list = db('company_member')
            ->alias('member')
            ->field('member.phone,psw,real_name,store.cname store_name,company.cid company_id,company.cname as company_name,store.cid store_id')
            ->join('company store','store.cid = member.parent_id','left')
            ->join('company company','company.cid=store.parent_id','left')
            ->where('member.phone',$member_info['phone'])
            ->select();

        $cumulative_money = db('company_member')->where(["phone"=>$member_info['phone']])->sum('cumulative_money');

        $zmoney = db('company_member')->where(["phone"=>$member_info['phone']])->sum('money');
        $tmoney = db('member_balance')->where(["phone"=>$member_info['phone']])->value('balance');


        $member_list_arr = array();
        foreach($member_list as $key=>$value){
            if(!$value['company_id']){
                $member_list_arr['company'][] = $value;
                $member_list_arr['company']['name'] = '子公司';
            }else{
                $member_list_arr[$value['company_id']][] = $value;
                $member_list_arr[$value['company_id']]['name'] = $value['company_name'];
            }

        }

        $member_list = $member_list_arr;
        $this->assign('tmoney',$tmoney);                     // 体现总额
        $this->assign('zmoney',$zmoney);                     // 实体店总额
        $this->assign('cumulative_money',$cumulative_money); // 累计总佣金
        $this->assign('member_list',$member_list);
        return $this->fetch('Index');
    }
   
   
}