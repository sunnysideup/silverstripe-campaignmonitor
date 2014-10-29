<?php

/**
 * @author nicolaas [at] sunnysideup.co.nz
 * TO DO: only apply the on afterwrite to people in the subscriber group.
 *
 **/

class CampaignMonitorMemberDOD extends DataExtension {

	/**
	 * name of the field to use for sign-ups
	 * @var String
	 */
	private static $campaign_monitor_signup_fieldname = "CampaignMonitorSubscriptions";

	/**
	 *
	 *
	 * @var null | CampaignMonitorAPIConnector
	 *
	 */
	private static $api = null;

	/**
	 *
	 * @return CampaignMonitorAPIConnector
	 */
	private function getCMAPI(){
		if(!self::$api) {
			self::$api = CampaignMonitorAPIConnector::create();
			self::$api->init();
		}
		return self::$api;
	}

	/**
	 * returns a form field for signing up to all available lists
	 * or if a list is provided, for that particular list.
	 *
	 * @param CampaignMonitorSignupPage | Null $listPage
	 *
	 * @return FormField
	 */
	public function getSignupField($listPage = null, $fieldName = "", $fieldTitle = "") {
		if(!is_object($listPage)) {
			$listPage = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listPage))->first();
		}
		$field = null;
		if(!$fieldName) {
			$fieldName = Config::inst()->get("CampaignMonitorMemberDOD", "campaign_monitor_signup_fieldname");
		}
		if($listPage) {
			if($listPage->ReadyToReceiveSubscribtions()) {
				$currentSelection = "Subscribe";
				$optionArray = array();
				$optionArray["Subscribe"] = _t("CampaignMonitorSignupPage.SUBSCRIBE_TO", "subscribe to")." ".$listPage->getListTitle();
				$optionArray["Unsubscribe"] = _t("CampaignMonitorSignupPage.UNSUBSCRIBE_FROM", "unsubscribe from ")." ".$listPage->getListTitle();
				if($this->owner->exists()) {
					$api = $this->getCMAPI();
					if($api->getSubscriberCanReceiveEmailsForThisList($this->owner)) {
						$currentSelection = "Unsubscribe";
					}
				}
				if(!$fieldTitle) {
					$fieldTitle = _t("CampaignMonitorSignupPage.SIGNUP_FOR", "Sign up for ")." ".$listPage->getListTitle();
				}
				$field = OptionsetField::create($fieldName, $fieldTitle, $optionArray, $currentSelection);
			}
		}
		else {
			if(!$fieldTitle) {
				$fieldTitle = _t("CampaignMonitorMemberDOD.NEWSLETTERSIGNUP", "Newsletter sign-up");
			}
			$lists = CampaignMonitorSignupPage::get_ready_ones();
			$array = array();
			foreach($lists as $list) {
				$array[$list->ListID] = $list->getListTitle();
			}
			if(count($array)) {
				$field = new CheckboxSetField(
					$fieldName,
					$fieldTitle,
					$array
				);
				$field->setDefaultItems($this->owner->CampaignMonitorSignupPageIDs());
			}
		}
		if(!$field) {
			$field = ReadonlyField::create(
				$fieldName,
				$fieldTitle,
				_t("CampaignMonitorMemberDOD.NO_LISTS_AVAILABLE", "No lists available right now.  Please come back soon.")
			);
		}
		return $field;
	}

	/**
	 * is this user currently signed up to one or more newsletters
	 *
	 * @return Boolean
	 */
	public function IsCampaignMonitorSubscriber() {
		CampaignMonitorSignupPage::get_ready_ones()
			->where("MemberID = ".$this->owner->ID)
			->innerJoin("Group_Members", "CampaignMonitorSignupPage ON CampaignMonitorSignupPage.GroupID = Group_Members.GroupID")
			->count() ? true : false;
	}

	/**
	 * add to Group
	 * add to CM database...
	 * @param CampaignMonitorSignupPage | Int $listPage
	 * @param array $customFields
	 * @return Boolean - returns true on success
	 */
	public function addCampaignMonitorList($listPage, $customFields = array()) {
		$api = $this->getCMAPI();
		$outcome = 0;
		if(is_numeric($listPage)) {
			$listPage = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listPage))->first();
		}
		//internal database
		if($listPage->GroupID) {
			if($gp = Group::get()->byID($listPage->GroupID)) {
				$groups = $this->owner->Groups();
				if($groups) {
					$this->owner->Groups()->add($gp);
					$outcome++;
				}
			}
		}
		if($listPage->ListID) {
			if(!$api->addSubscriber($listPage->ListID, $this->owner, $customFields, true, false)) {
				$outcome++;
			}
		}
		if($outcome > 1) {
			return true;
		}
		return false;
	}

	/**
	 *
	 * remove from Group
	 * remove from CM database...
	 * @param CampaignMonitorSignupPage | Int $listPage
	 * @return boolean returns true if successful.
	 */
	public function removeCampaignMonitorList($listPage) {
		$api = $this->getCMAPI();
		$outcome = 0;
		if(is_numeric($listPage)) {
			$listPage = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listPage))->first();
		}
		if($listPage->GroupID) {
			if($gp = Group::get()->byID($listPage->GroupID)) {
				$groups = $this->owner->Groups();
				if($groups) {
					$this->owner->Groups()->remove($gp);
					$outcome++;
				}
			}
		}
		if($listPage->ListID) {
			if(!$api->deleteSubscriber($listPage->ListID, $this->owner)) {
				$outcome++;
			}
		}
		if($outcome > 1) {
			return true;
		}
		return false;
	}

	/**
	 * returns a list of list IDs
	 * that the user is currently subscribed to.
	 *
	 * @return Array
	 */
	public function CampaignMonitorSignupPageIDs() {
		$api = $this->getCMAPI();
		$lists = $api->getListsForEmail($this->owner);
		$array = Array();
		foreach($lists as $listArray) {
			if(in_array($listArray["SubscriberState"], array("Active", "Bounced"))) {
				$array[$listArray["ListID"]] = $listArray["ListID"];
			}
		}
		return $array;
	}

}
