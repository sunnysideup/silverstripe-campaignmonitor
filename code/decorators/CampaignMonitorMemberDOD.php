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
	private static $_api = null;

	/**
	 *
	 * @return CampaignMonitorAPIConnector
	 */
	private function getCMAPI(){
		if(!self::$_api) {
			self::$_api = CampaignMonitorAPIConnector::create();
			self::$_api->init();
		}
		return self::$_api;
	}

	/**
	 * returns a form field for signing up to all available lists
	 * or if a list is provided, for that particular list.
	 *
	 * @param CampaignMonitorSignupPage | string | Null $listPage
	 * @param string $fieldName
	 * @param string $fieldTitle
	 *
	 * @return FormField
	 */
	public function getCampaignMonitorSignupField($listPage = null, $fieldName = "", $fieldTitle = "") {
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
					if($api->getSubscriberCanReceiveEmailsForThisList($listPage->ListID, $this->owner)) {
						$currentSelection = "Unsubscribe";
					}
				}
				if(!$fieldTitle) {
					$fieldTitle = _t("CampaignMonitorSignupPage.SIGNUP_FOR", "Sign up for ")." ".$listPage->getListTitle();
				}
				$segments = $listPage->CampaignMonitorSegments()->filter(array("AutomaticallyAddUser" => 0, "ShowToUser" => 1));
				$subscribeField = OptionsetField::create($fieldName, $fieldTitle, $optionArray, $currentSelection);
				if($segments && count($segments)) {
					foreach($segments as $segment) {
						$segmentOptions[$segment->SegmentID] = $segment->Title;
					}
					$segmentField = new CheckboxSetField(
						$fieldName."_Segment",
						_t("CampaignMonitorMemberDOD.SELECT_INTERESTS", "select interests"),
						$segmentOptions
					);
					$field = CompositeField::create($subscribeField, $segmentField);
				}
				else {
					$field = CompositeField::create($subscribeField);
				}
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
	 * action subscription form
	 * @param CampaignMonitorSignUpPage $page
	 * @param Array $array
	 * @param Form $form
	 *
	 * return string: can be subscribe / unsubscribe / error
	 */
	function processCampaignMonitorSignupField($page, $data, $form) {
		$typeOfAction = "unsubscribe";
		//many choices
		if(isset($data["SubscribeManyChoices"])) {
			$listPages = CampaignMonitorSignupPage::get_ready_ones();
			foreach($listPages as $listPage) {
				if(isset($data["SubscribeManyChoices"][$listPage->ListID]) && $data["SubscribeManyChoices"][$listPage->ListID]) {
					$this->owner->addCampaignMonitorList($listPage->ListID);
					$typeOfAction = "subscribe";
				}
				else {
					$this->owner->removeCampaignMonitorList($listPage->ListID);
				}
			}
		}
		//one choice
		elseif(isset($data["SubscribeChoice"])) {
			$params = array();
			if(isset($data["SubscribeChoice_Segment"])) {
				$params = $data["SubscribeChoice_Segment"];
			}
			if($data["SubscribeChoice"] == "Subscribe") {
				print_r($data);
				die("DDD");
				$this->owner->addCampaignMonitorList($page->ListID, $params);
				$typeOfAction = "subscribe";
			}
			else {
				$this->owner->removeCampaignMonitorList($page->ListID);
			}
		}
		else {
			user_error("Subscriber field missing", E_USER_WARNING);
		}
		return $typeOfAction;
	}

	/**
	 * immediately unsubscribe if you are logged in.
	 * @param HTTPRequest
	 */
	function unsubscribe($request) {
		$member = Member::currentUser();
		if ($member) {
			$member->removeCampaignMonitorList($this->ListID);
			$this->Content = $member->Email." has been removed from this list: ".$this->getListTitle();
		}
		else {
			Security::permissionFailure($this, _t("CAMPAIGNMONITORSIGNUPPAGE.LOGINFIRST", "Please login first."));
		}
		return array();
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
		if(is_string($listPage)) {
			$listPage = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listPage))->first();
		}
		//internal database
		if($listPage && $listPage->GroupID) {
			if($gp = Group::get()->byID($listPage->GroupID)) {
				$groups = $this->owner->Groups();
				if($groups) {
					$this->owner->Groups()->add($gp);
					$outcome++;
				}
			}
		}
		if($listPage && $listPage->ListID) {
			if(!$api->addSubscriber(
				$listPage->ListID,
				$this->owner,
				$customFields,
				true,
				false
			)) {
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
		if(is_string($listPage)) {
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
		if($lists && count($lists)) {
			foreach($lists as $listArray) {
				if(in_array($listArray["SubscriberState"], array("Active", "Bounced"))) {
					$array[$listArray["ListID"]] = $listArray["ListID"];
				}
			}
		}
		return $array;
	}

}
