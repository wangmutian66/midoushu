/**
 * 手机号码格式判断
 * @param tel
 * @returns {boolean}
 */
function checkMobile(tel) {
	//var reg = /(^1[3|4|5|7|8][0-9]{9}$)/;
	var reg = /^1[0-9]{10}$/;
	if (reg.test(tel)) {
		return true;
	}else{
		return false;
	};
}
//黑色遮罩层-隐藏
function undercover(){
	$('.mask-filter-div').hide();
}
//黑色遮罩层-显示
function cover(){
	$('.mask-filter-div').show();
}

function btn_del(){
    $('.ed_shdele').show();
    $('.mask-filter-div').show();
};

$(function (){
	$.getJSON('/Home/Api/getProvince',a=1,function (r){
		if(r.status == 1){
			var html = '';
			$.each(r.result,function(i,v){
				html += '<p data-id="'+v.id+'" style="cursor:pointer;" onclick="tk_get_city(\''+v.id+'\')">'+v.name+'</p>';
			})
			$('.province-list').html(html);
		}
	})

	$('.turnoff').click(function(){
        $(this).toggleClass('turnup');
        $("input[name=is_default]").val(Number($(this).hasClass('turnup')));
    });
    $('.ed_shdele .clos').click(function(){
        $('.ed_shdele').hide();
        $('.mask-filter-div').hide();
    });
})

function tk_get_city(id){
	$("input[name='province']").val(id);
	$("#area").html($("p[data-id='"+id+"']").html());
	$.getJSON('/Home/Api/getRegionByParentId',{parent_id:id},function (r){
		$('.province-list').hide();
		if(r.status == 1){
			var html = '';
			$.each(r.result,function(i,v){
				html += '<p data-id="'+v.id+'" style="cursor:pointer;" onclick="tk_get_arealist(\''+v.id+'\')">'+v.name+'</p>';
			})
			$('.city-list').html(html).show();
		}	
	})
}

function tk_get_arealist(id){
	$("input[name='city']").val(id);
	$("#area").append(" "+$("p[data-id='"+id+"']").html());
	$.getJSON('/Home/Api/getRegionByParentId',{parent_id:id},function (r){
		$('.province-list').hide();
		$('.city-list').hide();
		if(r.status == 1){
			var html = '';
			$.each(r.result,function(i,v){
				html += '<p data-id="'+v.id+'" style="cursor:pointer;" onclick="tk_last(\''+v.id+'\')">'+v.name+'</p>';
			})
			$('.area-list').html(html).show();
		}
	})
}
function tk_last(id){
	$("input[name='district']").val(id);
	$("#area").append(" "+$("p[data-id='"+id+"']").html());
	$('.province-list').show();
	$('.city-list').empty().hide();
	$('.area-list').empty().hide();
	$('.container').hide();
	$('body').css('overflow','auto');
	undercover();
}
function tkaddres(){
    $('.container').animate({width: '14.4rem', opacity: 'show'}, 'normal',function(){
        $('.container').show();
    });
    if(!$('.container').is(":hidden")){
        $('body').css('overflow','hidden');
        cover();
        $('.mask-filter-div').css('z-index','9999');
    }
}

function closelocation(){
    var province_div = $('.province-list');
    var city_div = $('.city-list');
    var area_div = $('.area-list');
    if(area_div.is(":hidden") == false){
        area_div.hide();
        city_div.show();
        province_div.hide();
        return;
    }
    if(city_div.is(":hidden") == false){
        area_div.hide();
        city_div.hide();
        province_div.show();
        return;
    }
    if(province_div.is(":hidden") == false){
        area_div.hide();
        city_div.hide();
        $('.container').animate({width: '0', opacity: 'show'}, 'normal',function(){
            $('.container').hide();
        });
        undercover();
        $('.mask-filter-div').css('z-index','inherit');
        return;
    }
}