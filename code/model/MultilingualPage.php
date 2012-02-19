<?php
/*
 * Extend from this page to make your site tree multilingual
 * Ex: extend MultilingualPage from Page
 * Tip: add static $hide_ancestor = 'MultilingualPage' to your Page to hide 
 * this page from being created.
 */
class MultilingualPage extends SiteTree{
	
	//must be empty, if not it inherits the db field
	static $db=array();
		
	
	//fix for multilanguage, override getField from dataobject.php
	public function getField($field) {
		
		// If we already have an object in $this->record, then we should just return that
		if(isset($this->record[$field]) && is_object($this->record[$field]))  return $this->record[$field];
		
		// Otherwise, we need to determine if this is a complex field
		if(self::is_composite_field($this->class, $field)) {
			$helper = $this->castingHelper($field);
			$fieldObj = Object::create_from_string($helper, $field);
			
			// write value only if either the field value exists,
			// or a valid record has been loaded from the database
			$value = (isset($this->record[$field])) ? $this->record[$field] : null;
			if($value || $this->exists()) $fieldObj->setValue($value, $this->record, false);			
			$this->record[$field] = $fieldObj;
			return $this->record[$field];
		}		
		
		if(Multilingual::current_lang()){
			$lang = Multilingual::current_lang();
			$langField = $field . '_' . $lang;	
			if(isset($this->record[$langField])) {			
				return $this->record[$langField];			
			} 
		}		
		return isset($this->record[$field]) ? $this->record[$field] : null;
	}	
	public function Link($action = null) {
		if($action == "index") {
			$action = "";
		}
		$langSegment ="";
		if(Multilingual::default_lang()!=Multilingual::current_lang()){
			$lang = Multilingual::current_lang();
			$langSegment = ($lang != "") ? "$lang/" : "";
		}
		return Controller::join_links(Director::baseURL(), $langSegment ,$this->RelativeLink($action));		
	}
}

class MultilingualPage_Controller extends ContentController {
	/*mulitlingual support*/
	public function Link($action = null) {
		$lang = Multilingual::current_lang();
		$langSegment ="";
		if(Multilingual::default_lang()!=Multilingual::current_lang()){
			$lang = Multilingual::current_lang();
			$langSegment = ($lang != "") ? "$lang/" : "";
		}
		return Controller::join_links(Director::baseURL(), $langSegment ,$this->RelativeLink($action));		
	}
}

?>
