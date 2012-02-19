<?php

class Multilingual extends DataObjectDecorator {
	
	/******************************************************************************************************
	/* lang statics, getter, setters
	/******************************************************************************************************/	
	
	static $decorated_class; // used in the child classes to get what we are decorating
	function get_decorated_class() {
		return static::$decorated_class;
	}
	private static $lang;
	private static $default_lang = "sv";

	/*
	 * All the languages available for the site, as an associative array
	 */
	static $langs = array(
		"Swedish" => array("sv" => "sv_SE"),
		"English" => array("en" => "en_US"),
	);
	static function set_langs($array) {
		static::$langs = $array;
	}

	
	/*
	 * All multilingual fields on the object, will
	 * translate to selected language if found on the decorated object.
	 * ex: "Title" will be "Title_de" and so on for each language set.
	 * these are set in the subclasses
	 */

	static $multilingual_fields = array();

	static function multilingual_fields() {
		return static::$multilingual_fields;
	}

	static function set_multilingal_fields($array) {
		static::$multilingual_fields = $array;
	}

	/*
	 * Getter / setter for current site lang
	 */

	static function current_lang() {

		if (empty(Multilingual::$lang)) {
			return Multilingual::default_lang();
		} else {
			return Multilingual::$lang;
		}
	}

	static function set_current_lang($lang) {
		return Multilingual::$lang = $lang;
	}

	/*
	 * Getter / setter for default site lang
	 */

	static function default_lang() {
		return Multilingual::$default_lang;
	}

	static function set_default_lang($lang) {
		Multilingual::$default_lang = $lang;
	}

	/*
	 * Returns the map of locales of lang, ex: sv=>sv_SE	
	 */

	static function map_locale() {
		$map = array();
		foreach (Multilingual::$langs as $keys => $langs) {
			$key = array_keys($langs);
			$value = array_values($langs);
			$map[$key[0]] = $value[0];
		}
		return $map;
	}

	/*
	 * Returns an associative array for lang dropdowns, ex: sv=>Swedish
	 */

	static function map_to_dropdown() {
		$map = array();
		foreach (Multilingual::$langs as $nicename => $array) {
			$langcode = array_keys($array);
			$langcode = $langcode[0];
			if ($langcode == Multilingual::default_lang()) {
				$langcode = "";
			}
			$map[$langcode] = $nicename;
		}
		return $map;
	}

	/*
	 * Returns all langs except the default lang as an array
	 */

	static function multilingual_extra_langs() {
		$langsarray = array_keys(Multilingual::map_locale());
		$arr = array_diff($langsarray, array(Multilingual::default_lang()));
		return ($arr);
	}

	
	
	
	/******************************************************************************************************
	/* Decoration functions 	 
	/******************************************************************************************************/
	
	/*
	 * Enable function, which simplifies the activation of multilingual module.
	 * It extends SiteTree, SiteConfig and the custom dataobject "TranslatableObject".
	 * It also creates URL-rules for all the languages.
	 */
	public static function enable(){
		DataObject::add_extension("SiteConfig", "Multilingual_SiteConfig");
		DataObject::add_extension("SiteTree", "Multilingual_SiteTree");
		DataObject::add_extension("MultilingualDataObject", "Multilingual_DataObject");
		foreach (multilingual::multilingual_extra_langs() as $lang) {
			Director::addRules(100, array(
				$lang . '/$URLSegment/$Action/$ID/$OtherID' => 'MultilingualModelAsController'
			));
		}
	}
	
