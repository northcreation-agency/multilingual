<?php
class MultilingualModelAsController extends ModelAsController {
	function init() {			
		parent::init();
		if(isset($_SERVER['REQUEST_URI'])){			
			$baseUrl = Director::baseUrl();
			$requestUri = $_SERVER['REQUEST_URI'];
			$lang = substr($requestUri, strlen($baseUrl), 2);						
			/*if(empty($lang)){
				
				Multilingual::set_current_lang(Multilingual::start_lang());	
				$localesmap= Multilingual::map_locale();						
				if(!empty($localesmap[$lang])){ 
					i18n::set_locale($localesmap[$lang]); //Setting the locale 
				}else { 
					i18n::set_locale($localesmap[Multilingual::start_lang()]); // default locale
				}       
			}*/
			
			
			Multilingual::set_current_lang($lang);	
			$localesmap= Multilingual::map_locale();						
			if(!empty($localesmap[$lang])){ 
				i18n::set_locale($localesmap[$lang]); //Setting the locale 
			}else { 
				i18n::set_locale($localesmap[Multilingual::default_lang()]); // default locale
			}
		}		
		if($this->request->latestParam("URLSegment") == "" || $this->request->latestParam("URLSegment") == 'home') {						
			
			$_POST['this_is_a_hack_to_stop_the_home_redirect'] = true; 			 			
			$urlparams = array('URLSegment' => 'home', 'Action' => ' ');			
			$this->setURLParams($urlparams);
		}
		
	}		
	/**
	 * @return ContentController
	 */
	public function getNestedController() {
		$request = $this->request;		
			
		
		
		$params=$this->getURLParams();		
		$URLSegment = $request->param('URLSegment')?$request->param('URLSegment'):$params['URLSegment'];		
		if(!$URLSegment) {
			throw new Exception('ModelAsController->getNestedController(): was not passed a URLSegment value.');
		}
				
		// Find page by link, regardless of current locale settings
		Translatable::disable_locale_filter();		
		
		
		/* modded from original */
		$lang=Multilingual::current_lang();
		if(Multilingual::$use_URLSegment && Multilingual::default_lang()!=$lang){
			$urlsegmentfield="URLSegment_".$lang;
			
		}else{
			$urlsegmentfield="URLSegment";
			
		}
		$sitetree = DataObject::get_one(
			'SiteTree', 
			sprintf(
				'"'.$urlsegmentfield.'" = \'%s\' %s', 
				Convert::raw2sql($URLSegment), 
				(SiteTree::nested_urls() ? 'AND "ParentID" = 0' : null)
			)
		);
		/* end modded*/
		
		
				
		Translatable::enable_locale_filter();
		
		if(!$sitetree) {			
			
			// If a root page has been renamed, redirect to the new location.
			// See ContentController->handleRequest() for similiar logic.
			$redirect = self::find_old_page($URLSegment);
			if($redirect = self::find_old_page($URLSegment)) {
				$params = $request->getVars();
				if(isset($params['url'])) unset($params['url']);
				$this->response = new SS_HTTPResponse();
				$this->response->redirect(
					Controller::join_links(
						$redirect->Link(
							Controller::join_links(
								$request->param('Action'), 
								$request->param('ID'), 
								$request->param('OtherID')
							)
						),
						// Needs to be in separate join links to avoid urlencoding
						($params) ? '?' . http_build_query($params) : null
					),
					301
				);
				
				return $this->response;
			}
			
			if($response = ErrorPage::response_for(404)) {
				return $response;
			} else {
				$this->httpError(404, 'The requested page could not be found.');
			}
		}
		
		// Enforce current locale setting to the loaded SiteTree object
		if($sitetree->Locale) Translatable::set_current_locale($sitetree->Locale);
		
		if(isset($_REQUEST['debug'])) {
			Debug::message("Using record #$sitetree->ID of type $sitetree->class with link {$sitetree->Link()}");
		}
		
		return self::controller_for($sitetree, $this->request->param('Action'));
	}
	
	
	/**
	 * Modded from original to make alternative URLSegments to work (from multilingual)
	 * 
	 * @param string $URLSegment A subset of the url. i.e in /home/contact/ home and contact are URLSegment.
	 * @param int $parentID The ID of the parent of the page the URLSegment belongs to. 
	 * @return SiteTree
	 */
	static function find_old_page($URLSegment,$parentID = 0, $ignoreNestedURLs = false) {
		$lang=Multilingual::current_lang();
		
		if(Multilingual::$use_URLSegment && Multilingual::default_lang()!=$lang){
			$urlsegmentfield="URLSegment_".$lang;			
		}else{
			$urlsegmentfield="URLSegment";			
		}
		$URLSegment = Convert::raw2sql($URLSegment);
		
		$useParentIDFilter = SiteTree::nested_urls() && $parentID;
				
		// First look for a non-nested page that has a unique URLSegment and can be redirected to.
		if(SiteTree::nested_urls()) {
			$pages = DataObject::get(
				'SiteTree', 
				"\"".$urlsegmentfield."\" = '$URLSegment'" . ($useParentIDFilter ? ' AND "ParentID" = ' . (int)$parentID : '')
			);
			if($pages && $pages->Count() == 1) return $pages->First();
		}
		
		// Get an old version of a page that has been renamed.
		$query = new SQLQuery (
			'"RecordID"',
			'"SiteTree_versions"',
			"\"".$urlsegmentfield."\" = '$URLSegment' AND \"WasPublished\" = 1" . ($useParentIDFilter ? ' AND "ParentID" = ' . (int)$parentID : ''),
			'"LastEdited" DESC',
			null,
			null,
			1
		);
		$record = $query->execute()->first();
		
		if($record && ($oldPage = DataObject::get_by_id('SiteTree', $record['RecordID']))) {
			// Run the page through an extra filter to ensure that all decorators are applied.
			if(SiteTree::get_by_link($oldPage->RelativeLink())) return $oldPage;
		}
	}

}

?>
