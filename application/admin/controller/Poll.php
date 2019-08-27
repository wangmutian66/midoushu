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
use think\Page;
use think\Db;
use think\Loader;
use think\Cache;

class Poll extends Base
{

    public function index()
    {
        $poll = db("delivery_log");
        $pollStatus = I('pollStatus/s','');
        $billStatus = I('billStatus/s','');
        $company = I('company/s','');
        $code = I('code/s','');
        $order_sn = I('order_sn/s','');
        $suppliers_name = I('suppliers_name/s','');

        if($pollStatus != "")
        {
            $where['pollStatus'] = ['eq',$pollStatus];
        }
        if($billStatus != "")
        {
            $where['billStatus'] = ['eq',str_replace('#',':',$billStatus)];
        }
        if($company != "")
        {
            $where['poll.shipping_code'] = ['like',"%$company%"];
        }
        if($code != "")
        {
            $where['invoice_no'] = ['like',"%$code%"];
        }
        if($order_sn != ""){
            $where['order_sn'] = ['like',"%$order_sn%"];
        }
        if($suppliers_name != ""){
            $where['suppliers_name'] = ['like',"%$suppliers_name%"];
        }

        $count = $poll->
            alias('poll')
            ->join('order o','o.order_id=poll.order_id','left')
            ->join('suppliers s','o.suppliers_id=s.suppliers_id','left')
            ->where($where)
            ->count();
        $Page = new Page($count,20);
        $show = $Page->show();
        $list = $poll
            ->alias('poll')
            ->field('poll.*,o.order_sn,suppliers_name')
            ->join('order o','o.order_id=poll.order_id','left')
            ->join('suppliers s','o.suppliers_id=s.suppliers_id','left')
            ->where($where)
            ->order('addtime desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $this->assign('pollStatus',$pollStatus);
        $this->assign('billStatus',$billStatus);
        $this->assign('company',$company);
        $this->assign('code',$code);
        $this->assign('order_sn',$order_sn);
        $this->assign('suppliers_name',$suppliers_name);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        $this->assign('page',$show);
        $this->assign('wuliuarr',config('delivery')['wuliuarr']);
        return $this->fetch();

    }

    public function detail()
    {
        $id = I('id/d',0);
        $data = db('delivery_log')
            ->alias('poll')
            ->field('poll.*,o.order_sn')
            ->join('order o','o.order_id=poll.order_id')
            ->find($id);
        if($data['pollStatus'] == "polling")
        {
            $data['pollStatus'] = "进行中";
        }
        elseif($data['pollStatus'] == "shutdown")
        {
            $data['pollStatus'] = "关闭";
        }
        elseif($data['pollStatus'] == "abort")
        {
            $data['pollStatus'] = "中止";
        }
        elseif($data['pollStatus'] == "updateall")
        {
            $data['pollStatus'] = "重新推送";
        }

        if(!empty($data['data'])){
            $context = unserialize($data['data']);
            if(count($context) > 0)
            {
                $kuaidilog = array();
                foreach($context as $key=>$wl)
                {
                    $kuaidilog[$key]['time'] = $wl['ftime'];
                    $kuaidilog[$key]['context'] = $wl['context'];
                }
            }
        }
        $this->assign('wuliuarr',config('delivery')['wuliuarr']);
        $this->assign('kuaidilog',$kuaidilog);
        $this->assign($data);
        return $this->fetch();
    }

    public function resub()
    {
        $id = I('id/d',0);
        $data = db('delivery_log')->find($id);

        $invoice_key = config('delivery')['key'];
        $getcom_url = config('delivery')['getcom_url'];
        $shipping_code = $data['shipping_code'];

        #根据单号查询快递公司编码
        $reslist = httpRequest($getcom_url."?num=".$data['invoice_no']."&key=".$invoice_key);
        $reslist = json_decode($reslist,true);

        if(!is_array($reslist)){
            exit(json_encode(array('errno'=>1,'error'=>"快递单号填写有误")));
        }

        $codes_keys = array_keys(config('delivery')['wuliuarr']);
        foreach($reslist as $cde){
            if(in_array($cde['comCode'],$codes_keys)){
                $shipping_code = $cde['comCode'];
                break;
            }
        }

        $callbackurl = config('delivery')['callbackurl'];
        $salt = get_rand_str(15,0,1);
        $post_data["schema"] = 'json' ;
        $post_data["param"] = '{"company":"'.$shipping_code.'","number":"'.$data['invoice_no'].'","from":"","to":"","key":"'.$invoice_key.'","parameters":{"callbackurl":"'.$callbackurl.'?orderid='.$data['order_id'].'","salt":"'.$salt.'","resultv2":"1"}}';


        #订阅请求地址
        $posturl = 'http://www.kuaidi100.com/poll';
        $o="";
        foreach ($post_data as $k=>$v)
        {
            $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
        }
        $post_data=substr($o,0,-1);
        $kuaidiresult = httpRequest($posturl,'POST',$post_data);
        $kuaidi = @json_decode($kuaidiresult,true);

        #快递公司编码和单号不匹配，通过智能接口查询快递公司编码
        if($kuaidi['returnCode'] == 700 || $kuaidi['returnCode'] == 702){
            exit(json_encode(array('errno'=>1,'error'=>"订阅提交失败")));
        }

        if($shipping_code == ''){
            exit(json_encode(array('errno'=>1,'error'=>"快递单号填写有误")));
        }else{
            $post_data = array();
            $post_data["schema"] = 'json';
            $post_data["param"] = '{"company":"'.$shipping_code.'","number":"'.$data['invoice_no'].'","from":"","to":"","key":"'.$invoice_key.'","parameters":{"callbackurl":"'.$callbackurl.'?orderid='.$data['order_id'].'","salt":"'.$salt.'","resultv2":"1"}}';
            $o="";
            foreach ($post_data as $k=>$v)
            {
                $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
            }
            $post_data=substr($o,0,-1);
            $kuaidiresult = httpRequest($posturl,'POST',$post_data);
            $kuaidi = @json_decode($kuaidiresult,true);
        }


        db('delivery_log')->where("id=$id")->update(array('shipping_code'=>$shipping_code,'subtime'=>time(),'orderstatus'=>$kuaidi['returnCode'],'ordermessage'=>$kuaidi['message']));
        $arr = array('errno'=>0);

        echo json_encode($arr);
    }

}