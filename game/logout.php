<?php
/**
 * 退出
 * by 刘姝含
 * 2018/10/30 星期二
**/
error_reporting(0);
session_start();
$_SESSION["userid"] = $user['id'];
$_SESSION["username"] = $user['username'];
$_SESSION["real_name"] = $user['real_name'];
$_SESSION["phone"] = $user['phone'];
echo '<script>alert("退出成功");location.href="index.php";</script>';
exit();
?>