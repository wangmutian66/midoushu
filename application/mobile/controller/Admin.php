<?php

namespace app\mobile\controller;
use Think\Db;
use app\common\logic\UsersLogic;
class Admin extends MobileBase {

	public function _initialize()
    {
        parent::_initialize();
    }

	public function index() {
		if(\think\Request::instance()->isGet()){
			if($mobile = I('post.mobile')) {
				$code = I('get.mobile_code');
				if (!$code){
					$this->error('请输入验证码');
				}
	            $userLogic = new UsersLogic();
	            $check_code = $userLogic->check_validate_code($code,$mobile,'phone',session_id(),6);
	            if ($check_code['status'] != 1){
	            	$this->error($check_code['msg']);
	            }

				$map['mobile']	=	['eq',$mobile];
				$item = M('company')->alias('company')
						->field('company.*,province.name province_name,city.name city_name,district.name district_name')
						->join('region province','province.id = company.province_id','left')
						->join('region city','city.id = company.city_id','left')
						->join('region district','district.id = company.district_id','left')
						->where($map)->find();

				if(empty($item)){
					$this->error('无此实体店');
				}
				if($item['parent_id'] == 0){
					$sign_where['company_id']	=	$item['cid'];
					$sign_where['store_id']		=	0;
				}else{
					$sign_where['company_id']	=	$item['parent_id'];
					$sign_where['store_id']		=	$item['cid'];
				}
				$recommender	=	db('company_sign')->where($sign_where)->find();
				$this->assign('item',$item);
				$this->assign('recommender',$recommender);
			}
		}
		$sms_time_out = tpCache('sms.sms_time_out');
        $sms_time_out = $sms_time_out ? $sms_time_out : 120;
        $this->assign("sms_time_out",$sms_time_out);
		return $this->fetch('index');
	}
	
	public function thumb() {
		$cid = I('get.cid/d');
		$litpic = M('company')->where("cid",$cid)->getfield('litpic');
		if(empty($litpic)) $litpic = __STATIC__.'/images/headbig.png';
		$this->assign('cid',$cid);
		$this->assign('litpic',$litpic);
		return $this->fetch();
	}

	

	public function add() {
		$provinceId = I('post.province/d');
		$cityId = I('post.city/d');
		$districtId = I('post.district/d');
		$cid = I('post.cid/d');
		$lng = I('post.lng/f');
		$lat = I('post.lat/f');
		$data = array(
			'province_id'	=>	$provinceId,
			'city_id'		=>	$cityId,
			'district_id'	=>	$districtId,
			'lng'	=>$lng,
			'lat'	=>$lat,
		);
		M('company')->where("cid",$cid)->data($data)->save();
		echo 1;
	}

	public function addThumb() {
		$cid = I('get.cid/d');
		if($cid){
			$path = "/public/upload/company/".date('Y')."/".date("m-d");
	        $path_nods = "public/upload/company/".date('Y')."/".date("m-d");
	        !is_dir($path_nods) && mkdir($path_nods, 0644, true);
	        $save_path = ROOT_PATH . $path;
	        $save_name = 'thumb_'.$cid.'.jpg';
	        $file = request()->file('fileData');
			$info = $file->validate(['type'=>'image/jpeg,image/png,image/jpg,image/gif'])->move($save_path,$save_name);
			if($info){
	            $msg['status']	=	1;
	            $msg['info']	= $path.'/'.$save_name;
	            $image = \think\Image::open(ROOT_PATH.$msg['info']);
				$image->thumb(500, 500)->save(ROOT_PATH.$msg['info']);
				M('company')->where("cid",$cid)->setField('litpic',$msg['info']);
	        }else{
	        	$msg['status']	=	0;
	            $msg['info']	=	$file->getError();
	        }
	        echo json_encode($msg);
		}
		
	}

	
}