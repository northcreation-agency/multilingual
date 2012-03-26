<?php

class ActivateLanguageTask extends BuildTask {

	protected $title = 'Activate a language for all pages';
	protected $description = 'Select one language to activate for all pages and dataobjects. /dev/tasks/ActivateLanguageTask.php?en=1';
	protected $enabled = true;

	function run($request) {
		$this->ActivatLang();
		
		DataObject::flush_and_destroy_cache();
	}
	function ActivateLang(){
		$idArray = explode('&', $_SERVER["QUERY_STRING"]);		

		if (sizeof($idArray) > 1) {
			foreach ($idArray as $ignore => $avPair) {
				list($index, $value) = explode("=", $avPair);
				$allowed_langs = array_keys(self::map_locale());
				print_r($allowed_langs);
				if ($value && in_array($index, $allowed_langs)) {
					if ($index == Multilingual::default_lang()) {
						$fieldname = "LangActive";
					} else {
						$fieldname = "LangActive_" . $index;
					}

					//fix dataobjects
					$sqlquery = new SQLQuery("*", "MultilingualDataObject");
					$results = $sqlquery->execute();

					foreach ($results as $obj) {
						if (!empty($obj["ClassName"])) {
							$object = DataObject::get_by_id($obj["ClassName"], $obj["ID"]);
							$object->$fieldname = true;
							$object->write();
						}
					}


					//fix pages
					$sqlquery = new SQLQuery("*", "MultilingualPage");
					$results = $sqlquery->execute();

					foreach ($results as $obj) {
						$object = DataObject::get_by_id("MultilingualPage", $obj["ID"]);
						$object->$fieldname = true;
						if ($object->isPublished()) {
							$object->doPublish();
						} else {
							$object->write();
						}
					}
				}
			}
		}
	}

}