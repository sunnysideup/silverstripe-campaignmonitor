<?php

namespace Sunnysideup\CampaignMonitor\Api;

use Metadata\Cache\CacheInterface;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ViewableData;

/**
 * Main Holder page for Recipes
 *@author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorAPIConnector extends ViewableData
{
    /**
     * @var boolean
     */
    protected $debug = false;

    /**
     * @var boolean
     */
    protected $allowCaching = false;

    /**
     * @var int
     */
    protected $httpStatusCode = 0;

    /**
     * REQUIRED!
     * this is the CM url for logging in.
     * which can be used by the client.
     * @var string
     */
    private static $campaign_monitor_url = '';

    /**
     * REQUIRED!
     * @var string
     */
    private static $client_id = '';

    /**
     * OPTION 1: API KEY!
     * @var string
     */
    private static $api_key = '';

    /**
     * OPTION 2: OAUTH OPTION
     * @var string
     */
    private static $client_secret = '';

    /**
     * OPTION 2: OAUTH OPTION
     * @var string
     */
    private static $redirect_uri = '';

    /**
     * OPTION 2: OAUTH OPTION
     * @var string
     */
    private static $code = '';

    private static $_get_subscriber = [];

    /**
     * must be called to use this API.
     */
    public function init()
    {
        //require_once Director::baseFolder().'/'.SS_CAMPAIGNMONITOR_DIR.'/third_party/vendor/autoload.php';
        //require_once Director::baseFolder().'/'.SS_CAMPAIGNMONITOR_DIR.'/third_party/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
    }

    /**
     * turn debug on or off
     *
     * @param $b
     */
    public function setDebug($b)
    {
        $this->debug = $b;
    }

    /**
     * @param bool $b
     */
    public function setAllowCaching($b)
    {
        $this->allowCaching = $b;
    }

    /**
     * @return bool
     */
    public function getAllowCaching()
    {
        return $this->allowCaching;
    }

    /**
     * returns the HTTP code for the response.
     * This can be handy for debuging purposes.
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /*******************************************************
     * client
     *
     *******************************************************/

    /**
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getCampaigns()
    {
        //require_once '../../csrest_clients.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_clients.php';
        $wrap = new \CS_REST_Clients($this->Config()->get('client_id'), $this->getAuth());
        $result = $wrap->get_campaigns();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/clients/{id}/campaigns',
            'Got sent campaigns'
        );
    }

    public function getDrafts()
    {
        //require_once '../../csrest_clients.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_clients.php';
        $wrap = new \CS_REST_Clients($this->Config()->get('client_id'), $this->getAuth());
        $result = $wrap->get_drafts();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/clients/{id}/drafts',
            'Got draft campaigns'
        );
    }

    /**
     * Gets all subscriber lists the current client has created
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
     * array(
     *     {
     *         'ListID' => The id of the list
     *         'Name' => The name of the list
     *     }
     * )
     */
    public function getLists()
    {
        //require_once '../../csrest_clients.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_clients.php';
        $wrap = new \CS_REST_Clients($this->Config()->get('client_id'), $this->getAuth());
        $result = $wrap->get_lists();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/clients/{id}/lists',
            'Got Lists'
        );
    }

    public function getScheduled()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    /**
     * list of people that are definitely suppressed...
     * @param  int $page     page number
     * @param  int $pageSize size of page
     * @param  string $sortByField (email)
     * @param  string $sortDirection (asc)
     *
     * @return
     */
    public function getSuppressionlist($page, $pageSize, $sortByField = 'email', $sortDirection = 'asc')
    {
        $wrap = new \CS_REST_Clients(
            $this->Config()->get('client_id'),
            $this->getAuth()
        );
        $result = $wrap->get_suppressionlist(
            $page,
            $pageSize,
            $sortByField,
            $sortDirection
        );
        return $this->returnResult(
            $result,
            'GET /api/v3/clients/{id}/suppressionlist',
            'Get suppression list'
        );
    }

    public function getTemplates()
    {
        $wrap = new \CS_REST_Clients(
            $this->Config()->get('client_id'),
            $this->getAuth()
        );
        $result = $wrap->get_templates();
        return $this->returnResult(
            $result,
            'GET /api/v3/clients/{id}/templates',
            'Get Templates'
        );
    }

    public function getTemplate($templatID)
    {
        $wrap = new \CS_REST_Templates(
            $templatID,
            $this->getAuth()
        );
        $result = $wrap->get();
        return $this->returnResult(
            $result,
            'GET /api/v3/templates/{ID}',
            'Got Summary'
        );
    }

    /**
     * @param CampaignMonitorCampaign $campaignMonitorCampaign
     *
     * @return \CS_REST_Wrapper_Result
     */
    public function createTemplate($campaignMonitorCampaign)
    {
        $name = 'Template for ' . $campaignMonitorCampaign->Name;
        if (! $name) {
            $name = 'no name set';
        }

        $wrap = new \CS_REST_Templates(null, $this->getAuth());
        $result = $wrap->create(
            $this->Config()->get('client_id'),
            [
                'Name' => $name,
                'HtmlPageURL' => $campaignMonitorCampaign->PreviewLink(),
                'ZipFileURL' => '',
            ]
        );
        if (isset($result->http_status_code) && ($result->http_status_code === 201 || $result->http_status_code === 201)) {
            $code = $result->response;
            $campaignMonitorCampaign->CreateFromWebsite = false;
            $campaignMonitorCampaign->CreatedFromWebsite = true;
            $campaignMonitorCampaign->TemplateID = $code;
        } else {
            $campaignMonitorCampaign->CreateFromWebsite = false;
            $campaignMonitorCampaign->CreatedFromWebsite = false;
            $code = 'Error';
            if (is_object($result->response)) {
                $code = $result->response->Code . ':' . $result->response->Message;
            }
            $campaignMonitorCampaign->MessageFromNewsletterServer = $code;
        }
        $campaignMonitorCampaign->write();
        return $this->returnResult(
            $result,
            'POST /api/v3/templates/{clientID}',
            'Created Template'
        );
    }

    /**
     * @param CampaignMonitorCampaign $campaignMonitorCampaign
     * @param string $templateID
     *
     * @return \CS_REST_Wrapper_Result
     */
    public function updateTemplate($campaignMonitorCampaign, $templateID)
    {
        $name = 'Template for ' . $campaignMonitorCampaign->Name;
        if (! $name) {
            $name = 'no name set';
        }
        $wrap = new \CS_REST_Templates($templateID, $this->getAuth());
        $result = $wrap->create(
            $this->Config()->get('client_id'),
            [
                'Name' => $name,
                'HtmlPageURL' => $campaignMonitorCampaign->PreviewLink(),
                'ZipFileURL' => '',
            ]
        );
        if (isset($result->http_status_code) && ($result->http_status_code === 201 || $result->http_status_code === 201)) {
            $code = $result->response;
            $campaignMonitorCampaign->CreateFromWebsite = false;
            $campaignMonitorCampaign->CreatedFromWebsite = true;
        } else {
            $campaignMonitorCampaign->CreateFromWebsite = false;
            $campaignMonitorCampaign->CreatedFromWebsite = false;
            $code = 'Error';
            if (is_object($result->response)) {
                $code = $result->response->Code . ':' . $result->response->Message;
            }
            $campaignMonitorCampaign->MessageFromNewsletterServer = $code;
        }
        $campaignMonitorCampaign->write();
        return $this->returnResult(
            $result,
            'PUT /api/v3/templates/{ID}',
            'Updated Template'
        );
    }

    public function deleteTemplate($templateID)
    {
        $wrap = new \CS_REST_Templates($templateID, $this->getAuth());
        $result = $wrap->delete();
        return $this->returnResult(
            $result,
            'DELETE /api/v3/templates/{ID}',
            'Deleted Template'
        );
    }

    /*******************************************************
     * lists
     *
     *******************************************************/

    /**
     * Creates a new list based on the provided details.
     * Both the UnsubscribePage and the ConfirmationSuccessPage parameters are optional
     *
     * @param string $title - the page to redirect subscribers to when they unsubscribeThe list title
     * @param string $unsubscribePage - The page to redirect subscribers to when they unsubscribe
     * @param bool $confirmedOptIn - Whether this list requires confirmation of subscription
     * @param string $confirmationSuccessPage - The page to redirect subscribers to when they confirm their subscription
     * @param string $unsubscribeSetting - Unsubscribe setting must be CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS or CS_REST_LIST_UNSUBSCRIBE_SETTING_ONLY_THIS_LIST.  See the documentation for details: http://www.campaignmonitor.com/api/lists/#creating_a_list
     *
     * @return \CS_REST_Wrapper_Result A successful response will be the ID of the newly created list
     */
    public function createList($title, $unsubscribePage, $confirmationSuccessPage, $confirmedOptIn = false, $unsubscribeSetting = null)
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists(null, $this->getAuth());
        //we need to do this afterwards otherwise the definition below
        //is not recognised
        if (! $unsubscribeSetting) {
            $unsubscribeSetting = CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS;
        }
        $result = $wrap->create(
            $this->Config()->get('client_id'),
            [
                'Title' => $title,
                'UnsubscribePage' => $unsubscribePage,
                'ConfirmedOptIn' => $confirmedOptIn,
                'ConfirmationSuccessPage' => $confirmationSuccessPage,
                'UnsubscribeSetting' => $unsubscribeSetting,
            ]
        );
        return $this->returnResult(
            $result,
            'POST /api/v3.1/lists/{clientID}',
            'Created with ID'
        );
    }

    /**
     * Creates custom field for list
     *
     * @param string $listID - list ID
     * @param string $type - type of custom field
     * @param string $title - field type
     * @param array $options - options for dropdown field type
     *
     * @return \CS_REST_Wrapper_Result A successful response will be the key of the newly created custom field
     */
    public function createCustomField($listID, $visible, $type, $title, $options = [])
    {
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        switch ($type) {
            case 'text':
                $type = CS_REST_CUSTOM_FIELD_TYPE_TEXT;
                break;
            case 'number':
                $type = CS_REST_CUSTOM_FIELD_TYPE_NUMBER;
                break;
            case 'multi_select_one':
                $type = CS_REST_CUSTOM_FIELD_TYPE_MULTI_SELECTONE;
                break;
            case 'multi_select_many':
                $type = CS_REST_CUSTOM_FIELD_TYPE_MULTI_SELECTMANY;
                break;
            case 'date':
                $type = CS_REST_CUSTOM_FIELD_TYPE_DATE;
                break;
            default:
                user_error('You must select one from text, number, multi_select_one, multi_select_many, date)');
        }
        $result = $wrap->create_custom_field([
            'FieldName' => $title,
            'DataType' => $type,
            'Options' => $options,
            'VisibleInPreferenceCenter' => $visible ? true : false,
        ]);
        return $this->returnResult(
            $result,
            'POST /api/v3/lists/{ID}/customfields',
            "Created Custom Field for ${listID} "
        );
    }

    /**
     * Creates custom field for list
     *
     * @param string $listID - list ID
     * @param string $key
     *
     * @return \CS_REST_Wrapper_Result
     */
    public function deleteCustomField($listID, $key)
    {
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->delete_custom_field($key);
        return $this->returnResult(
            $result,
            'DELETE /api/v3/lists/{ID}/{Key}',
            "Delete Custom Field for ${listID} with key ${key}"
        );
    }

    /**
     * Deletes an existing list from the system
     * @param int $listID
     * @return \CS_REST_Wrapper_Result A successful response will be empty
     */
    public function deleteList($listID)
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->delete();
        return $this->returnResult(
            $result,
            'DELETE /api/v3.1/lists/{ID}',
            'Deleted with code'
        );
    }

    /**
     * Gets the basic details of the current list
     *
     * @param int $listID
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getList($listID)
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->get();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/lists/{ID}',
            'Got list details'
        );
    }

    /**
     * Gets all active subscribers added since the given date
     *
     * @param int $listID
     * @param string $daysAgo The date to start getting subscribers from
     * @param int $page The page number to get
     * @param int $pageSize The number of records per page
     * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getActiveSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = 'DATE', $sortDirection = 'DESC')
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->get_active_subscribers(
            date('Y-m-d', strtotime('-' . $daysAgo . ' days')),
            $page,
            $pageSize,
            $sortByField,
            $sortDirection
        );
        return $this->returnResult(
            $result,
            'GET /api/v3.1/lists/{ID}/active',
            'Got active subscribers'
        );
    }

    /**
     * Gets all unconfirmed subscribers added since the given date
     *
     * @param int $listID
     * @param string $daysAgo The date to start getting subscribers from
     * @param int $page The page number to get
     * @param int $pageSize The number of records per page
     * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getUnconfirmedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = 'DATE', $sortDirection = 'DESC')
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->get_unconfirmed_subscribers(
            date('Y-m-d', strtotime('-' . $daysAgo . ' days')),
            $page,
            $pageSize,
            $sortByField,
            $sortDirection
        );
        return $this->returnResult(
            $result,
            'GET /api/v3.1/lists/{ID}/unconfirmed',
            'Got unconfimred subscribers'
        );
    }

    /**
     * Gets all bounced subscribers who have bounced out since the given date
     *
     * @param int $listID
     * @param string $daysAgo The date to start getting subscribers from
     * @param int $page The page number to get
     * @param int $pageSize The number of records per page
     * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getBouncedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = 'DATE', $sortDirection = 'DESC')
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->get_bounced_subscribers(
            date('Y-m-d', strtotime('-' . $daysAgo . ' days')),
            $page,
            $pageSize,
            $sortByField,
            $sortDirection
        );
        return $this->returnResult(
            $result,
            'GET /api/v3.1/lists/{ID}/bounced',
            'Got bounced subscribers'
        );
    }

    /**
     * Gets all unsubscribed subscribers who have unsubscribed since the given date
     *
     * @param int $listID
     * @param string $daysAgo The date to start getting subscribers from
     * @param int $page The page number to get
     * @param int $pageSize The number of records per page
     * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getUnsubscribedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = 'DATE', $sortDirection = 'DESC')
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->get_unsubscribed_subscribers(
            date('Y-m-d', strtotime('-' . $daysAgo . ' days')),
            $page,
            $pageSize,
            $sortByField,
            $sortDirection
        );
        return $this->returnResult(
            $result,
            'GET /api/v3.1/lists/{ID}/unsubscribed',
            'Got unsubscribed subscribers'
        );
    }

    /**
     * Gets all unsubscribed subscribers who have unsubscribed since the given date
     *
     * @param int $listID
     * @param string $daysAgo The date to start getting subscribers from
     * @param int $page The page number to get
     * @param int $pageSize The number of records per page
     * @param string $sortByField ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getDeletedSubscribers($listID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = 'email', $sortDirection = 'asc')
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->get_deleted_subscribers(
            date('Y-m-d', strtotime('-' . $daysAgo . ' days')),
            $page,
            $pageSize,
            $sortByField,
            $sortDirection
        );
        return $this->returnResult(
            $result,
            'GET /api/v3/lists/{ID}/delete',
            'Got deleted subscribers'
        );
    }

    /**
     * Updates the details of an existing list
     * Both the UnsubscribePage and the ConfirmationSuccessPage parameters are optional
     *
     * @param string $title - he page to redirect subscribers to when they unsubscribeThe list title
     * @param string $unsubscribePage - The page to redirect subscribers to when they unsubscribe
     * @param bool $confirmedOptIn - Whether this list requires confirmation of subscription
     * @param string $confirmationSuccessPage - The page to redirect subscribers to when they confirm their subscription
     * @param string $unsubscribeSetting - Unsubscribe setting must be CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS or CS_REST_LIST_UNSUBSCRIBE_SETTING_ONLY_THIS_LIST.  See the documentation for details: http://www.campaignmonitor.com/api/lists/#creating_a_list
     * @param bool $addUnsubscribesToSuppList -  When UnsubscribeSetting is CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS, whether unsubscribes from this list should be added to the suppression list.
     * @param bool $acrubActiveWithSuppList - When UnsubscribeSetting is CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS, whether active subscribers should be scrubbed against the suppression list.
     *
     * @return \CS_REST_Wrapper_Result A successful response will be empty
     */
    public function updateList(
        $listID,
        $title,
        $unsubscribePage,
        $confirmationSuccessPage,
        $unsubscribeSetting,
        $confirmedOptIn = false,
        $addUnsubscribesToSuppList = true,
        $scrubActiveWithSuppList = true
    ) {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        if (! $unsubscribeSetting) {
            $unsubscribeSetting = CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS;
        }
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->update([
            'Title' => $title,
            'UnsubscribePage' => $unsubscribePage,
            'ConfirmedOptIn' => $confirmedOptIn,
            'ConfirmationSuccessPage' => $confirmationSuccessPage,
            'UnsubscribeSetting' => $unsubscribeSetting,
            'AddUnsubscribesToSuppList' => $addUnsubscribesToSuppList,
            'ScrubActiveWithSuppList' => $scrubActiveWithSuppList,
        ]);
        return $this->returnResult(
            $result,
            'PUT /api/v3.1/lists/{ID}',
            'Updated with code'
        );
    }

    public function getSegments($listID)
    {
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        //we need to do this afterwards otherwise the definition below
        //is not recognised
        $result = $wrap->get_segments();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/lists/{listid}/segments',
            'Got segment details'
        );
    }

    /**
     * Gets statistics for list subscriptions, deletions, bounces and unsubscriptions
     *
     * @param int $listID
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getListStats($listID)
    {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->get_stats();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/lists/{ID}/stats',
            'Got Lists Stats'
        );
    }

    public function getListCustomFields($listID)
    {
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->get_custom_fields();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/lists/{ID}/customfields',
            'Got Lists Custom Fields'
        );
    }

    /*******************************************************
     * create campaigns
     *
     *******************************************************/

    /**
     * @param CampaignMonitorCampaign $campaignMonitorCampaign
     * @param array $listIDs
     * @param array $segmentIDs
     * @param string $templateID - OPTIONAL!
     * @param array $templateContent - OPTIONAL!
     */
    public function createCampaign(
        $campaignMonitorCampaign,
        $listIDs = [],
        $segmentIDs = [],
        $templateID = '',
        $templateContent = []
    ) {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $siteConfig = SiteConfig::current_site_config();

        $subject = $campaignMonitorCampaign->Subject;
        if (! $subject) {
            $subject = 'no subject set';
        }

        $name = $campaignMonitorCampaign->Name;
        if (! $name) {
            $name = 'no name set';
        }

        $fromName = $campaignMonitorCampaign->FromName;
        if (! $fromName) {
            $fromName = $siteConfig->Title;
        }

        $fromEmail = $campaignMonitorCampaign->FromEmail;
        if (! $fromEmail) {
            $fromEmail = Config::inst()->get(Email::class, 'admin_email');
        }

        $replyTo = $campaignMonitorCampaign->ReplyTo;
        if (! $replyTo) {
            $replyTo = $fromEmail;
        }

        $listID = $campaignMonitorCampaign->Pages()->first()->ListID;

        $wrap = new \CS_REST_Campaigns(null, $this->getAuth());
        if ($templateID) {
            $result = $wrap->create_from_template(
                $this->Config()->get('client_id'),
                [
                    'Subject' => $subject,
                    'Name' => $name,
                    'FromName' => $fromName,
                    'FromEmail' => $fromEmail,
                    'ReplyTo' => $replyTo,
                    'ListIDs' => [$listID],
                    'SegmentIDs' => [],
                    'TemplateID' => $templateID,
                    'TemplateContent' => $templateContent,
                ]
            );
        } else {
            $result = $wrap->create(
                $this->Config()->get('client_id'),
                [
                    'Subject' => $subject,
                    'Name' => $name,
                    'FromName' => $fromName,
                    'FromEmail' => $fromEmail,
                    'ReplyTo' => $replyTo,
                    'HtmlUrl' => $campaignMonitorCampaign->PreviewLink(),
                    'TextUrl' => $campaignMonitorCampaign->PreviewLink('textonly'),
                    'ListIDs' => [$listID],
                ]
            );
        }
        if (isset($result->http_status_code) && ($result->http_status_code === 201 || $result->http_status_code === 201)) {
            $code = $result->response;
            $campaignMonitorCampaign->CreateFromWebsite = false;
            $campaignMonitorCampaign->CreatedFromWebsite = true;
            $campaignMonitorCampaign->CampaignID = $code;
        } else {
            $campaignMonitorCampaign->CreateFromWebsite = false;
            $campaignMonitorCampaign->CreatedFromWebsite = false;
            $code = 'Error';
            if (is_object($result->response)) {
                $code = $result->response->Code . ':' . $result->response->Message;
            }
            $campaignMonitorCampaign->MessageFromNewsletterServer = $code;
        }
        $campaignMonitorCampaign->write();
        return $this->returnResult(
            $result,
            'CREATE /api/v3/campaigns/{clientID}',
            'Created Campaign'
        );
    }

    public function deleteCampaign($campaignID)
    {
        $wrap = new \CS_REST_Campaigns($campaignID, $this->getAuth());
        $result = $wrap->delete();
        return $this->returnResult(
            $result,
            'DELETE /api/v3/campaigns/{id}',
            'Deleted Campaign'
        );
    }

    /*******************************************************
     * information about the campaigns
     *
     *******************************************************/

    public function getBounces()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    public function getClicks()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    /**
     * Gets a summary of all campaign reporting statistics
     *
     * @param int $campaignID
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getSummary($campaignID)
    {
        $wrap = new \CS_REST_Campaigns($campaignID, $this->getAuth());
        $result = $wrap->get_summary();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/campaigns/{id}/summary',
            'Got Summary'
        );
    }

    /**
     * Gets the email clients that subscribers used to open the campaign
     *
     * @param int $campaignID
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
     * array(
     *     {
     *         Client => The email client name
     *         Version => The email client version
     *         Percentage => The percentage of subscribers who used this email client
     *         Subscribers => The actual number of subscribers who used this email client
     *     }
     * )
     */
    public function getEmailClientUsage($campaignID)
    {
        $wrap = new \CS_REST_Campaigns($campaignID, $this->getAuth());
        $result = $wrap->get_email_client_usage();
        return $this->returnResult(
            $result,
            'GET /api/v3.1/campaigns/{id}/emailclientusage',
            'Got email client usage'
        );
    }

    public function getListsAndSegments()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    public function getOpens()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    public function getRecipients()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    public function getSpam()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

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
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
    public function getUnsubscribes($campaignID, $daysAgo = 3650, $page = 1, $pageSize = 999, $sortByField = 'EMAIL', $sortDirection = 'ASC')
    {
        //require_once '../../csrest_campaigns.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_campaigns.php';
        $wrap = new \CS_REST_Campaigns($campaignID, $this->getAuth());
        $result = $wrap->get_unsubscribes(
            date('Y-m-d', strtotime('-' . $daysAgo . ' days')),
            $page,
            $pageSize,
            $sortByField,
            $sortDirection
        );
        return $this->returnResult(
            $result,
            'GET /api/v3.1/campaigns/{id}/unsubscribes',
            'Got unsubscribes'
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
     * @param string|Member $member Subscriber's email address (or Member)
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
     * array(
     *     {
     *         'ListID' => The id of the list
     *         'ListName' => The name of the list
     *         'SubscriberState' => The state of the subscriber in the list
     *         'DateSubscriberAdded' => The date the subscriber was added
     *     }
     * )
     */
    public function getListsForEmail($member)
    {
        if ($member instanceof Member) {
            $member = $member->Email;
        }
        //require_once '../../csrest_clients.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_clients.php';
        $wrap = new \CS_REST_Clients($this->Config()->get('client_id'), $this->getAuth());
        $result = $wrap->get_lists_for_email($member);
        return $this->returnResult(
            $result,
            '/api/v3.1/clients/{id}/listsforemail',
            'Got lists to which email address ' . $member . ' is subscribed'
        );
    }

    /**
     * Adds a new subscriber to the specified list
     *
     * @param int $listID
     * @param Member|object $member (Member or standard object with Email, FirstName, Surname properties)
     * @param array $customFields
     * @param array $customFields The subscriber details to use during creation.
     * @param bool $resubscribe Whether we should resubscribe this subscriber if they already exist in the list
     * @param bool $RestartSubscriptionBasedAutoResponders Whether we should restart subscription based auto responders which are sent when the subscriber first subscribes to a list.
     *
     * NOTE that for the custom fields they need to be formatted like this:
     *    Array(
     *        'Key' => The custom fields personalisation tag
     *        'Value' => The value for this subscriber
     *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
     *    )
     *
     * @return \CS_REST_Wrapper_Result A successful response will be empty
     */
    public function addSubscriber(
        $listID,
        $member,
        $customFields = [],
        $resubscribe = true,
        $restartSubscriptionBasedAutoResponders = false
    ) {
        //require_once '../../csrest_subscribers.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_subscribers.php';
        $wrap = new \CS_REST_Subscribers($listID, $this->getAuth());
        foreach ($customFields as $key => $customFieldValue) {
            if (! is_array($customFields[$key])) {
                $customFields[] = [
                    'Key' => $key,
                    'Value' => $customFieldValue,
                    'Clear' => $customFieldValue ? false : true,
                ];
                unset($customFields[$key]);
            }
        }
        $request = [
            'EmailAddress' => $member->Email,
            'Name' => trim($member->FirstName . ' ' . $member->Surname),
            'CustomFields' => $customFields,
            'Resubscribe' => $resubscribe,
            'RestartSubscriptionBasedAutoResponders' => $restartSubscriptionBasedAutoResponders,
            'ConsentToTrack' => 'no',
        ];
        $result = $wrap->add(
            $request
        );
        return $this->returnResult(
            $result,
            'POST /api/v3.1/subscribers/{list id}.{format}',
            'Subscribed with code ...'
        );
    }

    /**
     * Updates an existing subscriber (email, name, state, or custom fields) in the specified list.
     * The update is performed even for inactive subscribers, but will return an error in the event of the
     * given email not existing in the list.
     *
     * @param int $listID
     * @param string $oldEmailAddress
     * @param Member|object $member (Member or standard object with Email, FirstName, Surname properties)
     * @param array $customFields The subscriber details to use during creation.
     * @param bool $resubscribe Whether we should resubscribe this subscriber if they already exist in the list
     * @param bool $restartSubscriptionBasedAutoResponders Whether we should restart subscription based auto responders which are sent when the subscriber first subscribes to a list.
     *
     * NOTE that for the custom fields they need to be formatted like this:
     *    Array(
     *        'Key' => The custom fields personalisation tag
     *        'Value' => The value for this subscriber
     *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
     *    )
     *
     * @return \CS_REST_Wrapper_Result A successful response will be empty
     */
    public function updateSubscriber(
        $listID,
        $member,
        $oldEmailAddress = '',
        $customFields = [],
        $resubscribe = true,
        $restartSubscriptionBasedAutoResponders = false
    ) {
        if (! $oldEmailAddress) {
            $oldEmailAddress = $member->Email;
        }
        foreach ($customFields as $key => $customFieldValue) {
            if (! is_array($customFields[$key])) {
                $customFields[] = [
                    'Key' => $key,
                    'Value' => $customFieldValue,
                    'Clear' => $customFieldValue ? false : true,
                ];
                unset($customFields[$key]);
            }
        }
        //require_once '../../csrest_subscribers.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_subscribers.php';
        $wrap = new \CS_REST_Subscribers($listID, $this->getAuth());
        $result = $wrap->update(
            $oldEmailAddress,
            [
                'EmailAddress' => $member->Email,
                'Name' => trim($member->FirstName . ' ' . $member->Surname),
                'CustomFields' => $customFields,
                'Resubscribe' => $resubscribe,
                'RestartSubscriptionBasedAutoResponders' => $restartSubscriptionBasedAutoResponders,
                'ConsentToTrack' => 'no',
            ]
        );
        return $this->returnResult(
            $result,
            'PUT /api/v3.1/subscribers/{list id}.{format}?email={email}',
            "updated with email ${oldEmailAddress} ..."
        );
    }

    /**
     * Updates an existing subscriber (email, name, state, or custom fields) in the specified list.
     * The update is performed even for inactive subscribers, but will return an error in the event of the
     * given email not existing in the list.
     *
     * @param int $listID
     * @param ArraySet $membersSet - list of Member|object with Email, FirstName, Surname fields.
     * @param array $customFields The subscriber details to use during creation. Each array item needs to have the same key as the member ID - e.g. array( 123 => array( [custom fields here] ), 456 => array( [custom fields here] ) )
     * @param bool $resubscribe Whether we should resubscribe any existing subscribers
     * @param bool $queueSubscriptionBasedAutoResponders By default, subscription based auto responders do not trigger during an import. Pass a value of true to override this behaviour
     * @param bool $restartSubscriptionBasedAutoResponders By default, subscription based auto responders will not be restarted
     *
     * NOTE that for the custom fields they need to be formatted like this:
     *    Array(
     *        'Key' => The custom fields personalisation tag
     *        'Value' => The value for this subscriber
     *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
     *    )

     * @return \CS_REST_Wrapper_Result A successful response will be empty
     */
    public function addSubscribers(
        $listID,
        $membersSet,
        $resubscribe,
        $customFields = [],
        $queueSubscriptionBasedAutoResponders = false,
        $restartSubscriptionBasedAutoResponders = false
    ) {
        //require_once '../../csrest_subscribers.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_subscribers.php';
        $wrap = new \CS_REST_Subscribers($listID, $this->getAuth());
        $importArray = [];
        foreach ($membersSet as $member) {
            $customFieldsForMember = [];
            if (isset($customFields[$member->ID])) {
                $customFieldsForMember = $customFields[$member->ID];
            } elseif (isset($customFields[$member->Email])) {
                $customFieldsForMember = $customFields[$member->Email];
            }
            foreach ($customFieldsForMember as $key => $customFieldValue) {
                if (! is_array($customFieldsForMember[$key])) {
                    $customFieldsForMember[] = [
                        'Key' => $key,
                        'Value' => $customFieldValue,
                        'Clear' => $customFieldValue ? false : true,
                    ];
                    unset($customFieldsForMember[$key]);
                }
            }
            if ($member instanceof Member) {
                $importArray[] = [
                    'EmailAddress' => $member->Email,
                    'Name' => trim($member->FirstName . ' ' . $member->Surname),
                    'CustomFields' => $customFieldsForMember,
                    'ConsentToTrack' => 'no',
                ];
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
            'POST /api/v3.1/subscribers/{list id}/import.{format}',
            'review details ...'
        );
    }

    /**
     * @param int $listID
     * @param Member|string $member - email address or Member Object
     *
     * @return \CS_REST_Wrapper_Result A successful response will be empty
     */
    public function deleteSubscriber($listID, $member)
    {
        if ($member instanceof Member) {
            $member = $member->Email;
        }
        $wrap = new \CS_REST_Subscribers($listID, $this->getAuth());
        $result = $wrap->delete($member);
        return $this->returnResult(
            $result,
            'DELETE /api/v3.1/subscribers/{list id}.{format}?email={emailAddress}',
            'Unsubscribed with code  ...'
        );
    }

    /**
     * Unsubscribes the given subscriber from the current list
     *
     * @param int $listID
     * @param Member|string $member
     *
     * @return \CS_REST_Wrapper_Result A successful response will be empty
     */
    public function unsubscribeSubscriber($listID, $member)
    {
        if ($member instanceof Member) {
            $member = $member->Email;
        }
        $wrap = new \CS_REST_Subscribers($listID, $this->getAuth());
        $result = $wrap->unsubscribe($member);
        return $this->returnResult(
            $result,
            'GET /api/v3.1/subscribers/{list id}/unsubscribe.{format}',
            'Unsubscribed with code  ...'
        );
    }

    /**
     * Is this user part of this list at all?
     *
     * @param int $listID
     * @param Member|string $member
     *
     * @return bool
     */
    public function getSubscriberExistsForThisList($listID, $member)
    {
        if ($member instanceof Member) {
            $member = $member->Email;
        }
        $outcome = $this->getSubscriber($listID, $member);
        if ($outcome && isset($outcome->State)) {
            if ($this->debug) {
                echo '<h3>Subscriber Exists For This List</h3>';
            }
            return true;
        }
        if ($this->debug) {
            echo '<h3>Subscriber does *** NOT *** Exist For This List</h3>';
        }
        return false;
    }

    /**
     * Can we send e-mails to this person in the future for this list?
     *
     * @param int $listID
     * @param Member | String $member
     *
     * @return bool
     */
    public function getSubscriberCanReceiveEmailsForThisList($listID, $member)
    {
        if ($member instanceof Member) {
            $member = $member->Email;
        }
        $outcome = $this->getSubscriber($listID, $member);
        if ($outcome && isset($outcome->State)) {
            if ($outcome->State === 'Active') {
                if ($this->debug) {
                    echo '<h3>Subscriber Can Receive Emails For This List</h3>';
                }
                return true;
            }
        }
        if ($this->debug) {
            echo '<h3>Subscriber Can *** NOT *** Receive Emails For This List</h3>';
        }
        return false;
    }

    /**
     * This e-mail / user has been banned from a list.
     *
     * @param int $listID
     * @param Member | String $member
     *
     * @return bool
     */
    public function getSubscriberCanNoLongerReceiveEmailsForThisList($listID, $member)
    {
        $subscriberExistsForThisList = $this->getSubscriberExistsForThisList($listID, $member);
        $subscriberCanReceiveEmailsForThisList = $this->getSubscriberCanReceiveEmailsForThisList($listID, $member);
        if ($subscriberExistsForThisList) {
            if (! $subscriberCanReceiveEmailsForThisList) {
                if ($this->debug) {
                    echo '<h3>Subscriber Can No Longer Receive Emails For This List</h3>';
                }
                return true;
            }
        }
        if ($this->debug) {
            echo '<h3>Subscriber Can *** STILL *** Receive Emails For This List</h3>';
        }
        return false;
    }

    /**
     * Gets a subscriber details, including custom fields
     *
     * @param int $listID
     * @param Member | String $member
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
     */
    public function getSubscriber($listID, $member, $cacheIsOK = true)
    {
        if ($member instanceof Member) {
            $member = $member->Email;
        }
        $key = $listID . '_' . $member;
        if (isset(self::$_get_subscriber[$key]) && $cacheIsOK) {
            //do nothing
        } else {
            $wrap = new \CS_REST_Subscribers($listID, $this->getAuth());
            $result = $wrap->get($member);
            self::$_get_subscriber[$key] = $this->returnResult(
                $result,
                'GET /api/v3.1/subscribers/{list id}.{format}?email={email}',
                'got subscribed subscriber'
            );
        }
        return self::$_get_subscriber[$key];
    }

    /**
     * Gets a subscriber details, including custom fields
     *
     * @param int $listID
     * @param Member | String $member
     *
     * @return \CS_REST_Wrapper_Result A successful response will be an object of the form
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
     */
    public function getHistory($listID, $member)
    {
        if ($member instanceof Member) {
            $member = $member->Email;
        }
        $wrap = new \CS_REST_Subscribers($listID, $this->getAuth());
        $result = $wrap->get_history($member);
        return $this->returnResult(
            $result,
            'GET /api/v3.1/subscribers/{list id}/history.{format}?email={email}',
            'got subscriber history'
        );
    }

    /**
     * provides the Authorisation Array
     * @return array
     */
    protected function getAuth()
    {
        if ($auth = $this->getFromCache('getAuth')) {
            return $auth;
        }
        if ($apiKey = $this->Config()->get('api_key')) {
            $auth = ['api_key' => $apiKey];
        } else {
            $client_id = $this->Config()->get('client_id');
            $client_secret = $this->Config()->get('client_secret');
            $redirect_uri = $this->Config()->get('redirect_uri');
            $code = $this->Config()->get('code');

            $result = CS_REST_General::exchange_token($client_id, $client_secret, $redirect_uri, $code);

            if ($result->was_successful()) {
                $auth = [
                    'access_token' => $result->response->access_token,
                    'refresh_token' => $result->response->refresh_token,
                ];
                //TODO: do we need to check expiry date?
                //$expires_in = $result->response->expires_in;
                # Save $access_token, $expires_in, and $refresh_token.
                if ($this->debug) {
                    'access token: ' . $result->response->access_token . "\n";
                    'expires in (seconds): ' . $result->response->expires_in . "\n";
                    'refresh token: ' . $result->response->refresh_token . "\n";
                }
            } else {
                # If you receive '121: Expired OAuth Token', refresh the access token
                if ($result->response->Code === 121) {
                    $wrap = new \CS_REST_General($auth);
                    list($new_access_token, , $new_refresh_token) = $wrap->refresh_token();

                    $auth = [
                        'access_token' => $new_access_token,
                        'refresh_token' => $new_refresh_token,
                    ];
                }

                if ($this->debug) {
                    $result->response->error . ': ' . $result->response->error_description . "\n";
                }
            }
        }
        $this->saveToCache($auth, 'getAuth');
        return $auth;
    }

    /**
     * returns the result or NULL in case of an error
     * @param \CS_REST_Wrapper_Result $result
     * @return mixed | Null
     */
    protected function returnResult($result, $apiCall, $description)
    {
        if ($this->debug) {
            echo "<h1>${description} ( ${apiCall} ) ...</h1>";
            if ($result->was_successful()) {
                echo '<h2>SUCCESS</h2>';
            } else {
                echo '<h2>FAILURE: ' . $result->http_status_code . '</h2>';
            }
            echo '<pre>';
            print_r($result);
            echo '</pre>';
            echo '<hr /><hr /><hr />';
            ob_flush();
            flush();
        }
        if ($result->was_successful()) {
            if (isset($result->response)) {
                return $result->response;
            }
            return true;
        }
        $this->httpStatusCode = $result->http_status_code;
        return null;
    }

    /*******************************************************
     * caching
     *
     *******************************************************/

    /**
     * @param string $name
     * @return mixed
     */
    protected function getFromCache($name)
    {
        if ($this->getAllowCaching()) {
            $name = 'CampaignMonitorAPIConnector_' . $name;
            $cache = Injector::inst()->get(CacheInterface::class . '.' . $name);
            $value = $cache->has($name) ? $cache->get($name) : null;
            if (! $value) {
                return null;
            }
            return unserialize($value);
        }
    }

    /**
     * @param mixed $unserializedValue
     * @param string $name
     */
    protected function saveToCache($unserializedValue, $name)
    {
        if ($this->getAllowCaching()) {
            $serializedValue = serialize($unserializedValue);
            $name = 'CampaignMonitorAPIConnector_' . $name;
            $cache = Injector::inst()->get(CacheInterface::class . '.' . $name);
            $cache->set($name, $serializedValue);
            return true;
        }
    }
}
