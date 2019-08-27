<?php
return	array('storeMenu' => array(
            array('name'=>'后台首页','url'=>U('/Store/System/Statistics'),'icon'=>'h-ico3.png','default'=>1),
            array('name'=>'成员管理','url'=>U('/Store/Level/Staff',array("t"=>"2")),'icon'=>'h-ico3.png','default'=>0),
            array('name'=>'修改密码','url'=>U('/Store/System/Setpsw'),'icon'=>'h-ico11.png','default'=>0),
            array('name'=>'资金管理','url'=>U('/Store/CapitalManage/index'),'icon'=>'h-ico5.png','default'=>0),
            array('name'=>'返利流水','url'=>U('/Store/Rebate/Index'),'icon'=>'h-ico5.png','default'=>0),
            array('name'=>'线下消费','url'=>U('/Store/Rebate/Sweep'),'icon'=>'h-ico5.png','default'=>0),
            array('name'=>'线下换购订单','url'=>U('/Store/Rebate/OrderStore'),'icon'=>'h-ico5.png','default'=>0),
            array('name'=>'权限','url'=>'javascript:;','icon'=>'h-ico5.png','default'=>0,"child"=>array(
                array('name'=>'管理员管理','url'=>U('/store/Role/index'),'icon'=>'h-ico5.png','default'=>0),
                array('name'=>'角色管理','url'=>U('/store/Role/role'),'icon'=>'h-ico5.png','default'=>0),
                //array('name'=>'权限资源列表','url'=>U('/store/Role/right_list'),'icon'=>'h-ico5.png','default'=>0),
            )),

        )
);