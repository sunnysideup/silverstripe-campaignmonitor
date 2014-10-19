<?php

/**
 * Page for Signing Up to Campaign Monitor List
 *
 * Each page relates to one CM list.
 *
 * @author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorSignupPage extends Page {

	private static $icon = "campaignmonitor/images/treeicons/CampaignMonitorSignupPage";

	private static $db = array(
		'ListID' => 'Varchar(32)',

		'ConfirmTitle' => 'Varchar(255)',
		'ConfirmMenuTitle' => 'Varchar(255)',
		'ConfirmMessage' => 'HTMLText',

		'ThankYouTitle' => 'Varchar(255)',
		'ThankYouMenuTitle' => 'Varchar(255)',
		'ThankYouMessage' => 'HTMLText',

		'SadToSeeYouGoTitle' => 'Varchar(255)',
		'SadToSeeYouGoMenuTitle' => 'Varchar(255)',
		'SadToSeeYouGoMessage' => 'HTMLText',

		'SignUpHeader' => 'Varchar(100)',
		'SignUpIntro' => 'HTMLText',
		'SignUpButtonLabel' => 'Varchar(20)',

		'ShowOldNewsletters' => 'Boolean',
		'ShowAllNewsletterForSigningUp' => 'Boolean'
	);

	private static $has_one = array(
		"Group" => "Group"
	);

	private static $belongs_many_many = array(
		"CampaignMonitorCampaigns" => "CampaignMonitorCampaign"
	);


	/**
	 *
	 * @var CampaignMonitorAPIConnector | Null
	 */
	protected static $api = null;


	public static function get_ready_ones() {
		$listPages = CampaignMonitorSignupPage::get();
		$array = array(0 => 0);
		foreach($listPages as $listPage) {
			if($listPage->ReadyToReceiveSubscribtions()) {
				$array[$listPage->ID] = $listPage->ID;
			}
		}
		return CampaignMonitorSignupPage::get()->filter(array("ID" => $array));
	}

	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.NewCampaign', new LiteralField('CreateNewCampaign', '<p>To create a new mail out go to <a href="'. Config::inst()->get("CampaignMonitorWrapper", "campaign_monitor_url") .'">Campaign Monitor</a> site.</p>'));

		$fields->addFieldToTab('Root.List', new LiteralField('ListIDExplanation', '<p>The way this works is that each sign-up page needs to be associated with a campaign monitor subscription list.</p>'));
		$fields->addFieldToTab('Root.List', new DropdownField('ListID', 'Related List from Campaign Monitor - this must be selected', $this->makeDropdownListFromLists()));
		$fields->addFieldToTab('Root.List', new ReadonlyField('GroupID', 'Related member group'));
		$fields->addFieldToTab('Root.List', new CheckboxField('ShowAllNewsletterForSigningUp', 'Show all newsletters for signing up'));

		$fields->addFieldToTab('Root.StartForm', new LiteralField('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they go through to another page (the actual CampaignMonitorSignUpPage) to complete all the details.'));
		$fields->addFieldToTab('Root.StartForm', new TextField('SignUpHeader', 'Sign up header (e.g. sign up now)'));
		$fields->addFieldToTab('Root.StartForm', new HtmlEditorField('SignUpIntro', 'Sign up form intro (e.g. sign up for our monthly newsletter ...'));
		$fields->addFieldToTab('Root.StartForm', new TextField('SignUpButtonLabel', 'Sign up button label for start form (e.g. register now)'));

		$fields->addFieldToTab('Root.Confirm', new TextField('ConfirmTitle', 'Title'));
		$fields->addFieldToTab('Root.Confirm', new TextField('ConfirmMenuTitle', 'Menu Title'));
		$fields->addFieldToTab('Root.Confirm', new HtmlEditorField('ConfirmMessage', 'Thank you message after submitting form'));

		$fields->addFieldToTab('Root.ThankYou', new TextField('ThankYouTitle', 'Title'));
		$fields->addFieldToTab('Root.ThankYou', new TextField('ThankYouMenuTitle', 'Menu Title'));
		$fields->addFieldToTab('Root.ThankYou', new HtmlEditorField('ThankYouMessage', 'Thank you message after submitting form'));

		$fields->addFieldToTab('Root.SadToSeeYouGo', new TextField('SadToSeeYouGoTitle', 'AlternativeTitle'));
		$fields->addFieldToTab('Root.SadToSeeYouGo', new TextField('SadToSeeYouGoMenuTitle', 'Menu Title'));
		$fields->addFieldToTab('Root.SadToSeeYouGo', new HtmlEditorField('SadToSeeYouGoMessage', 'Sad to see you  go message after submitting form'));

		$fields->addFieldToTab('Root.Archive', new CheckboxField('ShowOldNewsletters', 'Show old newsletters?'));
		$fields->addFieldToTab('Root.TestAndStats', new LiteralField("LinkToStats"));
		return $fields;
	}

	/**
	 *
	 * @return CampaignMonitorAPIConnector
	 */
	public function getAPI(){
		if(!self::$api) {
			self::$api = CampaignMonitorAPIConnector::create();
			self::$api->init();
		}
		return self::$api;
	}

	/**
	 *
	 * @var Null | Array
	 */
	private static $drop_down_list = null;

	/**
	 * returns available list for client
	 * @return array
	 */
	protected function makeDropdownListFromLists() {
		if(self::$drop_down_list === null) {
			$array = array();
			$api = $this->getAPI();
			$lists = $api->getLists();
			if(is_array($lists) && count($lists)) {
				foreach($lists as $list) {
					$array[$list["ListID"]] = $list["Name"];
				}
			}
			//remove subscription list IDs from other pages
			$subscribePages = CampaignMonitorSignupPage::get()
				->exclude("ID", $this->ID);
			foreach($subscribePages as $page) {
				if(isset($array[$page->ListID])) {
					unset($array[$page->ListID]);
				}
			}
			self::$drop_down_list = $array;
		}
		return self::$drop_down_list;
	}

	/**
	* you can add this function to other pages to have a form
	* that starts the basic after which the client needs to complete the rest.
	*	*
	* @param Controller $controller
	* @return Form
	**/
	public function CampaignMonitorStartForm(Controller $controller, $formName = "CampaignMonitorStarterForm") {
		if(!$this->ReadyToReceiveSubscribtions()) {
			//user_error("You first need to setup a Campaign Monitor Page for this function to work.", E_USER_NOTICE);
			return false;
		}
		$fields = new FieldList(new TextField("Email", "Email"));
		$actions = new FieldList(new FormAction("campaignmonitorstarterformstartaction", $this->SignUpButtonLabel));
		$form = new Form(
			$controller,
			$formName,
			$fields,
			$actions
		);
		$form->setFormAction($this->Link("preloademail"));
		return $form;
	}

	/**
	 * name of the list connected to.
	 * @return String
	 */
	public function getListTitle() {
		$a = $this->makeDropdownListFromLists();
		if(isset($a[$this->ListID])) {
			return $a[$this->ListID];
		}
	}

	/**
	 * tells us if the page is ready to receive subscriptions
	 * @return Boolean
	 */
	function ReadyToReceiveSubscribtions(){
		return $this->ListID && $this->GroupID;
	}

	/**
	 * check list and group IDs
	 *
	 */
	function onBeforeWrite() {
		parent::onBeforeWrite();
		//check list
		if(!$this->getListTitle()) {
			$this->ListID = 0;
		}
		//check group
		if($this->GroupID) {
			$gp = $this->Group();
			if(!$gp || !$gp->exists()) {
				$this->GroupID = 0;
			}
		}
		//add group
		if($this->ListID) {
			if(!$this->GroupID) {
				$gp = new Group();
				$this->GroupID = $gp->ID;
			}
		}
		$gp->Title = _t("CampaignMonitor.NEWSLETTER", "NEWSLETTER") .": " . $this->getListTitle();
		$gp->write();
	}

	/**
	 *
	 *
	 */
	function onAfterWrite() {
		parent::onAfterWrite();
		if($this->ShowOldNewsletters) {
			$this->AddOldCampaigns();
		}
	}

	public function AddOldCampaigns(){
		$task = CampaignMonitorAddOldCampaigns::create();
		$task->setVerbose(false);
		$task->run(null);
	}

	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		$update = array();
		$page = CampaignMonitorSignupPage::get()->First();

		if($page) {
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

	/**
	 * retains email for processing
	 * @var String
	 */
	protected $email = '';

	/**
	 * holder for selected campaign
	 *
	 * @var Null | CampaignMonitorCampaign
	 */
	protected $campaign = '';

	private static $allowed_actions = array(
		"SignupForm" => true,
		"subscribe" => true,
		"unsubscribe" => true,
		"thankyou" => true,
		"confirm" => true,
		"sadtoseeyougo" => true,
		"preloademail" => true,
		"viewcampaign" => true,
		"stats" => "ADMIN",
		"resetoldcampaigns" => "CMS_ACCESS_CMSMain"
	);

	function init() {
		parent::init();
		Requirements::themedCSS("CampaignMonitorSignupPage");
	}

	/**
	 * creates a subscription form...
	 * @return Form
	 */
	function SignupForm() {
		if($this->ReadyToReceiveSubscribtions()) {
			// Create fields
			$member = Member::currentUser();
			if(!$member) {
				$member = new Member();
			}
			if($this->ShowAllNewsletterForSigningUp) {
				$signupField = $member->getSignupField(null, "SubscribeChoices");
			}
			else {
				$signupField = $member->getSignupField($this->ListID, "SubscribeChoice");
			}
			$fields = new FieldList(
				$signupField,
				new TextField('FirstName', 'First Name'),
				new TextField('Surname', 'Surname'),
				new EmailField('Email', 'Email', $this->email)
			);
			// Create action
			$actions = new FieldList(
				new FormAction('subscribe', 'Subscribe')
			);
			// Create Validators
			$validator = new RequiredFields('Name', 'Email', 'SubscribeChoice');
			$form = new Form($this, 'SignupForm', $fields, $actions, $validator);
			if($member->exists()) {
				$form->loadDataFrom($member);
			}
			else {
				$form->Fields()->fieldByName("Email")->setValue($this->email);
			}
			return $form;
		}
		else {
			return _t("CampaignMonitorSignupPage.NOTREADY", "You can not suscribe to this newsletter at present.");
		}
	}


	function subscribe($data, $form) {
		if($this->ReadyToReceiveSubscribtions()) {
				//true until proven otherwise.
			$subscriptionChanged = true;
			$api = $this->getAPI();
			$member = Member::currentUser();
			if(!$member) {
				$memberAlreadyLoggedIn = false;
				if($existingMember = Member::get()->filter(array("Email" => Convert::raw2sql($data["Email"])))->First()) {
					$form->addErrorMessage('Email', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use. Please log in for this email or try another email address."), 'warning');
					$this->redirectBack();
					return;
				}
				$member = new Member();
			}
			else {
				$memberAlreadyLoggedIn = true;
				if($existingMember = Member::get()
					->filter(array("Email" => Convert::raw2sql($data["Email"])))->First()
					->exclude(array("ID" => $member->ID))
				) {
					$form->addErrorMessage('Email', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use by someone else. Please log in for this email or try another email address."), 'warning');
					$this->redirectBack();
					return;
				}
			}
			if(!isset($data["SubscribeChoice"])) {
				$form->addErrorMessage('SubscribeChoice', _t("CAMPAIGNMONITORSIGNUPPAGE.NO_NAME", "Please choose your subscription."), 'warning');
				$this->redirectBack();
				return;
			}
			$form->saveInto($member);
			if($memberAlreadyLoggedIn) {
				//nothing more to do
			}
			//create new member!
			else {
				$member->SetPassword = true;
				$member->Password = Member::create_new_password();
			}
			$member->write();
			if(!$memberAlreadyLoggedIn) {
				$member->logIn($keepMeLoggedIn = false);
			}
			if(isset($data["SubscribeChoices"])) {
				$listPages = CampaignMonitorSignupPage::get_ready_ones();
				foreach($listPages as $listPage) {
					if(isset($data["SubscribeChoices"][$listPage->ListID]) && $data["SubscribeChoices"][$listPage->ListID]) {
						$member->addCampaignMonitorList($listPage->ListID);
					}
					else {
						$member->removeCampaignMonitorList($listPage->ListID);
					}
				}
			}
			elseif(isset($data["SubscribeChoice"])) {
				if($data["SubscribeChoice"] == "Subscribe") {
					$member->addCampaignMonitorList($this->ListID);
					$this->redirect($this->Link('thankyou'));
				}
				else {
					$member->removeCampaignMonitorList($this->ListID);
					$this->redirect($this->Link('sadtoseeyougo'));
				}
			}
			else {
				user_error("Subscriber field missing", E_USER_WARNING);
			}
		}
		else {
			user_error("No list to subscribe to", E_USER_WARNING);
		}
	}

	/**
	 * immediately unsubscribe if you are logged in.
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

	function confirm($request) {
		$this->Title = $this->ConfirmTitle;
		$this->MenuTitle = $this->ConfirmMenuTitle;
		$this->Content = $this->ConfirmMessage;
		return array();
	}

	function thankyou($request) {
		$this->Title = $this->ThankYouTitle;
		$this->MenuTitle = $this->ThankYouMenuTitle;
		$this->Content = $this->ThankYouMessage;
		return array();
	}

	function sadtoseeyougo($request) {
		$this->Title = $this->SadToSeeYouGoTitle;
		$this->MenuTitle = $this->SadToSeeYouGoMenuTitle;
		$this->Content = $this->SadToSeeYouGoMessage;
		return array();
	}

	function preloademail(SS_HTTPRequest $request){
		$data = $request->requestVars();
		if(isset($data["Email"])) {
			$email = Convert::raw2sql($data["Email"]);
			if($email) {
				$this->email = $email;
			}
		}
		else {
			if($m = Member::currentUser()) {
				$this->email = $m->Email;
			}
		}
		return array();
	}

	/**
	 *
	 * action to show one campaign...
	 */
	function viewcampaign($request){
		$campaignID = intval($request->param("ID"));
		$this->campaign = CampaignMonitorCampaign::get()->filter(array("CampaignID" => $campaignID));
		if(!$this->campaign) {
			return $this->httpError(404, _t("CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND", "Message not found."));
		}
		return array();
	}


	/**
	 *
	 * @return Boolean
	 */
	function HasCampaign(){
		return $this->campaign ? true : false;
	}

	/**
	 *
	 * @return Null | CampaignMonitorCampaign
	 */
	function Campaign(){
		return $this->campaign;
	}

	/**
	 * same as $this->CampaignMonitorCampaigns()
	 * but sorted correctly.
	 *
	 * @return DataSet of CampaignMonitorCampaigns
	 */
	function PreviousCampaignMonitorCampaigns(){
		if($this->ShowOldNewsletters) {
			$campaigns = $this->CampaignMonitorCampaigns();
			return CampaignMonitorCampaign::get()->filter(array("ID" => $campaigns->map("ID", "ID")->toArray()));
		}
	}



	/**
	 * action for admins only to see a whole bunch of
	 * stats
	 */
	function stats() {
		if(Permission::check("Admin")) {
			//run tests here
			$api = $this->getAPI();
			$html = "<h1>Debug Response</h1>";
			$html .= "<h2>Main Client Stuff</h2>";
			$html .= "<h3>Link to confirm page</h3><pre>".$this->Link("confirm")."</pre>";
			$html .= "<h3>Link to thank-you-page</h3><pre>".$this->Link("thankyou")."</pre>";
			$html .= "<h3>Link to sad-to-see-you-go page</h3><pre>".$this->Link("sadtoseeyougo")."</pre>";
			$html .= "<h3>All Campaigns</h3><pre>".$api->getCampaigns()."</pre>";
			$html .= "<h3>All Lists</h3><pre>".$api->getLists()."</pre>";
			if($this->ListID) {
				$html .= "<h2>Lists</h2";
				$html .= "<h3>List Details</h3><pre>".$api->getList($this->ListID)."</pre>";
				$html .= "<h3>Active Subscribers</h3><pre>".$api->getActiveSubscribers($this->ListID)."</pre>";
				$html .= "<h3>Unconfirmed Subscribers</h3><pre>".$api->getUnconfirmedSubscribers($this->ListID)."</pre>";
				$html .= "<h3>Bounced Subscribers</h3><pre>".$api->getBouncedSubscribers($this->ListID)."</pre>";
				$html .= "<h3>Unsubscribed Subscribers</h3><pre>".$api->getUnsubscribedSubscribers($this->ListID)."</pre>";
				$html .= "<h3>List Stats</h3><pre>".$api->getListStats($this->ListID)."</pre>";
			}
			else {
				$html .= "<h2 style=\"color: red;\">ERROR: No Lists selected</h2";
			}
			$this->Content = $html;
		}
		else {
			Security::permissionFailure($this, _t("CAMPAIGNMONITORSIGNUPPAGE.TESTFAILURE", "This function is only available for administrators"));
		}
	}

	/**
	 * returns a bunch of stats about a campaign
	 * IF the user is an admin AND a campaign is selected
	 * @return String (html) | false
	 */
	function CampaignStats(){
		if(Permission::check("Admin")) {
			if($this->campaign) {
				//run tests here
				$api = $this->getAPI();
				$html .= "<h2>Campaign Stats</h2>";
				$html .= "<h3>Campaign: ".$this->campaign->Subject."</h3>";
				$html .= "<h3>Summary</h3><pre>".$api->getSummary($this->campaign->CampaignID)."</pre>";
				$html .= "<h3>Email Client Usage</h3><pre>".$api->getEmailClientUsage($this->campaign->CampaignID)."</pre>";
				$html .= "<h3>Unsubscribes</h3><pre>".$api->getUnsubscribes($this->campaign->CampaignID)."</pre>";
				$this->Content = $html;
			}
		}
		else {
			return false;
		}
	}

	/**
	 * removes all campaigns
	 * so that they can be re-imported.
	 */
	function resetoldcampaigns() {
		if(!Permission::check("CMS_ACCESS_CMSMain")) {
			Security::permissionFailure($this, _t('Security.PERMFAILURE',' This page is secured and you need CMS rights to access it. Enter your credentials below and we will send you right along.'));
		}
		else {
			DB::query("DELETE FROM \"CampaignMonitorCampaign\";");
			DB::query("DELETE FROM \"CampaignMonitorCampaign_Pages\";");
			die("old campaigns have been deleted");
		}
	}

}
