//var _hmt = _hmt || []; (function() { var hm = document.createElement("script"); hm.src = "https://hm.baidu.com/hm.js?9aa13fb437fbb00412ba2c39e9b63905"; var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(hm, s); })();

!window.jQuery && document.write("<script src=\"/template/pc/chengxin/static/js/jquery-1.11.3.min.js\">"+"</script>");


$(function (){
    var from_id = 0;
    var to_id = 0;
    if(window.localStorage){
        var storage=window.localStorage;
        console.log(typeof(storage.chat_data) )
        if (typeof(storage.chat_data) != 'undefined'){
            var storage_chat_data = JSON.parse(storage.chat_data);
            from_id = storage_chat_data.fromid;
            to_id = storage_chat_data.toid;
        }
        /*var data={
            name:'xiecanyong',
            sex:'man',
            hobby:'program'
        };*/
        //storage.removeItem("a");
    //  var d = JSON.stringify(data);
    //  storage.setItem("data",d);
    //    console.log(storage.data);
    //  var storage=window.localStorage;
    //  storage.user_id = 6
    //  storage.setItem("user_id",999);
    //storage.removeItem("a");
    //  storage.clear();
    //  console.log(storage.user_id);
    }

    $.ajax({
      url: "/home/Api/public_log",
      type: "GET",
      data: "from_id="+from_id+"&to_id="+to_id,
    //  cache: false,
      success: function(html){
        $("body").append(html);
      }
    });
})

function show_message(){
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
