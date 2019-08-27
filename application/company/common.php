<?php

/*显示错误提示*/
function print_error($data){
	
	$html = 	'<script>';
	if(is_set($data['url'])){
		$html .=	"alert('{$data}');location.href = '{$data['url']}'";
	}else{
		$html .=	"alert('{$data}');window.history.go(-1)";
	}
	$html .= 	'</script>';
	
}



/**
 * 子公司操作记录
 * @param $log_url 操作URL
 * @param $log_info 记录信息
 */
function companyLog($log_info,$tims=null){
    $add['log_time'] = (empty($tims)) ? (time()) : $tims;
    $add['cid'] = session('company.cid');
    $add['log_info'] = $log_info;
    $add['log_ip'] = request()->ip();
    $add['log_url'] = request()->baseUrl() ;
    M('CompanyLog')->add($add);
}



/*获取子公司的父级数据*/
/*function get_company_parent($store_id=0){
	if($store_id==0){
		return ;
	}else{
		if($company_store_row = S('company_store_row_'.$store_id)){
			return $company_store_row;
		}else{
			$company_store_row = db('company')->find($store_id);
			if($company_store_row){
				S('company_store_row_'.$store_id,$company_store_row);
			}else{
				return ;
			}
		}
		return $company_store_row ;
	}

}*/

/*根据给出的列表 返回需要的列*/
function TK_get_row($list,$key){
	if(empty($list)) return ;
	/*dump($list);
	die;*/
	foreach ($list as $k => $v) {
		$data[] = $v[$key];
	}
	return $data;
}

function get_company_name($cid){
	if(empty($cid)){return ;}
	$company_name = S('company_name_'.$cid);
	if($company_name){
		return $company_name;
	}else{
		$company_name =  db('company')->find($cid)['cname'];
		S('company_name_'.$cid,$company_name);
		return $company_name;
	}
}

function pay_status($status){
	switch ($status) {
		case 0:
			return '待支付';
			break;
		case 1:
			return '已支付';
			break;
		case -1:
			return '作废';
			break;
		default:
			# code...
			break;
	}
}
/**
 * 实体店名称
 */
function shitis($cid)
{
   
    $map = array();
    $map['cid'] = $cid;
    $shiti = M('Company')->where($map)->find();
    return $shiti['cname'];
}
/**
 * 导出excel
 * @param $strTable	表格内容
 * @param $filename 文件名
 */
function downloadExcel($strTable,$filename)
{
	header("Content-type: application/vnd.ms-excel");
	header("Content-Type: application/force-download");
	header("Content-Disposition: attachment; filename=".$filename."_".date('Y-m-d').".xls");
	header('Expires:0');
	header('Pragma:public');
	echo '<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'.$strTable.'</html>';
}