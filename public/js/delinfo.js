function delcompany(obj,table_name){
    if(confirm("你确定？一定？肯定？要删除此记录？")){
        var delurl = $(obj).attr('data-url');
        var html = "<input type='text' name='verify' id='verify' style='width:50px;'>";
        html += "<img src=\"/index.php?m=Admin&c=Admin&a=vertify\" id=\"imgVerify\" alt=\"\" onclick=\"fleshVerify()\" style='width:80px;height:35px;vertical-align:middle;'>";
        //询问框
        layer.confirm('确认删除', {
            title: '确认删除',
            btn: ['确定', '取消'],
            content: "<div style='text-align: center'>验证码：" + html + "</div>"
        }, function () {
            var verify = $('#verify').val();
            if(verify == ''){
                layer.alert('请输入验证码！', {icon: 2});
                return;
            }
            $.ajax({
                url:delurl,
                type:'POST',
                dataType:'JSON',
                data:{verify:verify,table_name:table_name},
                success:function(info){
                    if(info.status == 0){
                        $(obj).parent().parent().parent().remove();
                    }else{
                        layer.alert(info.msg, {icon: 2});
                    }
                }
            })
            layer.closeAll('dialog');
        }, function () {

        });
    }
}

function fleshVerify(){
    $('#imgVerify').attr('src','/index.php?m=Admin&c=Admin&a=vertify&r='+Math.floor(Math.random()*100));//重载验证码
}