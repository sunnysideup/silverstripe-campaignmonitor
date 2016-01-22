<?php

/**
 * Main Holder page for Recipes
 *@author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorAPIConnector extends Object {

	/**
	 * REQUIRED!
	 * this is the CM url for logging in.
	 * which can be used by the client.
	 * @var String
	 */
	private static $campaign_monitor_url = "";

	/**
	 * REQUIRED!
	 * @var String
	 */
	private static $client_id = "";


	/**
	 * OPTION 1: API KEY!
	 * @var String
	 */
	private static $api_key = "";


	/**
	 * OPTION 2: OAUTH OPTION
	 * @var String
	 */
	private static $client_secret = "";

	/**
	 * OPTION 2: OAUTH OPTION
	 * @var String
	 */
	private static $redirect_uri = "";

	/**
	 * OPTION 2: OAUTH OPTION
	 * @var String
	 */
	private static $code = "";

	/**
	 *
	 * @var Boolean
	 */
	protected $debug = false;

	/**
	 *
	 * @var Boolean
	 */
	protected $allowCaching = false;

	/**
	 *
	 * @var Int
	 */
	protected $httpStatusCode = 0;


	/**
	 *
	 * must be called to use this API.
	 */
	public function init(){
		//require_once Director::baseFolder().'/'.SS_CAMPAIGNMONITOR_DIR.'/third_party/vendor/autoload.php';
		//require_once Director::baseFolder().'/'.SS_CAMPAIGNMONITOR_DIR.'/third_party/vendor/campaignmonitor/createsend-php/csrest_lists.php';
	}

	/**
	 * turn debug on or off
	 *
	 * @param Boolean
	 */
	public function setDebug($b){
		$this->debug = $b;
	}

	/**
	 *
	 * @param Boolean $b
	 */
	public function setAllowCaching($b) {
		$this->allowCaching = $b;
	}

	/**
	 *
	 * @return Boolean
	 */
	public function getAllowCaching() {
		return $this->allowCaching;
	}

	/**
	 * provides the Authorisation Array
	 * @return Array
	 */
	protected function getAuth(){
		if($auth = $this->getFromCache("getAuth")) {
			return $auth;
		}
		else {
			if($apiKey = $this->Config()->get("api_key")) {
				$auth = array('api_key' => $apiKey);
			}
			else {
				$client_id = $this->Config()->get("client_id");
				$client_secret = $this->Config()->get("client_secret");
				$redirect_uri = $this->Config()->get("redirect_uri");
				$code = $this->Config()->get("code");

				$result = CS_REST_General::exchange_token($client_id, $client_secret, $redirect_uri, $code);

				if($result->was_successful()) {
					$auth = array(
						'access_token' => $result->response->access_token,
						'refresh_token' => $result->response->refresh_token
					);
					//TODO: do we need to check expiry date?
					//$expires_in = $result->response->expires_in;
					# Save $access_token, $expires_in, and $refresh_token.
					if($this->debug) {
						 "access token: ".$result->response->access_token."\n";
						 "expires in (seconds): ".$result->response->expires_in."\n";
						 "refresh token: ".$result->response->refresh_token."\n";
					}
				}
				else {
					# If you receive '121: Expired OAuth Token', refresh the access token
					if ($result->response->Code == 121) {
						$wrap = new CS_REST_General($auth);
						list($new_access_token, $new_expires_in, $new_refresh_token) = $wrap->refresh_token();
					}
					$auth = array(
						'access_token' => $new_access_token,
						'refresh_token' => $new_refresh_token
					);
					if($this->debug) {
						 'An error occurred:\n';
						 $result->response->error.': '.$result->response->error_description."\n";
					}
				}
			}
			$this->saveToCache($auth, "getAuth");
			return $auth;
		}
	}

	/**
	 * returns the result or NULL in case of an error
	 * @param CS_REST_Wrapper_Result
	 * @return Mixed | Null
	 */
	protected function returnResult($result, $apiCall, $description) {
		if($this->debug) {
			echo "<h1>$description ( $apiCall ) ...</h1>";
			if($result->was_successful()) {
				echo "<h2>SUCCESS</h2>";
			}
			else {
				echo "<h2>FAILURE: ".$result->http_status_code."</h2>";
			}
			echo "<pre>";
			print_r($result);
			echo "</pre>";
			echo "<hr /><hr /><hr />";
		}
		if($result->was_successful()) {
			if($result->response && is_array($result->response)) {
				return $result->response;
			}
			return true;
		}
		else {
			$this->httpStatusCode = $result->http_status_code;
			return null;
		}
	}

	/**
	 * returns the HTTP code for the response.
	 * This can be handy for debuging purposes.
	 * @return Int
	 */
	public function getHttpStatusCode(){
		return $this->httpStatusCode;
	}

	/*******************************************************
	 * caching
	 *
	 *******************************************************/

	/**
	 * @param String $name
	 * @return Mixed
	 */
	protected function getFromCache($name) {
		if($this->getAllowCaching()) {
			$name = "CampaignMonitorAPIConnector_".$name;
			$cache = SS_Cache::factory($name);
			$value = $cache->load($name);
			if(!$value) {
				return null;
			}
			return unserialize($value);
		}
	}

	/**
	 * @param Mixed $unserializedValue
	 * @param String $name
	 */
	protected function saveToCache($unserializedValue, $name) {
		if($this->getAllowCaching()) {
			$serializedValue = serialize($unserializedValue);
			$name = "CampaignMonitorAPIConnector_".$name;
			$cache = SS_Cache::factory($name);
			$cache->save($serializedValue, $name);
			return true;
		}
	}


	/*******************************************************
	 * client
	 *
	 *******************************************************/

	/**
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * array(
	 *     {
	 *         'WebVersionURL' => The web version url of the campaign
	 *         'WebVersionTextURL' => The web version url of the text version of the campaign
	 *         'CampaignID' => The id of the campaign
	 *         'Subject' => The campaign subject
	 *         'Name' => The name of the campaign
	 *         'FromName' => The from name for the campaign
	 *         'FromEmail' => The from email address for the campaign
	 *         'ReplyTo' => The reply to email address for the campaign
	 *         'SentDate' => The sent data of the campaign
	 *         'TotalRecipients' => The number of recipients of the campaign
	 *     }
	 */
	public function getCampaigns(){
		//require_once '../../csrest_clients.php';
		$wrap = new CS_REST_Clients($this->Config()->get("client_id"), $this->getAuth());
		$result = $wrap->get_campaigns();
		return $this->returnResult(
			$result,
			"GET /api/v3.1/clients/{id}/campaigns",
			"Got sent campaigns"
		);
	}

	public function getDrafts(){
		//require_once '../../csrest_clients.php';
		$wrap = new CS_REST_Clients($this->Config()->get("client_id"), $this->getAuth());
		$result = $wrap->get_drafts();
		return $this->returnResult(
			$result,
			"GET /api/v3.1/clients/{id}/drafts",
			"Got draft campaigns"
		);
	}

	/**
	 * Gets all subscriber lists the current client has created
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * array(
	 *     {
	 *         'ListID' => The id of the list
	 *         'Name' => The name of the list
	 *     }
	 * )
	 */
	public function getLists(){
		//require_once '../../csrest_clients.php';
		$wrap = new CS_REST_Clients($this->Config()->get("client_id"),$this->getAuth());
		$result = $wrap->get_lists();
		return $this->returnResult(
			$result,
			"GET /api/v3.1/clients/{id}/lists",
			"Got Lists"
		);
	}

	public function getScheduled(){user_error("This method is still to be implemented, see samples for an example");}


	public function getSuppressionlist(){user_error("This method is still to be implemented, see samples for an example");}

	public function getTemplates(){user_error("This method is still to be implemented, see samples for an example");}


	/*******************************************************
	 * lists
	 *
	 *******************************************************/

	/**
	 * Creates a new list based on the provided details.
	 * Both the UnsubscribePage and the ConfirmationSuccessPage parameters are optional
	 *
	 * @param string $title - he page to redirect subscribers to when they unsubscribeThe list title
	 * @param string $unsubscribePage - The page to redirect subscribers to when they unsubscribe
	 * @param boolean $confirmedOptIn - Whether this list requires confirmation of subscription
	 * @param string $confirmationSuccessPage - The page to redirect subscribers to when they confirm their subscription
	 * @param string $unsubscribeSetting - Unsubscribe setting must be CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS or CS_REST_LIST_UNSUBSCRIBE_SETTING_ONLY_THIS_LIST.  See the documentation for details: http://www.campaignmonitor.com/api/lists/#creating_a_list
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be the ID of the newly created list
	 */
	public function createList($title, $unsubscribePage, $confirmedOptIn = false, $confirmationSuccessPage, $unsubscribeSetting = null){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists(NULL, $this->getAuth());
		//we need to do this afterwards otherwise the definition below
		//is not recognised
		if(!$unsubscribeSetting) {
			$unsubscribeSetting = CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS;
		}
		$result = $wrap->create(
			$this->Config()->get("client_id"),
			array(
				'Title' => $title,
				'UnsubscribePage' => $unsubscribePage,
				'ConfirmedOptIn' => $confirmedOptIn,
				'ConfirmationSuccessPage' => $confirmationSuccessPage,
				'UnsubscribeSetting' => $unsubscribeSetting
			)
		);
		return $this->returnResult(
			$result,
			"POST /api/v3.1/lists/{clientID}",
			"Created with ID"
		);
	}

	/**
	 * Deletes an existing list from the system
	 * @param Int $listID
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	public function deleteList($listID){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->delete();
		return $this->returnResult(
			$result,
			"DELETE /api/v3.1/lists/{ID}",
			"Deleted with code"
		);
	}

	/**
	 * Gets the basic details of the current list
	 *
	 * @param Int $listID
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'ListID' => The id of the list
	 *     'Title' => The title of the list
	 *     'UnsubscribePage' => The page which subscribers are redirected to upon unsubscribing
	 *     'ConfirmedOptIn' => Whether the list is Double-Opt In
	 *     'ConfirmationSuccessPage' => The page which subscribers are
	 *         redirected to upon confirming their subscription
	 *     'UnsubscribeSetting' => The unsubscribe setting for the list. Will
	 *         be either CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS or
	 *         CS_REST_LIST_UNSUBSCRIBE_SETTING_ONLY_THIS_LIST.
	 * }
	 */
	public function getList($listID){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get();
		return $this->returnResult(
			$result,
			"GET /api/v3.1/lists/{ID}",
			"Got list details"
		);
	}

	/**
	 * Gets all active subscribers added since the given date
	 *
	 * @param Int $listID
	 * @param string $daysAgo The date to start getting subscribers from
	 * @param int $page The page number to get
	 * @param int $pageSize The number of records per page
	 * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
	 * @param string $sortDirection ('ASC', 'DESC')
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'ResultsOrderedBy' => The field the results are ordered by
	 *     'OrderDirection' => The order direction
	 *     'PageNumber' => The page number for the result set
	 *     'PageSize' => The page size used
	 *     'RecordsOnThisPage' => The number of records returned
	 *     'TotalNumberOfRecords' => The total number of records available
	 *     'NumberOfPages' => The total number of pages for this collection
	 *     'Results' => array(
	 *         {
	 *             'EmailAddress' => The email address of the subscriber
	 *             'Name' => The name of the subscriber
	 *             'Date' => The date that the subscriber was added to the list
	 *             'State' => The current state of the subscriber, will be 'Active'
	 *             'CustomFields' => array (
	 *                 {
	 *                     'Key' => The personalisation tag of the custom field
	 *                     'Value' => The value of the custom field for this subscriber
	 *                 }
	 *             )
	 *         }
	 *     )
	 * }
	 */
	public function getActiveSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = "DATE", $sortDirection = "DESC"){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_active_subscribers(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		return $this->returnResult(
			$result,
			"GET /api/v3.1/lists/{ID}/active",
			"Got subscribers"
		);
	}

	/**
	 * Gets all unconfirmed subscribers added since the given date
	 *
	 * @param Int $listID
	 * @param string $daysAgo The date to start getting subscribers from
	 * @param int $page The page number to get
	 * @param int $pageSize The number of records per page
	 * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
	 * @param string $sortDirection ('ASC', 'DESC')
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'ResultsOrderedBy' => The field the results are ordered by
	 *     'OrderDirection' => The order direction
	 *     'PageNumber' => The page number for the result set
	 *     'PageSize' => The page size used
	 *     'RecordsOnThisPage' => The number of records returned
	 *     'TotalNumberOfRecords' => The total number of records available
	 *     'NumberOfPages' => The total number of pages for this collection
	 *     'Results' => array(
	 *         {
	 *             'EmailAddress' => The email address of the subscriber
	 *             'Name' => The name of the subscriber
	 *             'Date' => The date that the subscriber was added to the list
	 *             'State' => The current state of the subscriber, will be 'Unconfirmed'
	 *             'CustomFields' => array (
	 *                 {
	 *                     'Key' => The personalisation tag of the custom field
	 *                     'Value' => The value of the custom field for this subscriber
	 *                 }
	 *             )
	 *         }
	 *     )
	 * }
	 */
	public function getUnconfirmedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = "DATE", $sortDirection = "DESC"){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_unconfirmed_subscribers(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		return $this->returnResult(
			$result,
			"GET /api/v3.1/lists/{ID}/unconfirmed",
			"Got subscribers"
		);
	}

	/**
	 * Gets all bounced subscribers who have bounced out since the given date
	 *
	 * @param Int $listID
	 * @param string $daysAgo The date to start getting subscribers from
	 * @param int $page The page number to get
	 * @param int $pageSize The number of records per page
	 * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
	 * @param string $sortDirection ('ASC', 'DESC')
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'ResultsOrderedBy' => The field the results are ordered by
	 *     'OrderDirection' => The order direction
	 *     'PageNumber' => The page number for the result set
	 *     'PageSize' => The page size used
	 *     'RecordsOnThisPage' => The number of records returned
	 *     'TotalNumberOfRecords' => The total number of records available
	 *     'NumberOfPages' => The total number of pages for this collection
	 *     'Results' => array(
	 *         {
	 *             'EmailAddress' => The email address of the subscriber
	 *             'Name' => The name of the subscriber
	 *             'Date' => The date that the subscriber bounced out of the list
	 *             'State' => The current state of the subscriber, will be 'Bounced'
	 *             'CustomFields' => array (
	 *                 {
	 *                     'Key' => The personalisation tag of the custom field
	 *                     'Value' => The value of the custom field for this subscriber
	 *                 }
	 *             )
	 *         }
	 *     )
	 * }
	 */
	public function getBouncedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = "DATE", $sortDirection = "DESC"){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_bounced_subscribers(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		return $this->returnResult(
			$result,
			"GET /api/v3.1/lists/{ID}/bounced",
			"Got subscribers"
		);

	}


	/**
	 * Gets all unsubscribed subscribers who have unsubscribed since the given date
	 *
	 * @param Int $listID
	 * @param string $daysAgo The date to start getting subscribers from
	 * @param int $page The page number to get
	 * @param int $pageSize The number of records per page
	 * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
	 * @param string $sortDirection ('ASC', 'DESC')
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'ResultsOrderedBy' => The field the results are ordered by
	 *     'OrderDirection' => The order direction
	 *     'PageNumber' => The page number for the result set
	 *     'PageSize' => The page size used
	 *     'RecordsOnThisPage' => The number of records returned
	 *     'TotalNumberOfRecords' => The total number of records available
	 *     'NumberOfPages' => The total number of pages for this collection
	 *     'Results' => array(
	 *         {
	 *             'EmailAddress' => The email address of the subscriber
	 *             'Name' => The name of the subscriber
	 *             'Date' => The date that the subscriber was unsubscribed from the list
	 *             'State' => The current state of the subscriber, will be 'Unsubscribed'
	 *             'CustomFields' => array (
	 *                 {
	 *                     'Key' => The personalisation tag of the custom field
	 *                     'Value' => The value of the custom field for this subscriber
	 *                 }
	 *             )
	 *         }
	 *     )
	 * }
	 */
	public function getUnsubscribedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = "DATE", $sortDirection = "DESC"){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_unsubscribed_subscribers(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		return $this->returnResult(
			$result,
			"GET /api/v3.1/lists/{ID}/unsubscribed",
			"Got subscribers"
		);
	}

	/**
	 * Updates the details of an existing list
	 * Both the UnsubscribePage and the ConfirmationSuccessPage parameters are optional
	 *
	 * @param string $title - he page to redirect subscribers to when they unsubscribeThe list title
	 * @param string $unsubscribePage - The page to redirect subscribers to when they unsubscribe
	 * @param boolean $confirmedOptIn - Whether this list requires confirmation of subscription
	 * @param string $confirmationSuccessPage - The page to redirect subscribers to when they confirm their subscription
	 * @param string $unsubscribeSetting - Unsubscribe setting must be CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS or CS_REST_LIST_UNSUBSCRIBE_SETTING_ONLY_THIS_LIST.  See the documentation for details: http://www.campaignmonitor.com/api/lists/#creating_a_list
	 * @param boolean $addUnsubscribesToSuppList -  When UnsubscribeSetting is CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS, whether unsubscribes from this list should be added to the suppression list.
	 * @param boolean $acrubActiveWithSuppList - When UnsubscribeSetting is CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS, whether active subscribers should be scrubbed against the suppression list.
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	public function updateList($listID, $title, $unsubscribePage, $confirmedOptIn = false, $confirmationSuccessPage, $unsubscribeSetting, $addUnsubscribesToSuppList = true, $scrubActiveWithSuppList = true) {
		//require_once '../../csrest_lists.php';
		if(!$unsubscribeSetting) {
			$unsubscribeSetting = CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS;
		}
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->update(array(
			'Title' => $title,
			'UnsubscribePage' => $unsubscribePage,
			'ConfirmedOptIn' => $confirmedOptIn,
			'ConfirmationSuccessPage' => $confirmationSuccessPage,
			'UnsubscribeSetting' => $unsubscribeSetting,
			'AddUnsubscribesToSuppList' => $addUnsubscribesToSuppList,
			'ScrubActiveWithSuppList' => $scrubActiveWithSuppList
		));
		return $this->returnResult(
			$result,
			"PUT /api/v3.1/lists/{ID}",
			"Updated with code"
		);
	}

	public function getSegments($listID){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		//we need to do this afterwards otherwise the definition below
		//is not recognised
		$result = $wrap->get_segments();
		return $this->returnResult(
			$result,
			"GET /api/v3.1/lists/{listid}/segments",
			"Got segment details"
		);
	}

	/**
	 * Gets statistics for list subscriptions, deletions, bounces and unsubscriptions
	 *
	 * @param Int $listID
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'TotalActiveSubscribers'
	 *     'NewActiveSubscribersToday'
	 *     'NewActiveSubscribersYesterday'
	 *     'NewActiveSubscribersThisWeek'
	 *     'NewActiveSubscribersThisMonth'
	 *     'NewActiveSubscribersThisYeay'
	 *     'TotalUnsubscribes'
	 *     'UnsubscribesToday'
	 *     'UnsubscribesYesterday'
	 *     'UnsubscribesThisWeek'
	 *     'UnsubscribesThisMonth'
	 *     'UnsubscribesThisYear'
	 *     'TotalDeleted'
	 *     'DeletedToday'
	 *     'DeletedYesterday'
	 *     'DeletedThisWeek'
	 *     'DeletedThisMonth'
	 *     'DeletedThisYear'
	 *     'TotalBounces'
	 *     'BouncesToday'
	 *     'BouncesYesterday'
	 *     'BouncesThisWeek'
	 *     'BouncesThisMonth'
	 *     'BouncesThisYear'
	 * }
	 */
	function getListStats($listID){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_stats();
		return $this->returnResult(
			$result,
			"GET /api/v3.1/lists/{ID}/stats",
			"Got Lists Stats"
		);
	}

	/*******************************************************
	 * create campaigns
	 *
	 *******************************************************/

	function createCampaign(
		$campaignMonitorCampaign,
		$listIDs = array(),
		$segmentIDs = array()
	){
		//require_once '../../csrest_lists.php';
		$siteConfig = SiteConfig::current_site_config();

		$subject = $campaignMonitorCampaign->Subject;
		if(!$subject) {
			$subject = "no subject set";
		}

		$name = $campaignMonitorCampaign->Name;
		if(!$name) {
			$name = "no name set";
		}

		$fromName = $campaignMonitorCampaign->FromName;
		if(!$fromName) {
			$fromName = $siteConfig->Title;
		}

		$fromEmail = $campaignMonitorCampaign->FromEmail;
		if(!$fromEmail) {
			$fromEmail = Config::inst()->get('Email', 'admin_email');
		}

		$replyTo = $campaignMonitorCampaign->ReplyTo;
		if(!$replyTo) {
			$replyTo = $fromEmail;
		}

		$listID = $campaignMonitorCampaign->Pages()->first()->ListID;

		$wrap = new CS_REST_Campaigns(null, $this->getAuth());
		$result = $wrap->create(
			$this->Config()->get("client_id"),
			array(
				'Subject' => $subject,
				'Name' => $name,
				'FromName' => $fromName,
				'FromEmail' => $fromEmail,
				'ReplyTo' => $replyTo,
				'HtmlUrl' => $campaignMonitorCampaign->PreviewLink(),
				'TextUrl' => $campaignMonitorCampaign->PreviewLink("textonly"),
				'ListIDs' => array($listID)
			)
		);
		if(isset($result->http_status_code) && ($result->http_status_code == 201 || $result->http_status_code == 201)) {
			$code = $result->response;
		}
		else {
			$code = "Error";
			if(is_object($result->response)) {
				$code = $result->response->Code.":".$result->response->Message;
			}
		}
		$campaignMonitorCampaign->CreatedFromWebsite = true;
		$campaignMonitorCampaign->CampaignID = $code;
		$campaignMonitorCampaign->write();

		return $this->returnResult(
			$result,
			"CREATE /api/v3/campaigns/{clientID}",
			"Created Campaign"
		);
	}

	function deleteCampaign($campaignID){
		$wrap = new CS_REST_Campaigns($campaignID, $this->getAuth());
		$result = $wrap->delete();
		return $this->returnResult(
			$result,
			"DELETE /api/v3/campaigns/{id}",
			"Deleted Campaign"
		);
	}

	/*******************************************************
	 * information about the campaigns
	 *
	 *******************************************************/

	function getBounces(){user_error("This method is still to be implemented, see samples for an example");}

	function getClicks(){user_error("This method is still to be implemented, see samples for an example");}

	/**
	 * Gets a summary of all campaign reporting statistics
	 *
	 * @param int $campaignID
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'Recipients' => The total recipients of the campaign
	 *     'TotalOpened' => The total number of opens recorded
	 *     'Clicks' => The total number of recorded clicks
	 *     'Unsubscribed' => The number of recipients who unsubscribed
	 *     'Bounced' => The number of recipients who bounced
	 *     'UniqueOpened' => The number of recipients who opened
	 *     'WebVersionURL' => The url of the web version of the campaign
	 *     'WebVersionTextURL' => The url of the web version of the text version of the campaign
	 *     'WorldviewURL' => The public Worldview URL for the campaign
	 *     'Forwards' => The number of times the campaign has been forwarded to a friend
	 *     'Likes' => The number of times the campaign has been 'liked' on Facebook
	 *     'Mentions' => The number of times the campaign has been tweeted about
	 *     'SpamComplaints' => The number of recipients who marked the campaign as spam
	 * }
	 */
	function getSummary($campaignID){
		$wrap = new CS_REST_Campaigns($campaignID, $this->getAuth());
		$result = $wrap->get_summary();
		return $this->returnResult(
			$result,
			"GET /api/v3.1/campaigns/{id}/summary",
			"Got Summary"
		);
	}

	/**
	 * Gets the email clients that subscribers used to open the campaign
	 *
	 * @param Int $campaignID
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * array(
	 *     {
	 *         Client => The email client name
	 *         Version => The email client version
	 *         Percentage => The percentage of subscribers who used this email client
	 *         Subscribers => The actual number of subscribers who used this email client
	 *     }
	 * )
	 */
	function getEmailClientUsage($campaignID){
		$wrap = new CS_REST_Campaigns($campaignID, $this->getAuth());
		$result = $wrap->get_email_client_usage();
		return $this->returnResult(
			$result,
			"GET /api/v3.1/campaigns/{id}/emailclientusage",
			"Got email client usage"
		);
	}

	function getListsAndSegments(){user_error("This method is still to be implemented, see samples for an example");}

	function getOpens(){user_error("This method is still to be implemented, see samples for an example");}

	function getRecipients(){user_error("This method is still to be implemented, see samples for an example");}

	function getSpam(){user_error("This method is still to be implemented, see samples for an example");}

	/**
	 * Gets all unsubscribes recorded for a campaign since the provided date
	 *
	 * @param int $campaignID ID of the Campaign
	 * @param string $daysAgo The date to start getting subscribers from
	 * @param int $page The page number to get
	 * @param int $pageSize The number of records per page
	 * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
	 * @param string $sortDirection ('ASC', 'DESC')
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'ResultsOrderedBy' => The field the results are ordered by
	 *     'OrderDirection' => The order direction
	 *     'PageNumber' => The page number for the result set
	 *     'PageSize' => The page size used
	 *     'RecordsOnThisPage' => The number of records returned
	 *     'TotalNumberOfRecords' => The total number of records available
	 *     'NumberOfPages' => The total number of pages for this collection
	 *     'Results' => array(
	 *         {
	 *             'EmailAddress' => The email address of the subscriber who unsubscribed
	 *             'ListID' => The list id of the list containing the subscriber
	 *             'Date' => The date of the unsubscribe
	 *             'IPAddress' => The ip address where the unsubscribe originated
	 *         }
	 *     )
	 * }
	 */
	public function getUnsubscribes($campaignID, $daysAgo = 3650, $page =1, $pageSize = 999, $sortByField = "EMAIL", $sortDirection = "ASC"){
		//require_once '../../csrest_campaigns.php';
		$wrap = new CS_REST_Campaigns($campaignID, $this->getAuth());
		$result = $wrap->get_unsubscribes(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		return $this->returnResult(
			$result,
			"GET /api/v3.1/campaigns/{id}/unsubscribes",
			"Got unsubscribes"
		);
	}



	/*******************************************************
	 * user
	 *
	 * states:
	 *
	 * Active – Someone who is on a list and will receive any emails sent to that list.
	 *
	 * Unconfirmed – The individual signed up to a confirmed opt-in list
	 * but has not clicked the link in the verification email sent to them.
	 *
	 * Unsubscribed – The subscriber has removed themselves from a list, or lists,
	 * via an unsubscribe link or form.
	 * You can also change a subscriber's status to unsubscribed through your account.
	 *
	 * Bounced – This describes an email address that campaigns cannot be delivered to,
	 * which can happen for a number of reasons.
	 *
	 * Deleted – Means the subscriber has been deleted from a list through your account.
	 *
	 *******************************************************/

	/**
	 * Gets the lists across a client to which a subscriber with a particular
	 * email address belongs.
	 *
	 * @param string | Member $email Subscriber's email address (or Member)
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * array(
	 *     {
	 *         'ListID' => The id of the list
	 *         'ListName' => The name of the list
	 *         'SubscriberState' => The state of the subscriber in the list
	 *         'DateSubscriberAdded' => The date the subscriber was added
	 *     }
	 * )
	 */
	public function getListsForEmail($member){
		if($member instanceof Member) {
			$member = $member->Email;
		}
		//require_once '../../csrest_clients.php';
		$wrap = new CS_REST_Clients($this->Config()->get("client_id"), $this->getAuth());
		$result = $wrap->get_lists_for_email($member);
		return $this->returnResult(
			$result,
			"/api/v3.1/clients/{id}/listsforemail",
			"Got lists to which email address ".$member." is subscribed"
		);
	}


	/**
	 * Adds a new subscriber to the specified list
	 *
	 * @param Int $listID
	 * @param Member $member
	 * @param Array $customFields
	 * @param array $customFields The subscriber details to use during creation.
	 * @param boolean $resubscribe Whether we should resubscribe this subscriber if they already exist in the list
	 * @param boolean $RestartSubscriptionBasedAutoResponders Whether we should restart subscription based auto responders which are sent when the subscriber first subscribes to a list.
	 *
	 * NOTE that for the custom fields they need to be formatted like this:
	 *    Array(
	 *        'Key' => The custom fields personalisation tag
	 *        'Value' => The value for this subscriber
	 *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
	 *    )
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	function addSubscriber(
		$listID,
		$member,
		$customFields = array(),
		$resubscribe = true,
		$restartSubscriptionBasedAutoResponders = false
	){
		//require_once '../../csrest_subscribers.php';
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$result = $wrap->add(
			array(
				'EmailAddress' => $member->Email,
				'Name' => $member->getName(),
				'CustomFields' => $customFields,
				'Resubscribe' => $resubscribe,
				'RestartSubscriptionBasedAutoResponders' => $restartSubscriptionBasedAutoResponders
			)
		);
		return $this->returnResult(
			$result,
			"POST /api/v3.1/subscribers/{list id}.{format}",
			"Subscribed with code ..."
		);
	}

	/**
	 * Updates an existing subscriber (email, name, state, or custom fields) in the specified list.
	 * The update is performed even for inactive subscribers, but will return an error in the event of the
	 * given email not existing in the list.
	 *
	 * @param Int $listID
	 * @param String $oldEmailAddress
	 * @param Member $member
	 * @param array $customFields The subscriber details to use during creation.
	 * @param boolean $resubscribe Whether we should resubscribe this subscriber if they already exist in the list
	 * @param boolean $restartSubscriptionBasedAutoResponders Whether we should restart subscription based auto responders which are sent when the subscriber first subscribes to a list.
	 *
	 * NOTE that for the custom fields they need to be formatted like this:
	 *    Array(
	 *        'Key' => The custom fields personalisation tag
	 *        'Value' => The value for this subscriber
	 *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
	 *    )
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	public function updateSubscriber(
		$listID,
		$oldEmailAddress = "",
		Member $member,
		$customFields = array(),
		$resubscribe = true,
		$restartSubscriptionBasedAutoResponders = false
	){
		if(!$oldEmailAddress) {
			$oldEmailAddress = $member->Email;
		}
		//require_once '../../csrest_subscribers.php';
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$result = $wrap->update(
			$oldEmailAddress,
			array(
				'EmailAddress' => $member->Email,
				'Name' => $member->getName(),
				'CustomFields' => $customFields,
				'Resubscribe' => $resubscribe,
				'RestartSubscriptionBasedAutoResponders' => $restartSubscriptionBasedAutoResponders,
			)
		);
		return $this->returnResult(
			$result,
			"PUT /api/v3.1/subscribers/{list id}.{format}?email={email}",
			"Subscribed with code ..."
		);
	}

	/**
	 * Updates an existing subscriber (email, name, state, or custom fields) in the specified list.
	 * The update is performed even for inactive subscribers, but will return an error in the event of the
	 * given email not existing in the list.
	 *
	 * @param Int $listID
	 * @param ArraySet $memberSet - list of mebers
	 * @param array $customFields The subscriber details to use during creation. Each array item needs to have the same key as the member ID - e.g. array( 123 => array( [custom fields here] ), 456 => array( [custom fields here] ) )
	 * @param $resubscribe Whether we should resubscribe any existing subscribers
	 * @param $queueSubscriptionBasedAutoResponders By default, subscription based auto responders do not trigger during an import. Pass a value of true to override this behaviour
	 * @param $restartSubscriptionBasedAutoResponders By default, subscription based auto responders will not be restarted
	 *
	 * NOTE that for the custom fields they need to be formatted like this:
	 *    Array(
	 *        'Key' => The custom fields personalisation tag
	 *        'Value' => The value for this subscriber
	 *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
	 *    )

	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	function addSubscribers(
		$listID,
		$membersSet,
		$customFields = array(),
		$resubscribe,
		$queueSubscriptionBasedAutoResponders = false,
		$restartSubscriptionBasedAutoResponders = false
	) {
		//require_once '../../csrest_subscribers.php';
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$importArray = array();
		foreach($membersSet as $member) {
			$customFieldsForMember = array();
			if(isset($customFields[$member->ID])) {
				$customFieldsForMember = $customFields[$member->ID];
			}
			elseif(isset($customFields[$member->Email])) {
				$customFieldsForMember = $customFields[$member->Email];
			}
			if($member instanceof Member) {
				$importArray[] = Array(
					'EmailAddress' => $member->Email,
					'Name' => $member->getName(),
					'CustomFields' => $customFieldsForMember
				);
			}
		}
		$result = $wrap->import(
			$importArray,
			$resubscribe,
			$queueSubscriptionBasedAutoResponders,
			$restartSubscriptionBasedAutoResponders
		);
		return $this->returnResult(
			$result,
			"POST /api/v3.1/subscribers/{list id}/import.{format}",
			"review details ..."
		);
	}

	/**
	 * @param Int $listID
	 * @param Member | String $member - email address or Member Object
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	function deleteSubscriber($listID, $member){
		if($member instanceof Member) {
			$member = $member->Email;
		}
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$result = $wrap->delete($member);
		return $this->returnResult(
			$result,
			"DELETE /api/v3.1/subscribers/{list id}.{format}?email={emailAddress}",
			"Unsubscribed with code  ..."
		);
	}

	/**
	 * Unsubscribes the given subscriber from the current list
	 *
	 * @param Int $listID
	 * @param Member | String $member
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	function unsubscribeSubscriber($listID, $member){
		if($member instanceof Member) {
			$member = $member->Email;
		}
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$result = $wrap->unsubscribe($member);
		return $this->returnResult(
			$result,
			"GET /api/v3.1/subscribers/{list id}/unsubscribe.{format}",
			"Unsubscribed with code  ..."
		);
	}

	/**
	 * Is this user part of this list at all?
	 *
	 * @param Int $listID
	 * @param Member | String $member
	 *
	 * @return Boolean
	 */
	public function getSubscriberExistsForThisList($listID, $member) {
		if($member instanceof Member) {
			$member = $member->Email;
		}
		$outcome = $this->getSubscriber($listID, $member);
		if($outcome && isset($outcome->State)) {
			if($this->debug) {
				echo "<h3>Subscriber Exists For This List</h3>";
			}
			return true;
		}
		if($this->debug) {
			echo "<h3>Subscriber does *** NOT *** Exist For This List</h3>";
		}
		return false;
	}

	/**
	 * Can we send e-mails to this person in the future for this list?
	 *
	 * @param Int $listID
	 * @param Member | String $member
	 *
	 * @return Boolean
	 */
	public function getSubscriberCanReceiveEmailsForThisList($listID, $member) {
		if($member instanceof Member) {
			$member = $member->Email;
		}
		$outcome = $this->getSubscriber($listID, $member);
		if($outcome && isset($outcome->State)) {
			if($outcome->State == "Active") {
				if($this->debug) {
					echo "<h3>Subscriber Can Receive Emails For This List</h3>";
				}
				return true;
			}
		}
		if($this->debug) {
			echo "<h3>Subscriber Can *** NOT *** Receive Emails For This List</h3>";
		}
		return false;
	}

	/**
	 * This e-mail / user has been banned from a list.
	 *
	 * @param Int $listID
	 * @param Member | String $member
	 *
	 * @return Boolean
	 */
	public function getSubscriberCanNoLongerReceiveEmailsForThisList($listID, $member) {
		$subscriberExistsForThisList = $this->getSubscriberExistsForThisList($listID, $member);
		$subscriberCanReceiveEmailsForThisList = $this->getSubscriberCanReceiveEmailsForThisList($listID, $member);
		if($subscriberExistsForThisList){
			if(!$subscriberCanReceiveEmailsForThisList) {
				if($this->debug) {
					echo "<h3>Subscriber Can No Longer Receive Emails For This List</h3>";
				}
				return true;
			}
		}
		if($this->debug) {
			echo "<h3>Subscriber Can *** STILL *** Receive Emails For This List</h3>";
		}
		return false;
	}

	/**
	 * Gets a subscriber details, including custom fields
	 *
	 * @param Int $listID
	 * @param Member | String $member
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'EmailAddress' => The subscriber email address
	 *     'Name' => The subscribers name
	 *     'Date' => The date the subscriber was added to the list
	 *     'State' => The current state of the subscriber
	 *     'CustomFields' => array(
	 *         {
	 *             'Key' => The custom fields personalisation tag
	 *             'Value' => The custom field value for this subscriber
	 *         }
	 *     )
	 * }
	 *
	 */
	function getSubscriber($listID, $member){
		if($member instanceof Member) {
			$member = $member->Email;
		}
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$result = $wrap->get($member);
		return $this->returnResult(
			$result,
			"GET /api/v3.1/subscribers/{list id}.{format}?email={email}",
			"got subscriber"
		);
	}

	/**
	 * Gets a subscriber details, including custom fields
	 *
	 * @param Int $listID
	 * @param Member | String $member
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be an object of the form
	 * {
	 *     'EmailAddress' => The subscriber email address
	 *     'Name' => The subscribers name
	 *     'Date' => The date the subscriber was added to the list
	 *     'State' => The current state of the subscriber
	 *     'CustomFields' => array(
	 *         {
	 *             'Key' => The custom fields personalisation tag
	 *             'Value' => The custom field value for this subscriber
	 *         }
	 *     )
	 * }
	 *
	 */
	function getHistory($listID, $member){
		if($member instanceof Member) {
			$member = $member->Email;
		}
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$result = $wrap->get_history($member);
		return $this->returnResult(
			$result,
			"GET /api/v3.1/subscribers/{list id}/history.{format}?email={email}",
			"got subscriber history"
		);
	}

}
