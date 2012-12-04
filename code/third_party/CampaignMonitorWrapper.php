<?php
/*
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *@also see: http://www.campaignmonitor.com/api/
 *
 *what should we be able to do
 *1. check if client exists
 *1b. if not, create cllient with data from CampaignMonitorPage (client editable stuff) + _config (web developer editable stuff
 *2. link from CampaignMonitorPage CMS view to CampaignMonitor Website so that client does not have to search for it.
 *3. list of campaigns and basic statistics - to be shown in CampaignMonitorPage CMS view
 *4. list of lists and basic list statistics - to be shown in CampaignMonitorPage CMS view
 *5a. synchronise list members with Silverstripe users (HOW???????)
 *5b. update list config: listTitle,. unsubscribePage, confirmationSuccessPage, confirmOptIn
 *5c. identify CampaignMonitor list that should synchronise with group Membership (Silverstripe Tables).
  **/

class CampaignMonitorWrapper extends Object {

	//basic basics
	protected static $cm = null;
	public static function set_cm($v) {self::$cm = $v;}
	public static function get_cm() {return self::$cm;}

	protected static $campaign_monitor_url = "http://yourcompany.createsend.com/";
	public static function set_campaign_monitor_url($v) {self::$campaign_monitor_url = $v;}
	public static function get_campaign_monitor_url() {return self::$campaign_monitor_url;}

	//basic configs
	protected static $api_key = '';
	public static function set_api_key($v) {self::$api_key = $v;}
	public static function get_api_key() {return self::$api_key;}

	protected static $client_ID = '';
	public static function set_client_ID($v) {self::$client_ID = $v;}
	public static function get_client_ID() {return self::$client_ID;}

	protected $list_group_membership = array();
	public static function add_list_group_membership($name, $code) {
	  self::$list_group_membership[] = array(
			"Name" => $name,
			"Code" => $code
	  );
	}

	//__________client config... ONLY set by web developer
	// $accessLevel = '63';
	// $username = 'apiusername';
	// $password = 'apiPassword';
	// $billingType = 'ClientPaysWithMarkup';
	// $currency = 'USD';
	// $deliveryFee = '7';
	// $costPerRecipient = '3';
	// $designAndSpamTestFee = '10';
	//__________client config... editable by client
	//companyName = 'Created From API';
	//contactName = 'Joe Smith';
	//emailAddress = 'joe@domain.com';
	//country = 'United States of America';
	//timeZone = '(GMT-05:00) Eastern Time (US & Canada)';

	//campaign
	protected $campaignID = '';
  public function setCampaignID($v) {$this->campaignID = $v;}
  public function getCampaignID() {return $this->campaignID;}
	// $campaignName = 'March newsletter';
	// $subject = 'March newsletter';
	// $fromName = 'John Smith';
	// $fromEmail = 'john@smith.com';
	// $replyEmail = 'john@smith.com';
	// $confirmationEmail = 'joe@domain.com';
	// $sendDate = '2089-02-15 09:00:00';
	// $htmlContent = 'http://www.campaignmonitor.com/uploads/templates/previews/template-1-left-sidebar/index.html';
	// $textContent = 'http://www.campaignmonitor.com/uploads/templates/previews/template-1-left-sidebar/textversion.txt';
	// $subcriberListIDArray = array('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
	// $subscriberSegments = "";

	//template
	protected $templateID = '';
  public function setTemplateID($v) {$this->templateID = $v;}
  public function getTemplateID() {return $this->templateID;}
	// $templateName = 'Updated Template Name';
	// $htmlURL = "http://notarealdomain.com/templates/test/index.html";
	// $zipURL = "http://notarealdomain.com/templates/test/images.zip";
	// $screenshotURL = "http://notarealdomain.com/templates/test/screenshot.jpg";

	//list
	protected $listID = '';
  public function setListID($v) {$this->listID = $v;}
  public function getListID() {return $this->listID;}
	// $listTitle = 'Updated API Created List';
	// $unsubscribePage = '';
	// $confirmOptIn = 'false';
	// $confirmationSuccessPage = '';

	function __construct() {
		require_once(dirname(__FILE__) . '/CMBase.php');
		//if (!self::$api_key) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSAGENOAPIKEYINCONFIG', "You need to set an $api_key in your config."), E_USER_WARNING);}
		//if (!self::$client_ID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSAGENOCLIENTIDINCONFIG', "You need to set an $client_ID in your config."), E_USER_WARNING);}
		self::$cm = new CampaignMonitor(self::$api_key, self::$client_ID);
	}

