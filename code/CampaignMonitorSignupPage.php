<?php

/**
 * Main Holder page for Recipes
 *@author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorSignupPage extends Page {

	static $icon = "campaignmonitor/images/treeicons/CampaignMonitorSignupPage";

	static $db = array(
    'ListID' => 'Varchar(32)',
    'ListTitle' => 'Varchar(55)',
		'ThankYouMessage' => 'HTMLText',
		'AlternativeTitle' => 'Varchar(255)',
		'AlternativeMenuTitle' => 'Varchar(255)',
		'AlternativeMetaTitle' => 'Varchar(255)',
		'SadToSeeYouGoMessage' => 'HTMLText',
		'SadToSeeYouGoTitle' => 'Varchar(255)',
		'SadToSeeYouGoMenuTitle' => 'Varchar(255)',
		'SadToSeeYouGoMetaTitle' => 'Varchar(255)',
		'SignUpHeader' => 'Varchar(100)',
		'SignUpIntro' => 'HTMLText',
		'SignUpButtonLabel' => 'Varchar(20)',
		'ShowOldNewsletters' => 'Boolean',
		'ReadyToReceiveSubscribtions' => 'Boolean'
	);

	static $has_one = array(
		"Group" => "Group"
	);

	static $has_many = array(
		"CampaignMonitorCampaigns" => "CampaignMonitorCampaign"
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.Content.CreateNewMailOut', new LiteralField('CreateNewCampaign', '<p>To create a new mail out go to <a href="'.CampaignMonitorWrapper::get_campaign_monitor_url().'">Campaign Monitor</a> site.</p>'));
		$fields->addFieldToTab('Root.Content', new Tab("MustComplete"), "Main");
		$fields->addFieldToTab('Root.Content.MustComplete', new LiteralField('ListIDExplanation', '<p>The way this works is that each sign-up page needs to be associated with a campaign monitor subscription list.</p>'));
		$fields->addFieldToTab('Root.Content.MustComplete', new DropdownField('ListID', 'Related List from Campaign Monitor - this must be selected', $this->makeDropdownListFromLists()));
		$fields->addFieldToTab('Root.Content.MustComplete', new TextField('ListTitle', 'List title to be shown...'));
		$fields->addFieldToTab('Root.Content.MustComplete', new TreeDropdownField('GroupID', 'Related member group'));
		$fields->addFieldToTab('Root.Content.MustComplete', new CheckboxField('ReadyToReceiveSubscribtions', 'Newsletter is ready to receive subscriptions (all above fields MUST be completed)'));

		$fields->addFieldToTab('Root.Content.StartForm', new LiteralField('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they go through to another page (the actual CampaignMonitorSignUpPage) to complete all the details.'));
		$fields->addFieldToTab('Root.Content.StartForm', new TextField('SignUpHeader', 'Sign up header (e.g. sign up now)'));
		$fields->addFieldToTab('Root.Content.StartForm', new HTMLEditorField('SignUpIntro', 'Sign up form intro (e.g. sign up for our monthly newsletter ...'));
		$fields->addFieldToTab('Root.Content.StartForm', new TextField('SignUpButtonLabel', 'Sign up button label for start form (e.g. register now)'));
		$fields->addFieldToTab('Root.Content.ThankYou', new TextField('AlternativeTitle', 'Title'));
		$fields->addFieldToTab('Root.Content.ThankYou', new TextField('AlternativeMenuTitle', 'Menu Title'));
		$fields->addFieldToTab('Root.Content.ThankYou', new TextField('AlternativeMetaTitle', 'Meta Title'));
		$fields->addFieldToTab('Root.Content.ThankYou', new HTMLEditorField('ThankYouMessage', 'Thank you message after submitting form'));
		$fields->addFieldToTab('Root.Content.SadToSeeYouGo', new TextField('SadToSeeYouGoTitle', 'AlternativeTitle'));
		$fields->addFieldToTab('Root.Content.SadToSeeYouGo', new TextField('SadToSeeYouGoMenuTitle', 'Menu Title'));
		$fields->addFieldToTab('Root.Content.SadToSeeYouGo', new TextField('SadToSeeYouGoMetaTitle', 'Meta Title'));
		$fields->addFieldToTab('Root.Content.SadToSeeYouGo', new HTMLEditorField('SadToSeeYouGoMessage', 'Sad to see you  go message after submitting form'));
		$fields->addFieldToTab('Root.Content.OldNewsletters', new CheckboxField('ShowOldNewsletters', 'Show old newsletters?'));
		return $fields;
	}

	protected function makeDropdownListFromLists() {
		$array = array();
		$CMWrapper = new CampaignMonitorWrapper();
		$lists = $CMWrapper->clientGetLists();
		if(is_array($lists) && count($lists)) {
			foreach($lists as $list) {
				$array[$list["ListID"]] = $list["Name"];
			}
		}
		//remove subscription list IDs from other pages
		/*
		$subscribePages = DataObject::get("CampaignMonitorSignupPage". "ReadyToReceiveSubscribtions = 1");
		foreach($subscribePages as $page) {
			if($page->ID != $this->ID) {
				if(isset($array[$page->ListID])) {
					unset($array[$page->ListID]);
				}
			}
		}
		*/
		return $array;
	}


	/**
	* you can add this function to other pages to have a form that starts the basic after which the client needs to complete the rest.
	*
	**/

	static function CampaignMonitorStarterForm($controller) {
		$page = DataObject::get_one("CampaignMonitorSignupPage");

		if(!$page || !$page->ListID) {
			//user_error("You first need to setup a Campaign Monitor Page for this function to work.", E_USER_NOTICE);
			return false;
		}
		$fields = new FieldSet(new TextField("Email", "Email"));
		$actions = new FieldSet(new FormAction("campaignmonitorstarterformstartaction", $page->SignUpButtonLabel));
		$form = new Form(
			$controller,
			"CampaignMonitorStarterForm",
			$fields,
			$actions
		);
		$form->setFormAction($page->Link("preloademail"));
		return $form;
	}

  // Return a properly setup instance of the wrapper class
  public function newCMWrapper () {
    $CMWrapper = new CampaignMonitorWrapper();
		if(!$this->ListID) {
			$lists = $CMWrapper->clientGetLists();
			if(!$lists) {
				$CMWrapper->listCreate($listTitle = 'List for '.$this->Title,$unsubscribePage = '',$confirmOptIn = 'false',$confirmationSuccessPage = '');
				$lists = $CMWrapper->clientGetLists();
			}
			if(is_array($lists) && isset($lists["ListID"])) {
				$this->ListID = $lists["ListID"];
			}
			elseif(is_array($lists) && isset($lists[0]["ListID"])) {
				$this->ListID = $lists[0]["ListID"];
			}
			elseif(is_int($lists)) {
				$this->ListID = $lists;
			}
		}
    $CMWrapper->setListID ($this->ListID);
    return $CMWrapper;
  }

	function MakeListTitle() {
		if($this->ListTitle) {
			return $this->ListTitle;
		}
		else {
			$a = $this->makeDropdownListFromLists();
			if(isset($a[$this->ListID])) {
				return $a[$this->ListID];
			}
		}
	}

	function onBeforeWrite() {
		parent::onBeforeWrite();
		if(!$this->ListID || !$this->GroupID || !$this->ListTitle) {
			$this->ReadyToReceiveSubscribtions = 0;
		}
		if(!$this->GroupID && $this->ListTitle) {
			$gp = new Group();
			$gp->Title = "CAMPAIGN MONITOR: ".$this->ListTitle;
			$gp->write();
			$this->GroupID = $gp->ID;
		}
		//make sure it is connected to a list.
		$CMWrapper = $this->newCMWrapper();
	}


	function onAfterWrite() {
		parent::onAfterWrite();
		if($this->ShowOldNewsletters) {
			$CMWrapper = $this->newCMWrapper();
			$campaigns = $CMWrapper->clientGetCampaigns();
			if(is_array($campaigns)) {
				foreach($campaigns as $campaign) {
					if(!DataObject::get("CampaignMonitorCampaign", "CampaignID = '".$campaign["CampaignID"]."' AND ParentID = ".$this->ID)) {
						$CampaignMonitorCampaign = new CampaignMonitorCampaign();
						$CampaignMonitorCampaign->CampaignID = $campaign["CampaignID"];
						$CampaignMonitorCampaign->Subject = $campaign["Subject"];
						$CampaignMonitorCampaign->Name = $campaign["Name"];
						$CampaignMonitorCampaign->SentDate = $campaign["SentDate"];
						$CampaignMonitorCampaign->TotalRecipients = $campaign["TotalRecipients"];
						$CampaignMonitorCampaign->ParentID = $this->ID;
						$CampaignMonitorCampaign->write();
					}
				}
			}
		}
		else {
			DB::query("DELETE FROM \"CampaignMonitorCampaign\" WHERE \"ParentID\" = '".$this->ID."';");
		}
	}

	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		$update = array();
		$page = DataObject::get_one("CampaignMonitorSignupPage");

		if($page) {
			$CMWrapper = $this->newCMWrapper();
			if(!$page->SignUpHeader) {
				$page->SignUpHeader = 'Sign Up Now';
				$update[]= "created default entry for SignUpHeader";
			}
			if(strlen($page->SignUpIntro) < strlen("<p> </p>")) {
				$page->SignUpIntro = '<p>Enter your email to sign up for our newsletter</p>';
				$update[]= "created default entry for SignUpIntro";
			}
			if(!$page->SignUpButtonLabel) {
				$page->SignUpButtonLabel = 'Register Now';
				$update[]= "created default entry for SignUpButtonLabel";
			}
			if(count($update)) {
				$page->writeToStage('Stage');
				$page->publish('Stage', 'Live');
				DB::alteration_message($page->ClassName." created/updated: ".implode(" --- ",$update), 'created');
			}
		}
	}
}

