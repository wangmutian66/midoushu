<?php
/**
 * Author: 隔壁老王
 * Date: 2018年4月16日14:58:13
 */
namespace app\admin\model;
use think\Model;
class CompanyModel extends Model {
    public $table = '__COMPANY__';
    public $pk = 'cid';
    protected $autoWriteTimestamp = true;

  #  protected $createTime = 'create_time';
  #  protected $updateTime = 'update_date';
    public function WithdrawLog(){
        return $this->hasMany('StoreWithdrawLog','store_id');
    }
    
}
