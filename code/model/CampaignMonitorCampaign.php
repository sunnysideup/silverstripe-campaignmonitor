<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaign extends DataObject {

	private static $db = array(
		"CampaignID" => "Varchar(40)",
		"Name" => "Varchar(255)",
		"Subject" => "Varchar(255)",
		"SentDate" => "SS_Datetime",
		"WebVersionURL" => "Varchar(255)",
    "WebVersionTextURL" => "Varchar(255)",
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

	function Link($action = null){
		if($page = $this->Pages()->First()) {
			$link = $page->Link("viewcampaign/".$this->CampaignID);
			return $link;
		}
		return "#";
	}

	function canCreate($member = null){
		return false;
	}

	function canDelete($member = null){
		return false;
	}


}

