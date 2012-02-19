<?php
class MultilingualModelAsController extends ModelAsController {
	function init() {			
		parent::init();
		if(isset($_SERVER['REQUEST_URI'])){			
			$baseUrl = Director::baseUrl();
			$requestUri = $_SERVER['REQUEST_URI'];
			$lang = substr($requestUri, strlen($baseUrl), 2);			
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
		
		/* modded from original */
		$params=$this->getURLParams();		
		$URLSegment = $request->param('URLSegment')?$request->param('URLSegment'):$params['URLSegment'];		
		if(!$URLSegment) {
			throw new Exception('ModelAsController->getNestedController(): was not passed a URLSegment value.');
		}
		/* end modded*/
		
		
		// Find page by link, regardless of current locale settings
		Translatable::disable_locale_filter();
		$sitetree = DataObject::get_one(
			'SiteTree', 
			sprintf(
				'"URLSegment" = \'%s\' %s', 
				Convert::raw2sql($URLSegment), 
				(SiteTree::nested_urls() ? 'AND "ParentID" = 0' : null)
			)
		);
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

}

?>
