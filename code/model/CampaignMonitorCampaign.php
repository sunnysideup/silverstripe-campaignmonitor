<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaign extends DataObject {

	/**
	 *
	 * @var array
	 */ 
	private static $emogrifier_add_allowed_media_types = array(
		"media screen and (min-width: 0)"
	);
	
	/**
	 *
	 * @var array
	 */ 
	private static $emogrifier_remove_allowed_media_types = array();

	private static $db = array(
		"HasBeenSent" => "Boolean",
		"CreateFromWebsite" => "Boolean",
		"CreatedFromWebsite" => "Boolean",
		"CampaignID" => "Varchar(40)",
		"Name" => "Varchar(100)",
		"Subject" => "Varchar(100)",
		"FromName" => "Varchar(100)",
		"FromEmail" => "Varchar(100)",
		"ReplyTo" => "Varchar(100)",
		"SentDate" => "SS_Datetime",
		"WebVersionURL" => "Varchar(255)",
		"WebVersionTextURL" => "Varchar(255)",
		"Hide" => "Boolean",
		"Content" => "HTMLText"
	);

	private static $indexes = array(
		"CampaignID" => true,
		"Hide" => true
	);

	private static $field_labels = array(
		"CreateFromWebsite" => "Create on newsletter server"
	);

	private static $has_one = array(
		"CampaignMonitorCampaignStyle" => "CampaignMonitorCampaignStyle"
	);

	private static $many_many = array(
		"Pages" => "CampaignMonitorSignupPage"
	);

	private static $searchable_fields = array(
		"Subject" => "PartialMatchFilter",
		"Hide" => "ExactMatch"
	);

	private static $summary_fields = array(
		"Subject" => "Subject",
		"SentDate" => "Sent Date"
	);

	private static $singular_name = "Campaigns";

	private static $plural_name = "Campaign";

	private static $default_template = "CampaignTemplate";

	private static $default_sort = "Hide ASC, SentDate DESC";

	function canDelete($member = null){
		return $this->HasBeenSentCheck() ? false : parent::canDelete($member);
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		//readonly
		$fields->makeFieldReadonly("CampaignID");
		$fields->makeFieldReadonly("WebVersionURL");
		$fields->makeFieldReadonly("WebVersionTextURL");
		$fields->makeFieldReadonly("SentDate");
		$fields->makeFieldReadonly("HasBeenSent");
		//removed
		$fields->removeFieldFromTab("Root.Main", "CreatedFromWebsite");
		//pages
		$source = CampaignMonitorSignupPage::get()->map("ID", "Title")->toArray();
		$fields->removeFieldFromTab("Root.Main", "Pages");
		if(count($source))  {
			$fields->addFieldToTab("Root.Pages", new CheckboxSetField("Pages", "Shown on the following pages ...", $source));
		}
		if($this->ExistsOnCampaignMonitorCheck()){
			$fields->removeFieldFromTab("Root.Main", "CreateFromWebsite");
			$fields->removeFieldFromTab("Root.Main", "CampaignMonitorCampaignStyleID");
			if(!$this->HasBeenSentCheck()) {
				$fields->addFieldToTab("Root.Main", new LiteralField("CreateFromWebsiteRemake", "<h2>To edit this newsletter, please first delete it from your newsletter server</h2>"), "CampaignID");
			}
			$fields->makeFieldReadonly("Name");
			$fields->makeFieldReadonly("Subject");
			$fields->makeFieldReadonly("FromName");
			$fields->makeFieldReadonly("FromEmail");
			$fields->makeFieldReadonly("ReplyTo");
			$fields->makeFieldReadonly("SentDate");
			$fields->makeFieldReadonly("WebVersionURL");
			$fields->makeFieldReadonly("WebVersionTextURL");
			$fields->makeFieldReadonly("Content");
		}
		if($this->HasBeenSentCheck()) {
			$fields->addFieldToTab("Root.Main", new LiteralField("Link", "<h2><a target=\"_blank\" href=\"".$this->Link()."\">Link</a></h2>"), "CampaignID");
		}
		else {
			$fields->removeFieldFromTab("Root.Main", "Hide");
			if($this->exists()) {
				if($this->ExistsOnCampaignMonitorCheck()) {
					$fields->removeFieldFromTab("Root.Main", "CreateFromWebsite");
				}
				else {
					$fields->addFieldToTab("Root.Main", new LiteralField("PreviewLink", "<h2><a target\"_blank\" href=\"".$this->PreviewLink()."\">Preview Link</a></h2>"), "CampaignID");
				}
			}
			else {
				$fields->removeFieldFromTab("Root.Main", "CreateFromWebsite");
			}
		}
		return $fields;
	}

	function Link($action = ""){
		if($page = $this->Pages()->First()) {
			$link = $page->Link("viewcampaign".$action."/".$this->ID."/");
			return Director::absoluteURL($link);
		}
		return "#";
	}


	function PreviewLink($action = ""){
		if($page = $this->Pages()->First()) {
			$link = $page->Link("previewcampaign".$action."/".$this->ID."/");
			return Director::absoluteURL($link);
		}
		return "";
	}

	function getNewsletterContent(){
		$extension = $this->extend("updateNewsletterContent", $content);
		if(is_array($extension) && count($extension)) {
			return $extension[0];
		}
		$isThemeEnabled = Config::inst()->get('SSViewer', 'theme_enabled');
		if(!$isThemeEnabled) {
			Config::inst()->update('SSViewer', 'theme_enabled', true);
		}
		Requirements::clear();
		$templateName = $this->getBestTemplate();
		$html = $this->renderWith($templateName);
		if(!$isThemeEnabled) {
			Config::inst()->update('SSViewer', 'theme_enabled', false);
		}
		if(class_exists('\Pelago\Emogrifier')) {
			$allCSS = "";
			$cssFileLocations = $this->getCSSFileLocations();
			foreach($cssFileLocations as $cssFileLocation) {
				$cssFileHandler = fopen($cssFileLocation, 'r');
				$allCSS .= fread($cssFileHandler,  filesize($cssFileLocation));
				fclose($cssFileHandler);
			}
			$emogrifier = new \Pelago\Emogrifier($html, $allCSS);
			$addMediaTypes = $this->Config()->get("emogrifier_add_allowed_media_types");
			foreach($addMediaTypes as $type) {
				$emogrifier->addAllowedMediaType($type);
			}
			$removeMediaTypes = $this->Config()->get("emogrifier_remove_allowed_media_types");
			foreach($removeMediaTypes as $type) {
				$emogrifier->removeAllowedMediaType($type);
			}
			$html = $emogrifier->emogrify();
		}
		return $html;
	}

	/**
	 * @return string
	 */
	protected function getBestTemplate(){
		if($style = $this->CampaignMonitorCampaignStyle()) {
			if($style->exists() && $style->TemplateName) {
				return $style->TemplateName;
			}
		}
		return "CampaignMonitorCampaign";
	}

	/**
	 * @return array
	 */
	protected function getCSSFileLocations(){
		if($style = $this->CampaignMonitorCampaignStyle()) {
			return $style->getCSSFilesAsArray();
		}
		return array();
	}

	function onBeforeWrite(){
		parent::onBeforeWrite();
		if($this->CampaignID) {
			$this->CreateFromWebsite = false;
		}
	}

	function onAfterWrite(){
		parent::onAfterWrite();
		if($this->Pages()->count() == 0) {
			if($page = CampaignMonitorSignupPage::get()->first()) {
				$this->Pages()->add($page);
				$this->write();
			}
		}
		if(!$this->ExistsOnCampaignMonitorCheck()  && $this->CreateFromWebsite) {
			$api = $this->getAPI();
			$api->createCampaign($this);
		}
	}

	function onBeforeDelete(){
		parent::onBeforeDelete();
		if($this->HasBeenSentCheck()) {
			//do nothing
		}
		else {
			if($this->ExistsOnCampaignMonitorCheck()) {
				$api = $this->getAPI();
				$api->deleteCampaign($this->CampaignID);
			}
		}
	}

	private static $_api = null;

	/**
	 *
	 * @return CampaignMonitorAPIConnector
	 */
	protected function getAPI(){
		if(!self::$_api) {
			self::$_api = CampaignMonitorAPIConnector::create();
			self::$_api->init();
		}
		return self::$_api;
	}

	private $_hasBeenSent = null;

	public function HasBeenSentCheck(){
		if($this->_hasBeenSent === null) {
			if(!$this->CampaignID) {
				$this->_hasBeenSent = false;
			}
			elseif(!$this->HasBeenSent) {
				$api = $this->getAPI();
				$result = $this->api->getCampaigns();
				if(isset($result)) {
					foreach($result as $key => $campaign) {
						if($this->CampaignID == $campaign->CampaignID) {
							$this->HasBeenSent = true;
							$this->HasBeenSent->write();
							$this->_hasBeenSent = true;
							break;
						}
					}
				}
			}
			else {
				$this->_hasBeenSent = $this->HasBeenSent;
			}
		}
		return $this->_hasBeenSent;
	}

	private $_existsOnCampaignMonitorCheck = null;

	public function ExistsOnCampaignMonitorCheck(){
		if($this->_existsOnCampaignMonitorCheck === null) {
			if(!$this->CampaignID) {
				$this->_existsOnCampaignMonitorCheck = false;
			}
			else {
				$api = $this->getAPI();
				$result = $this->api->getSummary($this->CampaignID);
				$result = $this->api->getDrafts();
				if(isset($result)) {
					foreach($result as $key => $campaign) {
						if($this->CampaignID == $campaign->CampaignID) {
							$this->_existsOnCampaignMonitorCheck = true;
							break;
						}
					}
				}
				else{
					$this->_existsOnCampaignMonitorCheck = false;
				}
			}
		}
		return $this->_existsOnCampaignMonitorCheck;
	}

}


