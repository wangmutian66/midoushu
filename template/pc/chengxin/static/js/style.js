$(function() {
	$('.containor').on('mouseenter', function() {
		$(".nav_right").removeClass('hide');
	}).on('mouseleave', function() {
		$(".nav_right").addClass('hide');
		$(".sub").addClass('hide');
	}).on('mouseenter', 'li', function(e) {
		var li_data = $(this).attr('data-id');
		$(".sub").addClass('hide');
		$('.sub[data-id="' + li_data + '"]').removeClass('hide');
	})
});

$(function() {
	$('#xianshi').on('mouseenter', function() {
		$(".nav_left").removeClass('hide');
	}).on('mouseleave', function() {
		$(".nav_left").addClass('hide');
		
	})
	$('#xianshi2').on('mouseenter', function() {
		$(".nav_left").removeClass('hide');
	}).on('mouseleave', function() {
		$(".nav_left").addClass('hide');
		
	})
	$('#xianshi3').on('mouseenter', function() {
		$(".nav_left").removeClass('hide');
	}).on('mouseleave', function() {
		$(".nav_left").addClass('hide');
		
	})

})