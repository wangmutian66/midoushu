// JavaScript Document
//var ws =  new WebSocket("wss://www.midoushu.com:8282");
//用于生成uuid
function S4() {
    return (((1+Math.random())*0x10000)|0).toString(16).substring(1);
}
function guid() {
    return (S4()+S4()+"-"+S4()+"-"+S4()+"-"+S4()+"-"+S4()+S4()+S4());
}
var ws;
var lockReconnect = false;//避免重复连接
var wsUrl = Socket_url;
function createWebSocket(url) {
    try {
        ws  =  new WebSocket(wsUrl);
        initEventHandle();
    } catch (e) {
        reconnect(url);
    }
}
function reconnect(url) {
    if(lockReconnect) return;
    lockReconnect = true;
    //没连接上会一直重连，设置延迟避免请求过多
    setTimeout(function () {
        createWebSocket(url);
        lockReconnect = false;
    }, 2000);
}

function initEventHandle() {
    ws.onclose = function (evnt) {
        console.log('websocket服务关闭了');
    //    reconnect(wsUrl);
    };
    ws.onerror = function (evnt) {
        console.log('websocket服务出错了');
   //     reconnect(wsUrl);
    };
    ws.onopen = function (evnt) {
        //心跳检测重置
    //    heartCheck.reset().start();
    };
    ws.onmessage = function (evnt) {
        //拿到任何消息都说明当前连接是正常的
        //接受消息后的UI变化
        var message = eval("("+evnt.data+")");
        doWithMsg(message);
    }

    //收到消息推送
    function doWithMsg(message) {
    //	console.log(message);
		switch (message.type){
			case "init":
				var bild = '{"type":"bind","fromid":"'+fromid+'"}';
				ws.send(bild);
				get_name(toid);
				header_load();
				massage_load();
				online = '{"type":"online","toid":"'+toid+'","fromid":"'+fromid+'"}';
				ws.send(online);
				if(is_user == 1){
					say_hello();
				}
				changeNoRead();
				autoreply_start();
			break;
			case 'text':
			//	console.log(message.data);
				if(fromid == message.toid && toid == message.fromid){
					message_html = add_message(message);
					$("#chat_content").append(message_html);
					Hint();
					go_bottom();
				}
				autoreply_reset();
			break;
			case 'send_file':
				if(fromid == message.toid && toid == message.fromid){
					message_html = add_message(message);
					$("#chat_content").append(message_html);
					Hint();
					go_bottom();
					autoreply_reset();
				}
			break;
			case 'online':
				if(message.status==1){
					online=1;
					$(".shop-online").text("在线");
				}else{
					online=0;
					$(".shop-online").text("不在线");
				}
			break;
			case "send_goods":
				Hint();
				var log_html = add_message(message);
				$("#chat_content").append(log_html);
				go_bottom();
				autoreply_reset();
			break;
			case "other_user":
				if(fromid == message.toid && toid == message.fromid){
					Hint();
					layer.msg('正在跳转至其他客服..请稍候...', {icon: 16 ,shade: 0.01});
					setTimeout(function(){
						location.href='/customer_service/Chat/indexpcuser/fromid/'+fromid+'/toid/'+message.data;
					},2000); 
				}
			case 'delete_massage':
				//撤回消息
				if(fromid == message.toid && toid == message.fromid){
					var uuid = message.id;
					$("#"+uuid).remove();
				}
				autoreply_reset();
			break;	
			case 'ping':
				ws.send('{"type":"ping"},"fromid":"'+fromid+'"}');
			break;
			default:
				message_html = add_message(message);
				$("#chat_content").append(message_html);
				Hint();
				go_bottom();
				autoreply_reset();
		}
		html_bind();
    }

}

