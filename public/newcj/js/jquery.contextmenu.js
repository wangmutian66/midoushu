jQuery.fn.contextPopup=function(menuData){var settings={contextMenuClass:'contextMenuPlugin',gutterLineClass:'gutterLine',headerClass:'header',seperatorClass:'divider',title:'',items:[]};$.extend(settings,menuData);function createMenu(e){var menu=$('<ul class="'+settings.contextMenuClass+'"><div class="'+settings.gutterLineClass+'"></div></ul>').appendTo(document.body);if(settings.title){$('<li class="'+settings.headerClass+'"></li>').text(settings.title).appendTo(menu);}
settings.items.forEach(function(item){if(item){var rowCode='<li><a href="#"><span></span></a></li>';var row=$(rowCode).appendTo(menu);if(item.icon){var icon=$('<img>');icon.attr('src',item.icon);icon.insertBefore(row.find('span'));}
row.find('span').text(item.label);if(item.action){row.find('a').click(function(){item.action(e);});}}else{$('<li class="'+settings.seperatorClass+'"></li>').appendTo(menu);}});menu.find('.'+settings.headerClass).text(settings.title);return menu;}
this.bind('contextmenu',function(e){var menu=createMenu(e).show();var left=e.pageX+5,top=e.pageY;if(top+menu.height()>=$(window).height()){top-=menu.height();}
if(left+menu.width()>=$(window).width()){left-=menu.width();}
menu.css({zIndex:1000001,left:left,top:top}).bind('contextmenu',function(){return false;});var bg=$('<div></div>').css({left:0,top:0,width:'100%',height:'100%',position:'absolute',zIndex:1000000}).appendTo(document.body).bind('contextmenu click',function(){bg.remove();menu.remove();return false;});menu.find('a').click(function(){bg.remove();menu.remove();});return false;});return this;};