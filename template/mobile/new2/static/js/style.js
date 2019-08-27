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
    var ed = $('.mslide.gd-01 ul li').length - 2;
	$('.mslide.gd-01').append("<div class=" + "dot" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.gd-01 .dot').append("<span></span>");
	};
	$('.mslide.gd-01 .dot span:first').addClass('cur');
	var wid = - ($('.mslide.gd-01 .dot').width() / 2);
	$('.mslide.gd-01 .dot').css('position','absolute').css('left','50%').css('margin-left',wid);
});

//轮播
$(function(){
    $('#slideTpshop1').swipeSlide({
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
    var ed = $('.mslide.zygd ul li').length - 2;
	$('.mslide.zygd').append("<div class=" + "dot" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.zygd .dot').append("<span></span>");
	};
	$('.mslide.zygd .dot span:first').addClass('cur');
	var wid = - ($('.mslide.zygd .dot').width() / 2);
	$('.mslide.zygd .dot').css('position','absolute').css('left','50%').css('margin-left',wid);
});

//天天特惠轮播
$(function(){
    $('#slideTpshop2').swipeSlide({
        continuousScroll:true,
        speed : 3000,
        transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
        firstCallback : function(i,sum,me){
            me.find('.dot2').children().first().addClass('cur');
        },
        callback : function(i,sum,me){
            me.find('.dot2').children().eq(i).addClass('cur').siblings().removeClass('cur');
        }
    });
    //圆点
    var ed = $('.mslide.ttth ul li').length - 2;
	$('.mslide.ttth').append("<div class=" + "dot2" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.ttth .dot2').append("<span></span>");
	};
	$('.mslide.ttth .dot2 span:first').addClass('cur');
	var wid = - ($('.mslide.ttth .dot2').width() / 2);
	$('.mslide.ttth .dot2').css('position','absolute').css('left','50%').css('margin-left',wid);
});

//现金轮播
$(function(){
    $('#slideTpshop3').swipeSlide({
        continuousScroll:true,
        speed : 3000,
        transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
        firstCallback : function(i,sum,me){
            me.find('.dot3').children().first().addClass('cur');
        },
        callback : function(i,sum,me){
            me.find('.dot3').children().eq(i).addClass('cur').siblings().removeClass('cur');
        }
    });
    //圆点
    var ed = $('.mslide.xjzq ul li').length - 2;
	$('.mslide.xjzq').append("<div class=" + "dot3" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.xjzq .dot3').append("<span></span>");
	};
	$('.mslide.xjzq .dot3 span:first').addClass('cur');
	var wid = - ($('.mslide.xjzq .dot3').width() / 2);
	$('.mslide.xjzq .dot3').css('position','absolute').css('left','50%').css('margin-left',wid);
});

//米豆轮播
$(function(){
    $('#slideTpshop4').swipeSlide({
        continuousScroll:true,
        speed : 3000,
        transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
        firstCallback : function(i,sum,me){
            me.find('.dot4').children().first().addClass('cur');
        },
        callback : function(i,sum,me){
            me.find('.dot4').children().eq(i).addClass('cur').siblings().removeClass('cur');
        }
    });
    //圆点
    var ed = $('.mslide.mdzq ul li').length - 2;
	$('.mslide.mdzq').append("<div class=" + "dot4" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.mdzq .dot4').append("<span></span>");
	};
	$('.mslide.mdzq .dot4 span:first').addClass('cur');
	var wid = - ($('.mslide.mdzq .dot4').width() / 2);
	$('.mslide.mdzq .dot4').css('position','absolute').css('left','50%').css('margin-left',wid);
});

//福利轮播
$(function(){
    $('#slideTpshop5').swipeSlide({
        continuousScroll:true,
        speed : 3000,
        transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
        firstCallback : function(i,sum,me){
            me.find('.dot5').children().first().addClass('cur');
        },
        callback : function(i,sum,me){
            me.find('.dot5').children().eq(i).addClass('cur').siblings().removeClass('cur');
        }
    });
    //圆点
    var ed = $('.mslide.flzq ul li').length - 2;
	$('.mslide.flzq').append("<div class=" + "dot5" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.flzq .dot5').append("<span></span>");
	};
	$('.mslide.flzq .dot5 span:first').addClass('cur');
	var wid = - ($('.mslide.flzq .dot5').width() / 2);
	$('.mslide.flzq .dot5').css('position','absolute').css('left','50%').css('margin-left',wid);
});

//现金专区首页轮播
$(function(){
    $('#slideTpshop_cash').swipeSlide({
        continuousScroll:true,
        speed : 3000,
        transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
        firstCallback : function(i,sum,me){
            me.find('.dot_cash').children().first().addClass('cur');
        },
        callback : function(i,sum,me){
            me.find('.dot_cash').children().eq(i).addClass('cur').siblings().removeClass('cur');
        }
    });
    //圆点
    var ed = $('.mslide.cash_ys ul li').length - 2;
	$('.mslide.cash_ys').append("<div class=" + "dot_cash" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.cash_ys .dot_cash').append("<span></span>");
	};
	$('.mslide.cash_ys .dot_cash span:first').addClass('cur');
	var wid = - ($('.mslide.cash_ys .dot_cash').width() / 2);
	$('.mslide.cash_ys .dot_cash').css('position','absolute').css('left','50%').css('margin-left',wid);
});

//专题精选
$(function(){
    $('#slideTpshop-pro').swipeSlide({
        continuousScroll:true,
        speed : 3000,
        transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
        firstCallback : function(i,sum,me){
            me.find('.xq_dot').children().first().addClass('cur');
        },
        callback : function(i,sum,me){
            me.find('.xq_dot').children().eq(i).addClass('cur').siblings().removeClass('cur');
        }
    });
    //圆点
    var ed = $('.mslide.xq_banner ul li').length - 2;
	$('.mslide.xq_banner').append("<div class=" + "xq_dot" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.xq_banner .xq_dot').append("<span></span>");
	};
	$('.mslide.xq_banner .xq_dot span:first').addClass('cur');
	var wid = - ($('.mslide.xq_banner .xq_dot').width() / 2);
	$('.mslide.xq_banner .xq_dot').css('position','absolute').css('left','50%').css('margin-left',wid);
});

//每日一淘二级页轮播
$(function(){
	$('#mryt').swipeSlide({
		continuousScroll:true,
		speed : 3000,
		transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
		firstCallback : function(i,sum,me){
			me.find('.mryt_dot').children().first().addClass('cur');
		},
		callback : function(i,sum,me){
			me.find('.mryt_dot').children().eq(i).addClass('cur').siblings().removeClass('cur');
		}
	});
	//圆点
	var ed = $('.mslide.mryt ul li').length - 2;
	$('.mslide.mryt').append("<div class=" + "mryt_dot" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.mryt .mryt_dot').append("<span></span>");
	};
	$('.mslide.mryt .mryt_dot span:first').addClass('cur');
	var wid = - ($('.mslide.mryt .mryt_dot').width() / 2);
	$('.mslide.mryt .mryt_dot').css('position','absolute').css('left','50%').css('margin-left',wid);
});




//新品推荐二级页轮播
$(function(){
	$('#xptj').swipeSlide({
		continuousScroll:true,
		speed : 3000,
		transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
		firstCallback : function(i,sum,me){
			me.find('.xptj_dot').children().first().addClass('cur');
		},
		callback : function(i,sum,me){
			me.find('.xptj_dot').children().eq(i).addClass('cur').siblings().removeClass('cur');
		}
	});
	//圆点
	var ed = $('.mslide.xptj ul li').length - 2;
	$('.mslide.xptj').append("<div class=" + "xptj_dot" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.xptj .xptj_dot').append("<span></span>");
	};
	$('.mslide.xptj .xptj_dot span:first').addClass('cur');
	var wid = - ($('.mslide.xptj .xptj_dot').width() / 2);
	$('.mslide.xptj .xptj_dot').css('position','absolute').css('left','50%').css('margin-left',wid);
});


//特卖专区二级页轮播
$(function(){
	$('#tmzq').swipeSlide({
		continuousScroll:true,
		speed : 3000,
		transitionType : 'cubic-bezier(0.22, 0.69, 0.72, 0.88)',
		firstCallback : function(i,sum,me){
			me.find('.tmzq_dot').children().first().addClass('cur');
		},
		callback : function(i,sum,me){
			me.find('.tmzq_dot').children().eq(i).addClass('cur').siblings().removeClass('cur');
		}
	});
	//圆点
	var ed = $('.mslide.tmzq ul li').length - 2;
	$('.mslide.tmzq').append("<div class=" + "tmzq_dot" + "></div>");
	for(var i = 0; i<ed ;i++){
		$('.mslide.tmzq .tmzq_dot').append("<span></span>");
	};
	$('.mslide.tmzq .tmzq_dot span:first').addClass('cur');
	var wid = - ($('.mslide.tmzq .tmzq_dot').width() / 2);
	$('.mslide.tmzq .tmzq_dot').css('position','absolute').css('left','50%').css('margin-left',wid);
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
});

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
function checkfilltextarea(tea,nums,k){

    var len = $(tea).val().length;
    if(len > nums){
        $(tea).val($(tea).val().substring(0,nums));
	}
	var num = nums - len;
    num <= 0 ? $("#zero"+k).text(0): $("#zero"+k).text(num);  //防止出现负数
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







