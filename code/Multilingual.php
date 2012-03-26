<?php

class Multilingual extends DataObjectDecorator {
	/*	 * ****************************************************************************************************
	  /* lang statics, getter, setters
	  /***************************************************************************************************** */

	static $use_URLSegment = false;
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
	private static $filter_enabled=true;
	public function filter_enabled() {
		return self::$filter_enabled;
	}
	public function set_filter_enabled() {
		self::$filter_enabled=true;
	}
	public function set_filter_disabled() {
		self::$filter_enabled=false;
	}
	
	
	/*Add global multilingual fields*/
	static function set_sitetree_global_multilingual_fields(array $fields){		
		//Multilingual::$sitetree_global_multilingual_fields=$fields;		
		Multilingual_SiteTree::$multilingual_fields=$fields;
	}
	static function set_siteconfig_global_multilingual_fields(array $fields){		
		//Object::set_static("SiteConfig", "multilingual_fields", $fields);
		//Multilingual::$siteconfig_global_multilingual_fields=$fields;		
		Multilingual_SiteConfig::$multilingual_fields=$fields;
	}

	
	

	/*	 * ****************************************************************************************************
	  /* Decoration functions
	  /***************************************************************************************************** */

	/*
	 * Enable function, which simplifies the activation of multilingual module.
	 * It extends SiteTree, SiteConfig and the custom dataobject "TranslatableObject".
	 * It also creates URL-rules for all the languages.
	 */

