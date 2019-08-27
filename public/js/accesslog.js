
!window.jQuery && document.write("<script src=\"/template/pc/chengxin/static/js/jquery-1.11.3.min.js\">"+"</scr"+"ipt>");

document.write("<div style='display: none;'>")
document.write('<iframe src="/home/Api/public_log" width="0" height="0" scrolling="no" frameborder="0" onload="plog(this)"></iframe>')
document.write("</div>")
function plog(element){
    var body = document.body;
    var div = document.createElement("div");
    div.id = "mDiv";
    div.innerHTML = element.contentWindow.document.body.innerHTML;
    body.appendChild(div);

    // $(document.body).append($(element).contents().find("body").html());
}
function show_message(){
    // document.getElementsByClassName('message')[0].style.display='inline-block';

    // document.getElementsByClassName('message')[0].style.bottom=0;
    $(".message").show().animate({
        "bottom":"0px"
    },500);
}


function sendChatMessage(){
    var lyname = $("#lyname").val();
    var mobile = $("#mobile").val();
    var content = $("#content").val();


    if(lyname == ""){
        layer.alert("请填写姓名",{icon:2});
        return false;
    }

    if(mobile == ""){
        layer.alert("请填写手机号",{icon:2});
        return false;
    }

    if(!isPoneAvailable(mobile)){

        layer.alert("请填写正确手机号",{icon:2});
        return false;
    }

    if(content == ""){
        layer.alert("请填写留言内容",{icon:2});
        return false;
    }

    var param = new Object();
    param['lyname'] = lyname;
    param['mobile'] = mobile;
    param['content'] = content;

    $.ajax({
        url:"/home/Api/sendChatMessage",
        data:param,
        dataType:"json",
        type:"post",
        success:function(data){
            if(data == 1){
                $(".message").hide().css({
                    "bottom":"-400px"
                });
                layer.alert("留言成功！",{icon:1});
            }
        }

    });

}

function isPoneAvailable(str) {
    var myreg=/^[1][3,4,5,7,8][0-9]{9}$/;
    if (!myreg.test(str)) {
        return false;
    } else {
        return true;
    }
}
