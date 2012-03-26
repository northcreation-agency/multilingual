

jQuery().ready(function($){
	/*
	 * For dropdown - not done
	 */
	$("#TopLangSelectorDropdown").livequery("change",function(){		
		lang=$(this).find("option:selected").val();		
		if(lang.length>0){
			$(this).closest("form").find("div.field.multilingual").hide();
			$(this).closest("form").find("div.field.multilingual[id*=_"+lang+"]").show();
		}else{
			//If lang is default lang
			$(this).closest("form").find("div.field.multilingual").show();
			$(this).closest("form").find("div.field.multilingual[id*=_]").hide();
		}
		
		//only set cookie if in page mode, not in dataobject popups
		if($(this).closest("form").attr("id")=="Form_EditForm"){
			setCookie("CurrentLanguageAdmin",lang, 7);
		}
	});



	/*
	 * For links
	 */
	$("#TopLangSelector a").livequery("click",function(){				
		lang=$(this).attr("rel");				
		if(lang.length>0){			
			$(this).closest("form").find("div.field.multilingual").hide();
			$(this).closest("form").find("div.field.multilingual[id*=_"+lang+"]").show();
		}else{
			//If lang is default lang
			$(this).closest("form").find("div.field.multilingual").show();
			$(this).closest("form").find("div.field.multilingual[id*=_]").hide();
		}		
		$(this).closest("ul").find("a").removeClass("selected");
		$(this).addClass("selected");
		
		//we fix URLsegment if URLSegment is used in multilingual
		if($("#URL").find(".multilingual").length){
			pageurlarray=($(this).attr("href").substr(1)).split("/");
			newpageurlarray=pageurlarray.slice(0,(pageurlarray.length-2));
			newurl=newpageurlarray.join("/");		
			$("#Form_EditForm_BaseUrlLabel").text($("base").attr("href")+ newurl+"/");
		}
		//only set cookie if in page mode, not in dataobject popups
		if($(this).closest("form").attr("id")=="Form_EditForm"){
			setCookie("CurrentLanguageAdmin",lang, 7);
		}
		
		return false;
	});
});

function setCookie(c_name,value,exdays){
	var exdate=new Date();
	exdate.setDate(exdate.getDate() + exdays);
	var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());	
	document.cookie=c_name + "=" + c_value+"; javahere=yes;path=/admin";
}