	/*
	 * This function does not return anything in the traditional sense for a decorator. 
	 * Instead it adds to the DB-array for all the classes that have the found multilingual fields
	 */
	public function extraStatics() {
		
		if (Multilingual::multilingual_extra_langs()) {
			$db = array();
			$decorated_class = static::get_decorated_class();
			
			//subclasses, and decorated, parent class
			$subclasses = ClassInfo::subclassesFor($decorated_class);
			foreach ($subclasses as $key=>$class) {
				$db = array();
				$origfields = Object::get_static($class, "db");
				$origkeys = array_keys($origfields);				
				if(!($class_multilingual_fields=Object::get_static($class, "multilingual_fields"))){
					$class_multilingual_fields=array();
				}
				foreach (Multilingual::multilingual_extra_langs() as $lang) {
					
					//look after the global fields set in _config.php
					foreach (static::multilingual_fields() as $fieldtotranslate) {
						// make sure we find a field with correct name that hasnt been translated before
						if (in_array($fieldtotranslate, $origkeys) && !in_array($fieldtotranslate . "_" . $lang, $origfields)) {
							$db[$fieldtotranslate . "_" . $lang] = $origfields[$fieldtotranslate];
						}
					}
					//look after class multilingual fields set on class
					foreach ($class_multilingual_fields as $fieldtotranslate) {
						// make sure we find a field with correct name that hasnt been translated before
						if (in_array($fieldtotranslate, $origkeys) && !in_array($fieldtotranslate . "_" . $lang, $origfields)) {
							$db[$fieldtotranslate . "_" . $lang] = $origfields[$fieldtotranslate];
						}
					}
				}								
				Object::set_static($class, "db", array_merge($origfields, $db));
			}			
		}
	}
	
	
	
	/******************************************************************************************************
	/* ADMIN / CMS functions
	/******************************************************************************************************/
	
	/*
	 * Admin lang when changing languages in admin.
	 * In admin we use a cookie to set and remember current language.
	 * The cookie is set from js-file javascript/multilingual.js
	 * @returns String language
	 */
	static function admin_current_lang($showfull = true) {
		$currentlang = Cookie::get("CurrentLanguageAdmin");
		if (!$currentlang) {
			if ($showfull) {
				return Multilingual::default_lang();
			} else {
				return "";
			}
		} else {
			return $currentlang;
		}
	}	

