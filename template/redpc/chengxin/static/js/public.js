if ( $.browser.msie ){             
    if ( $.browser.version == "6.0" ){               
        document.execCommand("BackgroundImageCache", false, true);   
    }   
}
;(function($){
    $.extend({
        /*"tab":function(elements,e){
			var ev = (!e) ? "mouseover" : "click";
		    elements.bind(ev,t);			
			function t(){
				var $my = $(this);
		        if($my.is(".on")) return false;
		        elements.each(function(){
		            $(this).removeClass("on");
			        $(this).next().fadeOut("fast");
		        });
	            $my.addClass('on');
		        $my.next().fadeIn("fast");
			}
		},*/
		"tab":function(elements,e){
			var ev = (!e) ? "mouseover" : "click";
			if(ev == "mouseover"){
				elements.hoverIntent(t,function(){});
			}else{
				elements.live(ev,t);
			}		   		
			function t(){
				var $my = $(this);
		        if($my.is(".on")) return false;
		        elements.each(function(){
		            $(this).removeClass("on");
			        $(this).next().fadeOut("fast");
		        });
	            $my.addClass('on');
		        $my.next().fadeIn("fast");
			}
		},
		"stab": function(elements,e){
			var ev = (!e) ? "mouseover" : "click";
		    elements.live(ev,b);
			function b(){
			    elements.removeClass("leftline");
                var index = elements.index(this);
				if(index > 0) elements.slice(0,index).addClass("leftline");
			}
		},
		"itab" : function(elements,e){
			var ev = (!e) ? "mouseover" : "click";
		    elements.live(ev,b);
			function b(){
				var $my = $(this);
		        if($my.is(".on")) return false;
		        elements.each(function(){
		            $(this).removeClass("on");			        
		        });
	            $my.addClass('on');
				var url = $my.attr("url");
				if(url) $("#frame_content").attr("src",url);
			}
		}
	});
	$.fn.extend({
	    "tab":function(e){			
		    $.tab($(this),e);
			return $(this);
		},
		"stab" : function(e){
			$.stab($(this).tab(e),e);
			return $(this);
		},
		"itab" : function(e){
			$.itab($(this),e);
			return $(this);
		}
	});
})(jQuery);


;(function($){    
	$.fn.extend({
	    "scrolltop":function(time,num){
			time = (!time && isNaN(time)) ? 4000 : time;
			num = (!num && isNaN(num)) ? 2 : num;
			var scrollTimer1;					
			$(this).each(function(){										
				var $self = $(this);
				$(this).hover(function(){			
	        		window.clearInterval(scrollTimer1);
	    		},function(){		    		
					scrollTimer1 = window.setInterval(function(){
		    		scrollNews($self);
				},time);			
        		}).trigger("mouseleave");
			});
			
			function scrollNews(obj){				
				var $self = obj.find("ul:first");
				var length = $self.find("li").length;
				var mod = length % 2;			
				var lineheight = $self.find("li:first").attr("offsetHeight");
				$self.animate({"marginTop":-lineheight+"px"},600,function(){					
		    		$lis = $self.css("margin-top","0px").find("li:lt("+num+")").appendTo($self);
					if(num == 2 && mod > 0){
						$lis.first().css("float","right");
						$lis.last().css("float","left");
					}
				});
			}    
		}
	});
})(jQuery);

