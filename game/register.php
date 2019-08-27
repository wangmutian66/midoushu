<?php
/**
* 用户信息
* by 刘姝含
* 2018/10/30 星期二
**/
error_reporting(0);
$php_version = explode('-', phpversion());
$php_version = substr($php_version[0], 0, 1 );
if($_POST) {
	include_once 'mysql.php';
	if($php_version>=7){
		$db = new mysqli('127.0.0.1', 'root', '123456');
		$db->query("set names 'utf8'"); //数据库输出编码 应该与你的数据库编码保持一致.南昌网站建设公司百恒网络PHP工程师建议用UTF-8 国际标准编码.
    	$db -> select_db('game'); //打开数据库
	}else{
		$db = new mysql('127.0.0.1', 'mds_game', 'wn6KipDKTxFyRMHK', 'mds_game', '', 'utf8');
	}
	$data = $_POST;
	$username = htmlentities(trim($_POST['username']));
	$password = htmlentities(trim($_POST['password']));
	$repassword = htmlentities (trim($_POST['repassword']));
	$email = htmlentities (trim($_POST['email']));
	$realName = htmlentities (trim($_POST['real_name']));
	$cardNo = htmlentities (trim($_POST['card_no']));
	$phone = htmlentities (trim($_POST['phone']));
	$now = time();

	// if(empty($password)) {
	// 	echo '密码不能为空';
	// 	header("Refresh:3;url=register.php");exit();
	// } else if($password != $repassword) {
	// 	echo '密码和确认密码不一致';
	// 	header("Refresh:3;url=register.php");exit();
	// } else if(empty($username)) {
	// 	echo '用户名不能为空';
	// 	header("Refresh:3;url=register.php");exit();
	// } else if(empty($email)) {
	// 	echo '邮箱不能为空';
	// 	header("Refresh:3;url=register.php");exit();
	// } else if(empty($realName)) {
	// 	echo '真实姓名不能为空';
	// 	header("Refresh:3;url=register.php");exit();
	// } else if(empty($cardNo)) {
	// 	echo '身份证号码不能为空';
	// 	header("Refresh:3;url=register.php");exit();
	// } else if(empty($phone)) {
	// 	echo '手机号不能为空';
	// 	header("Refresh:3;url=register.php");exit();
	// } else {
	if($php_version>=7){
		$sqlCardNoExist = "select id from tp_game_user where `username`='$username' limit 1";
		$resCardNoExist = $db->query($sqlCardNoExist);
		if(mysqli_num_rows($resCardNoExist)) {
			echo '<script>alert("该用户名已经被注册");history.go(-1);</script>';
			exit();
		}
		

		//检查email 是否被注册
		$sqlEmailExist = "select id from tp_game_user where `email`='$email' limit 1";
		$resEmailExist = $db->query($sqlEmailExist);
		if(mysqli_num_rows($resEmailExist)) {
			echo '<script>alert("邮箱已经被注册");history.go(-1);</script>';
			exit();
		}

		//检查手机号 是否被注册
		$sqlPhoneExist = "select id from tp_game_user where `phone`='$phone' limit 1";
		$resPhoneExist = $db->query($sqlPhoneExist);
		if(mysqli_num_rows($resPhoneExist)) {
			echo '<script>alert("手机号已经被注册");history.go(-1);</script>';
			exit();
		}
			//检查身份证号是否被注册
		$sqlCardNoExist = "select id from tp_game_user where `card_no`='$cardNo' limit 1";
		$resCardNoExist = $db->query($sqlCardNoExist);
		if(mysqli_num_rows($resCardNoExist)) {
			echo '<script>alert("身份证号已经被注册");history.go(-1);</script>';
			exit();
		}
	}else{
		$sqlCardNoExist = "select id from tp_game_user where `username`='$username' limit 1";
		$resCardNoExist = $db->query($sqlCardNoExist);
		if(mysql_num_rows($resCardNoExist)) {
			echo '<script>alert("该用户名已经被注册");history.go(-1);</script>';
			exit();
		}


		//检查email 是否被注册
		$sqlEmailExist = "select id from tp_game_user where `email`='$email' limit 1";
		$resEmailExist = $db->query($sqlEmailExist);
		if(mysql_num_rows($resEmailExist)) {
			echo '<script>alert("邮箱已经被注册");history.go(-1);</script>';
			exit();
		}

		//检查手机号 是否被注册
		$sqlPhoneExist = "select id from tp_game_user where `phone`='$phone' limit 1";
		$resPhoneExist = $db->query($sqlPhoneExist);
		if(mysql_num_rows($resPhoneExist)) {
			echo '<script>alert("手机号已经被注册");history.go(-1);</script>';
			exit();
		}
			//检查身份证号是否被注册
		$sqlCardNoExist = "select id from tp_game_user where `card_no`='$cardNo' limit 1";
		$resCardNoExist = $db->query($sqlCardNoExist);
		if(mysql_num_rows($resCardNoExist)) {
			echo '<script>alert("身份证号已经被注册");history.go(-1);</script>';
			exit();
		}
	}
		 //检查帐号是否被注册
	
	// }
	//注册
	$pwd = md5(md5($password));
	if($php_version>=7){
		$sql = "insert into tp_game_user(username,real_name,password,email,phone,card_no,create_time) values('$username','$realName','$pwd','$email','$phone','$cardNo','$now')";
		$db->query($sql);
	}else{
		$sql = "insert into tp_game_user set `username`='{$username}', `real_name`='{$realName}', `password`='{$pwd}', `email`='{$email}', `phone`='{$phone}',";
		$sql .= "`card_no`= '{$cardNo}', `create_time`='{$now}'";
		$db->query($sql);
	}
	

	echo '<script>alert("注册成功");location.href="login.php";</script>';
	exit();
} else {
	?>
	<!DOCTYPE HTML>
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
		</div>

		<!-- /头部 -->

		<!-- 主体 -->

		<header>
			<div class="container">
				<h3>用户注册</h3>
			</div>
		</header>

		<div id="main-container" class="container">
			<div class="row">



				<section>
					<div class="span12">
						<form  name="myform" class="login-form" action="register.php" method="post">
							<div class="control-group">
								<label class="control-label" for="inputEmail">用户名</label>
								<div class="controls">
									<input type="text"  class="span3" placeholder="请输入用户名"  ajaxurl="/member/checkUserNameUnique.html" errormsg="请填写1-16位用户名" nullmsg="请填写用户名" datatype="*1-16" value="" name="username" >
								</div>
							</div>
							<div class="control-group">
								<label class="control-label" for="inputPassword">密码</label>
								<div class="controls">
									<input type="password" id="inputPassword"  class="span3" placeholder="请输入密码"  errormsg="密码为6-20位" nullmsg="请填写密码" datatype="*6-20" name="password" >
								</div>
							</div>
							<div class="control-group">
								<label class="control-label" for="inputPasswordres">确认密码</label>
								<div class="controls">
									<input type="password" id="inputPasswordres" class="span3" placeholder="请再次输入密码" recheck="password" errormsg="您两次输入的密码不一致" nullmsg="请填确认密码" datatype="*" name="repassword" >
								</div>
							</div>
							<div class="control-group">
								<label class="control-label" for="inputEmail">邮箱</label>
								<div class="controls">
									<input type="text"  class="span3" placeholder="请输入电子邮件"  ajaxurl="/member/checkUserEmailUnique.html" errormsg="请填写正确格式的邮箱" nullmsg="请填写邮箱" datatype="e" value="" name="email" >
								</div>
							</div>

							<div class="control-group">
								<label class="control-label" for="inputEmail">手机</label>
								<div class="controls">
									<input type="text" placeholder="请输入手机号" name="phone" id="phone"><br>
			  <!-- <input type="number" style="width:120px;" placeholder="验证码" name="code" id="code" maxlength="6">  
			  	<input type="button" class="btn" id="btn" style="margin-top:-10px;" value=" 获取验证码" onclick="check_phone();">  -->
			  </div>
			</div>


			<div class="control-group">
				<label class="control-label" for="inputEmail">真实姓名</label>
				<div class="controls">
					<input type="text" class="span3" placeholder="请输入真实姓名"   value="" name="real_name" id="real_name" >
				</div>
			</div>


			<div class="control-group">
				<label class="control-label" for="inputEmail">身份证号码</label>
				<div class="controls">
					<input type="text" class="span3" placeholder="请输入身份证号码"   value="" id='card_no' name='card_no' >
				</div>
			</div>



          <!--<div class="control-group">
            <label class="control-label" for="inputPassword">验证码</label>
            <div class="controls">
              <input type="text" id="inputPassword" class="span3" placeholder="请输入验证码"  errormsg="请填写5位验证码" nullmsg="请填写验证码" datatype="*5-5" name="verify">
            </div>
          </div>

           <div class="control-group">
            <label class="control-label"></label>
            <div class="controls">
                <img class="verifyimg reloadverify" alt="点击切换" src="picture/index.php" style="cursor:pointer;">
            </div>
            <div class="controls Validform_checktip text-warning"></div>
        </div> -->

        <div class="control-group">
        	<input name="agree" required valid="{&quot;required&quot;:true}" msg="{&quot;required&quot;:&quot;请同意直真用户注册协议&quot;}" checked="true" type="checkbox">
        	<a href="/index.php?s=/Home/Article/detail/id/16.html">同意用户注册协议</a>
        </div>

        <div class="control-group">
        	<div class="controls">
        		<button type="submit"  name="submit1" class="btn" onclick="return check(this.form)">注 册</button>

        	</div>
        </div>
    </form>
</div>
</section>


</div>
</div>

<script type="text/javascript">
	$(function(){
		$(window).resize(function(){
			$("#main-container").css("min-height", $(window).height() - 343);
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
    				哈尔滨创赏科技开发有限公司 服务热线：400-110-8690 黑ICP备18001528号-2<br>

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

<script type="text/javascript">
	$(document)
	.ajaxStart(function(){
		$("button:submit").addClass("log-in").attr("disabled", true);
	})
	.ajaxStop(function(){
		$("button:submit").removeClass("log-in").attr("disabled", false);
	});


	$("myform").submit(function(){
		var self = $(this);
		$.post(self.attr("action"), self.serialize(), success, "json");
		return false;

		function success(data){
			if(data.status){
				window.location.href = data.url;
			} else {
				self.find(".Validform_checktip").text(data.info);
    				//刷新验证码
    				$(".reloadverify").click();
    			}
    		}
    	});

	$(function(){
		var verifyimg = $(".verifyimg").attr("src");
		$(".reloadverify").click(function(){
			if( verifyimg.indexOf('?')>0){
				$(".verifyimg").attr("src", verifyimg+'&random='+Math.random());
			}else{
				$(".verifyimg").attr("src", verifyimg.replace(/\?.*$/,'')+'?'+Math.random());
			}
		});
	});
</script>

<script type="text/javascript">
	function check(form){
		
		if(form.username.value==""){
			alert("请输入用户名");
			form.username.focus();
			return false;
		}
		
		if(form.password.value==""){
			alert("请输入密码");
			form.password.focus();
			return false;
		}

		if (form.password.value.length<6 || form.password.value.length>15){
			alert("密码长度限制在6-15位!");
			form.password.focus();
			return false;
		}

		if(form.password.value!=form.repassword.value){
			alert("两次输入的密码不同");
			form.repassword.focus();
			return false;
		}


		if(form.email.value==""){
			alert("请输入您的Email地址");
			form.email.focus();
			return false;
		}

		var myRegex = /@.*\.[a-z]{2,6}/;
		var email = form.email.value;
			  // email = email.replace(/^ | $/g,"");
			  // email = email.replace(/^\.*|\.*$/g,"");
			  // email = email.toLowerCase();

			  if (!myRegex.test(email)){
			  	alert ("请输入有效的E-MAIL!");
			  	form.email.focus();
			  	return false;
			  }

			  var phone=$('#phone').val();
			  if(phone==""){
			  	alert("请输入手机号");
			  	form.phone.focus();
			  	return false;
			  }

			/*var code=$('#code').val();
			if(code==""){
				alert("请输入验证码");
				return false;
			}*/

			reg = /^[\u4E00-\u9FA5]{2,4}$/;
			// var name=form.real_name.value;
			if(form.real_name.value == ''){  
				alert('请输入姓名');  
				form.real_name.focus();
				return false;  
			}
			if(!reg.test(form.real_name.value)){  
				alert('请您输入正确的姓名');  
				form.real_name.focus();
				return false;  
			}

			var idcard=form.card_no.value;
			if(form.card_no.value==""){
				alert("请输入身份证号，身份证号不能为空");
				form.card_no.focus();
				return false;
			}
		}


	</script>



	<!-- 用于加载js代码 -->
	<!-- 页面footer钩子，一般用于加载插件JS文件和JS代码 -->
	<div class="hidden"><!-- 用于加载统计代码等隐藏元素 -->

	</div>

	<!-- /底部 -->
</body>
</html>
<?php
}