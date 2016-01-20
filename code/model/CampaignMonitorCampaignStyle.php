<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaignStyle extends DataObject {

	private static $db = array(
		"Title" => "Varchar(100)",
		"TemplateName" => "Varchar(200)",
		"CSSFiles" => "Text"
	);

	private static $indexes = array(
		"Title" => true
	);

	private static $has_many = array(
		"CampaignMonitorCampaign" => "CampaignMonitorCampaign"
	);

	private static $searchable_fields = array(
		"Title" => "PartialMatchFilter"
	);

	private static $summary_fields = array(
		"Title" => "Title"
	);

	private static $singular_name = "Campaign Template";

	private static $plural_name = "Campaign Templates";

	private static $default_template = "CampaignMonitorCampaign";

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab("Root.Debug", ReadonlyField::create("TemplateName"));
		$fields->addFieldToTab("Root.Debug", ReadonlyField::create("FileLocation"));
		$fields->addFieldToTab("Root.Debug", ReadonlyField::create("CSSFiles"));
		return $fields;
	}

	function canCreate($member = null) {
		return false;
	}

	function getFoldersToSearch() {
		$array = array(
			Director::baseFolder()."/campaignmonitor/templates/Email/",
			Director::baseFolder() ."/".SSViewer::get_theme_folder()."_campaignmonitor/templates/Email/"
		);
		foreach($array as $key => $folder) {
			if(!file_exists($folder)) {
				unset($array[$key]);
			}
		}
		return $array;
	}

	/**
	 *
	 * @return string | null
	 */
	function getFileLocation(){
		foreach($this->getFoldersToSearch() as $folder) {
			$fileLocation = $folder."/".$this->TemplateName.".ss";
			if(file_exists($fileLocation)) {
				return $fileLocation;
			}
		}
		$this->delete();
	}

	function getCSSFiles(){
		return implode("," $this->getCSSFilesAsArray());
	}
	function getCSSFilesAsArray(){
		$dom = new DOMDocument();
		$cssFiles = array();
		$fileLocation = $this->getFileLocation();
		if($fileLocation) {
			$dom->loadHTMLFile($fileLocation); // Can replace with $dom->loadHTML($str);
			$linkTags = $dom->getElementsByTagName('link');
			foreach($linkTags as $linkTag){
				if(strtolower($linkTag->getAttribute("rel")) == "stylesheet") {
					$cssFiles[] = $linkTag->getAttribute("href");
				}
				 // if $link_tag rel == stylesheet
				 //   get href value and load CSS
			}
		}
		else {
			user_error("Can not find file");
		}
	}

	function requireDefaultRecords(){
		parent::requireDefaultRecords();
		$templates = array();
		foreach($this->getFoldersToSearch() as $folder) {
			$finder = new SS_FileFinder();
			$finder->setOption('name_regex', '/^.*\.ss$/');
			$found = $finder->find($folder);
			foreach ($found as $key => $value) {
				$template = pathinfo($value);
				$templates[$template['filename']] = $template['filename'];
			}
		}
		foreach($templates as $template) {
			$filter = array("TemplateName" => $template);
			$obj = CampaignMonitorCampaignStyle::get()->filter($filter)->first();
			if(!$obj) {
				$obj = CampaignMonitorCampaignStyle::create($filter+array("Title" => $template));
				$obj->write();
			}
		}
		$excludes = $obj = CampaignMonitorCampaignStyle::get()->exclude(array("TemplateName" => $templates));
		foreach($excludes as $exclude) {
			$exclude->delete();
		}
	}

}