class CampaignMonitorSignupPage_Controller extends Page_Controller {

	protected $showThankYouMessage = false;

	protected $showSadToSeeYouGoMessage = false;

	protected $hasMessage = false;

	protected $email = '';

	function init() {
		parent::init();
		Requirements::themedCSS("CampaignMonitorSignupPage");
	}

	// Subscription form

	function FormHTML() {
		if($this->ReadyToReceiveSubscribtions) {
			// Create fields
			$m = Member::currentMember();
			if(!$m) {
				$m = new Member();
			}
			$fields = new FieldSet(
				$this->getSubscribeField($m),
				new TextField('FirstName', 'First Name'),
				new TextField('Surname', 'Surname'),
				new EmailField('Email', 'Email', $this->email)
			);
			// Create action
			$actions = new FieldSet(
				new FormAction('subscribe', 'Subscribe')
			);
			// Create Validators
			$validator = new RequiredFields('Name', 'Email', 'SubscribeChoice');
			$f = new Form($this, 'FormHTML', $fields, $actions, $validator);
			if($m->ID) {
				$f->loadDataFrom($m);
			}
			else {
				$f->Fields()->fieldByName("Email")->setValue($this->email);
			}
			return $f;
		}
		else {
			return _t("CampaignMonitorSignupPage.NOTREADY", "You can not suscribe to this newsletter at present.");
		}
	}