$(function(){
	//初始化websocket
    createWebSocket(wsUrl);
	//加载更多聊天记录
	$("#saytext").focus();
	$('.qqface').qqFace({
		assign:'saytext',
		path:STATIC+'/qqFace/arclist/'	//表情存放的路径
	});
	 $(".reply_click").click(function (){
		$("#saytext").text($(this).html());
	})
	/*发送图片*/
	$(".image_up").click(function(){
		$("#file").click();
	})

	//记录ID值
	if(window.localStorage){
        var storage=window.localStorage;
        var chat_data ={
            fromid:fromid,
            toid:toid,
            fromip:fromip
        };
        var chat_data_string = JSON.stringify(chat_data);
        storage.setItem("chat_data",chat_data_string);
    }

	/*发送正常文字类信息*/
	$(".send-btn").click(function(){
		var uuid = "cms"+guid();
		if (ws.readyState!==1) {
			showErrorMsg('系统繁忙，请刷新页面重新发送信息');
		}
		var text = $("#saytext").text();
		if (text == ''){
			return ;	 
		}
		var message_html = '';
		var message = '{"data":"'+text+'","type":"say","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'","uuid":"'+uuid+'"}';
		message_html = add_message(message);
		$("#chat_content").append(message_html);

		ws.send(message);
		save_message(message);	
		go_bottom();
		$("#saytext").text("");
		$("#saytext").focus();
	 })
	/*常见问题*/
	$(".answer").click(function (){
		var text = $("#cq_"+$(this).attr('rel')).val();
		var html = '';
		html = '<div class="message">',
		html +=	'<div class="tx"><img src="'+STATIC+'/img/xt.png"></div>',
		html += '<div class="xx">',
		html +=	'<div class="dp">',
		html +=	'<div class="sj">',
		html +=	'<span>'+nowdate()+'</span>',
		html +=	'</div>',
		html +=	'</div>',
		html +=	'<div class="nr">',
		html +=	'<div class="fd"><img src="'+STATIC+'/pc/images/hf-l.png"></div>',
		html +=	'<span>'+text+'</span>',
		html +=	'</div>',
		html +=	'</div>',
		html +=	'</div>';
		$("#chat_content").append(html);
		go_bottom();
	});
	
	/*发送浏览过的商品*/
	$(".send_goods").bind('click',send_goods)
	/*发送订单号码*/
	$(".send_order_code").click(function (){
		var uuid = "cms"+guid();
		var text = '订单号码：'+$(this).attr('rel');
		var message = '{"data":"'+text+'","type":"say","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'","uuid":"'+uuid+'"}';
		/*console.log(message);
		return false;*/
		var log_html = add_message(message);
		$("#chat_content").append(log_html);
		ws.send(message);
		save_message(message);	
		go_bottom();
	})

	/*监控截图*/
	paseImg();
});


