


function company_change(obj){
	var company_id = obj.val();
	$("#store_id").attr("disabled",true).css("background-color","#EEEEEE;");
	$.getJSON('/Admin/Company/ajax_get_store',{company_id:company_id},function (r){
		var html = '<option value="">请选择</option>';
		$("#level_id").html(html);
		var s = 1;
		if(r.status == 1){
			$.each(r.list,function (i,k){
				var selected = '';
				if(k.cid == "{$Think.get.store_id}"){
					selected = " selected"	;
				}
				html += "<option value='"+k.cid+"' "+selected+">"+k.cname+"</option>";
			})
		}
		$("#store_id").html(html).attr("disabled",false).css("background-color","");
	})
}

/*function store_change(option_id){
	option_id=option_id||'level_id';
	var company_id = $("#company_id :selected").val();
	var store_id = $("#store_id :selected").val();
	$("#"+option_id).attr("disabled",true).css("background-color","#EEEEEE;");
	$.getJSON("/Admin/Company/ajax_get_level",{company_id:company_id,store_id:store_id},function (r){
		var html = '<option value="">请选择</option>';
		if(r.status == 1){
			$.each(r.list,function (i,k){
				var selected = '';
				if(k.id == "{$Think.get.level_id}"){
					selected = " selected"	;
				}
				html += "<option value='"+k.id+"' "+selected+">"+k.lv_name+"</option>";
			})
		}
		$("#"+option_id).html(html).attr("disabled",false).css("background-color","");
	})	
}
*/