	protected function getSubscribeField($member) {
		$currentSelection = "Subscribe";
		$optionArray = array("Subscribe" => "Subscribe to ".$this->MakeListTitle());
		if($member->ID) {
			$optionArray["Unsubscribe"] = "Unsubscribe from ".$this->MakeListTitle();
			$campaignMonitorSubscriptions = $member->CampaignMonitorSubscriptionsPageIdList();
			if(is_array($campaignMonitorSubscriptions) && count($campaignMonitorSubscriptions)) {
				if(isset($campaignMonitorSubscriptions[$this->ID])) {
					$currentSelection = "Subscribe";
				}
				else {
					$currentSelection = "Unsubscribe";
				}
			}
			else {
				$CMWrapper = $this->newCMWrapper();
				if($CMWrapper->subscriberIsUnsubscribed($member->Email)) {
					$currentSelection = "Unsubscribe";
				}
			}
		}
		$field = new OptionsetField('SubscribeChoice', "Subscription", $optionArray, $currentSelection);
		return $field;

	}

	function subscribe($data, $form) {
		if($this->ListID) {
				//true until proven otherwise.
			$subscriptionChanged = true;
			$CMWrapper = $this->newCMWrapper();
			$member = Member::currentMember();
			$memberAlreadyLoggedIn = false;
			if(!$member) {
				if($existingMember = DataObject::get_one("Member", "Email = '".Convert::raw2sql($data["Email"])."'")) {
					$form->addErrorMessage('Email', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use. Please log in for this email or try another email address"), 'warning');
					Director::redirectBack();
					return;
				}
				$member = new Member();
			}
			else {
				$memberAlreadyLoggedIn = true;
			}
			if(!isset($data["SubscribeChoice"])) {
				$form->addErrorMessage('SubscribeChoice', _t("CAMPAIGNMONITORSIGNUPPAGE.NO_NAME", "Please choose your subscription."), 'warning');
				Director::redirectBack();
				return;
			}
			if(($CMWrapper->subscriberIsSubscribed($data["Email"]) || $CMWrapper->subscriberIsUnconfirmed($data["Email"])) && $data["SubscribeChoice"] == "Subscribe") {
				$subscriptionChanged = false;
			}
			elseif($CMWrapper->subscriberIsUnsubscribed($data["Email"]) && $data["SubscribeChoice"] == "Unsubscribe") {
				$subscriptionChanged = false;
			}
			$form->saveInto($member);
			if(!$memberAlreadyLoggedIn) {
				$member->SetPassword = true;
				$member->Password = Member::create_new_password();
			}
			$member->write();
			if(!$memberAlreadyLoggedIn) {
				$member->logIn($keepMeLoggedIn = false);
			}
			if($data["SubscribeChoice"] == "Subscribe") {
				if($subscriptionChanged) {
					$member->addCampaignMonitorList($this, $alsoSynchroniseCMDatabase = true);
				}
				Director::redirect($this->Link().'thankyou/');
			}
			else {
				if($subscriptionChanged) {
					$member->removeCampaignMonitorList($this, $alsoSynchroniseCMDatabase = true);
					$member->write();
				}
				Director::redirect($this->Link().'sadtoseeyougo/');
			}
		}
		else {
			user_error("No list to subscribe to", E_USER_WARNING);
		}
	}

  // Unsubscribe immediately...
  function unsubscribe() {
		$member = Member::currentMember();
    if ($member) {
			$member->removeCampaignMonitorList($this);
			$this->Content = $member->Email." has been removed from this list: ".$this->ListTitle;
    }
		else {
			Security::permissionFailure($this, _t("CAMPAIGNMONITORSIGNUPPAGE.LOGINFIRST", "Please login first."));
		}
		return array();
  }

	function thankyou() {
		$this->showThankYouMessage = true; // TODO: what does this var do???
		if($this->AlternativeTitle) {$this->MetaTitle = $this->AlternativeTitle;}
		if($this->AlternativeMenuTitle) {$this->MetaTitle = $this->AlternativeMenuTitle;}
		if($this->AlternativeMetaTitle) {$this->MetaTitle = $this->AlternativeMetaTitle;}
		return array();
	}
	function sadtoseeyougo() {
		$this->showSadToSeeYouGoMessage = true;
		if($this->SadToSeeYouGoTitle) {$this->MetaTitle = $this->SadToSeeYouGoTitle;}
		if($this->SadToSeeYouGoMenuTitle) {$this->MetaTitle = $this->SadToSeeYouGoMenuTitle;}
		if($this->SadToSeeYouGoMetaTitle) {$this->MetaTitle = $this->SadToSeeYouGoMetaTitle;}
		return array();
	}

	function preloademail(SS_HTTPRequest $request){
		$data = $request->requestVars();
		if(isset($data["Email"])) {
			$email = $data["Email"];
			if($email) {
				$this->email = $email;
			}
		}
		else {
			if($m = Member::currentMember()) {
				$this->email = $m->Email;
			}
		}
		return array();
	}

	function ShowThankYouMessage() {
		return $this->showThankYouMessage;
	}

	function ShowSadToSeeYouGoMessage (){
		return $this->showSadToSeeYouGoMessage;
	}

	function HasMessage() {
		if($this->ShowThankYouMessage() || $this->ShowSadToSeeYouGoMessage()) {
			return true;
		}
		return false;
	}

	function test() {
		//add user to CM and check results
		//to run this test go to http://www.mysite.com/NameOfPage/test/
		if(Permission::check("Admin")) {

			//run tests here
      $CMWrapper = $this->newCMWrapper();
      if (!$CMWrapper->testConnection())
        user_error('Cannot connect to CampaignMonitor: ' . $CMWrapper->lastErrorMessage, E_USER_WARNING);
      if (!$CMWrapper->testListSetup())
        user_error('List not setup: ' . $CMWrapper->lastErrorMessage, E_USER_WARNING);

      // Test connection with CM

			//returning array will show page as normal...
			return array();
		}
		else {
			Security::permissionFailure($this, _t("CAMPAIGNMONITORSIGNUPPAGE.TESTFAILURE", "This function is only available for administrators"));
		}

	}

	function resetoldcampaigns() {
		if(!Permission::check("CMS_ACCESS_CMSMain")) {
			Security::permissionFailure($this, _t('Security.PERMFAILURE',' This page is secured and you need CMS rights to access it. Enter your credentials below and we will send you right along.'));
		}
		else {
			DB::query("DELETE FROM \"CampaignMonitorCampaign\";");
			die("old campaigns have been deleted");
		}
	}

}
