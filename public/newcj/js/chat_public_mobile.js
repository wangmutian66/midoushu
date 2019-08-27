//用于生成uuid
function S4() {
    return (((1+Math.random())*0x10000)|0).toString(16).substring(1);
}
function guid() {
    return (S4()+S4()+"-"+S4()+"-"+S4()+"-"+S4()+"-"+S4()+S4()+S4());
}

var ws =  new WebSocket(Socket_url);

ws.onmessage = function(e){
	var message = JSON.parse(e.data);
	console.log(e);
	switch (message.type){
		case "init":
			var bild = '{"type":"bind","fromid":"'+fromid+'"}';
			ws.send(bild);
			get_name(toid);
			massage_load();
			online = '{"type":"online","toid":"'+toid+'","fromid":"'+fromid+'}';
			ws.send(online);
			say_hello();
			go_bottom();
			changeNoRead();
			autoreply_start();
		break;
		case 'ping':
			ws.send('{"type":"ping"},"fromid":"'+fromid+'"}');
		break;
		case "text":
			if(toid == message.fromid) {
				var log_html = add_message(message);
				$(".chat-content").append(log_html);
			}
			go_bottom();
			changeNoRead();
			autoreply_reset();
			break;
		case "send_file":
			if(fromid == message.toid && toid == message.fromid){
				var log_html = add_message(message);
				ws.send(message);
				$(".chat-content").append(log_html);
				go_bottom();
				changeNoRead();
				autoreply_reset();
			}
			break;
		case 'delete_massage':
			//撤回消息
			var uuid = message.id;
			$("#"+uuid).remove();
			autoreply_reset();
		break;
		case "other_user":
			if(toid == message.fromid){
				showErrorMsg('正在跳转至其他客服..请稍候...');
				setTimeout("location.href='/customer_service/Chat/index/fromid/"+fromid+"/toid/"+message.data+"'","2000"); 
			}
		case 'online':
			if(message.status==1){
				online=1;
				$(".shop-online").text("在线");
			}else{
				online=0;
				$(".shop-online").text("不在线");
			}
		  break;
	}
}

 $(".send-btn").click(function(){
	var uuid = "cms"+guid();
	if (ws.readyState!==1) {
		showErrorMsg('系统繁忙，请刷新页面重新发送信息');
	}
	var text = $(".send-input").val();
	if (text == ''){
		return ;	 
	}

	var message = '{"data":"'+text+'","type":"say","fromid":"'+fromid+'","toid":"'+toid+'","uuid":"'+uuid+'","fromip":"'+fromip+'"}';
	var log_html = add_message(message);
	ws.send(message);
	$(".chat-content").append(log_html);
	save_message(message);
	go_bottom();
	$(".send-input").val("");
	$(".send-input").focus();
})
function save_message(message){
	$.ajax({
		type: "POST",
		dataType:'JSON',
		url: API_URL+"save_message",
		data:{data:message},
		success: function(r){
			if(r.status == 0){
				showErrorMsg('存储信息失败');
			}
		}
	});
}
function changeNoRead(){
    $.post(API_URL+"changeNoRead",{"fromid":fromid,"toid":toid},function(){},'json');
}
function get_name(toid){
	$.post(API_URL + 'getName',{uid:toid},function (r){
		to_name = r;
		$(".shop-titlte").text('与'+to_name + '聊天中...');
	})	
}
function massage_load(){
 //   var arr=[];
	$.post(API_URL + 'massage_load',{fromid:fromid,toid:toid,page:more_page},function (r){
		from_head = r.head_pic.from_head;
		to_head = r.head_pic.to_head;
		var log_html = '';
		$.each(r.list,function(index,content){
			log_html += add_message(content);
		})
		$(".chat-content").append(log_html);
		more_page++;
	},'json');
}


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
			log_html = '<p style="display:block;text-align: center;padding-top: 0.5rem" id="more"><a class="ajax_log">加载更多</a></p>' + log_html;
			this_obj.unbind('click').remove();
			$("#chat_content").prepend(log_html);
			$('.ajax_log').bind('click',ajax_log);
			$('.message').find('span img').bind('click',open_img);
			more_page++;
			console.log(more_page);
		}
		if(r.this_count < 10){
			$('.ajax_log').unbind('click').remove();
			$("#chat_content").prepend('<p style="display:block;text-align: center;padding-top: 0.5rem" id="more"><a class="ajax_log">没有更多了</a></p>');
			return false;
		}
	},'json');
}


