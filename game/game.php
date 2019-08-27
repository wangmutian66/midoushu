﻿<!DOCTYPE HTML>
<html>
<head>
	<meta charset="UTF-8">
<title>哈尔滨创赏科技开发有限公司</title>
<link href="css/bootstrap.css" rel="stylesheet">
<link href="css/bootstrap-responsive.css" rel="stylesheet">
<link href="css/docs.css" rel="stylesheet">
<link href="css/onethink.css" rel="stylesheet">

<!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
<!--[if lt IE 9]>
<script src="js/html5shiv.js"></script>
<![endif]-->

<!--[if lt IE 9]>
<script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>
<![endif]-->
<!--[if gte IE 9]><!-->
<script type="text/javascript" src="js/jquery-2.0.3.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<!--<![endif]-->
<!-- 页面header钩子，一般用于加载插件CSS文件和代码 -->

</head>
<body>
	<!-- 头部 -->
	<!-- 导航条
================================================== -->
    <header style="height:80px;">
        <div class="container">
            <h3 style="text-align:left;">哈尔滨创赏科技开发有限公司</h3>
          
        </div>
    </header>

<div class="navbar navbar-inverse navbar-fixed-top">
    <div class="navbar-inner">
        <div class="container">
            
            <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <?php include_once('public/menu.html');?>
    </div>
</div>

	<!-- /头部 -->
	
	<!-- 主体 -->
	
<div id="main-container" class="container">
    <div class="row">
        
        <!-- 左侧 nav
        ================================================== -->
           <!--  <div class="span3 bs-docs-sidebar">
                
                <ul class="nav nav-list bs-docs-sidenav">
                                    </ul>
            </div> -->
        
        
        <style>
.pay-model{ float:left; width:150px; border:2px solid #ddd; height:55px; margin-right:20px; cursor:pointer}
.pay-model img{ display:block; width:100%;}
.pay-model.checked{ border:2px solid #0855e4}

.right_tt {
    height: 30px;
    line-height: 30px;
    color: #003994;
    font-size: 16px;
    position: relative;
    border-bottom: 1px solid #ddd;
    font-family: "微软雅黑";
}
.right_tt div {
    position: absolute;
    right: 0;
    top: 0px;
    font-size: 12px;
    font-weight: normal;
    color: #666;
    text-align: right;
}
a {
    text-decoration: none;
    outline: none;
    color: #666;
}
.inner {
    margin: 20px 0;
}
</style>
<br>

<div class="wrap">
	    <div class="full">    
    	<center><div class="right_tt">在线充值</div></center>
        <div class="inner">

<form id="form1" name="form1" method="post" action="">
<table style="font-size:16px;" width="100%" cellspacing="10" cellpadding="0" border="0">
  <tbody><tr>
    <td width="10%" height="40" align="right">充值用户名：</td>
    <td><input name="username" id="username" class="input_box" size="50" maxlength="50" type="text"></td>
  </tr>
  <tr>
    <td height="40" align="right">确认用户名：</td>
    <td><input name="username2" id="username2" class="input_box" size="50" maxlength="50" type="text"></td>
  </tr>

<tr>
    <td height="40" align="right">充值内容：</td>
   <td> <select name="" class="input_box">
		<option value="晶币" selected>米币
		
    </select></td>
  </tr>


  <tr>
    <td height="40" align="right">充值金额：</td>
    <td><input name="money" id="money" class="input_box" size="10" maxlength="10" type="text"> 元 【1人民币=100米币】</td>
  </tr>
  <tr>
    <td height="40" align="right">充值方式：</td>
    <td>
    <div class="pay-model checked"><img src="picture/weixinzhifu.jpg"></div>
    <div class="pay-model"><img src="picture/zhifu.jpg"></div>
    </td>
  </tr>
  <tr>
    <td height="40" align="right">&nbsp;</td>
    <td>
		<input type="hidden" name="userid" value="" id="userid">
      <input name="button" id="button" value="确认充值" class="btn btn-submit" type="submit">
    </td>
  </tr>
</tbody></table>
</form>

<script>
<?php
	$isLogin = $_COOKIE['username'];
	if(!empty($isLogin)) {echo 'var isLogin = true;';} else {echo 'var isLogin = false;';}
?>
$(function(){
	$('.pay-model').click(function(){
		$(this).addClass('checked').siblings().removeClass('checked');
	});
	
	$('.btn-submit').click(function(){

		if(!isLogin){
			alert('请先登录');
			return false;
		}


		if($.trim($('#username').val())==''){
			alert('请输入充值用户名');
			$('#username').focus();
			return false;
		}
		if($.trim($('#username2').val())==''){
			alert('请确认用户名');
			$('#username2').focus();
			return false;
		}
		if($('#username2').val()!=$('#username').val()){
			alert('两次输入的用户名不一样');
			$('#username2').focus();
			return false;
		}
		if($.trim($('#money').val())==''){
			alert('请输入充值金额');
			$('#money').focus();
			return false;
		}
		if(isNaN($('#money').val())){
			alert('金额必须是数字');
			$('#money').focus();
			return false;
		}
		alert('对不起，暂时未开通支付功能！');
	});
});
</script>        </div>
    </div>
</div>



    </div>
</div>

<script type="text/javascript">
    $(function(){
        $(window).resize(function(){
            $("#main-container").css("min-height", $(window).height() - 543);
        }).resize();
    })
</script>
	<!-- /主体 -->

	<!-- 底部 -->
	
    <!-- 底部
    ================================================== -->
    <footer class="footer">
      <div class="container">
          <p>
海南省老城高新技术产业示范区海南生态软件园A17幢一层2001<br>
              哈尔滨创赏科技开发有限公司 服务热线：18589650735 琼ICP备18003607号-1<br>

健康游戏忠告：抵制不良游戏 拒绝盗版游戏 注意自我保护 谨防受骗上当 适度游戏益脑 沉迷游戏伤身 合理安排时间 享受健康生活
</p>
      </div>
    </footer>

<script type="text/javascript">
(function(){
	var ThinkPHP = window.Think = {
		"ROOT"   : "", //当前网站地址
		"APP"    : "/index.php?s=", //当前项目地址
		"PUBLIC" : "/Public", //项目公共目录地址
		"DEEP"   : "/", //PATHINFO分割符
		"MODEL"  : ["3", "", "html"],
		"VAR"    : ["m", "c", "a"]
	}
})();
</script>
 <!-- 用于加载js代码 -->
<!-- 页面footer钩子，一般用于加载插件JS文件和JS代码 -->
<div class="hidden"><!-- 用于加载统计代码等隐藏元素 -->
	
</div>

	<!-- /底部 -->
</body>
</html>