;(function($){
	/* hoverIntent by Brian Cherne */
	$.fn.hoverIntent = function(f,g) {
		// default configuration options
		var cfg = {
			sensitivity: 7,
			interval: 100,
			timeout: 0
		};
		// override configuration options with user supplied object
		cfg = $.extend(cfg, g ? { over: f, out: g } : f );

		// instantiate variables
		// cX, cY = current X and Y position of mouse, updated by mousemove event
		// pX, pY = previous X and Y position of mouse, set by mouseover and polling interval
		var cX, cY, pX, pY;

		// A private function for getting mouse position
		var track = function(ev) {
			cX = ev.pageX;
			cY = ev.pageY;
		};

		// A private function for comparing current and previous mouse position
		var compare = function(ev,ob) {
			ob.hoverIntent_t = clearTimeout(ob.hoverIntent_t);
			// compare mouse positions to see if they've crossed the threshold
			if ( ( Math.abs(pX-cX) + Math.abs(pY-cY) ) < cfg.sensitivity ) {
				$(ob).unbind("mousemove",track);
				// set hoverIntent state to true (so mouseOut can be called)
				ob.hoverIntent_s = 1;
				return cfg.over.apply(ob,[ev]);
			} else {
				// set previous coordinates for next time
				pX = cX; pY = cY;
				// use self-calling timeout, guarantees intervals are spaced out properly (avoids JavaScript timer bugs)
				ob.hoverIntent_t = setTimeout( function(){compare(ev, ob);} , cfg.interval );
			}
		};

		// A private function for delaying the mouseOut function
		var delay = function(ev,ob) {
			ob.hoverIntent_t = clearTimeout(ob.hoverIntent_t);
			ob.hoverIntent_s = 0;
			return cfg.out.apply(ob,[ev]);
		};

		// A private function for handling mouse 'hovering'
		var handleHover = function(e) {
			// next three lines copied from jQuery.hover, ignore children onMouseOver/onMouseOut
			var p = (e.type == "mouseover" ? e.fromElement : e.toElement) || e.relatedTarget;
			while ( p && p != this ) { try { p = p.parentNode; } catch(e) { p = this; } }
			if ( p == this ) { return false; }

			// copy objects to be passed into t (required for event object to be passed in IE)
			var ev = jQuery.extend({},e);
			var ob = this;

			// cancel hoverIntent timer if it exists
			if (ob.hoverIntent_t) { ob.hoverIntent_t = clearTimeout(ob.hoverIntent_t); }

			// else e.type == "onmouseover"
			if (e.type == "mouseover") {
				// set "previous" X and Y position based on initial entry point
				pX = ev.pageX; pY = ev.pageY;
				// update "current" X and Y position based on mousemove
				$(ob).bind("mousemove",track);
				// start polling interval (self-calling timeout) to compare mouse coordinates over time
				if (ob.hoverIntent_s != 1) { ob.hoverIntent_t = setTimeout( function(){compare(ev,ob);} , cfg.interval );}

			// else e.type == "onmouseout"
			} else {
				// unbind expensive mousemove event
				$(ob).unbind("mousemove",track);
				// if hoverIntent state is true, then call the mouseOut function after the specified delay
				if (ob.hoverIntent_s == 1) { ob.hoverIntent_t = setTimeout( function(){delay(ev,ob);} , cfg.timeout );}
			}
		};

		// bind the function to the two event listeners
		return this.mouseover(handleHover).mouseout(handleHover);
	};
	
})(jQuery);