function add_message(content){
	if(typeof(content) == 'string'){
		content = JSON.parse(content);
	}
	var details = '';

	if(content.uuid == 'sys_prompt'){
		return '';
	}
	if(content.data == '你好，我正在浏览米豆薯商城'){
		return '';
	}
	if(content.type==3 || content.type == 'send_goods'){
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
	}else if(content.type==2 || content.type == 'send_file'){		//如果是图片
		details = '<img src="'+content.data+'">';
	}else if(content.type == 4 || content.type == 'file'){
		details = "<img src="+STATIC+"/images/h1.png style='height:1rem;' /> <br> <a href=" + content.data+ " target='_blank'>点我下载</a> ";
	}else{
		details = replace_em(content.data);
	}
	var event = 'ontouchstart="gtouchstart($(this))" ontouchmove="gtouchmove()" ontouchend="gtouchend()"';
	if(fromid==content.fromid){
		temp_html = '<div class="chat-text section-right flex" id="'+content.uuid+'" '+event+'>';
		temp_html += '<span class="text"><i class="icon icon-sanjiao3 t-32"></i>';
		temp_html += details;
		temp_html += '</span>';
		temp_html += '<span class="char-img" style="background-image: url('+from_head+')"></span> </div>';
	}else{
		temp_html = '<div class="chat-text section-left flex" id="'+content.uuid+'" '+event+'> ';
		temp_html += '<span class="char-img" style="background-image: url('+to_head+')"></span>';
		temp_html += '<span class="text"><i class="icon icon-sanjiao4 t-32"></i>';
		temp_html += details;
		temp_html += '</span> </div>';		
	}
	return temp_html;
}

var timeOutEvent=0;//定时器   
//开始按   
function gtouchstart(obj){
    timeOutEvent = setTimeout(function (){
		if(typeof(obj.attr('id')) !='undefined'){
			if(obj.attr('id') != 'sys_msg'){
				obj.attr('id')
				if(obj.find('img').length > 0){
					window.open(obj.find('img').attr('src'))
				}else{
					var uuid = obj.attr('id');
					var massage = '{"type":"delete_massage","toid":"'+toid+'","fromid":"'+fromid+'","id":"'+uuid+'"}';
					if(confirm('确定要撤回消息吗?') == true){
						var massage = '{"type":"delete_massage","toid":"'+toid+'","fromid":"'+fromid+'","id":"'+uuid+'"}';
						var res = delete_massage(massage);
						if(res.status == 1){
							ws.send(massage);
							$("#"+uuid).remove();
						}else{
							alert(res.info);
							return false;
						}			
					}
				}
			}else{

			}
		}
	},500);//这里设置定时器，定义长按500毫秒触发长按事件，时间可以自己改，个人感觉500毫秒非常合适   
    return false;   
};   

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
//手释放，如果在500毫秒内就释放，则取消长按事件，此时可以执行onclick应该执行的事件   
function gtouchend(){   
    clearTimeout(timeOutEvent);//清除定时器   
    if(timeOutEvent!=0){   
        //这里写要执行的内容（尤如onclick事件）   
    //    alert("你这是点击，不是长按");   
    }   
    return false;   
};   
//如果手指有移动，则取消所有事件，此时说明用户只是要移动而不是长按   
function gtouchmove(){   
    clearTimeout(timeOutEvent);//清除定时器   
    timeOutEvent = 0;   
};   
   


$('#saytext').ready(function (){
	$('.icon-emoji1').qqFace({
		assign:'saytext',
		path:STATIC +'/qqFace/arclist/'	//表情存放的路径
	});
})
//查看结果
function replace_em(str){
	if(typeof(str) == 'undefined') return '';
    str = str.replace(/\</g,'&lt;');
    str = str.replace(/\>/g,'&gt;');
    str = str.replace(/\n/g,'<br/>');
    str = str.replace('//','/')
    str = str.replace(/\[em_([0-9]*)\]/g,'<img src="'+STATIC+'/qqFace/arclist/$1.gif" border="0" />');
    return str;
}
$(".image_up").click(function(){
    $("#file").click();
})

/* 拉到底部 */
function go_bottom(){
	setTimeout(function(){
		$("#chat_content").scrollTop(1000000);
	},400);
}

function open_img(){
	window.open($(this).attr('src'));
}

function say_hello(){	
	setTimeout(function(){
		var massage = '{"data":"你好，我是'+to_name+',欢迎使用米豆薯客服系统,有什么能帮助您的么？","type":"say","fromid":"'+toid+'","toid":"'+fromid+'","fromip":"'+fromip+'","uuid":"sys_msg"}';
		var log_html = add_message(massage);
		$("#chat_content").append(log_html);
		var default_massage = '{"data":"你好，我正在浏览米豆薯商城","type":"say","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'","uuid":"say_hello"}';
		ws.send(default_massage);
		go_bottom();
	},"1500"); 
}


/*客户长时间不动，退出系统*/
var maxTime = 120; // seconds
var time = maxTime;
var close_max_time = 240;
var close_time = close_max_time;
$('body').on('keydown mousemove mousedown', function(event) {
	var e = event || window.event;
	if (e && e.keyCode == 13) { //回车键的键值为13
		$(".send-btn").click();
		return false;
	}
	time = maxTime; // reset
	close_time = close_max_time; 
});