/*请求头像*/
function header_load(){
	$.getJSON(API_URL+'get_head_pic',{fromid:fromid,toid:toid},function(r){
		from_head = r.from_head;
		to_head = r.to_head;
		default_send_goods();
	})
}
/*默认发送商品*/
function default_send_goods(){
	if(typeof(send_goods_id) == 'string' && send_goods_id != ''){
    	$('.default_send_goods').bind('click',send_goods).click();
    }
}
/*发送商品信息*/
function send_goods(){
	var goods_url = $(this).attr('rel_href');
	var goods_img = $(this).attr('img_src');
	var goods_name = $(this).attr('rel_title');
	var goods_price = $(this).attr('rel_price');
	var goods_id = $(this).attr('rel');
	var is_red = $(this).attr('is_red');
	var send_text = '{"goods_url":"'+goods_url+'","goods_img":"'+goods_img+'","goods_name":"'+goods_name+'","goods_price":"'+goods_price+'","is_red":"'+is_red+'"}';
	var message = '{"data":'+send_text+',"type":"send_goods","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'"}';
	var log_html = add_message(message);
	$("#chat_content").append(log_html);
	ws.send(message);
	send_goods_save(send_text);
	go_bottom();
}
/*将信息生成html代码*/
function add_message(content){
	if(typeof(content) == 'string'){
		content = JSON.parse(content);
	}
	//connection_data
//	console.log(content.uuid);
	if(content.uuid == 'connection_data'){
		return '';
	}
	if(is_user == 1 && content.uuid == 'sys_prompt'){
		return '';
	}
	var log_html = '';			//消息主体
	var right_head_pic = '';	//对方的头像
	var left_head_pic = '';		//自己的头像
	var details = '';			//编译后的内容
	var css_left_right = '';	//自己说话的时候带的CSS
	var triangle_img = '';		//三角符号
	var say_time = '';
	if(fromid==content.fromid){	
		//如果是我自己说的话
		var css_left_right = 'ys';
		right_head_pic = '<div class="tx"><img src="'+from_head+'"></div>';
		triangle_img = 'hf-r.png';
	}else{
		left_head_pic = '<div class="tx"><img src="'+to_head+'"></div>';
		triangle_img = 'hf-l.png';
	}

	if(content.type==2 || content.type == 'send_file'){		//如果是图片
		details = '<img style="max-height:100px"  src="'+content.data+'">';
	}else if(content.type==3 || content.type == 'send_goods'){		//如果是商品
		if(typeof(content.data) == 'string'){
			content.data = JSON.parse(content.data);	
		}
		if(content.data.is_red == 1){
			content.data.goods_price = content.data.goods_price / 10 + '米豆';
		}
		details = ['<div class="sp">',
					'<div class="tu"><a href="'+content.data.goods_url+'" target="_blank"><img src="'+content.data.goods_img+'"></a></div>',
					'<div class="wz">',
					'<div class="bt"><a href="'+content.data.goods_url+'" target="_blank">'+content.data.goods_name+'</a></div>',
					'<div class="price"><a href="'+content.data.goods_url+'" target="_blank">¥</a><a target="_blank" href="'+content.data.goods_url+'">'+content.data.goods_price+'</a></div>',
					'</div></div>'].join("");
	}else if(content.type == 4 || content.type == 'file'){		//如果是文件
		details = "<img src="+STATIC+"/images/h1.png style='height:50px;' /> <br> <a href=" + content.data+ " target='_blank'>点我下载</a> ";
	}else{
		//如果是文本
		//console.log(content.data);
		details = replace_em(content.data);
	}
	if(typeof(content.time) == 'undefined'){
		say_time = nowdate();
	}else{
		say_time = mydate(content.time)
	}
	log_html +=  	'<div id="'+content.uuid+'" class="message '+css_left_right+'">',
	log_html += 	left_head_pic;
	log_html += 	'<div class="xx">',
	log_html +=		'<div class="dp">',
	log_html +=		'<div class="sj">',
	log_html +=		'<span>'+say_time+'</span>',
	log_html +=		'</div>',
	log_html +=		'</div>',
	log_html +=		'<div class="nr">',
	log_html +=		'<div class="fd"><img src="'+STATIC+'/pc/images/'+triangle_img+'"></div>',
	log_html +=		'<span>'+details+'</span>',
	log_html +=		'</div>',
	log_html +=		'</div>',
	log_html +=		right_head_pic;
	log_html +=		'</div>';
	return log_html;
}
/*提示声音*/
function Hint(){
	if(is_user == 1){
		$('#chatAudio')[0].play();	
	}
}
 /*上传文件*/   
$("#file").change(function(){
	var uuid = 'file' + guid();
    formdata = new FormData();
    formdata.append('fromid',fromid);
    formdata.append('toid',toid);
    formdata.append('online',online);
    formdata.append('file',$("#file")[0].files[0]);
    formdata.append('uuid',uuid);
    var file_name =  $("#file")[0].files[0]['name'];
    var ext = file_name.toLowerCase().split('.').splice(-1)[0];

  	var img_arr = ["jpg","png","gif","jpeg",'bmp'];
  	var file_arr = ['xls','xlsx','doc','docx'];
  	var file_type = 0;
    if (img_arr.indexOf(ext) >= 0) {
    	file_type = 2;
    }else if(file_arr.indexOf(ext) >=0){
    	file_type = 4;
    }else{
    	showErrorMsg('只能上传图片、Word、Excel');
    	return false;
    }
    $.ajax({
        url:API_URL+"uploadimg",
        type:'POST',
        cache:false,
        data:formdata,
        dataType:'json',
        processData:false,
        contentType:false,
        success:function(data,status,xhr){
            if(data.status=='ok'){
				var message2 = '{"data":"'+data.img_name+'","fromid":"'+fromid+'","toid":"'+toid+'","type":"'+file_type+'","uuid":"'+uuid+'"}';
				var log_html = add_message(message2);
				$("#chat_content").append(log_html);
                $("#file").val("");
                if(file_type == 4){
                	var message = '{"data":"'+data.img_name+'","fromid":"'+fromid+'","toid":"'+toid+'","type":"file","file_type":"'+file_type+'","uuid":"'+uuid+'"}';	
                }else{
                	var message = '{"data":"'+data.img_name+'","fromid":"'+fromid+'","toid":"'+toid+'","type":"send_file","file_type":"'+file_type+'","uuid":"'+uuid+'"}';
                }
                ws.send(message);
                go_bottom();
            }else{
				showErrorMsg(data.status);
            }
        }
    });
})



