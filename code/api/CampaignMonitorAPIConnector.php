<?php

/**
 * Main Holder page for Recipes
 *@author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorAPIConnector extends Object {

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
	protected $debug = true;

	/**
	 *
	 * @var Boolean
	 */
	protected $allowCaching = true;

	/**
	 *
	 * @var Int
	 */
	protected $httpStatusCode = 0;


	/**
	 *
	 * must be called to use this API.
	 */
	function init(){
		require_once Director::baseFolder().'/'.SS_CAMPAIGNMONITOR_DIR.'/third_party/vendor/autoload.php';
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
						echo "access token: ".$result->response->access_token."\n";
						echo "expires in (seconds): ".$result->response->expires_in."\n";
						echo "refresh token: ".$result->response->refresh_token."\n";
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
						echo 'An error occurred:\n';
						echo $result->response->error.': '.$result->response->error_description."\n";
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
	protected function prepareReponse($result) {
		if($result->was_successful()) {
			if(!$result->response) {
				return true;
			}
			return $result->response;
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
	protected function getHttpStatusCode(){
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
			return unserialize($data);
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
		if($this->debug) {
			echo "Result of /api/v3.1/clients/{id}/campaigns\n<br />";
			if($result->was_successful()) {
				echo "Got campaigns\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			$this->prepareReponse($result);
		}
	}

	public function getDrafts(){user_error("This method is still to be implemented, see samples for an example");}

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
		if($this->debug) {
			echo "Result of /api/v3.1/clients/{id}/lists\n<br />";
			if($result->was_successful()) {
				echo "Got lists\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			$this->prepareReponse($result);
		}
	}

	public function getScheduled(){user_error("This method is still to be implemented, see samples for an example");}

	public function getSegments(){user_error("This method is still to be implemented, see samples for an example");}

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
		if(!$unsubscribeSetting) {
			$unsubscribeSetting = CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS;
		}
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists(NULL, $this->getAuth());
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
		if($this->debug) {
			echo "Result of POST /api/v3.1/lists/{clientID}\n<br />";
			if($result->was_successful()) {
				echo "Created with ID\n<br />".$result->response;
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
				echo '</pre>';
			}
		}
		else {
			$this->prepareReponse($result);
		}
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
		if($this->debug) {
			echo "Result of DELETE /api/v3.1/lists/{ID}\n<br />";
			if($result->was_successful()) {
				echo "Deleted with code\n<br />".$result->http_status_code;
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
				echo '</pre>';
			}
		}
		else {
			$this->prepareReponse($result);
		}
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
		if($this->debug) {
			echo "Result of GET /api/v3.1/lists/{ID}\n<br />";
			if($result->was_successful()) {
				echo "Got list details\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
		}
		else {
			$this->prepareReponse($result);
		}
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
	public function getActiveSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 100000, $sortByField = "EMAIL", $sortDirection = "ASC"){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_active_subscribers(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		if($this->debug) {
			echo "Result of GET /api/v3.1/lists/{ID}/active\n<br />";
			if($result->was_successful()) {
				echo "Got subscribers\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			$this->prepareReponse($result);
		}
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
	public function getBouncedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 100000, $sortByField = "EMAIL", $sortDirection = "ASC"){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_bounced_subscribers(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		if($this->debug) {
			echo "Result of GET /api/v3.1/lists/{ID}/bounced\n<br />";
			if($result->was_successful()) {
				echo "Got subscribers\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			$this->prepareReponse($result);
		}
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
	public function getUnconfirmedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 100000, $sortByField = "EMAIL", $sortDirection = "ASC"){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_unconfirmed_subscribers(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		if($this->debug) {
			echo "Result of GET /api/v3.1/lists/{ID}/unconfirmed\n<br />";
			if($result->was_successful()) {
				echo "Got subscribers\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			$this->prepareReponse($result);
		}
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
	public function getUnsubscribedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 100000, $sortByField = "EMAIL", $sortDirection = "ASC"){
		//require_once '../../csrest_lists.php';
		$wrap = new CS_REST_Lists($listID, $this->getAuth());
		$result = $wrap->get_unsubscribed_subscribers(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		if($this->debug) {
			echo "Result of GET /api/v3.1/lists/{ID}/unsubscribed\n<br />";
			if($result->was_successful()) {
				echo "Got subscribers\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			$this->prepareReponse($result);
		}
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
		if($this->debug) {
			echo "Result of PUT /api/v3.1/lists/{ID}\n<br />";
			if($result->was_successful()) {
				echo "Updated with code\n<br />".$result->http_status_code;
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
				echo '</pre>';
			}
		}
		else {
			$this->prepareReponse($result);
		}
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
		if($this->debug) {
			echo "Result of GET /api/v3.1/lists/{ID}/stats\n<br />";
			if($result->was_successful()) {
				echo "Got list stats\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			$this->prepareReponse($result);
		}
	}


	/*******************************************************
	 * information about the campaigns
	 *
	 *******************************************************/

	function getBounces(){user_error("This method is still to be implemented, see samples for an example");}

	function getClicks(){user_error("This method is still to be implemented, see samples for an example");}

	function getEmailClientUsage(){user_error("This method is still to be implemented, see samples for an example");}

	function getListsAndSegments(){user_error("This method is still to be implemented, see samples for an example");}

	function getOpens(){user_error("This method is still to be implemented, see samples for an example");}

	function getRecipients(){user_error("This method is still to be implemented, see samples for an example");}

	function getSpam(){user_error("This method is still to be implemented, see samples for an example");}

	function getSummary(){user_error("This method is still to be implemented, see samples for an example");}

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
	public function getUnsubscribes($campaignID, $daysAgo = 3650, $page =1, $pageSize = 10000, $sortByField = "EMAIL", $sortDirection = "ASC"){
		//require_once '../../csrest_campaigns.php';
		$wrap = new CS_REST_Campaigns($campaignID, $this->getAuth());
		$result = $wrap->get_unsubscribes(
			date('Y-m-d', strtotime('-'.$daysAgo.' days')),
			$page,
			$pageSize,
			$sortByField,
			$sortDirection
		);
		if($this->debug) {
			echo "Result of GET /api/v3.1/campaigns/{id}/unsubscribes\n<br />";
			if($result->was_successful()) {
				echo "Got unsubscribes\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			$this->prepareReponse($result);
		}
	}



	/*******************************************************
	 * user
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
		if($this->debug) {
			echo "Result of /api/v3.1/clients/{id}/listsforemail\n<br />";
			if($result->was_successful()) {
				echo "Got lists to which email address ".$email." is subscribed\n<br /><pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			return $this->prepareReponse($result);
		}
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
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	function addSubscriber($listID, Member $member, $customFields = array(), $resubscribe = true, $restartSubscriptionBasedAutoResponders = false){
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
		if($this->debug) {
			echo "Result of POST /api/v3.1/subscribers/{list id}.{format}\n<br />";
			if($result->was_successful()) {
				echo "Subscribed with code ".$result->http_status_code;
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
				echo '</pre>';
			}
		}
		else {
			return $this->prepareReponse($result);
		}
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
	 * @param boolean $RestartSubscriptionBasedAutoResponders Whether we should restart subscription based auto responders which are sent when the subscriber first subscribes to a list.
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	public function updateSubscriber($listID, $oldEmailAddress = "", Member $member, $customFields = array(), $resubscribe = true, $restartSubscriptionBasedAutoResponders = false){
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
		if($this->debug) {
			echo "Result of PUT /api/v3.1/subscribers/{list id}.{format}?email={email}\n<br />";
			if($result->was_successful()) {
				echo "Subscribed with code ".$result->http_status_code;
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
				echo '</pre>';
			}
		}
		else {
			return $this->prepareReponse($result);
		}
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

	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	function addSubscribers($listID, $membersSet, $customFields = array(), $resubscribe, $queueSubscriptionBasedAutoResponders = false, $restartSubscriptionBasedAutoResponders = false) {
		//require_once '../../csrest_subscribers.php';
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$importArray = array();
		foreach($membersSet as $member) {
			$importArray[] = Array(
				'EmailAddress' => $member->Email,
				'Name' => $member->getName(),
				'CustomFields' => isset($customField[$member->ID]) ? $customField[$member->ID] : array()
			);
		}
		$result = $wrap->import(
			$importArray,
			$resubscribe,
			$queueSubscriptionBasedAutoResponders,
			$restartSubscriptionBasedAutoResponders
		);
		if($this->debug) {
			echo "Result of POST /api/v3.1/subscribers/{list id}/import.{format}\n<br />";
			if($result->was_successful()) {
				echo "Subscribed with results <pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
				echo '</pre>';
				if($result->response->ResultData->TotalExistingSubscribers > 0) {
					echo 'Updated '.$result->response->ResultData->TotalExistingSubscribers.' existing subscribers in the list';
				}
				else if($result->response->ResultData->TotalNewSubscribers > 0) {
					echo 'Added '.$result->response->ResultData->TotalNewSubscribers.' to the list';
				}
				else if(count($result->response->ResultData->DuplicateEmailsInSubmission) > 0) {
					echo $result->response->ResultData->DuplicateEmailsInSubmission.' were duplicated in the provided array.';
				}
				echo 'The following emails failed to import correctly.<pre>';
				var_dump($result->response->ResultData->FailureDetails);
			}
		}
		else {
			return $this->prepareReponse($result);
		}
	}

	/**
	 * @param Int $listID
	 * @param Member | String $member
	 *
	 * @return CS_REST_Wrapper_Result A successful response will be empty
	 */
	function deleteSubscriber($listID, $member){
		if($member instanceof Member) {
			$member = $member->Email;
		}
		$wrap = new CS_REST_Subscribers($listID, $this->getAuth());
		$result = $wrap->delete($member);
		if($this->debug) {
			echo "Result of DELETE /api/v3.1/subscribers/{list id}.{format}?email={emailAddress}\n<br />";
			if($result->was_successful()) {
				echo "Unsubscribed with code ".$result->http_status_code;
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
				echo '</pre>';
			}
		}
		else {
			return $this->prepareReponse($result);
		}
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
		if($this->debug) {
			echo "Result of GET /api/v3.1/subscribers/{list id}/unsubscribe.{format}\n<br />";
			if($result->was_successful()) {
				echo "Unsubscribed with code ".$result->http_status_code;
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
				echo '</pre>';
			}
		}
		else {
			return $this->prepareReponse($result);
		}
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
		if($this->debug) {
			echo "Result of GET /api/v3.1/subscribers/{list id}.{format}?email={email}\n<br />";
			if($result->was_successful()) {
				echo "Got subscriber <pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			return $this->prepareReponse($result);
		}
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
		if($this->debug) {
			echo "Result of GET /api/v3.1/subscribers/{list id}/history.{format}?email={email}\n<br />";
			if($result->was_successful()) {
				echo "Got subscriber history <pre>";
				var_dump($result->response);
			}
			else {
				echo 'Failed with code '.$result->http_status_code."\n<br /><pre>";
				var_dump($result->response);
			}
			echo '</pre>';
		}
		else {
			return $this->prepareReponse($result);
		}
	}

}