;(function($) { 
	$.fn.wwtip = function(options) {		
    	var opts = $.extend({}, $.fn.wwtip.defaults, options);
		var hovertime,$tip,outtime,title;
		if($("."+opts.tipclass).length==0){
			var $tip = $("<div class=\""+opts.tipclass+"\" style=\"position:absolute; display:none;\"></div>");
		    $("body").append($tip);
		}else{
			$tip = $("."+opts.tipclass);
		}
    	return this.each(function() {
			title  		= $(this).attr("title");
			$(this).removeAttr("title");					
			$(this).hover(function(){							
				var offset,h,w,l,t,h1,w1		
				w1			= $tip.outerWidth(true);
				h1			= $tip.outerHeight(true);			
				offset 		= $(this).offset();
				w 			= $(this).outerWidth(true);
				h 			= $(this).outerHeight(true);
				switch(opts.position){
					case "centerdown":
						l           = parseInt(offset.left - ((w1/2) - (w/2)));
						t           = offset.top + h;
					break;
					case "centerup":
						l           = parseInt(offset.left - ((w1/2) - (w/2)));
						t           = offset.top-h1;
					break;
					default:
					break;
				}								
				$tip.css({
					left : l+"px",
					top  : t+"px"
				});				
						
				if(title){
					$(this).removeAttr("title");				
					opts.content = title;									
				}else{
					if(opts.content) opts.content.css("display","block");					
				}
				if(!opts.content) return;
				$tip.html(opts.content);
				if(hovertime) clearTimeout(hovertime);
				if(outtime) clearTimeout(outtime);
				if($tip.is(":hidden")) outtime = setTimeout(function(){$tip.show();},opts.time);
			},function(){
				//if(title) $(this).attr("title",title);
				if(outtime) clearTimeout(outtime);				
				if($tip.is(":visible")) hovertime = setTimeout(function(){$tip.hide();},opts.time);
			});
			$tip.hover(function(){
				if(outtime) clearTimeout(outtime);				
				if(hovertime) clearTimeout(hovertime);
			    if($tip.is(":hidden")) $(this).show();	
			},function(){
				if($tip.is(":visible")) $(this).hide();
			});			
    	});    
	}; 
	// 插件的defaults    
	$.fn.wwtip.defaults = {    
		tipclass	: 		"tipsmalldown",   
		time 		: 		200,
		content     :       "",
		position    :       "centerdown"  
	};    
	// 闭包结束    
})(jQuery);



function htmlspecialchars (string, quote_style, charset, double_encode) {
    // http://kevin.vanzonneveld.net
    // +   original by: Mirek Slugen
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Nathan
    // +   bugfixed by: Arno
    // +    revised by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +    bugfixed by: Brett Zamir (http://brett-zamir.me)
    // +      input by: Ratheous
    // +      input by: Mailfaker (http://www.weedem.fr/)
    // +      reimplemented by: Brett Zamir (http://brett-zamir.me)
    // +      input by: felix
    // +    bugfixed by: Brett Zamir (http://brett-zamir.me)
    // %        note 1: charset argument not supported
    // *     example 1: htmlspecialchars("<a href='test'>Test</a>", 'ENT_QUOTES');
    // *     returns 1: '&lt;a href=&#039;test&#039;&gt;Test&lt;/a&gt;'
    // *     example 2: htmlspecialchars("ab\"c'd", ['ENT_NOQUOTES', 'ENT_QUOTES']);
    // *     returns 2: 'ab"c&#039;d'
    // *     example 3: htmlspecialchars("my "&entity;" is still here", null, null, false);
    // *     returns 3: 'my &quot;&entity;&quot; is still here'
    var optTemp = 0,
        i = 0,
        noquotes = false;
    if (typeof quote_style === 'undefined' || quote_style === null) {
        quote_style = 2;
    }
    string = string.toString();
    if (double_encode !== false) { // Put this first to avoid double-encoding
        string = string.replace(/&/g, '&amp;');
    }
    string = string.replace(/</g, '&lt;').replace(/>/g, '&gt;');

    var OPTS = {
        'ENT_NOQUOTES': 0,
        'ENT_HTML_QUOTE_SINGLE': 1,
        'ENT_HTML_QUOTE_DOUBLE': 2,
        'ENT_COMPAT': 2,
        'ENT_QUOTES': 3,
        'ENT_IGNORE': 4
    };
    if (quote_style === 0) {
        noquotes = true;
    }
    if (typeof quote_style !== 'number') { // Allow for a single string or an array of string flags
        quote_style = [].concat(quote_style);
        for (i = 0; i < quote_style.length; i++) {
            // Resolve string input to bitwise e.g. 'ENT_IGNORE' becomes 4
            if (OPTS[quote_style[i]] === 0) {
                noquotes = true;
            }
            else if (OPTS[quote_style[i]]) {
                optTemp = optTemp | OPTS[quote_style[i]];
            }
        }
        quote_style = optTemp;
    }
    if (quote_style & OPTS.ENT_HTML_QUOTE_SINGLE) {
        string = string.replace(/'/g, '&#039;');
    }
    if (!noquotes) {
        string = string.replace(/"/g, '&quot;');
    }

    return string;
}