<?php
return array(
    'code'    => 'alipay',
    'name'    => 'PC端支付宝',
    'version' => '2.0',
    'author'  => 'liyi',
    'desc'    => 'PC端支付宝插件 ',
    'scene'   =>2,  // 使用场景 0 PC+手机 1 手机 2 PC
    'icon'    => 'logo.gif',
    'config' => array(
        array('name' => 'app_id','label'               =>'应用ID',     'type' => 'text',     'value' => ''),
        array('name' => 'charset','label'              =>'编码格式',   'type' => 'text',     'value' => 'UTF-8'),
        array('name' => 'sign_type','label'            =>'签名方式',   'type' => 'text',     'value' => 'RSA2'),
    ),
    'bank_code'=>array(
            '招商银行'=>'CMB-DEBIT',
            '中国工商银行'=>'ICBC-DEBIT',
            '交通银行'=>'COMM-DEBIT',
            '中国建设银行'=>'CCB-DEBIT',
            '中国民生银行'=>'CMBC',
            '中国银行'=>'BOC-DEBIT',
            '中国农业银行'=>'ABC',        
            '上海银行'=>'SHBANK',                                           
    )
);