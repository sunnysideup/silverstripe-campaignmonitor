<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaign extends DataObject {

	private static $db = array(
		"CampaignID" => "Varchar(40)",
		"Subject" => "Varchar(255)",
		"Name" => "Varchar(255)",
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
		"Title" => "PartialMatchFilter",
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

}

