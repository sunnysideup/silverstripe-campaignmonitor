<?php


/**
 * simple class to see that everything is working ...
 *
 *
 */

class CampaignMonitorAPIConnector_TestController extends Controller {

	private static $url_segment = "create-send-test";

	private static $allowed_actions = array(
		"testall" => "CMS_ACCESS_CMSMain",
		"testlists" => "CMS_ACCESS_CMSMain",
		"testcampaigns" => "CMS_ACCESS_CMSMain",
		"testsubscribers" => "CMS_ACCESS_CMSMain"
	);

	/**
	 * example data
	 * @var Array
	 */
	protected $egData = array(
		"limit" => 10,
		"listID" => "",
		"listIDtoDelete" => "",
		"campaignID" => "",
		"listTitle" => "Test List 9",
		"unsubscribePage" => "http://unsub",
		"confirmedOptIn" => false,
		"confirmationSuccessPage" => "http://confirmed",
		"unsubscribeSetting" => null,
		"addUnsubscribesToSuppList" => true,
		"scrubActiveWithSuppList" => true,
		"oldEmailAddress" => "oldemail@test.nowhere",
		"newEmailAddress" => "newemail@test.nowhere"
	);

	/**
	 * contains API once started
	 * @var CampaignMonitorAPIConnector
	 */
	protected $api = null;

	/**
	 * should we show as much as possible?
	 * @var Boolean
	 */
	protected $showAll = false;

	function init(){
		parent::init();
		if(!Config::inst()->get("CampaignMonitorAPIConnector", "client_id")) {
			user_error("To use the campaign monitor module you must set the basic authentication credentials such as CampaignMonitorAPIConnector.client_id");
		}
		$this->egData["listTitle"] = $this->egData["listTitle"].rand(0,999999999999);
	}

	/**
	 * link for controller
	 * we add baseURL to make it work for all set ups.
	 * @var String
	 */
	function Link($action = null){
		$link = Director::baseURL().$this->Config()->get("url_segment")."/";
		if($action) {
			$link .= $action . "/";
		}
		return $link;
	}

	/**
	 * run all tests
	 */
	function testall(){
		$this->testlists();
		$this->testcampaigns();
		$this->testsubscribers();
		$this->index();
		die("<h1>THE END</h1>");
	}

	function index(){
		echo "
			<hr /><hr /><hr /><hr /><hr />
			<ul>
				<li><a href=\"".$this->Link("testlists")."\">test lists</a></li>
				<li><a href=\"".$this->Link("testcampaigns")."\">test campaigns</a></li>
				<li><a href=\"".$this->Link("testsubscribers")."\">test subscribers</a></li>
				<li><a href=\"".$this->Link("testall")."\">test all</a></li>
			</ul>
			<hr /><hr /><hr /><hr /><hr />
		";
	}

	function testlists(){
		$this->setupTests();

		//create list
		$result = $this->api->createList(
			$this->egData["listTitle"],
			$this->egData["unsubscribePage"],
			$this->egData["confirmedOptIn"],
			$this->egData["confirmationSuccessPage"],
			$this->egData["unsubscribeSetting"]
		);
		$this->egData["listIDtoDelete"] = $result;

		//update list
		$result = $this->api->updateList(
			$this->egData["listIDtoDelete"],
			$this->egData["listTitle"]."updated_22",
			$this->egData["unsubscribePage"]."updated",
			$this->egData["confirmedOptIn"],
			$this->egData["confirmationSuccessPage"]."updated",
			$this->egData["unsubscribeSetting"],
			$addUnsubscribesToSuppList = true,
			$scrubActiveWithSuppList = true
		);

		//delete list
		if($this->egData["listIDtoDelete"]) {
			$result = $this->api->deleteList($this->egData["listIDtoDelete"]);
		}

		//getList
		$result = $this->api->getList($this->egData["listID"]);

		$result = $this->api->getActiveSubscribers(
			$this->egData["listID"],
			$daysAgo = 3650,
			$page = 1,
			$pageSize = $this->egData["limit"],
			$sortByField = "DATE",
			$sortDirection = "DESC"
		);

		$result = $this->api->getUnconfirmedSubscribers(
			$this->egData["listID"],
			$daysAgo = 3650,
			$page = 1,
			$pageSize = $this->egData["limit"],
			$sortByField = "DATE",
			$sortDirection = "DESC"
		);

		$result = $this->api->getBouncedSubscribers(
			$this->egData["listID"],
			$daysAgo = 3650,
			$page = 1,
			$pageSize = $this->egData["limit"],
			$sortByField = "DATE",
			$sortDirection = "DESC"
		);

		$result = $this->api->getUnsubscribedSubscribers(
			$this->egData["listID"],
			$daysAgo = 3650,
			$page = 1,
			$pageSize = $this->egData["limit"],
			$sortByField = "DATE",
			$sortDirection = "DESC"
		);

		$result = $this->api->getSegments($this->egData["listID"]);

		$result = $this->api->getListStats($this->egData["listID"]);

		$result = $this->api->getListCustomFields($this->egData["listID"]);

		echo "<h2>end of list tests</h2>";
		$this->index();
	}

