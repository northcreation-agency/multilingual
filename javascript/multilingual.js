jQuery().ready(function($){
	/*
	 * For dropdown
	 */
	$("#TopLangSelectorDropdown").livequery("change",function(){		
		lang=$(this).find("option:selected").val();		
		if(lang.length>0){
			$("#Form_EditForm").find("div.field.multilingual").hide();
			$("#Form_EditForm").find("div.field.multilingual[id$=_"+lang+"]").show();
		}else{
			//If lang is default lang
			$("#Form_EditForm").find("div.field.multilingual").show();
			$("#Form_EditForm").find("div.field.multilingual[id*=_]").hide();
		}
		setCookie("CurrentLanguageAdmin",lang, 7);
	});



	/*
	 * For links
	 */
	$("#TopLangSelector a").livequery("click",function(){		
		lang=$(this).attr("rel");		
		if(lang.length>0){
			$("#Form_EditForm").find("div.field.multilingual").hide();
			$("#Form_EditForm").find("div.field.multilingual[id$=_"+lang+"]").show();
		}else{
			//If lang is default lang
			$("#Form_EditForm").find("div.field.multilingual").show();
			$("#Form_EditForm").find("div.field.multilingual[id*=_]").hide();
		}		
		jQuery(this).closest("ul").find("a").removeClass("selected");
		jQuery(this).addClass("selected");
		
		
		setCookie("CurrentLanguageAdmin",lang, 7);		
		return false;
	});
});

function setCookie(c_name,value,exdays){
	var exdate=new Date();
	exdate.setDate(exdate.getDate() + exdays);
	var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());	
	document.cookie=c_name + "=" + c_value+"; javahere=yes;path=/admin";
}