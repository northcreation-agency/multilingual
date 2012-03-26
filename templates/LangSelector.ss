<% if LangSelector %>
<div id="lang">
	<ul class="language-selector">
		<% control LangSelector(true) %>
			<li><a href="$Link" title="$LangNice" class="flag-$LangCode $Selected"><img src="$ImgURL" height="15" alt="$LangNice" /><span>$LangNice</span></a></li>
		<% end_control %>
	</ul>				  
</div>
<% end_if %>