<?php
/**
* 登录
* by 刘姝含
* 2018/10/30 星期二
**/
error_reporting(0);
session_start();
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
	//$db = new mysql('127.0.0.1', 'mdsgame', 'mdsgame', '7r4GtCE6SAiOE6Dm', '', 'utf8');
//  $db = new mysql('127.0.0.1', 'root', 'root', 'midoushu', '', 'utf8');
  $data = $_POST;
  $username = htmlentities(trim($data['username']));
  $password = htmlentities(trim($data['password']));
  $pwd = md5(md5($password));
  // echo $username.",,,".$pwd;exit();
	// if(empty($username) || empty($password)) {
	// // 	echo '用户名或者密码不能为空';
	//  //	header("Refresh:3;url=login.php");exit();
	// }
  $sql = "select * from tp_game_user where username='$username' and password='$pwd' limit 1";
  $res = $db->query($sql);
  if($php_version>=7){
    $user = mysqli_fetch_array($res);
  }else{
    $user = mysql_fetch_array($res);
  }
  if(!empty($user)) {
		//写session
    $_SESSION["userid"] = $user['id'];
    $_SESSION["username"] = $user['username'];
    $_SESSION["real_name"] = $user['real_name'];
    $_SESSION["phone"] = $user['phone'];
		//is_line
    $user_id = $user['id'];
    $sqlLine = "update tp_game_user set is_line=1 where id=$user_id";
    $db->query($sqlLine);
    echo '<script>alert("登录成功");location.href="index.php";</script>';
    exit();
  } else {
    echo '<script>alert("用户名或者密码错误");history.go(-1);</script>';
    exit();
  }
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

    <header >
      <div class="container">
        <h3>用户登录</h3>
      </div>
    </header>

    <div id="main-container" class="container">
      <div class="row">


        <section>
         <div class="span12">
          <form class="login-form" action="login.php" method="post">
            <div class="control-group">
              <label class="control-label" for="username">用户名</label>
              <div class="controls">
                <input type="text" id="username" class="span3" placeholder="请输入用户名"  ajaxurl="/member/checkUserNameUnique.html" errormsg="请填写1-16位用户名" nullmsg="请填写用户名" datatype="*1-16" value="" name="username">
              </div>
            </div>
            <div class="control-group">
              <label class="control-label" for="inputPassword">密码</label>
              <div class="controls">
                <input type="password" id="inputPassword"  class="span3" placeholder="请输入密码"  errormsg="密码为6-20位" nullmsg="请填写密码" datatype="*6-20" name="password">
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
          </div>-->
          <div class="control-group">
            <div class="controls">
              <label class="checkbox">
                <input type="checkbox"> 自动登陆
              </label>
              <button type="submit" class="btn">登 陆</button>
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

<script type="text/javascript">

    	/*$(document)
	    	.ajaxStart(function(){
	    		$("button:submit").addClass("log-in").attr("disabled", true);
	    	})
	    	.ajaxStop(function(){
	    		$("button:submit").removeClass("log-in").attr("disabled", false);
	    	});


    	$("form").submit(function(){
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
          });*/
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