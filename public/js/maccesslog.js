/*
window.onload=function(){
    var q = "";
    if(document.getElementById("q")){
        q = document.getElementById("q").value;
    }
    var body = document.body;
    var div = document.createElement("div");
    div.id = "mDiv";
    div.style.display="none";
    div.innerHTML = '<iframe src="/mobile/Index/mpublic_log/q/'+q+'" width="0" height="0" scrolling="no" frameborder="0" "></iframe>';
    body.appendChild(div);
}


function show_message(){
    location.href='/Mobile/chat';
}
*/

!window.jQuery && document.write("<script src=\"/template/pc/chengxin/static/js/jquery-1.11.3.min.js\">"+"</script>");

$(function (){
    var q = "";
    if(document.getElementById("q")){
        q = document.getElementById("q").value;
    }
    $.ajax({
          url: "/mobile/Index/mpublic_log",
          type: "GET",
          data: "q="+q,
        //  cache: false,
          success: function(html){
            $("body").append(html);
          }
    });

})