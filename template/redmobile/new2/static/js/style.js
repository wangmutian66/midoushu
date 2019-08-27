/*
 * Public js
 */
//导航颜色
$(function(){
	var $_header=$('header');
	$(window).scroll(function(){
          var hei = $(window).scrollTop();
   	  	  if(hei > $_header.height()){
			  $_header.addClass('headerbg');
   	  	  }else{
			  $_header.removeClass('headerbg');
   	  	  };
	});
});

//回到顶部
$(function(){
	$("footer .comebackTop").click(function () {
	        var speed=300;//滑动的速度
	        $('body,html').animate({ scrollTop: 0 }, speed);
	        return false;
	});
});

//ajax开始加载前显示loading，加载完后隐藏loading
$(document).ajaxStart(function(){
    $('.loadbefore').show();
}).ajaxStop(function(){
    $('.loadbefore').hide();
})

//底部导航
$(function(){
	$(".footer ul li a").click(function () {
	        $(this).addClass('yello').parent().siblings().find('a').removeClass('yello')
	});
});

//轮播
$(function(){
    $('#slideTpshop').swipeSlide({
        continuousScroll:true,
        speed : 3000,
        transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
        firstCallback : function(i,sum,me){
            me.find('.dot').children().first().addClass('cur');
        },
        callback : function(i,sum,me){
            me.find('.dot').children().eq(i).addClass('cur').siblings().removeClass('cur');
        }
    });
    //圆点
    var ed = $('.mslide ul li').length - 2;
	$('.mslide').append("<div class=" + "dot" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide .dot').append("<span></span>");
	};
	$('.mslide .dot span:first').addClass('cur');
	var wid = - ($('.mslide .dot').width() / 2);
	$('.mslide .dot').css('position','absolute').css('left','50%').css('margin-left',wid);
});


//米豆专区首页轮播
$(function(){
    $('#new_red_ys').swipeSlide({
        continuousScroll:true,
        speed : 3000,
        transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
        firstCallback : function(i,sum,me){
            me.find('.dot_red').children().first().addClass('cur');
        },
        callback : function(i,sum,me){
            me.find('.dot_red').children().eq(i).addClass('cur').siblings().removeClass('cur');
        }
    });
    //圆点
    var ed = $('.mslide.red_ys ul li').length - 2;
	$('.mslide.red_ys').append("<div class=" + "dot_red" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.red_ys .dot_red').append("<span></span>");
	};
	$('.mslide.red_ys .dot_red span:first').addClass('cur');
	var wid = - ($('.mslide.red_ys .dot_red').width() / 2);
	$('.mslide.red_ys .dot_red').css('position','absolute').css('left','50%').css('margin-left',wid);
});


//首页八大品类二级页轮播
$(function(){
	$('#category').swipeSlide({
		continuousScroll:true,
		speed : 3000,
		transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
		firstCallback : function(i,sum,me){
			me.find('.category_dot').children().first().addClass('cur');
		},
		callback : function(i,sum,me){
			me.find('.category_dot').children().eq(i).addClass('cur').siblings().removeClass('cur');
		}
	});
	//圆点
	var ed = $('.mslide.category ul li').length - 2;
	$('.mslide.category').append("<div class=" + "category_dot" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.category .category_dot').append("<span></span>");
	};
	$('.mslide.category .category_dot span:first').addClass('cur');
	var wid = - ($('.mslide.category .category_dot').width() / 2);
	$('.mslide.category .category_dot').css('position','absolute').css('left','50%').css('margin-left',wid);
});


//radio选中
$(function(){
	$('.radio .che').click(function(){
		$(this).toggleClass('check_t');
	})
})
$(function(){
	$('.radio .all').click(function(){
		$(this).siblings().toggleClass('check_t');
	})
})


$(function(){
	//头部菜单
	$('.classreturn .menu a:last').click(function(e){
		$('.tpnavf').toggle();
		e.stopPropagation();
	});
	$('body').click(function(){
		$('.tpnavf').hide();
	});
	//左侧导航
	$('.classlist ul li').click(function(){
		$(this).addClass('red').siblings().removeClass('red');
	});
})

//黑色遮罩层-隐藏
function undercover(){
	$('.mask-filter-div').hide();
}
//黑色遮罩层-显示
function cover(){
	$('.mask-filter-div').show();
}
//action文件导航切换
$(function(){
	$('.paihang-nv ul li').click(function(){
		$(this).addClass('ph').siblings().removeClass('ph');
	})
})
//确认收货和催单
$(function(){
	$('.receipt').click(function(){
		$('.surshko').show();
		cover();
	})
	$('.weiyi a').click(function(){
		$('.surshko').hide();
		undercover();
	})
});
$(function(){
	$('.tuid').click(function(){
		$('.cuidd').show();
		cover();
	})
	$('.weiyi a').click(function(){
		$('.cuidd').hide();
		undercover();
	})
});
/**
 * 留言字数限制
 * tea ：要限制数字的class名
 * nums ：允许输入的最大值
 * zero ：输入时改变数值的ID
 */
function checkfilltextarea(tea,nums){
    var len = $(tea).val().length;
    if(len > nums){
        $(tea).val($(tea).val().substring(0,nums));
    }
    var num = nums - len;
    num <= 0 ? $("#zero").text(0): $("#zero").text(num);  //防止出现负数
}

/**
 * 加减数量
 * n 点击一次要改变多少
 * maxnum 允许的最大数量(库存)
 * number ，input的id
 */
function altergoodsnum(n){
	var num = parseInt($('#number').val());
	var maxnum = parseInt($('#number').attr('max'));
	if(maxnum > 200){
		maxnum = 200;
	}
	num += n;
	num <= 0 ? num = 1 :  num;
	if(num >= maxnum){
		$(this).addClass('no-mins');
		num = maxnum;
	}
	$('#store_count').text(maxnum-num); //更新库存数量
	$('#number').val(num)
}
/**
 * 提示弹窗
 * */
function showErrorMsg(msg){
    layer.open({content:msg,time:2});
}