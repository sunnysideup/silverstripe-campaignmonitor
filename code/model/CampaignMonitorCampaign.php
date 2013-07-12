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
		"TotalRecipients" => "Int"
	);

	private static $has_one = array(
		"Parent" => "CampaignMonitorSignupPage"
	);

	private static $searchable_fields = array(
		"Title" => "PartialMatchFilter"
	);
	private static $summary_fields = array(
		"Subject" => "Subject",
		"SentDate" => "Sent Date"
	);

	private static $singular_name = "Campaigns";

	private static $plural_name = "Campaign";

	private static $default_sort = "SentDate DESC";

}

