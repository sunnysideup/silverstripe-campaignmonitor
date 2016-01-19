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
		"FileLocation" => "Text",
		"CSSFiles" => "Text"
	);

	private static $indexes = array(
		"Title" => true
	);

	private static $has_many = array(
		"CampaigMonitorCampaign" => "CampaigMonitorCampaign"
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
		$fields->addFieldToTab("Root.Debug", ReadonlyField("TemplateName"));
		$fields->addFieldToTab("Root.Debug", ReadonlyField("FileLocation"));
		$fields->addFieldToTab("Root.Debug", ReadonlyField("CSSFiles"));
		return $fields;
	}

	function getFileLocation(){
		$themedFile = Director::baseFolder()."/campaignmonitor/templates/Email/".$this->TemplateName.".ss";
		if(file_exists($themedFile)) {
			return $themedFile;
		}
		$unthemedFile = Director::baseFolder() ."/".SSViewer::get_theme_folder()."_campaignmonitor/templates/Email/".$this->TemplateName.".ss";
		if(file_exists($unthemedFile)) {
			return $unthemedFile;
		}
		Director::baseFolder()."/campaignmonitor/templates/Email/CampaignMonitorCampaign".$this->TemplateName.".ss";
		return 
		

	}

	function onBeforeWrite(){
		parent::onBeforeWrite();
		//check file location and/or create one ...
		
		$dom = new DOMDocument();
		$dom->loadHTMLFile('file.html'); // Can replace with $dom->loadHTML($str);

		$linkTags = $dom->getElementsByTagName('link');

		foreach($linkTags as $linkTag){
			print_r($linkTag);
			 // if $link_tag rel == stylesheet
			 //   get href value and load CSS
		}
		
	}

}