	/*
	 * Replace all fields added in $multilingual array to
	 * multilingual versions
	 * Please use the "SiteTree::disableCMSFieldsExtensions()" and "SiteTree::enableCMSFieldsExtensions()"
	 * bewtween "parent::getCMSFields()" in your sub classes for full access to all multilingual fields.
	 */
	public function updateCMSFields(FieldSet &$f) {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
		Requirements::javascript("multilingual/javascript/multilingual.js");
		Requirements::css("multilingual/css/multilingual.css");
		$values = array();		
		$class_multilingual_fields=Object::get_static($this->owner->ClassName, "multilingual_fields")?Object::get_static($this->owner->ClassName, "multilingual_fields"):array();				
		$global_multilingual_fields=static::multilingual_fields()?static::multilingual_fields():array();
		$multilingual_fields=array_merge($class_multilingual_fields, $global_multilingual_fields);		
		foreach ($multilingual_fields as $fieldname) {
			$values = null;
			$fields = null;			
			$originalfield = $f->dataFieldByName($fieldname);
			if ($originalfield) {				
				foreach (Multilingual::$langs as $langnice => $langarray) {
					$key = array_keys($langarray);
					$langcode = $key[0];
					if ($langcode == Multilingual::default_lang()) {
						$fieldid = $fieldname;
					} else {
						$fieldid = $fieldname . "_" . $langcode;
					}
					$extraclass = Multilingual::admin_current_lang() != $langcode ? "hiddenfield" : "";
					$values[$fieldid] = $langnice;
					$fieldclone = clone $originalfield;
					$fieldclone->setName($fieldid);
					$fieldclone->setTitle($originalfield->Title() . " 
						<span class='marked-label " . $langcode . "'>
							<img src='multilingual/images/flag-" . $langcode . ".png' />
						<span class='language-nice'>" . $langnice . "</span></span>");
					$fieldclone->setValue($this->owner->$fieldname);
					$fieldclone->addExtraClass("multilingual " . $extraclass);
					$fields[] = $fieldclone;
					$fieldclone = null;
				}

				$f->replaceField($fieldname, $compositefield = new CompositeField($fields));
				$compositefield->addExtraClass("multilingualfield");
			}
		}


		/*
		 * Flag links in cms
		 */
		$langselector = $this->CreateLangSelectorForAdmin();
		$f->unshift(new LiteralField("MultilingualSelector", $langselector, null, true));

		/*
		 * Alternate display - dropdown in cms
		 */
		//$f->unshift(new DropdownField("TopLangSelectorDropdown","", Multilingual::map_to_dropdown(),Multilingual::admin_current_lang()));
	}

	
	/*
	 * Build up necessary html for a simple flag selector for admin
	 */
	function CreateLangSelectorForAdmin() {
		$langselectors = '<ul class="langflags" id="TopLangSelector">';
		$origlang = Multilingual::current_lang();
		foreach (Multilingual::map_to_dropdown() as $langcode => $langnice) {
			Multilingual::set_current_lang($langcode);
			$selected = (Multilingual::admin_current_lang(false) == $langcode) ? "selected" : "";
			$classlangcode = !empty($langcode) ? $langcode : Multilingual::default_lang();
			if ($this->owner instanceof SiteTree) {
				$langselectors.='<li><a href="' . $this->owner->Link() . '" rel="' . $langcode . '" lang-title="' . $this->owner->Title . '" class="' . $classlangcode . ' ' . $selected . '" title="' . $langnice . '"><img src="multilingual/images/flag-' . $classlangcode . '.png" /></a></li>';
			} else {
				$langselectors.='<li><a href="' . Director::absoluteBaseURL() . $langcode . '" rel="' . $langcode . '" lang-title="' . $this->owner->Title . '" class="' . $classlangcode . ' ' . $selected . '" title="' . $langnice . '"><img src="multilingual/images/flag-' . $classlangcode . '.png" /></a></li>';
			}
		}
		Multilingual::set_current_lang($origlang);
		$langselectors.="</ul>";
		return $langselectors;
	}

	
	/******************************************************************************************************
	/* Template functions
	/******************************************************************************************************/

	function LangSelector($displayCurrentLang = false, $CheckExistsfield = false) {//checkexistfield couldbe ex "Title", it will then check if Title_XX exists
		$list = new DataObjectSet();
		$origlang = Multilingual::current_lang();
		foreach (Multilingual::$langs as $langnice => $langarray) {
			$arr = array_keys($langarray);
			$langcode = $arr[0];
			$isDefaultLang = $langcode == Multilingual::default_lang();
			Multilingual::set_current_lang($langcode);


			$do = new DataObject();
			$do->Link = $this->owner->Link();
			$do->LangCode = $langcode;
			$do->ImgURL = Director::absoluteBaseURL() . "multilingual/images/flag-" . $langcode . ".png";
			$do->Selected = $langcode == $origlang ? "selected" : "";
			$do->LangNice = $langnice;
			if ($displayCurrentLang || !$isDefaultLang) {
				if ($CheckExistsfield) {
					$checkfield = $CheckExistsfield . "_" . $langcode;
					if ($this->owner->$checkfield || $isDefaultLang) {
						$list->push($do);
					}
				} else {
					$list->push($do);
				}
			}
		}
		Multilingual::set_current_lang($origlang);
		return $list;
	}	

}

class Multilingual_SiteConfig extends Multilingual {
	static $decorated_class = "SiteConfig";
	static $multilingual_fields = array(
		"Tagline",
	);
}

class Multilingual_SiteTree extends Multilingual {
	static $decorated_class = "SiteTree";
	static $multilingual_fields = array(
		"Title",
		"MenuTitle",
		"Content",
		"MetaTitle",
		"MetaDescription",
		"MetaKeywords",
		"ExtraMeta",
	);
}

class Multilingual_DataObject extends Multilingual {
	static $decorated_class = "MultilingualDataObject";
	static $multilingual_fields = array(
		"Title"		
	);
}

?>
