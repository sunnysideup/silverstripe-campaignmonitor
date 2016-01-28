<?php

/**
 * @author nicolaas [at] sunnysideup.co.nz
 *
 * @description: this represents a sub group of a list, otherwise known as a segment
 *
 **/

class CampaignMonitorCustomField extends DataObject {

	private static $db = array(
		"Code" => "Varchar(64)",
		"Title" => "Varchar(64)",
		"Type" => "Varchar(32)",
		"Options" => "Text",
		"Visible" => "Boolean",
		"ListID" => "Varchar(32)"
	);

	private static $casting = array(
		"Key" => "Varchar"
	);

	private static $summary_fields = array(
		"Title" => "Title"
	);

	private static $indexes = array(
		"ListID" => true,
		"Code" => true
	);

	private static $has_one = array(
		"CampaignMonitorSignupPage" => "CampaignMonitorSignupPage"
	);

	/**
	 * form field matcher between CM and SS CMField => SSField
	 * @return array
	 */
	private static $field_translator = array(
		"MultiSelectOne" => "OptionSetField",
		"Text" => "Text",
		"Number" => "NumericField"
	);


	function canCreate($member = null) {
		return false;
	}

	function canDelete($member = null) {
		return false;
	}

	function canEdit($member = null) {
		return false;
	}

	function onBeforeWrite(){
		parent::onBeforeWrite();
		$this->Code = self::key_to_code($this->Code);
	}

	function getKey(){
		return "[".$this->Code."]";
	}

	function getOptionsAsArray(){
		return array("" => _t("CampaignMonitor.PLEASE_SELECT", "-- please select --"))+explode(",",$this->Options);
	}

	public static create_from_campaign_monitor_object($object, $listID) {
		$filterOptions = array(
			"ListID" => $listID,
			"Code" => self::key_to_code($object->Key)
		);
		$obj = CampaignMonitorCustomField::get()->filter($filterOptions);
		if(!$obj) {
			$obj = CampaignMonitorCustomField::create($filterOptions);
		}
		$page = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listID))->first();
		if($page) {
			$obj->CampaignMonitorSignupPageID = $page->ID;
		}
		$obj->ListID = $listID;
		$obj->Code = self::key_to_code($customFieldsObject->Key);
		$obj->Title = $customFieldsObject->FieldName;
		$obj->Type = $customFieldsObject->DataType;
		$obj->Options = implode(",",$customFieldsObject->FieldOptions);
		$obj->Visible = $customFieldsObject->VisibleInPreferenceCenter;
		$obj->write();
	}

	private static key_to_code($key) {
		return str_replace(array("[", "]"), "", $key);
	}

}


