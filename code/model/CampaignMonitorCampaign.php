<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaign extends DataObject {

	private static $db = array(
		"CampaignID" => "Varchar(40)",
		"Name" => "Varchar(100)",
		"Subject" => "Varchar(100)",
		"FromName" => "Varchar(100)",
		"FromEmail" => "Varchar(100)",
		"ReplyTo" => "Varchar(100)",
		"SentDate" => "SS_Datetime",
		"WebVersionURL" => "Varchar(255)",
    "WebVersionTextURL" => "Varchar(255)",
    "Content" => "HTMLText",
    "Hide" => "Boolean"
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
		$fields->removeFieldFromTab("Root", "Pages");
		$source = CampaignMonitorSignupPage::get()->map("ID", "Title")->toArray();
		if(count($source))  {
			$fields->addFieldToTab("Root.Main", new CheckboxSetField("Pages", "Shown on the following pages ...", $source));
		}
		return $fields;
	}

	function Link($action = ""){
		if($page = $this->Pages()->First()) {
			$link = $page->Link("viewcampaign".$action."/".$this->CampaignID);
			return $link;
		}
		return "#";
	}


	function PreviewLink($action = ""){
		if($page = $this->Pages()->First()) {
			$link = $page->Link("previewcampaign".$action."/".$this->CampaignID);
			return $link;
		}
		return "#";
	}

	function onAfterWrite(){
		parent::onAfterWrite();
		if(!$this->CampaignID) {
			$api = $this->getAPI();
			$api->createCampaign(

			);
		}
	}

	private static $_api = null;

	/**
	 *
	 * @return CampaignMonitorAPIConnector
	 */
	public function getAPI(){
		if(!self::$_api) {
			self::$_api = CampaignMonitorAPIConnector::create();
			self::$_api->init();
		}
		return self::$_api;
	}


	function canCreate($member = null){
		return parent::canCreate($member);
	}

	function canDelete($member = null){
		return false;
	}


}

