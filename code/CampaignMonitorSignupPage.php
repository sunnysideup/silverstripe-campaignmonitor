<?php

/**
 * Page for Signing Up to Campaign Monitor List
 *
 * Each page relates to one CM list.
 *
 * @author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorSignupPage extends Page {

	/**
	 *
	 * @inherited
	 */
	private static $icon = "campaignmonitor/images/treeicons/CampaignMonitorSignupPage";

	/**
	 *
	 * @inherited
	 */
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

	/**
	 *
	 * @inherited
	 */
	private static $has_one = array(
		"Group" => "Group"
	);

	/**
	 *
	 * @inherited
	 */
	private static $belongs_many_many = array(
		"CampaignMonitorCampaigns" => "CampaignMonitorCampaign"
	);

	/**
	 *
	 * @inherited
	 */
	private static $description = "Page to suscribe and review newsletter list(s)";

	/**
	 *
	 * @var CampaignMonitorAPIConnector | Null
	 */
	protected static $api = null;


	/**
	 * Campaign monitor pages that are ready to receive "guests"
	 * @return ArrayList
	 */
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

	/**
	 *
	 * @inherited
	 */
	function getCMSFields() {
		$fields = parent::getCMSFields();
		if($this->GroupID) {
			$groupLink = '<h2><a href="/admin/security/EditForm/field/Groups/item/'.$this->GroupID.'/edit">see group</a></h2>';
		}
		else {
			$groupLink = '<p>No Group has been selected yet.</p>';
		}
		$testControllerLink = Injector::inst()->get("CampaignMonitorAPIConnector_TestController")->Link();
		$campaignExample = CampaignMonitorCampaign::get()->Last();
		$campaignExampleLink = $this->Link();
		if($campaignExample) {
			$campaignExampleLink = $this->Link("viewcampaign/".$campaignExample->CampaignID);
		}
		$fields->addFieldToTab('Root.Newsletters',
			new TabSet('Options',
				new Tab('MainSettings',
					new LiteralField('CreateNewCampaign', '<p>To create a new mail out go to the <a href="'. Config::inst()->get("CampaignMonitorWrapper", "campaign_monitor_url") .'">Campaign Monitor</a> site.</p>'),
					new LiteralField('ListIDExplanation', '<p>The way this works is that each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
					new DropdownField('ListID', 'Related List from Campaign Monitor (*)', array(0 => "-- please select --") + $this->makeDropdownListFromLists()),
					new LiteralField('GroupLink', $groupLink),
					new CheckboxField('ShowAllNewsletterForSigningUp', 'Show all newsletters for signing up')
				),
				new Tab('StartForm',
					new LiteralField('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they go through to another page (the actual CampaignMonitorSignUpPage) to complete all the details.'),
					new TextField('SignUpHeader', 'Sign up header (e.g. sign up now)'),
					new HtmlEditorField('SignUpIntro', 'Sign up form intro (e.g. sign up for our monthly newsletter ...'),
					new TextField('SignUpButtonLabel', 'Sign up button label for start form (e.g. register now)')
				),
				new Tab('Confirm',
					new TextField('ConfirmTitle', 'Title'),
					new TextField('ConfirmMenuTitle', 'Menu Title'),
					new HtmlEditorField('ConfirmMessage', 'Message (e.g. thank you for confirming)')
				),
				new Tab('ThankYou',
					new TextField('ThankYouTitle', 'Title'),
					new TextField('ThankYouMenuTitle', 'Menu Title'),
					new HtmlEditorField('ThankYouMessage', 'Thank you message after submitting form')
				),
				new Tab('SadToSeeYouGo',
					new TextField('SadToSeeYouGoTitle', 'AlternativeTitle'),
					new TextField('SadToSeeYouGoMenuTitle', 'Menu Title'),
					new HtmlEditorField('SadToSeeYouGoMessage', 'Sad to see you  go message after submitting form')
				),
				new Tab('Campaigns',
					new CheckboxField('ShowOldNewsletters', 'Show old newsletters? Set to "NO" to remove all old newsletters links to this page. Set to "YES" to retrieve all old newsletters.'),
					new LiteralField('CampaignExplanation', '<h3>Unfortunately, newsletter lists are not automatically linked to individual newsletters, you can link them here...</h3>'),
					new CheckboxSetField('CampaignMonitorCampaigns', 'Newsletters shown', CampaignMonitorCampaign::get()->limit(100)->map()->toArray())
				),
				new Tab('Advanced',
					new LiteralField('MyControllerTest', '<h3><a href="'.$testControllerLink.'">Test Connections</a></h3>'),
					new LiteralField('MyStats', '<h3><a href="'.$this->Link("stats").'">Stats and Debug information</a></h3>'),
					new LiteralField('MyCampaignReset', '<h3><a href="'.$this->Link("resetoldcampaigns").'">Delete All Campaigns from Website</a></h3>'),
					new LiteralField('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="'.$campaignExampleLink.'">an example</a></h3>')
				)
			)
		);
		if(!Config::inst()->get("CampaignMonitorWrapper", "campaign_monitor_url"))  {
			$fields->removeFieldFromTab("Root.Newsletters.Options", "CreateNewCampaign");
		}
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
					$array[$list->ListID] = $list->Name;
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
	 *
	 * Or does a basic sign up if ajax submitted.
	 *
	 * @param Controller $controller
	 * @param String $formName
	 *
	 * @return Form
	 */
	public function CampaignMonitorStartForm(Controller $controller, $formName = "CampaignMonitorStarterForm") {
		if($email = Session::get("CampaignMonitorStartForm_AjaxResult_".$this->ID)) {
			return $this->renderWith("CampaignMonitorStartForm_AjaxResult", array("Email" => $email));
		}
		else {
			Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
			//Requirements::javascript(THIRDPARTY_DIR . '/jquery-form/jquery.form.js');
			Requirements::javascript(SS_CAMPAIGNMONITOR_DIR . '/javascript/CampaignMonitorStartForm.js');
			if(!$this->ReadyToReceiveSubscribtions()) {
				//user_error("You first need to setup a Campaign Monitor Page for this function to work.", E_USER_NOTICE);
				return false;
			}
			$fields = new FieldList(new EmailField("CampaignMonitorEmail", _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", "Email")));
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
	}

	/**
	 * adds a subcriber to the list without worrying about making it a user ...
	 *
	 * @param String $email
	 *
	 * @returns
	 */
	public function addSubscriber($email){
		if($this->ReadyToReceiveSubscribtions()) {
			$listID = $this->ListID;
			$email = Convert::raw2sql($email);
			if($member = Member::get()->filter(array("Email" => $email))->first()) {

			}
			else {
				$member = new Member();
				$member->Email = $email;
				$member->SetPassword = true;
				$member->Password = Member::create_new_password();
				$member->write();
			}
			if($group = $this->Group()) {
				$group->Members()->add($member);
			}
			$api = $this->getAPI();
			echo "....";
			$result = $api->addSubscriber($listID, $member);
			if($result == $email) {
				return null;
			}
			return "ERROR: could not subscribe";
		}
		return "ERROR: not ready";
	}

	/**
	 * name of the list connected to.
	 * @return String
	 */
	public function getListTitle() {
		if($this->ListID) {
			$a = $this->makeDropdownListFromLists();
			if(isset($a[$this->ListID])) {
				return $a[$this->ListID];
			}
		}
		return "";
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
		$gp = null;
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
				$gp->write();
			}
			$title = _t("CampaignMonitor.NEWSLETTER", "NEWSLETTER");
			if($myListName = $this->getListTitle()) {
				$title .= ": ".$myListName;
			}
			$gp->Title = (string)$title;
			$gp->write();
		}
		if($gp) {
			$this->GroupID = $gp->ID;
		}
	}

	/**
	 * add old campaings or remove them
	 * depending on the setting
	 *
	 */
	function onAfterWrite() {
		parent::onAfterWrite();
		if($this->ShowOldNewsletters) {
			$this->AddOldCampaigns();
		}
		else {
			$this->CampaignMonitorCampaigns()->removeAll();
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
		"stats" => true,
		"resetoldcampaigns" => true,
		"resetsignup" => true
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
				$signupField = $member->getSignupField(null, "SubscribeManyChoices");
			}
			else {
				$signupField = $member->getSignupField($this->ListID, "SubscribeChoice");
			}
			$fields = new FieldList(
				$signupField,
				new TextField('CampaignMonitorFirstName', _t("CAMPAIGNMONITORSIGNUPPAGE.FIRSTNAME", 'First Name')),
				new TextField('CampaignMonitorSurname', _t("CAMPAIGNMONITORSIGNUPPAGE.SURNAME",'Surname')),
				new EmailField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL",'Email'), $this->email)
			);
			// Create action
			$actions = new FieldList(
				new FormAction('subscribe', _t("CAMPAIGNMONITORSIGNUPPAGE.UPDATE_SUBSCRIPTIONS", "Update Subscriptions"))
			);
			// Create Validators
			$validator = new RequiredFields('CampaignMonitorEmail');
			$form = new Form($this, 'SignupForm', $fields, $actions, $validator);
			if($member->exists()) {
				$form->loadDataFrom($member);
			}
			else {
				$form->Fields()->fieldByName("CampaignMonitorEmail")->setValue($this->email);
			}
			return $form;
		}
		else {
			return _t("CampaignMonitorSignupPage.NOTREADY", "You can not suscribe to this newsletter at present.");
		}
	}

	/**
	 * action subscription form
	 * @param Array $array
	 * @param Form $form
	 *
	 * return redirect
	 */
	function subscribe($data, $form) {
		if($this->ReadyToReceiveSubscribtions()) {
			//true until proven otherwise.
			$newlyCreatedMember = false;
			$api = $this->getAPI();
			$member = Member::currentUser();

			//subscribe or unsubscribe?
			if(isset($data["SubscribeManyChoices"])) {
				$isSubscribe = true;
			}
			else {
				$isSubscribe = isset($data["SubscribeChoice"]) && $data["SubscribeChoice"] == "Subscribe";
			}

			//no member logged in: if the member already exists then you can't sign up.
			if(!$member) {
				$memberAlreadyLoggedIn = false;
				$existingMember = Member::get()->filter(array("Email" => Convert::raw2sql($data["CampaignMonitorEmail"])))->First();
				if($isSubscribe && $existingMember){
					//$form->addErrorMessage('Email', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use. Please log in for this email or try another email address."), 'warning');
					//$this->redirectBack();
					//return;
				}
				$member = $existingMember;
				if(!$member) {
					$newlyCreatedMember = true;
					$member = new Member();
				}
			}

			//logged in: if the member already as someone else then you can't sign up.
			else {
				$memberAlreadyLoggedIn = true;
				$existingMember = Member::get()
					->filter(array("Email" => Convert::raw2sql($data["CampaignMonitorEmail"])))
					->exclude(array("ID" => $member->ID))
					->First();
				if($isSubscribe && $existingMember) {
					//$form->addErrorMessage('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use by someone else. Please log in for this email or try another email address."), 'warning');
					//$this->redirectBack();
					//return;
				}
			}

			//are any choices being made
			if(!isset($data["SubscribeChoice"]) && !isset($data["SubscribeManyChoices"])) {
				$form->addErrorMessage('SubscribeChoice', _t("CAMPAIGNMONITORSIGNUPPAGE.NO_NAME", "Please choose your subscription."), 'warning');
				$this->redirectBack();
				return;
			}

			//if this is a new member then we save the member
			if($isSubscribe) {
				if($newlyCreatedMember) {
					$form->saveInto($member);
					$member->SetPassword = true;
					$member->Password = Member::create_new_password();
					$member->write();
					$member->logIn($keepMeLoggedIn = false);
				}
			}

			//many choices
			if(isset($data["SubscribeManyChoices"])) {
				$listPages = CampaignMonitorSignupPage::get_ready_ones();
				foreach($listPages as $listPage) {
					if(isset($data["SubscribeManyChoices"][$listPage->ListID]) && $data["SubscribeManyChoices"][$listPage->ListID]) {
						$member->addCampaignMonitorList($listPage->ListID);
						return $this->redirect($this->Link('thankyou'));
					}
					else {
						$member->removeCampaignMonitorList($listPage->ListID);
						return $this->redirect($this->Link('sadtoseeyougo'));
					}
				}
			}

			//one choice
			elseif(isset($data["SubscribeChoice"])) {
				if($data["SubscribeChoice"] == "Subscribe") {
					$member->addCampaignMonitorList($this->ListID);
					return $this->redirect($this->Link('thankyou'));
				}
				else {
					$member->removeCampaignMonitorList($this->ListID);
					return $this->redirect($this->Link('sadtoseeyougo'));
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
	 * action
	 * @param HTTPRequest
	 */
	function confirm($request) {
		$this->Title = $this->ConfirmTitle;
		$this->MenuTitle = $this->ConfirmMenuTitle;
		$this->Content = $this->ConfirmMessage;
		return array();
	}

	/**
	 * action
	 * @param HTTPRequest
	 */
	function thankyou($request) {
		$this->Title = $this->ThankYouTitle;
		$this->MenuTitle = $this->ThankYouMenuTitle;
		$this->Content = $this->ThankYouMessage;
		return array();
	}

	/**
	 * action
	 * @param HTTPRequest
	 */
	function sadtoseeyougo($request) {
		$this->Title = $this->SadToSeeYouGoTitle;
		$this->MenuTitle = $this->SadToSeeYouGoMenuTitle;
		$this->Content = $this->SadToSeeYouGoMessage;
		return array();
	}

	/**
	 * action
	 * @param HTTPRequest
	 */
	function preloademail(SS_HTTPRequest $request){
		$data = $request->requestVars();
		if(isset($data["CampaignMonitorEmail"])) {
			$email = Convert::raw2sql($data["CampaignMonitorEmail"]);
			if($email) {
				$this->email = $email;
				if(Director::is_ajax()) {
					if(!$this->addSubscriber($email)) {
						Session::set("CampaignMonitorStartForm_AjaxResult_".$this->ID, $data["CampaignMonitorEmail"]);
						return $this->renderWith("CampaignMonitorStartForm_AjaxResult");
					}
					else {
						return "ERROR";
					}
				}
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
		$campaignID = Convert::raw2sql($request->param("ID"));
		$this->campaign = CampaignMonitorCampaign::get()->filter(array("CampaignID" => $campaignID))->First();
		if(!$this->campaign) {
			return $this->httpError(404, _t("CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND", "Message not found."));
		}
		return array();
	}

	/**
	 *
	 * @return String
	 */
	function Email(){
		return $this->email;
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
			return CampaignMonitorCampaign::get()
				->filter(
					array(
						"ID" => $campaigns->map("ID", "ID")->toArray(),
						"Hide" => 0
					)
				);
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
			$html = "<div id=\"CampaignMonitorStats\">";
			$html .= "<h1>Debug Response</h1>";
			$html .= "<h2>Main Client Stuff</h2>";
			$html .= "<h3>Link to confirm page</h3>".Director::absoluteUrl($this->Link("confirm"))."";
			$html .= "<h3>Link to thank-you-page</h3>".Director::absoluteUrl($this->Link("thankyou"))."";
			$html .= "<h3>Link to sad-to-see-you-go page</h3>".Director::absoluteUrl($this->Link("sadtoseeyougo"))."";
			$html .= "<h3><a href=\"#\">All Campaigns</a></a></h3><pre>".print_r($api->getCampaigns(), 1)."</pre>";
			$html .= "<h3><a href=\"#\">All Lists</a></h3><pre>".print_r($api->getLists(), 1)."</pre>";
			if($this->ListID) {
				$html .= "<h2>List</h2";
				$html .= "<h3><a href=\"#\" id=\"MyListStatsAreHere\">List Stats</a></h3><pre>".print_r($api->getListStats($this->ListID), 1)."</pre>";
				$html .= "<h3><a href=\"#\">List Details</a></h3><pre>".print_r($api->getList($this->ListID), 1)."</pre>";
				$html .= "<h3><a href=\"#\">Active Subscribers (latest ones)</a></h3><pre>".print_r($api->getActiveSubscribers($this->ListID), 1)."</pre>";
				$html .= "<h3><a href=\"#\">Unconfirmed Subscribers (latest ones)</a></h3><pre>".print_r($api->getUnconfirmedSubscribers($this->ListID), 1)."</pre>";
				$html .= "<h3><a href=\"#\">Bounced Subscribers (latest ones)</a></h3><pre>".print_r($api->getBouncedSubscribers($this->ListID), 1)."</pre>";
				$html .= "<h3><a href=\"#\">Unsubscribed Subscribers (latest ones)</a></h3><pre>".print_r($api->getUnsubscribedSubscribers($this->ListID), 1)."</pre>";

			}
			else {
				$html .= "<h2 style=\"color: red;\">ERROR: No Lists selected</h2";

			}
			Requirements::customScript($this->JSHackForPreSections(), "CampaignMonitorStats");
			$html .= "</div>";
			$this->Content = $html;
		}
		else {
			Security::permissionFailure($this, _t("CAMPAIGNMONITORSIGNUPPAGE.TESTFAILURE", "This function is only available for administrators"), 1);
		}
		return array();
	}

	/**
	 * returns a bunch of stats about a campaign
	 * IF the user is an admin AND a campaign is selected
	 * @return String (html) | false
	 */
	function CampaignStats(){
		if(Permission::check("CMS_ACCESS_CMSMain")) {
			if($this->campaign) {
				//run tests here
				$api = $this->getAPI();
				$html = "<div id=\"CampaignMonitorStats\">";
				$html .= "<h2>Campaign Stats</h2>";
				$html .= "<h3><a href=\"#\">Campaign: ".$this->campaign->Subject."</a></h3>";
				$html .= "<h3><a href=\"#\">Summary</a></h3><pre>".print_r($api->getSummary($this->campaign->CampaignID), 1)."</pre>";
				$html .= "<h3><a href=\"#\">Email Client Usage</a></h3><pre>".print_r($api->getEmailClientUsage($this->campaign->CampaignID), 1)."</pre>";
				$html .= "<h3><a href=\"#\">Unsubscribes</a></h3><pre>".print_r($api->getUnsubscribes($this->campaign->CampaignID), 1)."</pre>";
				Requirements::customScript($this->JSHackForPreSections(), "CampaignMonitorStats");
				$html .= "</div>";
				$this->Content = $html;

			}
		}
		else {
			return false;
		}
	}

	/**
	 * action
	 * @param HTTPRequest
	 */
	function resetsignup($request){
		Session::clear("CampaignMonitorStartForm_AjaxResult_".$this->ID);
		return array();
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


	/**
	 * @return String
	 */
	private function JSHackForPreSections(){
		$js = <<<javascript
			jQuery(document).ready(
				function(){
					jQuery('pre').hide();
					jQuery('#CampaignMonitorStats').on(
						'click',
						'a',
						function(event){
							event.preventDefault();
							jQuery(this).parent().next('pre').slideToggle();
						}
					);
					jQuery("#MyListStatsAreHere").click();
				}
			);

javascript;
		return $js;
	}

}
