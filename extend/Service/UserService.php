<?php
namespace Service;

/* 
	该文件为引用示例文件，不需要删除 
	其他项目文件中 头部 使用 
	use Service\UserService;
	然后在function 中
	$a = new UserService;
    $a->print_echo();
    文件为惰性加载 ,不使用new 进行实例化 则文件不会运行
*/

class UserService
{
	public function __construct(){
		print_r(2);die;
	}
	public function print_echo(){
		echo 1;
	}

}