<?php

// Config is set in z_site/_config.php for the site using multilingual module

/******************************************************************************************************
 * Multilingual module with i18n support
 * 
/******************************************************************************************************/

Requirements::css("multilingual/css/langselector.css");

i18n::set_default_locale("sv_SE");
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


//PAGES------------------------------------------------------------------------------
// Global multilingual fields. If found in any child class it will translate 
// them (new fields will be created on the object).
// Ex: the field "Title" will create a new db field with name "Title_XX", 
// where XX is the lang-id, ex "es" for spanish 
//

// Multilingual fields for SiteTree class
Multilingual::set_sitetree_global_multilingual_fields(
array(
	"Title",		
	"MenuTitle",
	"Content",
	"MetaTitle",
	"MetaDescription",
	"MetaKeywords",
	"ExtraMeta",
));

//SITECONFIG------------------------------------------------------------------------------
// For SiteConfig to work properly, you have to sadly hack the SiteConfig.php class. 
// The upside is that its a small hack. Change the following:
// 
// "class SiteConfig extends DataObject" To:
// 
// "class SiteConfig extends MultilingualDataObject"
// 

// Multilingual fields for siteconfig
//because we cant add a static to the class (we dont want to do more hacking of the core)
//we add all multilingual fields for the SiteConfig here
Multilingual::set_siteconfig_global_multilingual_fields(
array(			
	"Title",
	"Tagline",
));



// GENERAL DATAOBJECTS------------------------------------------------------------------------------
// Global multilingual fields for general DataObjects and its descendants is
// best to add on the dataobject theself. Just add a new static for it:
// Ex: static $multilingual_fields=array("MyTitle","MyContent") etc.


// Multilingual URLSegment------------------------------------------------------------------------------
// Do you want to be able to traslate the URLSegment of pages?
Multilingual::$use_URLSegment=true;


//ENABLE  last!
Multilingual::enable();


/*
* MULTILINGUAL END
*/


?>
