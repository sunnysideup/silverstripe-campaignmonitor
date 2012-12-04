<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaign extends DataObject {

	public static $db = array(
		"CampaignID" => "Varchar(40)",
		"Subject" => "Varchar(255)",
		"Name" => "Varchar(255)",
		"SentDate" => "SS_Datetime",
		"TotalRecipients" => "Int"
	);

	public static $has_one = array(
		"Parent" => "CampaignMonitorSignupPage"
	);

	public static $searchable_fields = array(
		"Title" => "PartialMatchFilter"
	);
	public static $summary_fields = array(
		"Subject" => "Subject",
		"SentDate" => "Sent Date"
	);

	public static $singular_name = "Campaigns";

	public static $plural_name = "Campaign";

	public static $default_sort = "SentDate DESC";

}

