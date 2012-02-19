<?php
/******************************************************************************************************
 * Multilingual module with i18n support
 * 
/******************************************************************************************************/


Multilingual::set_default_lang("sv");
Multilingual::set_langs(array(
	"Swedish"=>array(
		"sv"=>"sv_SE"
	),
	"English"=>array(
		"en"=>"en_GB"
	),
	"German"=>array(
		"de"=>"de_DE"
	)
));


/*------------------------------------------------------------------------------
 * Global multilingual fields. If found in any child class it will translate 
 * them (new fields will be created on the object).
 * Ex: the field "Title" will create a new db field with name "Title_XX", 
 * where XX is the lang-id, ex "es" for spanish 
 */

// Global multilingual fields for SiteTree and its descendants
Multilingual_SiteTree::set_multilingal_fields(
array(
	"Title",		
	"MenuTitle",
	"Content",
	"MetaTitle",
	"MetaDescription",
	"MetaKeywords",
	"ExtraMeta",	
));

// Global multilingual fields for all MultilingualDataObject and its descendants
Multilingual_DataObject::set_multilingal_fields(array());


// Global multilingual fields for siteconfig
Multilingual_SiteConfig::set_multilingal_fields(
array(
	"Title",		
	"Tagline",
	"ReceiptEmailSubject"
));


//ENABLE  last!
Multilingual::enable();



?>
