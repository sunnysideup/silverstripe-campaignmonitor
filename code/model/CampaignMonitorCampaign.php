<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaign extends DataObject {

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

	private static $default_sort = "Hide ASC, SentDate DESC";


	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->makeFieldReadonly("CampaignID");
		$fields->makeFieldReadonly("WebVersionURL");
		$fields->makeFieldReadonly("WebVersionTextURL");
		$fields->makeFieldReadonly("SentDate");
		$fields->makeFieldReadonly("HasBeenSent");
		//pages
		$fields->removeFieldFromTab("Root", "Pages");
		$source = CampaignMonitorSignupPage::get()->map("ID", "Title")->toArray();
		$fields->removeFieldFromTab("Root", "CreatedFromWebsite");

		if(count($source))  {
			$fields->addFieldToTab("Root.Main", new CheckboxSetField("Pages", "Shown on the following pages ...", $source));
		}

		if($this->HasBeenSentCheck()) {
			$fields->removeFieldFromTab("Root", "CreateFromWebsite");
			$fields->addFieldToTab("Root.Main", new LiteralField("Link", "<h2><a target\"_blank\" href=\"".$this->Link()."\">Link</a></h2>"), "CampaignID");
		}
		else {
			$fields->removeFieldFromTab("Root", "Hide");
			if($this->CreatedFromWebsite) {
				$fields->removeFieldFromTab("Root", "CreateFromWebsite");
				$fields->addFieldToTab("Root.Main", new LiteralField("PreviewLink", "<h2><a target\"_blank\" href=\"".$this->PreviewLink()."\">Preview Link</a></h2>"), "CampaignID");
			}
			elseif(!$this->exists()) {
				$fields->removeFieldFromTab("Root", "CreateFromWebsite");
			}
		}
		return $fields;

	}

	function Link($action = ""){
		if($page = $this->Pages()->First()) {
			$link = $page->Link("viewcampaign".$action."/".$this->ID);
			return Director::absoluteURL($link);
		}
		return "#";
	}


	function PreviewLink($action = ""){
		if($page = $this->Pages()->First()) {
			$link = $page->Link("previewcampaign".$action."/".$this->ID);
			return Director::absoluteURL($link);
		}
		return "";
	}

	function getNewsletterContent(){
		$extension = $this->extend("updateNewsletterContent", $content);
		if($extension !== null) {
			return $extension[0];
		}
		return $this->Content;
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
		if(!$this->CampaignID  && $this->CreateFromWebsite) {
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
			if($this->CreatedFromWebsite) {
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

	public function HasBeenSentCheck(){
		if(!$this->CampaignID) {
			return false;
		}
		if(!$this->HasBeenSent) {
			$api = $this->getAPI();
			$result = $this->api->getCampaigns();
			if(isset($result)) {
				foreach($result as $key => $campaign) {
					if($this->CampaignID == $campaign->CampaignID) {
						$this->HasBeenSent = true;
						$this->HasBeenSent->write();
						return true;
					}
				}
			}
			else {
				user_error("error");
			}
		}
		return $this->HasBeenSent;
	}


}

