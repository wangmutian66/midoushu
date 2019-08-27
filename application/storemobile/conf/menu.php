<?php
return	array('storeMenu' => array(
            array('name'=>'后台首页','url'=>U('/Storemobile/System/statistics'),'icon'=>'home','default'=>1),
            array('name'=>'成员管理','url'=>U('/Storemobile/Level/Staff',array("t"=>"2")),'icon'=>'person','default'=>0),
            array('name'=>'修改密码','url'=>U('/Storemobile/System/Setpsw'),'icon'=>'lock_outline','default'=>0),
            array('name'=>'资金管理','url'=>U('/Storemobile/CapitalManage/index'),'icon'=>'attach_money','default'=>0),
            array('name'=>'返利流水','url'=>U('/Storemobile/Rebate/Index'),'icon'=>'autorenew','default'=>0),
            array('name'=>'线下消费','url'=>U('/Storemobile/Rebate/Sweep'),'icon'=>'payment','default'=>0),
            array('name'=>'线下换购订单','url'=>U('/Storemobile/Rebate/OrderStore'),'icon'=>'payment','default'=>0),
            /*
            array('name'=>'权限','url'=>'javascript:;','icon'=>'h-ico5.png','default'=>0,"child"=>array(
                array('name'=>'管理员管理','url'=>U('/store/Role/index'),'icon'=>'h-ico5.png','default'=>0),
                array('name'=>'角色管理','url'=>U('/store/Role/role'),'icon'=>'h-ico5.png','default'=>0),
                //array('name'=>'权限资源列表','url'=>U('/store/Role/right_list'),'icon'=>'h-ico5.png','default'=>0),
            )),*/

        )
);