	function testcampaigns(){
		$this->setupTests();

		//campaign summary

		$result = $this->api->getCampaigns();

		$result = $this->api->getDrafts();

		$result = $this->api->getSummary($this->egData["campaignID"]);

		$result = $this->api->getEmailClientUsage($this->egData["campaignID"]);

		$result = $this->api->getUnsubscribes(
			$this->egData["campaignID"],
			$daysAgo = 3650,
			$page =1,
			$pageSize = $this->egData["limit"],
			$sortByField = "EMAIL",
			$sortDirection = "ASC"
		);

		echo "<h3>creating a campaign</h3>";
		$obj = CampaignMonitorCampaign::create();
		$randNumber = rand(0, 9999999);
		$obj->Name = "test only ".$randNumber;
		$obj->Subject = "test only".$randNumber;
		$obj->CreateFromWebsite = true;
		$obj->write();
		$result = $this->api->getSummary($obj->CampaignID);

		echo "<h3>deleting a campaign</h3>";
		$obj->delete();

		echo "<h2>end of campaign tests</h2>";
		$this->index();

	}

	function testsubscribers() {

		$this->setupTests();


		//create list
		$result = $this->api->createList(
			$this->egData["listTitle"],
			$this->egData["unsubscribePage"],
			$this->egData["confirmedOptIn"],
			$this->egData["confirmationSuccessPage"],
			$this->egData["unsubscribeSetting"]
		);
		$this->egData["tempListID"] = $result;

		$customFieldKey = $this->api->createCustomField(
			$this->egData["tempListID"],
			$visible = true,
			$type = "multi_select_one",
			$title = "are you happy?",
			$options = array("YES", "NO")
		);

		for($i = 0; $i < 5; $i++) {
			$member[$i] = new Member();
			$email = "test_".$i."_".$this->egData["oldEmailAddress"];
			$member[$i] = Member::get()->filter(array("Email" => $email))->First();
			if(!$member[$i]) {
				$member[$i] = new Member();
				$member[$i]->Email = $email;
				$member[$i]->FirstName = "First Name $i";
				$member[$i]->Surname = "Surname $i";
				$member[$i]->write();
			}
			$result = $this->api->addSubscriber(
				$this->egData["tempListID"],
				$member[$i],
				$customFields = array($customFieldKey => "NO"),
				$resubscribe = true,
				$restartSubscriptionBasedAutoResponders = false
			);
			$result = $this->api->updateSubscriber(
				$this->egData["tempListID"],
				$email,
				$member[$i],
				$customFields = array($customFieldKey => "YES"),
				$resubscribe = true,
				$restartSubscriptionBasedAutoResponders = false
			);
			sleep(1);
		}


		/*
		$result = $this->api->addSubscribers(
			$this->egData["tempListID"],
			$membersSet,
			$customFields = array(),
			$resubscribe,
			$queueSubscriptionBasedAutoResponders = false,
			$restartSubscriptionBasedAutoResponders = false
		);
		*/

		$result = $this->api->deleteSubscriber(
			$this->egData["tempListID"],
			$member[2]
		);

		$result = $this->api->unsubscribeSubscriber(
			$this->egData["tempListID"],
			$member[3]
		);


		for($i = 0; $i < 5; $i++) {

			$result = $this->api->getSubscriberExistsForThisList(
				$this->egData["tempListID"],
				$member[$i]
			);

			$result = $this->api->getListsForEmail($member[$i]);

			$result = $this->api->getSubscriberCanReceiveEmailsForThisList(
				$this->egData["tempListID"],
				$member[$i]
			);

			$result = $this->api->getSubscriberCanNoLongerReceiveEmailsForThisList(
				$this->egData["tempListID"],
				$member[$i]
			);

			$result = $this->api->getSubscriber(
				$this->egData["tempListID"],
				$member[$i]
			);

			$result = $this->api->getHistory(
				$this->egData["tempListID"],
				$member[$i]
			);
			$result = $this->api->deleteSubscriber(
				$this->egData["tempListID"],
				$member[$i]
			);
			$member[$i]->delete();
			sleep(1);
		}

		//delete list
		if($this->egData["tempListID"]) {
			$this->api->deleteCustomField($this->egData["tempListID"], $customFieldKey);
			$result = $this->api->deleteList($this->egData["tempListID"]);
		}

		echo "<h2>end of subscriber tests</h2>";
		$this->index();
	}

	protected function setupTests(){
		$this->api = CampaignMonitorAPIConnector::create();
		$this->api->init();

		if($this->showAll) {
			$this->api->setDebug(true);
			$this->egData["limit"] = 100;
		}

		//getLists
		$result = $this->api->getLists();
		$this->egData["listID"] = $result[0]->ListID;

		//getCampaigns
		$result = $this->api->getCampaigns();
		if(isset($result[0])) {
			$this->egData["campaignID"] = $result[0]->CampaignID;
		}

		$this->api->setDebug(true);

	}

}
