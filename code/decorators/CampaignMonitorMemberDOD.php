<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *TO DO: only apply the on afterwrite to people in the subscriber group.
 *
 **/

class CampaignMonitorMemberDOD extends DataObjectDecorator {

	protected static $campaign_monitor_signup_fieldname = "CampaignMonitorSubscriptions";
		function set_campaign_monitor_signup_fieldname($s) {self::$campaign_monitor_signup_fieldname = $s;}
		function get_campaign_monitor_signup_fieldname() {return self::$campaign_monitor_signup_fieldname;}

	static function get_signup_field() {
		$lists = DataObject::get("CampaignMonitorSignupPage", $where = "ReadyToReceiveSubscribtions = 1");
		if($lists) {
			$field = new CheckboxSetField(self::get_campaign_monitor_signup_fieldname(), _t("CampaignMonitorMemberDOD.NEWSLETTERSIGNUP", "Newsletter sign-up"), $lists->toDropDownMap("ID", "ListTitle"));
			if($m = Member::currentUser()) {
				$field->setDefaultItems($m->CampaignMonitorSubscriptionsPageIdList());
			}
			return $field;
		}
		return null;
	}

	function onBeforeWrite() {
		parent::onBeforeWrite();
		if(isset($_REQUEST[self::get_campaign_monitor_signup_fieldname()]) && count($_REQUEST[self::get_campaign_monitor_signup_fieldname()])) {
			$listsToSignupFor = $_REQUEST[self::get_campaign_monitor_signup_fieldname()];
			$lists = DataObject::get("CampaignMonitorSignupPage", $where = "ReadyToReceiveSubscribtions = 1");
			foreach($lists as $page) {
				if(isset($_REQUEST[self::get_campaign_monitor_signup_fieldname()][$page->ID])) {
					$this->addCampaignMonitorList($page);
				}
				else {
					$this->removeCampaignMonitorList($page);
				}
			}
		}
		$this->synchroniseCMDatabase();
	}

	function onAfterWrite() {
		parent::onAfterWrite();
		$this->synchroniseCMDatabase();
	}

	protected function synchroniseCMDatabase() {
		$lists = DataObject::get("CampaignMonitorSignupPage", "ReadyToReceiveSubscribtions = 1");
		if($lists) {
			foreach($lists as $list) {

				if($list->GroupID) {
					//external database
					$CMWrapper = new CampaignMonitorWrapper();
					$CMWrapper->setListID ($list->ListID);
					$userIsUnconfirmed = $CMWrapper->subscriberIsUnconfirmed($this->owner->Email);
					if($userIsUnconfirmed && $userIsUnconfirmed != "?") {
						// do nothing
					}
					else {
						$userIsSubscribed = $CMWrapper->subscriberIsSubscribed($this->owner->Email);

						if(!$this->owner->inGroup($list->GroupID, $strict = TRUE)) {
							//not in group, but is subscribed.... unsubscribe....
							if($userIsSubscribed && $userIsSubscribed != "?"){
								if (!$CMWrapper->subscriberUnsubscribe($this->owner->Email)) {
									user_error(_t('CampaignMonitorMemberDOD.GETCMSMESSAGESUBSATTEMPTFAILED', 'Unsubscribe attempt failed: ') .$this->owner->Email.", ". $CMWrapper->lastErrorMessage, E_USER_WARNING);
								}
							}
						}
						else {
							//
							$userIsUnsubscribed = $CMWrapper->subscriberIsUnsubscribed($this->owner->Email);
							$userIsDeleted = $CMWrapper->subscriberIsDeleted($this->owner->Email);
							if((!$userIsSubscribed && $userIsSubscribed != "?") && (!$userIsUnsubscribed || $userIsUnsubscribed =! "?") &&(!$userIsDeleted || !$userIsDeleted )) {
								if (!$CMWrapper->subscriberAdd($this->owner->Email, $this->owner->getName())) {
									//NEED TO IMPLEMENT: http://www.campaignmonitor.com/api/method/client-getsuppressionlist/
									//user_error(_t('CampaignMonitorMemberDOD.GETCMSMESSAGESUBSATTEMPTFAILED', 'Subscribe attempt failed: ') .$this->owner->Email.", ". $CMWrapper->lastErrorMessage, E_USER_WARNING);
								}
							}
						}
					}
				}
			}
		}
	}

  public function addCampaignMonitorList($page, $alsoSynchroniseCMDatabase = false, $params = array()) {
    //internal database
		if($page->GroupID) {
			if($gp = DataObject::get_by_id("Group", $page->GroupID)) {
				$groups = $this->owner->Groups();
				if($groups) {
					$this->owner->Groups()->add($gp);
					if($alsoSynchroniseCMDatabase) {
						$this->synchroniseCMDatabase();
					}
				}
			}
		}
  }

  public function removeCampaignMonitorList($page, $alsoSynchroniseCMDatabase = false, $params = array()) {
    //internal database
		if($page->GroupID) {
			if($gp = DataObject::get_by_id("Group", $page->GroupID)) {
				$groups = $this->owner->Groups();
				if($groups) {
					$this->owner->Groups()->remove($gp);
					if($alsoSynchroniseCMDatabase) {
						$this->synchroniseCMDatabase();
					}
				}
			}
		}
  }

	function CampaignMonitorSubscriptionsPageIdList() {
		$array = Array();
		if($set = $this->owner->Groups()) {
			$idList = $set->getIdList();
			if(is_array($idList) && count($idList)) {
				$pages = DataObject::get("CampaignMonitorSignupPage", "GroupID IN(".implode(",", $idList).") AND ReadyToReceiveSubscribtions = 1");
				if($pages) {
					foreach($pages as $page) {
						$array[$page->ID] = $page->ID;
					}
				}
			}
		}
		return $array;
	}

}