	public static function enable() {
		/*Basic checks*/
		$SiteConfig=  singleton("SiteConfig");
		if(!($SiteConfig instanceof MultilingualSiteConfig)){
			user_error('You need to extend SiteConfig from MultilingualSiteConfig', E_USER_ERROR);
		}

		
		
		if (static::$use_URLSegment) {
			Multilingual_SiteTree::$multilingual_fields[] = "URLSegment";
		}
		
				
		DataObject::add_extension("MultilingualPage", "Multilingual_SiteTree");
		DataObject::add_extension("MultilingualDataObject", "Multilingual_DataObject");
		DataObject::add_extension("SiteConfig", "Multilingual_SiteConfig");


		/*
		 * Workaround to add extension last -  we want to get all other siteconfig fields from other extensions
		 * Make sure this siteconfig decoration is called last (put in a root folder z_multilingual or something)
		 */		
		$extension="Multilingual_SiteConfig";
		$extensions = Object::uninherited_static("SiteConfig", 'extensions');
		if($extensions) {
			unset($extensions[0]);//we unset the latest addition - multilingual_siteconfig			
			$extensions[]=$extension; // we add it in the last position - will be handled last by ss
		}else{
			$extensions = array($extension);		
		}
		Object::set_static("SiteConfig", 'extensions', $extensions);
		
		
		foreach (multilingual::multilingual_extra_langs() as $lang) {
			Director::addRules(95, array(
				$lang . '/$Controller//$Action/$ID/$OtherID' => '*',
				$lang . '/$URLSegment//$Action/$ID/$OtherID' => 'MultilingualModelAsController'
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
			
			//for default language
			$return = array(
				"db" => array(
					"LangActive"=>"Boolean"
				),
				"defaults" => array(
					"LangActive"=>true				
				)
			);

			//subclasses, and decorated, parent class
			$subclasses = ClassInfo::subclassesFor($decorated_class);
			foreach ($subclasses as $key => $class) {
				$db = array();
				$origfields = Object::get_static($class, "db");
				if (is_array($origfields)) {
					$origkeys = array_keys($origfields);
					if (!($class_multilingual_fields = Object::get_static($class, "multilingual_fields"))) {
						$class_multilingual_fields = array();
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

						$return["db"]["LangActive_" . $lang] = "Boolean";
						$return["defaults"]["LangActive_" . $lang] = true;
					}
					Object::set_static($class, "db", array_merge($origfields, $db));
				}
			}
			//SiteConfig is a DataObject so we already returned this from Multilingual_DataObject
			if($decorated_class!="SiteConfig"){
				return $return;
			}
		}
	}

	/*	 * ****************************************************************************************************
	  /* ADMIN / CMS functions
	  /***************************************************************************************************** */

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
	
	
	function onRequirementsForPopup() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
		Requirements::javascript("multilingual/javascript/multilingual.js");
		Requirements::css("multilingual/css/multilingual.css");
	}
	/*
	 * Replace all fields added in $multilingual array to multilingual versions
	 * Please use the "SiteTree::disableCMSFieldsExtensions()" and "SiteTree::enableCMSFieldsExtensions()"
	 * bewtween "parent::getCMSFields()" in your sub classes for full access to all multilingual fields.
	 */	
	public function updateCMSFields(FieldSet &$f) {		
		
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
		Requirements::javascript("multilingual/javascript/multilingual.js");
		Requirements::css("multilingual/css/multilingual.css");

		$class_multilingual_fields = Object::get_static($this->owner->ClassName, "multilingual_fields") ? Object::get_static($this->owner->ClassName, "multilingual_fields") : array();
		$global_multilingual_fields = static::multilingual_fields() ? static::multilingual_fields() : array();
		$multilingual_fields = array_merge($class_multilingual_fields, $global_multilingual_fields);
		foreach ($multilingual_fields as $fieldname) {

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

					//Fix original fields
					$extraclass = Multilingual::admin_current_lang() != $langcode ? "hiddenfield" : "";
					$fieldclone = clone $originalfield;
					$fieldclone->setName($fieldid);
					$fieldclone->setTitle($originalfield->Title() . " 
							<span class='marked-label " . $langcode . "'>
								<img src='multilingual/images/flag-" . $langcode . ".png' />
							<span class='language-nice'>" . $langnice . "</span></span>");
					$fieldclone->setValue($this->owner->$fieldname);					

					if ($fieldname == "URLSegment") {
						$fields[] = $urlsegmentfield = new FieldGroup($fieldclone);
						$urlsegmentfield->setID($fieldclone->id());
						$urlsegmentfield->addExtraClass("multilingual " . $extraclass);
					} else {
						$fieldclone->addExtraClass("multilingual " . $extraclass);
						$fields[] = $fieldclone;
					}
					$fieldclone = null;
				}
				if ($fieldname == "URLSegment") {
					$f->replaceField($fieldname, $compositefield = new FieldGroup($fields));
				} else {
					$f->replaceField($fieldname, $compositefield = new CompositeField($fields));
					$compositefield->setID("Multilingual_".$fieldname);
					$compositefield->addExtraClass("multilingualfield field-".$fieldname);
				}
			}
		}

		
		//if Widget
		if(strpos($this->owner->ClassName,"Widget")){
			
			$f->push(new HeaderField("LangHeading", "Visible in languages", 4));
			foreach (Multilingual::$langs as $langnice => $langarray ) {
				$key = array_keys($langarray);
				$langcode = $key[0];
				if ($langcode != Multilingual::default_lang()) {
					$langactive = new CheckboxField("LangActive_" . $langcode, "
						<span class='marked-label " . $langcode . "'>
							<img src='multilingual/images/flag-" . $langcode . ".png' />
						<span class='language-nice'>" . $langnice . "</span></span>");
					$f->push($langactive);
				}
			}
			
		}else{//if page or dataobject in popup
			
			//add LangActive field, not in SiteConfig			
			if($this->owner->ClassName!="SiteConfig"){
				if (!$f->fieldByName('Root.Behaviour')) {
					$f->addFieldToTab("Root",$behaviour=new Tab("Behaviour"));			
					$behaviour->setTitle(_t("SiteTree.TABBEHAVIOUR","Behaviour"));
				}
				$f->addFieldToTab("Root.Behaviour", new HeaderField("LangHeading", "Visible in languages", 4));
				foreach (Multilingual::$langs as $langnice => $langarray ) {
					$key = array_keys($langarray);
					$langcode = $key[0];
					$langfield="LangActive_" . $langcode;
					if ($langcode == Multilingual::default_lang()) {
						$langcode=Multilingual::default_lang();
						$langfield="LangActive";
					}					
					$langactive = new CheckboxField($langfield, "
						<span class='marked-label " . $langcode . "'>
							<img src='multilingual/images/flag-" . $langcode . ".png' />
						<span class='language-nice'>" . $langnice . "</span></span>");

					$f->addFieldToTab("Root.Behaviour", $langactive);
				
				}

			}
			
		}
		/*
		 * Flag links in cms
		 */
		$langselector = $this->CreateLangSelectorForAdmin();
		$f->unshift(new LiteralField("MultilingualSelector", $langselector, null, true));

		/*
		 * Alternate display - dropdown in cms - not done
		 */
		//$f->unshift(new DropdownField("TopLangSelectorDropdown","", Multilingual::map_to_dropdown(),Multilingual::admin_current_lang()));
	}
	
	
	
	
	
	public function requireDefaultRecords(){
		$idArray = explode('&',$_SERVER["QUERY_STRING"]);	
		if(sizeof($idArray)>1){
			foreach ($idArray as $index => $avPair){
			  list($ignore, $value) = explode("=", $avPair);
			  $id[$index] = $value;
			  $allowed_langs=array_values(self::map_locale());
			  if($value && in_array($index,$allowed_langs)){
			  	if($index==Multilingual::default_lang()){
			  		$fieldname="LangActive";
			  	}else{
			  		$fieldname="LangActive_".$index;
			  	}
		  		$MultilingualDataObjects=DataObject::get("MultilingualDataObject");
		  		if($MultilingualDataObjects){
		  			foreach($MultilingualDataObjects as $object){
		  				$object->$fieldname=true;
		  				$object->write();
		  			}
		  		}
		  		$MultilingualPages=DataObject::get("MultilingualPage");
		  		if($MultilingualPages){
	  				foreach($MultilingualPages as $object){
		  				$object->$fieldname=true;		  				
						if($object->isPublished()){
							$object->doPublish();
						}else{
							$object->write();
						}
		  			}

		  		}			  				  	
			  }
			}
		}
		
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

	/*	 * ***************************************************************************************************
	  /* Template functions
	  /***************************************************************************************************** */

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
			$langactive=$isDefaultLang?"LangActive":"LangActive_".$langcode;

			if (($displayCurrentLang || !$isDefaultLang) && $this->owner->$langactive) {
				$active = false;
				if (Multilingual::$use_URLSegment) {
					$langactive = "LangActive_" . $langcode;
					if ($this->owner->$langactive && Multilingual::default_lang() != $langcode) {
						$active = true;
					}
				}
				if ($CheckExistsfield || $active) {
					$checkfield = $CheckExistsfield . "_" . $langcode;
					if (($this->owner->$checkfield || $isDefaultLang) || $active) {
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
	
	//these are the multilingual fields for the siteconfig
	static $multilingual_fields = array(
		"Tagline",
	);
		

}

class Multilingual_SiteTree extends Multilingual {
	static $decorated_class = "SiteTree";
	
	//these are general multilingual fields. They will be translated even though they
	//are from other class "lower down the chain".
	static $multilingual_fields = array(
		"Title",
		"MenuTitle",
		"Content",
		"MetaTitle",
		"MetaDescription",
		"MetaKeywords",
		"ExtraMeta",
	);

	/*function augmentSQL(SQLQuery &$query) {
		if($this->owner instanceof MultilingualPage){
			$lang = Multilingual::current_lang();
			if ($lang != Multilingual::default_lang()) {
				$field = "LangActive_" . $lang;
			}else{//if default language
				$field = "LangActive";			
			}

			$baseTable = ClassInfo::baseDataClass($this->owner->class);
			$where = $query->where;
			if (
					$lang
					// unless the filter has been temporarily disabled
					&& Multilingual::filter_enabled()
					// DataObject::get_by_id() should work independently of language
					&& !$query->filtersOnID()
					// the query contains this table
					// @todo Isn't this always the case?!
					&& array_search($baseTable, array_keys($query->from)) !== false
					// or we're already filtering by Lang (either from an earlier augmentSQL() call or through custom SQL filters)
					&& !preg_match('/("|\'|`)' . $field . '("|\'|`)/', $query->getFilter())
			//&& !$query->filtersOnFK()
			) {
				$qry = sprintf('"%s"."' . $field . '" = \'%s\'', $baseTable, 1);
				$query->where[] = $qry;
			}
		}
		
	}*/

	//basically overload the function
	public function augmentStageChildren(&$staged, $showAll = false) {
		if($this->owner instanceof MultilingualPage){
			if ($this->owner->db('ShowInMenus')) {
				$extraFilter = ($showAll) ? '' : " AND \"ShowInMenus\"=1";
			} else {
				$extraFilter = '';
			}

			$lang = Multilingual::current_lang();
			if ($lang != Multilingual::default_lang()) {
				$extraFilter.=" AND \"LangActive_" . $lang . "\"=1";
			}else{
				$extraFilter.=" AND \"LangActive\"=1";
			}

			/*if($this->owner instanceof MultilingualPage){

			}else{

			}*/
			$baseClass = ClassInfo::baseDataClass($this->owner->class);

			$staged = DataObject::get($baseClass, "\"{$baseClass}\".\"ParentID\" = "
							. (int) $this->owner->ID . " AND \"{$baseClass}\".\"ID\" != " . (int) $this->owner->ID
							. $extraFilter, "");

			if (!$staged)
				$staged = new DataObjectSet();
		}
	}

	public function onBeforeWrite() {
		if (Multilingual::$use_URLSegment) {
			$originalTitle = $this->owner->Title;
			foreach (Multilingual::multilingual_extra_langs() as $lang) {
				Multilingual::set_current_lang($lang);
				$URLSegmentfield = "URLSegment_" . $lang;
				$Titlefield = "Title_" . $lang;
				if (empty($this->owner->$Titlefield)) {
					$this->owner->$Titlefield = $originalTitle . "-" . $lang;
				}
				if ((!$this->owner->$URLSegmentfield || $this->owner->$URLSegmentfield == 'new-page') && $this->owner->$Titlefield) {
					$this->owner->$URLSegmentfield = $this->owner->generateURLSegment($this->owner->$Titlefield);
				} else if ($this->owner->isChanged($URLSegmentfield)) {
					// Make sure the URLSegment is valid for use in a URL
					$segment = ereg_replace('[^A-Za-z0-9]+', '-', $this->owner->$URLSegmentfield);
					$segment = ereg_replace('-+', '-', $segment);

					// If after sanitising there is no URLSegment, give it a reasonable default
					if (!$segment) {
						$segment = "page-$this->owner->ID";
					}
					$this->owner->$URLSegmentfield = $segment;
				}
			}
			Multilingual::set_current_lang(Multilingual::default_lang());
		}
	
	}

}


class Multilingual_DataObject extends Multilingual {

	static $decorated_class = "MultilingualDataObject";
	
	//these are general multilingual fields. They will be translated even though they
	//are from other class "lower down the chain".
	static $multilingual_fields = array("Title");

	function augmentSQL(SQLQuery &$query) {
		if($this->owner instanceof MultilingualPage || $this->owner instanceof MultilingualDataObject){
			$lang = Multilingual::current_lang();
			if ($lang != Multilingual::default_lang()) {
				$field = "LangActive_" . $lang;
			}else{//if default language
				$field = "LangActive";			
			}

			$baseTable = ClassInfo::baseDataClass($this->owner->class);
			$where = $query->where;
			if (
					$lang
					// unless the filter has been temporarily disabled
					&& Multilingual::filter_enabled()
					// DataObject::get_by_id() should work independently of language
					&& !$query->filtersOnID()
					// the query contains this table
					// @todo Isn't this always the case?!
					&& array_search($baseTable, array_keys($query->from)) !== false
					// or we're already filtering by Lang (either from an earlier augmentSQL() call or through custom SQL filters)
					&& !preg_match('/("|\'|`)' . $field . '("|\'|`)/', $query->getFilter())
			//&& !$query->filtersOnFK()
			) {
				$qry = sprintf('"%s"."' . $field . '" = \'%s\'', $baseTable, 1);
				$query->where[] = $qry;
			}
		}
	}


}

?>
