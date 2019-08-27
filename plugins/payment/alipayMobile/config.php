<?php
return array(
    'code'=> 'alipayMobile',
    'name' => '手机网站支付宝',
    'version' => '2.0',
    'author' => 'liyi',
    'desc' => '手机端网站支付宝 ',
    'icon' => 'logo.gif',
    'scene' =>1,  // 使用场景 0 PC+手机 1 手机 2 PC
    'config' => array(
        array('name' => 'app_id',     'label' =>'应用ID',    'type' => 'text', 'value' => ''),
        array('name' => 'charset',    'label' =>'编码格式',  'type' => 'text', 'value' => 'UTF-8'),
        array('name' => 'sign_type',  'label' =>'签名方式',  'type' => 'text', 'value' => 'RSA2'),
    ), 
);