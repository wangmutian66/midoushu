<?php
namespace app\auto\controller;
use think\Cache;
use think\Controller;


class Kuaidi extends Controller{

    /**
     *
     * Author: 张洪凯
     * Date: 2018/11/24
     * 功能：快递异步推送接口
     */
    public function callback()
    {
        $red = I('red/d',0);
        $orderid = I('orderid/d',0);
        $param = I('param','');
        $param = str_replace("&quot;",'"',$param);
        if($orderid == 0 || !$param){
            echo  '{"result":"false","returnCode":"500","message":"失败"}';
            exit;
        }
        $param = json_decode($param,true);

        if($red == 1){
            $tableName = "delivery_red_log";
        }else{
            $tableName = "delivery_log";
        }

        try{
            //$param包含了文档指定的信息，...这里保存您的快递信息,$param的格式与订阅时指定的格式一致
            $pushStatus = M($tableName)->where("order_id=$orderid")->getField('pollStatus');
            if($pushStatus != 'shutdown'  && $pushStatus != 'abort')
            {
                
                $kuaidi_pushStatus = $param['status'];
                $lastResult = $param['lastResult'];
                $state = $lastResult['state'];

                switch($state)
                {
                    case 0:
                        $billStatus = "0:在途";
                        break;
                    case 1:
                        $billStatus = "1:揽件";
                        break;
                    case 2:
                        $billStatus = "2:疑难";
                        break;
                    case 3:
                        $billStatus = "3:签收";
                        break;
                    case 4:
                        $billStatus = "4:退签";
                        break;
                    case 5:
                        $billStatus = "5:派件";
                        break;
                    case 6:
                        $billStatus = "6:退回";
                        break;
                    case 7:
                        $billStatus = "7:转单";
                        break;
                }

                $data = $lastResult['data'];

                $context = array();
                foreach($data as $key=>$val)
                {
                    $context[$key] = $val;
                }

                $context = serialize($context);

                $result = db($tableName)->where("order_id=$orderid")->update(array('billStatus'=>$billStatus,'pollStatus'=>$kuaidi_pushStatus,'status'=>$state,'data'=>$context,'pushTime'=>time()));
                if($result || !db('delivery_log')->getError())
                {
                    echo  '{"result":"true","returnCode":"200","message":"成功"}';
                }
                else
                {
                    echo  '{"result":"false","returnCode":"500","message":"失败"}';
                }
            }

            //要返回成功（格式与订阅时指定的格式一致），不返回成功就代表失败，没有这个30分钟以后会重推
        }
        catch(Exception $e)
        {
            echo  '{"result":"false","returnCode":"500","message":"失败"}';
            //保存失败，返回失败信息，30分钟以后会重推
        }
    }

    /**
     *
     * Author: 张洪凯
     * Date: 2018/11/29
     * 功能：快递实时查询接口
     */
    public function doquery(){

        $invoice_no = I('num/s','');

        if($invoice_no == ''){
            return json_encode(array('log'=>array(),'com'=>'','comname'=>'','num'=>$invoice_no));
        }

        $invoice_key = config('delivery')['key'];
        $getcom_url = config('delivery')['getcom_url'];
        $codes_keys = array_keys(config('delivery')['wuliuarr']);
        $wuliuarr = config('delivery')['wuliuarr'];

        $reslist = httpRequest($getcom_url."?num=".$invoice_no."&key=".$invoice_key);
        $reslist = json_decode($reslist,true);


        if(!is_array($reslist)){
            return json_encode(array('log'=>array(),'com'=>'','comname'=>'','num'=>$invoice_no));
        }

        $shipping_code = '';
        foreach($reslist as $cde){
            if(in_array($cde['comCode'],$codes_keys)){
                $shipping_code = $cde['comCode'];
                break;
            }
        }

        if(!$shipping_code) return json_encode(array('log'=>array(),'com'=>'','comname'=>'','num'=>$invoice_no));

        $post_data = array();
        $post_data["customer"] = config('delivery')['customer'];
        $key= $invoice_key;
        $post_data["param"] = '{"com":"'.$shipping_code.'","num":"'.$invoice_no.'"}';

        $url='http://poll.kuaidi100.com/poll/query.do';
        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
        $post_data["sign"] = strtoupper($post_data["sign"]);
        $o="";
        foreach ($post_data as $k=>$v)
        {
            $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
        }
        $post_data=substr($o,0,-1);
        $result = httpRequest($url,'POST',$post_data);
        $resdata = str_replace("\"",'"',$result );
        $resdata = json_decode($resdata,true);

        $wuliudata = array();
        if($resdata['result'] !== false){
            $wuliudata = $resdata['data'];
        }

        $kuaidilog = array();
        if(count($wuliudata) > 0)
        {

            foreach($wuliudata as $key=>$wl)
            {
                $kuaidilog[$key]['ftime'] = $wl['ftime'];
                $kuaidilog[$key]['context'] = $wl['context'];
            }
        }

        return json_encode(array('log'=>$kuaidilog,'com'=>$shipping_code,'comname'=>$wuliuarr[$shipping_code],'num'=>$invoice_no));
    }

}