	// -------------------- CAMPAIGN SECTION --------------------

	public function campaignCreate(
		$campaignName = 'March newsletter',
		$subject = 'March newsletter',
		$fromName = 'John Smith',
		$fromEmail = 'john@smith.com',
		$replyEmail = 'john@smith.com',
		$confirmationEmail = 'joe@domain.com',
		$sendDate = '2089-02-15 09:00:00',
		$htmlContent = 'http://www.campaignmonitor.com/uploads/templates/previews/template-1-left-sidebar/index.html',
		$textContent = 'http://www.campaignmonitor.com/uploads/templates/previews/template-1-left-sidebar/textversion.txt',
		$subcriberListIDArray = array('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
	) {
		return self::$cm->campaignCreate( self::$client_ID, $campaignName, $subject, $fromName, $fromEmail, $replyEmail, $htmlContent, $textContent, $subcriberListIDArray, "" );
	}

	public function campaignDelete() {
		if(!$this->campaignID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSAGENOCAMPAIGNID', 'You need to set a campaignID for this function to work.'), E_USER_WARNING);}
		return self::$cm->campaignDelete($this->campaignID);
	}

	public function campaignGetBounces() {
		user_error(_t('CampaignMonitorWrapper.GETCMSMESSAGEFUNCTIONNOTIMPLEMENTED', 'this function has not been implemented yet!'), E_USER_ERROR);
	}

	public function campaignGetLists() {
    return self::$cm->campaignGetLists($this->campaignID);
	}

	public function campaignGetOpens() {
		user_error(_t('CampaignMonitorWrapper.GETCMSMESSFUNCNOTIMPLOWPRIORITYOPENEDCAMP', 'this function has not been implemented yet - low priority - shows users who opened campaign!'), E_USER_ERROR);
	}

	public function campaignGetSubscriberClicks() {
		user_error(_t('CampaignMonitorWrapper.GETCMSMESSFUNCNOTIMPLOWPRIORITY', 'this function has not been implemented yet - low priority!'), E_USER_ERROR);
	}

	public function campaignGetSummary($campaignID) {
		user_error(_t('CampaignMonitorWrapper.GETCMSMESSFUNCNOTIMPLOWPRIORITY', 'this function has not been implemented yet - low priority!'), E_USER_ERROR);
	}

	public function campaignGetUnsubscribes() {
		user_error(_t('CampaignMonitorWrapper.GETCMSMESSFUNCNOTIMPLOWPRIORITY', 'this function has not been implemented yet - low priority!'), E_USER_ERROR);
	}


	public function campaignSend($confirmationEmail = 'joe@domain.com',$sendDate = '2029-02-15 09:00:00') {
		if(!$this->campaignID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSAGENOCAMPAIGNID', 'You need to set a campaignID for this function to work.'), E_USER_WARNING);}
		return self::$cm->campaignSend( $this->campaignID, $confirmationEmail, $sendDate );
	}


	// -------------------- CLIENT SECTION --------------------

	public function clientCreate($companyName = 'Created From API',$contactName = 'Joe Smith',$emailAddress = 'joe@domain.com',$country = 'United States of America',$timeZone = '(GMT-05:00) Eastern Time (US & Canada)') {
		return self::$cm->clientCreate( $companyName, $contactName, $emailAddress, $country, $timeZone );
	}

	public function clientGetCampaigns() {
		$campaigns = self::$cm->clientGetCampaigns( self::$client_ID );
		if(is_array($campaigns) && isset($campaigns["anyType"]["Campaign"])) {
			if(!isset($campaigns["anyType"]["Campaign"][0])) {
				return Array(0 => $campaigns["anyType"]["Campaign"]);
			}
			return $campaigns["anyType"]["Campaign"];
		}
		elseif(is_string($campaigns)) {
			return $campaigns;
		}
	}
	
	public function clientGetDetail() {
		return self::$cm->clientGetDetail( self::$client_ID );
	}

	public function clientGetLists() {
		$lists = self::$cm->clientGetLists( self::$client_ID );
		if(is_array($lists) && isset($lists["anyType"]["List"])) {
			if(!isset($lists["anyType"]["List"][0])) {
				return Array(0 => $lists["anyType"]["List"]);
			}
			return $lists["anyType"]["List"];
		}
		elseif(is_string($lists)) {
			return $lists;
		}
		return "no list";
	}

	public function clientGetSegments() {
		user_error(_t('CampaignMonitorWrapper.GETCMSMESSFUNCNOTIMPLOWPRIORITY', 'this function has not been implemented yet - low priority!'), E_USER_ERROR);
	}

	public function clientGetSuppressionList(){
		return self::$cm->clientGetSuppressionList( self::$client_ID );
	}

	public function clientGetTemplates(){
		return self::$cm->clientGetTemplates( self::$client_ID );
	}

	public function clientUpdateAccessAndBilling(
		$accessLevel = '63',
		$username = 'apiusername',
		$password = 'apiPassword',
		$billingType = 'ClientPaysWithMarkup',
		$currency = 'USD',
		$deliveryFee = '7',
		$costPerRecipient = '3',
		$designAndSpamTestFee = '10'
	) {
		return self::$cm->clientUpdateAccessAndBilling( self::$client_ID, $accessLevel, $username, $password, $billingType, $currency, $deliveryFee, $costPerRecipient, $designAndSpamTestFee );
	}

	public function clientUpdateBasics($companyName = 'Created From API',$contactName = 'Joe Smith',$emailAddress = 'joe@domain.com',$country = 'United States of America',$timeZone = '(GMT-05:00) Eastern Time (US & Canada)') {
		return self::$cm->clientUpdateBasics( self::$client_ID, $companyName, $contactName, $emailAddress, $country, $timeZone );
	}


	// -------------------- LIST SECTION --------------------

	public function listCreate($listTitle = 'Updated API Created List',$unsubscribePage = '',$confirmOptIn = 'false',$confirmationSuccessPage = '') {
		return self::$cm->listCreate( self::$client_ID, $listTitle, $unsubscribePage, $confirmOptIn, $confirmationSuccessPage );
	}

	public function listDelete() {
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTIDFORDEL', 'You need to set a listID for listDelete to work.'), E_USER_WARNING);}
		return self::$cm->listDelete( $this->listID );
	}

	public function listCreateCustomField($fieldName = 'Nickname', $dataType = 'Text', $options = '') {
		/*
		// Below are examples for the other possible field types
		// Number field example
		$fieldName = 'Age';
		$dataType = 'Number';
		$options = '';
		// Multi-option select one example
		$fieldName = 'Sex';
		$dataType = 'MultiSelectOne';
		$options = 'Male||Female';
		// Multi-option select many example
		$fieldName = 'Hobby';
		$dataType = 'MultiSelectMany';
		$options = 'Surfing||Reading||Snowboarding';
		*/
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTIDCREATECUSTFIELD', 'You need to set a listID for listCreateCustomField to work.'), E_USER_WARNING);}
		return self::$cm->listCreateCustomField( $this->listID, $fieldName, $dataType, $options );
	}

	public function listDeleteCustomField($key = '[CustomFieldKey]') {
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		return self::$cm->listDeleteCustomField( $this->listID, $key );
	}

	public function listGetCustomFields() {
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		return self::$cm->listGetCustomFields( $this->listID );
	}

	public function listGetDetail() {
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		return self::$cm->listGetDetail( $this->listID );
	}

	public function listGetStats() {
		//Gets statistics for a subscriber list
		user_error("this function has not been implemented yet", E_USER_ERROR);
	}

	public function listUpdate($listTitle = 'Updated API Created List',$unsubscribePage = '',$confirmOptIn = 'false',$confirmationSuccessPage = '') {
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		return self::$cm->listUpdate( $this->listID, $listTitle, $unsubscribePage, $confirmOptIn, $confirmationSuccessPage );
	}

	public function subscribersGetUnsubscribed() {
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		$tempCM = new CampaignMonitor(self::$api_key, self::$client_ID, $this->campaignID, $this->listID );
		return $tempCM->subscribersGetUnsubscribed(1, $this->listID);
	}
	// -------------------- SUBSCRIBER SECTION --------------------

	public function subscriberAdd($subscriberEmail, $subscriberName) {
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		$tempCM = new CampaignMonitor(self::$api_key, self::$client_ID, $this->campaignID, $this->listID);
		//
		//passing email address, name.
    $result = $tempCM->subscriberAdd($subscriberEmail, $subscriberName);
    $this->lastErrorMessage = $result['Result']['Message'];
		return $result['Result']['Code'] == 0;
	}

	public function subscriberAddAndResubscribe($subscriberEmail, $subscriberName) {
		if(! $this->listID) {
			user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);
		}
		return self::$cm->subscriberAddAndResubscribe($subscriberEmail, $subscriberName, $this->listID);
	}

	public function subscriberAddAndResubscribeWithCustomFields() {
		user_error("this function has not been implemented yet", E_USER_ERROR);
	}

	public function subscriberAddWithCustomFields($subscriberEmail, $subscriberName, $params) {
		if(!$this->campaignID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSAGENOCAMPAIGNID', 'You need to set a campaignID for this function to work.'), E_USER_WARNING);}
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		$tempCM = new CampaignMonitor(self::$api_key, self::$client_ID, $this->campaignID, $this->listID );
		//
		//passing email address, name and custom fields. Custom fields should be added as an array as shown here with the Interests and Dog fields.
		//Multi-option field values are added as an array within this, as demonstrated for the Interests field.

		// TO DO ____________________ ! ____________________ ! ____________________ ! ____________________ ! ____________________ ! ____________________ ! ____________________ !
		//turn params into arguments for function!
		// TO DO ____________________ ! ____________________ ! ____________________ ! ____________________ ! ____________________ ! ____________________ ! ____________________ !

		$result = $tempCM->subscriberAddWithCustomFields($subscriberEmail, $subscriberName, $params);
		if($result['Code'] == 0) {
			return 'Success';
		}
		else {
			return 'Error : ' . $result['Message'];
		}
	}

	public function subscriberUnsubscribe($subscriberEmail) {
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		$TEMPcm = new CampaignMonitor(self::$api_key, self::$client_ID, $this->campaignID, $this->listID );
		$result = $TEMPcm->subscriberUnsubscribe($subscriberEmail);
    $this->lastErrorMessage = $result['Result']['Message'];
		return $result['Result']['Code'] == 0;
	}

	//Gets a list of all active subscribers for a list that have been added since the specified date
	public function subscriberGetActive() {
		if(! $this->listID) {
			user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);
		}
		$subscribers = self::$cm->subscribersGetActive(0, $this->listID);
		if(is_array($subscribers) && isset($subscribers['anyType']) && is_array($subscribers['anyType']) && isset($subscribers['anyType']['Subscriber'])) {
			return $subscribers['anyType']['Subscriber'];
		}
	}

	public function subscriberGetBounced() {
		//Gets a list of all subscribers for a list that have hard bounced since the specified date.
		user_error("this function has not been implemented yet", E_USER_ERROR);
	}

	public function subscriberIsSubscribed($subscriberEmail) {
		$array = $this->subscriberGetSingleSubscriber($subscriberEmail);
		if(isset($array["anyType"]) && $array["anyType"]) {
			if(isset($array["anyType"]["Code"])) {
				if($array["anyType"]["Code"] == 203) {
					return false;
				}
			}
		}
		if(is_array($array) && isset($array["anyType"]["EmailAddress"])) {
			if($array["anyType"]["EmailAddress"] == $subscriberEmail) {
				if($array["anyType"]["State"] == "Active") {
					return true;
				}
				else {
					return false;
				}
			}
		}
		//user_error("could not establish if '$subscriberEmail' is subscribed.", E_USER_NOTICE);
		return "?";
	}

	public function subscriberIsUnconfirmed($subscriberEmail) {
		$array = $this->subscriberGetSingleSubscriber($subscriberEmail);
		if(isset($array["anyType"]) && $array["anyType"]) {
			if(isset($array["anyType"]["Code"])) {
				if($array["anyType"]["Code"] == 203) {
					return false;
				}
			}
		}
		if(is_array($array) && isset($array["anyType"]["EmailAddress"])) {
			if($array["anyType"]["EmailAddress"] == $subscriberEmail) {
				if($array["anyType"]["State"] == "Unconfirmed") {
					return true;
				}
				else {
					return false;
				}
			}
		}
		//user_error("could not establish if '$subscriberEmail' is subscribed.", E_USER_NOTICE);
		return "?";
	}
	public function subscriberIsDeleted($subscriberEmail) {
		$array = $this->subscriberGetSingleSubscriber($subscriberEmail);
		if(isset($array["anyType"]) && $array["anyType"]) {
			if(isset($array["anyType"]["Code"])) {
				if($array["anyType"]["Code"] == 203) {
					return false;
				}
			}
		}
		if(is_array($array) && isset($array["anyType"]["EmailAddress"])) {
			if($array["anyType"]["EmailAddress"] == $subscriberEmail) {
				if($array["anyType"]["State"] == "Deleted") {
					return true;
				}
				else {
					return false;
				}
			}
		}
		//user_error("could not establish if '$subscriberEmail' is subscribed.", E_USER_NOTICE);
		return "?";
	}

	public function subscriberIsUnsubscribed($subscriberEmail) {
		$array = $this->subscriberGetSingleSubscriber($subscriberEmail);
		if(isset($array["anyType"]) && $array["anyType"]) {
			if(isset($array["anyType"]["Code"])) {
				if($array["anyType"]["Code"] == 203) {
					return false;
				}
			}
		}
		if(is_array($array) && isset($array["anyType"]["EmailAddress"])) {
			if($array["anyType"]["EmailAddress"] == $subscriberEmail) {
				if($array["anyType"]["State"] == "Unsubscribed") {
					return true;
				}
				else {
					return false;
				}
			}
		}
		//user_error("could not establish subscriber if '$subscriberEmail' is unsubscribed.", E_USER_NOTICE);
		return "?";
	}

	public function subscriberGetSingleSubscriber($subscriberEmail) {
		//This method returns the details of a particular subscriber, including email address, name, active/inactive status and all custom field data. If a subscriber with that email address does not exist in that list, an empty record is returned.
		if(!$this->listID) {user_error(_t('CampaignMonitorWrapper.GETCMSMESSLISTID', 'You need to set a listID for this function to work.'), E_USER_WARNING);}
		$TEMPcm = new CampaignMonitor(self::$api_key, self::$client_ID, null, $this->listID );
		return $TEMPcm->subscriberGetSingleSubscriber($this->listID, $subscriberEmail);
	}

	public function subscriberGetUnsubscribed($subscriberEmail) {
		//Gets a list of all subscribers for a list that have unsubscribed since the specified date.
		return $this->subscribersGetUnsubscribed();
	}

	// -------------------- TEMPLATE SECTION --------------------

	public function templateCreate(
		$templateName = 'Updated Template Name',
		$htmlURL = "http://notarealdomain.com/templates/test/index.html",
		$zipURL = "http://notarealdomain.com/templates/test/images.zip",
		$screenshotURL = "http://notarealdomain.com/templates/test/screenshot.jpg"
	) {
		return self::$cm->templateCreate(self::$client_ID, $templateName, $htmlURL, $zipURL, $screenshotURL);
	}

	public function templateDelete() {
		if(!$this->templateID) {user_error("You need to set a templateID for this function to work.", E_USER_WARNING);}
		return self::$cm->templateDelete($this->templateID);
	}

	public function templateGetDetail() {
		if(!$this->templateID) {user_error("You need to set a templateID for this function to work.", E_USER_WARNING);}
		return self::$cm->templateGetDetail($this->templateID);
	}

	public function templateUpdate(
		$templateName = 'Updated Template Name',
		$htmlURL = "http://notarealdomain.com/templates/test/index.html",
		$zipURL = "http://notarealdomain.com/templates/test/images.zip",
		$screenshotURL = "http://notarealdomain.com/templates/test/screenshot.jpg"
	) {
		if(!$this->templateID) {user_error("You need to set a templateID for this function to work", E_USER_WARNING);}
		return self::$cm->templateUpdate($this->templateID, $templateName, $htmlURL, $zipURL, $screenshotURL);
	}


  // -------------------- USER SECTION --------------------
	public function userGetApiKey() {
		return self::$cm->userGetApiKey();
	}

	public function userGetClients() {
		return self::$cm->userGetApiKey();
	}

	public function userGetCountries() {
		return self::$cm->userGetCountries();
	}

	public function userGetSystemDate() {
		return self::$cm->userGetSystemDate();
	}

	public function userGettimeZones() {
		return self::$cm->userGettimeZones();
	}

  // -------------------- TEST SECTION --------------------

  /*
   * True if connection is valid
   */
  public function testConnection() {
    $this->lastErrorMessage = '';
    $res = $this->clientGetLists();
    if ($res) {
      if (isset ($res['anyType']['Message'])) {
        $this->lastErrorMessage = $res['anyType']['Message'];
        return false;
      }
      else
        return true;
    }
    else
      return true;
  }

  public function testListSetup() {
    $this->lastErrorMessage = '';
    $res = $this->listGetDetail();
    if (isset ($res['anyType']['Message'])) {
      $this->lastErrorMessage = $res['anyType']['Message'];
      return $res['anyType']['Code'] != 301;
    }
    else
      return true;
  }

}