var close_intervalId = setInterval(function() {
	close_time--;
	if (close_time <= 0) {
		close_html();
		clearInterval(close_intervalId);
	}
}, 1000);

/*自动回复*/
if(typeof(chat_autoreply) == 'number'){
	var autoreply_TIME = chat_autoreply;
}

var autoreply_intervalId;
function autoreply_start(){
	if(typeof(chat_autoreply) == 'number'){
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
	clearInterval(autoreply_intervalId);
	autoreply_TIME = chat_autoreply;
	autoreply_intervalId = setInterval(interval_add, 1000);
}

function autoreply_html(){
//	console.log(leaveReply);
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

/*var intervalId;
function confirm_close_html(){
	time--;
	if (time <= 0) {
		clearInterval(intervalId);
		ShowInvalidLoginMessage();
	}
}
intervalId = setInterval(confirm_close_html, 1000);
function ShowInvalidLoginMessage() {
	if(confirm('您已经很长时间没有说话了，系统将在120秒后自动关闭聊天窗口')){
		var message = '{"data":"客户已经离开了","type":"say","fromid":"'+fromid+'","toid":"'+toid+'","fromip":"'+fromip+'","uuid":"sys_prompt"}';
		save_message(message);
		ws.send(message);
		window.location = 'https://www.midoushu.com';
	}else{
		time = maxTime; // reset
		close_time = close_max_time; 
		intervalId = setInterval(confirm_close_html, 1000);
	}
	
}*/

var eleFile = document.querySelector('#file');

// 压缩图片需要的一些元素和对象
var reader = new FileReader(), img = new Image();

// 选择的文件对象
var file = null;

// 缩放图片需要的canvas
var canvas = document.createElement('canvas');
var context = canvas.getContext('2d');

// base64地址图片加载完毕后
img.onload = function () {
    // 图片原始尺寸
    var originWidth = this.width;
    var originHeight = this.height;
    // 最大尺寸限制
 //   var maxWidth = 2480, maxHeight = 3508;
 	var maxWidth = 1500, maxHeight = 1500;
    // 目标尺寸
    var targetWidth = originWidth, targetHeight = originHeight;
    // 图片尺寸超过400x400的限制
    if (originWidth > maxWidth || originHeight > maxHeight) {
        if (originWidth / originHeight > maxWidth / maxHeight) {
            // 更宽，按照宽度限定尺寸
            targetWidth = maxWidth;
            targetHeight = Math.round(maxWidth * (originHeight / originWidth));
        } else {
            targetHeight = maxHeight;
            targetWidth = Math.round(maxHeight * (originWidth / originHeight));
        }
    }
        
    // canvas对图片进行缩放
    canvas.width = targetWidth;
    canvas.height = targetHeight;
    // 清除画布
    context.clearRect(0, 0, targetWidth, targetHeight);
    // 图片压缩
    context.drawImage(img, 0, 0, targetWidth, targetHeight);
    // canvas转为blob并上传
    canvas.toBlob(function (blob) {

    	var uuid = 'file' + guid();
	    formdata = new FormData();
        var xmlHttp = new XMLHttpRequest();
        formdata.append('file', blob); 
	    formdata.append('fromid',fromid);
	    formdata.append('toid',toid);
	    formdata.append('online',online);
	    formdata.append('uuid',uuid);
	    xmlHttp.open("POST", API_URL+"uploadimgbase", true);
	    xmlHttp.send(formdata);
        // 文件上传成功
        xmlHttp.onreadystatechange = function() {
        	$(".lodding").remove();
            if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
            	var json_html = JSON.parse(xmlHttp.responseText);
            	var message = '{"data":"'+json_html.info+'","fromid":"'+fromid+'","toid":"'+toid+'","type":"send_file","uuid":"'+uuid+'"}';
				var log_html = add_message(message);
				$(".chat-content").append(log_html);
                $("#file").val("");
                ws.send(message);
                go_bottom(); 　
            } else {　　　　　　
                console.log(xmlHttp.statusText);　　　　
            }
            
        };
        // 开始上传
    }, file.type || 'image/png');
};

// 文件base64化，以便获知图片原始尺寸
reader.onload = function(e) {
    img.src = e.target.result;
};
eleFile.addEventListener('change', function (event) {
    file = event.target.files[0];
    // 选择的文件是图片
    if (file.type.indexOf("image") == 0) {
        reader.readAsDataURL(file);    
        $(".chat-content").append('<div class="chat-text section-right flex lodding"><span class="text"><img width="100%" src="'+STATIC+'/images/lodding.gif?v=2"></span> <span class="char-img" style="background-image: url('+from_head+')"></span> </div>');
    	go_bottom();
    }
   
});