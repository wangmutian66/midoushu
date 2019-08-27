<?php
/**
 * tpshop

 * Author: 隔壁老王
 * Date: 2018年4月16日16:18:43
 */
namespace app\admin\model;
use think\Model;
class CompanyWithdrawalModel extends Model {
    public $table = '__COMPANY_WITHDRAWAL__';
    public $pk = 'id';
    protected $autoWriteTimestamp = true;

}
