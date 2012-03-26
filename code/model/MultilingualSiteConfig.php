<?php
/*
 * Necessary to make SiteConfig multilingual
 * An alternative is to simply copy-paste this function in to siteconfig directly
 */
class MultilingualSiteConfig extends DataObject{		
	function getRequirementsForPopup(){
		$this->extend("onRequirementsForPopup");
	}
	
	//fix for multilanguage, override getField from dataobject.php
	public function getField($field) {

		// If we already have an object in $this->record, then we should just return that
		if (isset($this->record[$field]) && is_object($this->record[$field]))
			return $this->record[$field];

		// Otherwise, we need to determine if this is a complex field
		if (self::is_composite_field($this->class, $field)) {
			$helper = $this->castingHelper($field);
			$fieldObj = Object::create_from_string($helper, $field);

			// write value only if either the field value exists,
			// or a valid record has been loaded from the database
			$value = (isset($this->record[$field])) ? $this->record[$field] : null;
			if ($value || $this->exists())
				$fieldObj->setValue($value, $this->record, false);
			$this->record[$field] = $fieldObj;
			return $this->record[$field];
		}

		if (Multilingual::current_lang()) {
			$lang = Multilingual::current_lang();
			$langField = $field . '_' . $lang;
			if (isset($this->record[$langField])) {
				return $this->record[$langField];
			}
		}
		return isset($this->record[$field]) ? $this->record[$field] : null;
	}


}
?>