/*拉倒底部*/
function go_bottom(){
	setTimeout(function(){
		$("#chat_content").scrollTop(1000000);
	},400);
}


//查看结果
function replace_em(str){
	if(typeof(str) == 'undefined') return '';
	str = str.replace(/\</g,'&lt;');
	str = str.replace(/\>/g,'&gt;');
	str = str.replace(/\n/g,'<br/>');
	str = str.replace(/\[em_([0-9]*)\]/g,'<img src="'+STATIC+'/qqFace/arclist/$1.gif" border="0" />');
	return str;
}


function changeNoRead(){
	$.post(
		API_URL+"changeNoRead",
		{"fromid":fromid,"toid":toid},
		function(){},'json'
	)
}
function get_name(toid){
	$.post(API_URL + 'getName',{uid:toid},function (r){
		toname = r;
		$(".shop-titlte").text('与'+toname + '聊天中...');
	})	
}
/*存储商品信息*/
function send_goods_save(message){
	$.ajax({
	   type: "POST",
	   dataType:'JSON',
	   url: API_URL+"send_goods_save",
	   data:{fromid:fromid,toid:toid,fromip:fromip,data:message},
	   success: function(r){
		//	console.log(r);
			if(r.status == 0){
				showErrorMsg('系统繁忙，请刷新页面重新发送信息');
			}
	   }
	});
}
/*保存文本信息*/
function save_message(message){
	$.ajax({
		type: "POST",
		dataType:'JSON',
		url: API_URL+"save_message",
		data:{data:message},
		success: function(r){
			if(r.status == 0){
				showErrorMsg('系统繁忙，请刷新页面重新发送信息');
			}
		}
	});
}


/**
 *根据时间戳格式化为日期形式
 */
function mydate(nS){
	return new Date(parseInt(nS) * 1000).toLocaleString().replace(/年|月/g, "-").replace(/日/g, " ");
}

function nowdate(){
	return new Date().toLocaleString().replace(/年|月/g, "-").replace(/日/g, " ")	;
}




/*转接其他客服*/

$(".go_other_chat").click(function (){
	var url = "/customer_service/chat/other_chat";
	layer.open({
		type: 2,
		shadeClose: true,
		shade: 0.8,
		area: ['900px', '600px'], //宽高
		content: url
	});
})

function ohter_user_call_back(id,nickname){
	var user_id = id ;
	var message = '{"data":"'+user_id+'","type":"other_user","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'"}';
	if(window.confirm('你确定让客户要跳转到'+nickname+'那里么？')){
		ws.send(message);
		ws.onclose();
	    $("#saytext").html('该用户已转接到其他客服，无法发送消息');
		$("#saytext").attr('contenteditable',false);
	}
    layer.closeAll();
    
}	

/*查看聊天记录*/
$("#see_log").click(function (){
	var url = "/customer_service/chat/chat_logs/fromid/"+fromid+"/toid/"+toid;
	layer.open({
	  type: 2,
	  shadeClose: true,
      shade: 0.8,
	  area: ['900px', '600px'], //宽高
	  content: url
	});
});

