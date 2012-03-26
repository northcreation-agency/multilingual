<?php

/*
 * Extend from this page to make your site tree multilingual
 * Ex: extend MultilingualPage from Page
 * 
 * Tip: add static $hide_ancestor = 'MultilingualPage' to your Page 
 * that extends this page to hide this page from being created.
 * 
 * 
 */

class MultilingualPage extends SiteTree {


	//must be empty, if not it inherits the db field
	static $db = array();
	
	//all pages that descend from MultilingualPage can have this static variable
	//it translates those specific fields on the page
	public static $multilingual_fields=array();
	
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

	public function Link($action = null) {
		if ($action == "index") {
			$action = "";
		}
		$langSegment = "";
		if (Multilingual::default_lang() != Multilingual::current_lang()) {
			$lang = Multilingual::current_lang();
			$langSegment = ($lang != "") ? "$lang/" : "";
		}
		return Controller::join_links(Director::baseURL(), $langSegment, $this->RelativeLink($action));
	}

}

class MultilingualPage_Controller extends ContentController {
	/* mulitlingual support */

	public function Link($action = null) {
		$lang = Multilingual::current_lang();
		$langSegment = "";
		if (Multilingual::default_lang() != Multilingual::current_lang()) {
			$lang = Multilingual::current_lang();
			$langSegment = ($lang != "") ? "$lang/" : "";
		}
		return Controller::join_links(Director::baseURL(), $langSegment, $this->RelativeLink($action));
	}

	/**
	 * This function overloads the original function in ContentController	 
	 *
	 * @return SS_HTTPResponse
	 */
	public function handleRequest(SS_HTTPRequest $request) {
		$child = null;
		$action = $request->param('Action');

		// If nested URLs are enabled, and there is no action handler for the current request then attempt to pass
		// control to a child controller. This allows for the creation of chains of controllers which correspond to a
		// nested URL.
		if ($action && SiteTree::nested_urls() && !$this->hasAction($action)) {


			// See ModelAdController->getNestedController() for similar logic
			Translatable::disable_locale_filter();
			
			/* START changed from orginal */
			$lang = Multilingual::current_lang();
			if (Multilingual::$use_URLSegment && Multilingual::default_lang() != $lang) {
				$urlsegmentfield = "URLSegment_" . $lang;								
			} else {
				$urlsegmentfield = "URLSegment";
			}
			// look for a page with this URLSegment
			$child = DataObject::get_one('SiteTree', sprintf(
					"\"ParentID\" = %s AND \"" . $urlsegmentfield . "\" = '%s'", $this->ID, Convert::raw2sql($action)
			));
			if($child && !$child->canView()){
				
				$child = null;
			}
			/* END changed from orginal */
			
			Translatable::enable_locale_filter();

			// if we can't find a page with this URLSegment try to find one that used to have 
			// that URLSegment but changed. See ModelAsController->getNestedController() for similiar logic.
			if (!$child) {
				$child = ModelAsController::find_old_page($action, $this->ID);				
				if ($child) {
					
					$response = new SS_HTTPResponse();
					$params = $request->getVars();
					if (isset($params['url']))
						unset($params['url']);
					$response->redirect(
							Controller::join_links(
									$child->Link(
											Controller::join_links(
													$request->param('ID'), // 'ID' is the new 'URLSegment', everything shifts up one position
													$request->param('OtherID')
											)
									),
									// Needs to be in separate join links to avoid urlencoding
									($params) ? '?' . http_build_query($params) : null
							), 301
					);
					return $response;
				}
			}
		}

		// we found a page with this URLSegment.
		if ($child) {
			$request->shiftAllParams();
			$request->shift();

			$response = ModelAsController::controller_for($child)->handleRequest($request);
		} else {
			// If a specific locale is requested, and it doesn't match the page found by URLSegment,
			// look for a translation and redirect (see #5001). Only happens on the last child in
			// a potentially nested URL chain.
			if ($request->getVar('locale') && $this->dataRecord && $this->dataRecord->Locale != $request->getVar('locale')) {
				$translation = $this->dataRecord->getTranslation($request->getVar('locale'));
				if ($translation) {
					$response = new SS_HTTPResponse();
					$response->redirect($translation->Link(), 301);
					throw new SS_HTTPResponse_Exception($response);
				}
			}			
			Director::set_current_page($this->data());
			$response = parent::handleRequest($request);
			Director::set_current_page(null);
		}

		return $response;
	}
	
	
	
	/**
	 * Overload Menu from ContentController
	 */
	public function getMenu($level = 1) {
		/*Modded*/
		$extraFilter="";
		if(Object::has_extension("MultilingualPage","Multilingual_SiteTree")){
			$lang = Multilingual::current_lang();
			if($lang!=Multilingual::default_lang()){
				$extraFilter.=" AND \"LangActive_".$lang."\"=1";
			}else{
				$extraFilter.=" AND \"LangActive\"=1";
			}
		}
		/*end modded*/
		
		if($level == 1) {
			
			$result = DataObject::get("SiteTree", "\"ShowInMenus\" = 1 AND \"ParentID\" = 0".$extraFilter);

		} else {
			$parent = $this->data();
			$stack = array($parent);
			
			if($parent) {
				while($parent = $parent->Parent) {
					array_unshift($stack, $parent);
				}
			}
			
			if(isset($stack[$level-2])) $result = $stack[$level-2]->Children();
		}

		$visible = array();

		// Remove all entries the can not be viewed by the current user
		// We might need to create a show in menu permission
 		if(isset($result)) {
			foreach($result as $page) {
				if($page->canView()) {
					$visible[] = $page;
				}
			}
		}

		return new DataObjectSet($visible);
	}

}

?>