/*
	监听剪切板
*/
function paseImg()
{
	var imgReader = function (item) {
		var blob = item.getAsFile(),
			reader = new FileReader();
		reader.onloadend = function (e) {
			//显示图像
		/*	var msg = "<img src='"+e.target.result+"' style='max-width:60%;max-height:250px;'/>";
			$('body').append(msg)*/
	//		$("#file_base64").val();
			upload_tk_img(e.target.result);
		};

		reader.readAsDataURL(blob);
	};


	function upload_tk_img(s){
		var uuid = "cms"+guid();
	    $.ajax({
	        url:API_URL+"uploadimgbase64",
	        type:'POST',
	        cache:false,
	        data:{img_src:s,fromid:fromid,toid:toid,uuid:uuid},
	        dataType:'json',
	        success:function(r){
	        //	console.log(r);
	            if(r.status==1){
	            	var message2 = '{"data":"'+r.info+'","fromid":"'+fromid+'","toid":"'+toid+'","type":"2","uuid":"'+uuid+'"}';
					var message2 = JSON.parse(message2);
					var log_html = add_message(message2);
					$("#chat_content").append(log_html);
	                go_bottom();
					var message = '{"data":"'+r.info+'","fromid":"'+fromid+'","toid":"'+toid+'","type":"send_file","uuid":"'+uuid+'"}';
	                $("#file").val("");
	                ws.send(message);
	            }else{
					showErrorMsg(data.status);
	            }
	        }
	    });
	}


	document.getElementById("saytext").addEventListener("paste",function(e){
	  var clipboardData = e.clipboardData,
		i = 0,
		items, item, types;
		if (clipboardData) {
		  items = clipboardData.items;
		  if (!items) {
			  return;
		  }
		  item = items[0];
		  types = clipboardData.types || [];

		  for (; i < types.length; i++) {
			  if (types[i] === 'Files') {
				  item = items[i];
				  break;
			  }
		  }
		  if (item && item.kind === 'file' && item.type.match(/^image\//i)) {
			  imgReader(item);
		  }
		}
	});
}




/*页面加载记录*/
 function massage_load(){
	$.post(API_URL + 'massage_load',{fromid:fromid,toid:toid,page:more_page},function (r){
		from_head = r.head_pic.from_head;
		to_head = r.head_pic.to_head;
		var log_html = '';
		$.each(r.list,function(index,content){	
			log_html += add_message(content);
		})
		$("#chat_content").append(log_html);
		more_page++;

		var li_html = '';
		$.each(r.record_log,function(k,v){
			li_html += '<li><a href="'+v.al_url+'" target="_blank">'+v.al_url+'</a></li>';
		})
		$('.gjul').html(li_html);

		go_bottom();
	},'json');
}
/*

console.log(message.fromip);
if(typeof(message.fromip) != 'undefined'){
	record_initialization(message.fromip);
}
*/

$('.ajax_log').bind('click',ajax_log);
/*获取更多日志*/
function ajax_log(){
	var this_obj = $(this);
	$.post(API_URL + 'massage_load',{fromid:fromid,toid:toid,page:more_page},function (r){
		var log_html = '';
		$.each(r.list,function(index,content){	
			log_html += add_message(content);
		})
		if(log_html != ''){
			log_html = '<a href="JavaScript:;" class="jz ajax_log">加载更多...</a>' + log_html;
			this_obj.unbind('click').remove();
			$("#chat_content").prepend(log_html);
			$('.ajax_log').bind('click',ajax_log);
			$('.message').find('span img').bind('click',open_img);
			more_page++;
		}
		if(r.this_count < 10){
			$('.ajax_log').unbind('click').remove();
			$("#chat_content").prepend("<div class='jz'>没有更多了</div>");
			return false;
		}
	},'json');
}



/*右键撤回消息*/
function right_click(e){
	if(e.which == 3){
		var uuid = $(this).attr("id");
		if(confirm('确定要撤回消息吗?') == true){
			var massage = '{"type":"delete_massage","toid":"'+toid+'","fromid":"'+fromid+'","id":"'+uuid+'"}';
			var res = delete_massage(massage);
		//	layer.closeAll();
			if(res.status == 1){
				ws.send(massage);
				$("#"+uuid).remove();
			}else{
				alert(res.info);
				return false;
			}			
		}
		
	}
	
}

function delete_massage(massage){
//	console.log(massage);
	var res = '';
	$.ajax({
		type: "POST",
		async:false,
		url: API_URL+"delete_massage",
		data:"data="+ massage,
		dataType:'json',
		success: function(r){
			res = r;
		}
	});
	return res;
}

$('body').ajaxComplete(function() { 
	html_bind();
	
})
function html_bind(){
	$('.message').find('span img').unbind('click');
	$('.message').find('span img').bind('click',open_img);
	$('.message.ys').unbind('mousedown');
	$('.message.ys').bind('mousedown',right_click);
}

document.oncontextmenu=function(ev){
    return false;    //屏蔽右键菜单
}


function open_img(){
	window.open($(this).attr('src'));
}


/*客户长时间不动，退出系统*/
var maxTime = 120; // seconds
var time = maxTime;
var close_max_time = 240;
var close_time = close_max_time;
//如果需要自动回复


$('body').on('keydown mousemove mousedown', function(event) {
	var e = event || window.event;
	if (e && e.keyCode == 13) { //回车键的键值为13
		$(".send-btn").click();
		return false;
	}
	time = maxTime; // reset
	close_time = close_max_time; 

});
if(is_user == 1){
	var intervalId = setInterval(function() {
		time--;
		if (time <= 0) {
			ShowInvalidLoginMessage();
			clearInterval(intervalId);
		}
	}, 1000);
	var close_intervalId = setInterval(function() {
		close_time--;
		if (close_time <= 0) {
			close_html();
			clearInterval(close_intervalId);
		}
	}, 1000);
}


/*自动回复*/
if(typeof(chat_autoreply) == 'number'){
	var autoreply_TIME = chat_autoreply;
}
var autoreply_intervalId;
function autoreply_start(){
	if(typeof(chat_autoreply) == 'number' && (is_user == 1)){
		autoreply_intervalId = setInterval(interval_add, 1000);
	}
}

function interval_add(){
	autoreply_TIME--;
	if (autoreply_TIME <= 0) {
		autoreply_html();
		clearInterval(autoreply_intervalId);
	}	
}
function autoreply_reset(){
	if(is_user == 1){
		clearInterval(autoreply_intervalId);
		autoreply_TIME = chat_autoreply;
		autoreply_intervalId = setInterval(interval_add, 1000);
	}
}
function autoreply_html(){
	var log_html =	'';
	var massage = '{"data":"'+leaveReply+'","type":"say","fromid":"'+toid+'","toid":"'+fromid+'","fromip":"'+fromip+'","uuid":"sys_msg"}';
	var log_html = add_message(massage);
	$("#chat_content").append(log_html);
	go_bottom();
}

function close_html(){
	//直接关闭页面
	var message = '{"data":"客户已经离开了","type":"say","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'","uuid":"sys_prompt"}';
	save_message(message);
	ws.send(message);
	window.close();
}
function ShowInvalidLoginMessage() {
	layer.confirm('您已经很长时间没有说话了，系统将在120秒后自动关闭聊天窗口', {
		btn: ['关闭页面','忽略消息'] //按钮
	}, function(){
		var message = '{"data":"客户已经离开了","type":"say","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'","uuid":"sys_prompt"}';
		save_message(message);
		ws.send(message);
		window.close();
	}, function(){
		time = maxTime; // reset
		close_time = close_max_time; 
	});
}

function say_hello(){	
	setTimeout(function(){
		var massage = '{"data":"你好，我是'+toname+',欢迎使用米豆薯客服系统,有什么能帮助您的么？","type":"say","fromid":"'+toid+'","toid":"'+fromid+'","fromip":"'+fromip+'","uuid":"sys_msg"}';
		var log_html = add_message(massage);
		$("#chat_content").append(log_html);
		var default_massage = '{"data":"我正在浏览米豆薯商城","type":"say","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'","uuid":"say_hello"}';
		ws.send(default_massage);
	//	save_message(default_massage);
		go_bottom();
	},"1000");
}
