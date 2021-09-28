<?php

namespace Sunnysideup\CampaignMonitor\Api\Traits;

use SilverStripe\Control\Email\Email;

trait Lists
{
    /**
     * Gets all subscriber lists the current client has created.
     *
     * @return mixed A successful response will be an object of the form
     *               array(
     *               {
     *               'ListID' => The id of the list
     *               'Name' => The name of the list
     *               }
     *               )
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

    /**
     * list of people that are definitely suppressed...
     *
     * @param int    $page          page number
     * @param int    $pageSize      size of page
     * @param string $sortByField   (email)
     * @param string $sortDirection (asc)
     *
     * @return mixed
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

    // lists

    /**
     * Creates a new list based on the provided details.
     * Both the UnsubscribePage and the ConfirmationSuccessPage parameters are optional.
     *
     * @param string $title                   - the page to redirect subscribers to when they unsubscribeThe list title
     * @param string $unsubscribePage         - The page to redirect subscribers to when they unsubscribe
     * @param bool   $confirmedOptIn          - Whether this list requires confirmation of subscription
     * @param string $confirmationSuccessPage - The page to redirect subscribers to when they confirm their subscription
     * @param string $unsubscribeSetting      - Unsubscribe setting must be CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS or CS_REST_LIST_UNSUBSCRIBE_SETTING_ONLY_THIS_LIST.  See the documentation for details: http://www.campaignmonitor.com/api/lists/#creating_a_list
     *
     * @return mixed A successful response will be the ID of the newly created list
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
     * Creates custom field for list.
     *
     * @param string $listID  - list ID
     * @param string $type    - type of custom field
     * @param string $title   - field type
     * @param array  $options - options for dropdown field type
     * @param mixed  $visible
     *
     * @return mixed A successful response will be the key of the newly created custom field
     */
    public function createCustomField(string $listID, $visible, $type, $title, $options = [])
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
            'VisibleInPreferenceCenter' => (bool) $visible,
        ]);

        return $this->returnResult(
            $result,
            'POST /api/v3/lists/{ID}/customfields',
            "Created Custom Field for {$listID} "
        );
    }

    /**
     * Creates custom field for list.
     *
     * @param string $listID - list ID
     * @param string $key
     *
     * @return mixed
     */
    public function deleteCustomField($listID, $key)
    {
        $wrap = new \CS_REST_Lists($listID, $this->getAuth());
        $result = $wrap->delete_custom_field($key);

        return $this->returnResult(
            $result,
            'DELETE /api/v3/lists/{ID}/{Key}',
            "Delete Custom Field for {$listID} with key {$key}"
        );
    }

    /**
     * Deletes an existing list from the system.
     *
     * @param string $listID
     *
     * @return mixed An unsuccessful response will be empty! A good result with contains something / be true
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
     * Gets the basic details of the current list.
     *
     * @param string $listID
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'ListID' => The id of the list
     *               'Title' => The title of the list
     *               'UnsubscribePage' => The page which subscribers are redirected to upon unsubscribing
     *               'ConfirmedOptIn' => Whether the list is Double-Opt In
     *               'ConfirmationSuccessPage' => The page which subscribers are
     *               redirected to upon confirming their subscription
     *               'UnsubscribeSetting' => The unsubscribe setting for the list. Will
     *               be either CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS or
     *               CS_REST_LIST_UNSUBSCRIBE_SETTING_ONLY_THIS_LIST.
     *               }
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
     * Gets all active subscribers added since the given date.
     *
     * @param string $listID
     * @param int    $daysAgo       The date to start getting subscribers from
     * @param int    $page          The page number to get
     * @param int    $pageSize      The number of records per page
     * @param string $sortByField   ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'ResultsOrderedBy' => The field the results are ordered by
     *               'OrderDirection' => The order direction
     *               'PageNumber' => The page number for the result set
     *               'PageSize' => The page size used
     *               'RecordsOnThisPage' => The number of records returned
     *               'TotalNumberOfRecords' => The total number of records available
     *               'NumberOfPages' => The total number of pages for this collection
     *               'Results' => array(
     *               {
     *               'EmailAddress' => The email address of the subscriber
     *               'Name' => The name of the subscriber
     *               'Date' => The date that the subscriber was added to the list
     *               'State' => The current state of the subscriber, will be 'Active'
     *               'CustomFields' => array (
     *               {
     *               'Key' => The personalisation tag of the custom field
     *               'Value' => The value of the custom field for this subscriber
     *               }
     *               )
     *               }
     *               )
     *               }
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
     * Gets all unconfirmed subscribers added since the given date.
     *
     * @param string $listID
     * @param int    $daysAgo       The date to start getting subscribers from
     * @param int    $page          The page number to get
     * @param int    $pageSize      The number of records per page
     * @param string $sortByField   ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'ResultsOrderedBy' => The field the results are ordered by
     *               'OrderDirection' => The order direction
     *               'PageNumber' => The page number for the result set
     *               'PageSize' => The page size used
     *               'RecordsOnThisPage' => The number of records returned
     *               'TotalNumberOfRecords' => The total number of records available
     *               'NumberOfPages' => The total number of pages for this collection
     *               'Results' => array(
     *               {
     *               'EmailAddress' => The email address of the subscriber
     *               'Name' => The name of the subscriber
     *               'Date' => The date that the subscriber was added to the list
     *               'State' => The current state of the subscriber, will be 'Unconfirmed'
     *               'CustomFields' => array (
     *               {
     *               'Key' => The personalisation tag of the custom field
     *               'Value' => The value of the custom field for this subscriber
     *               }
     *               )
     *               }
     *               )
     *               }
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
     * Gets all bounced subscribers who have bounced out since the given date.
     *
     * @param string $listID
     * @param int    $daysAgo       The date to start getting subscribers from
     * @param int    $page          The page number to get
     * @param int    $pageSize      The number of records per page
     * @param string $sortByField   ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'ResultsOrderedBy' => The field the results are ordered by
     *               'OrderDirection' => The order direction
     *               'PageNumber' => The page number for the result set
     *               'PageSize' => The page size used
     *               'RecordsOnThisPage' => The number of records returned
     *               'TotalNumberOfRecords' => The total number of records available
     *               'NumberOfPages' => The total number of pages for this collection
     *               'Results' => array(
     *               {
     *               'EmailAddress' => The email address of the subscriber
     *               'Name' => The name of the subscriber
     *               'Date' => The date that the subscriber bounced out of the list
     *               'State' => The current state of the subscriber, will be 'Bounced'
     *               'CustomFields' => array (
     *               {
     *               'Key' => The personalisation tag of the custom field
     *               'Value' => The value of the custom field for this subscriber
     *               }
     *               )
     *               }
     *               )
     *               }
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
     * Gets all unsubscribed subscribers who have unsubscribed since the given date.
     *
     * @param string $listID
     * @param int    $daysAgo       The date to start getting subscribers from
     * @param int    $page          The page number to get
     * @param int    $pageSize      The number of records per page
     * @param string $sortByField   ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'ResultsOrderedBy' => The field the results are ordered by
     *               'OrderDirection' => The order direction
     *               'PageNumber' => The page number for the result set
     *               'PageSize' => The page size used
     *               'RecordsOnThisPage' => The number of records returned
     *               'TotalNumberOfRecords' => The total number of records available
     *               'NumberOfPages' => The total number of pages for this collection
     *               'Results' => array(
     *               {
     *               'EmailAddress' => The email address of the subscriber
     *               'Name' => The name of the subscriber
     *               'Date' => The date that the subscriber was unsubscribed from the list
     *               'State' => The current state of the subscriber, will be 'Unsubscribed'
     *               'CustomFields' => array (
     *               {
     *               'Key' => The personalisation tag of the custom field
     *               'Value' => The value of the custom field for this subscriber
     *               }
     *               )
     *               }
     *               )
     *               }
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
     * Gets all unsubscribed subscribers who have unsubscribed since the given date.
     *
     * @param int    $daysAgo       The date to start getting subscribers from
     * @param int    $page          The page number to get
     * @param int    $pageSize      The number of records per page
     * @param string $sortByField   ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'ResultsOrderedBy' => The field the results are ordered by
     *               'OrderDirection' => The order direction
     *               'PageNumber' => The page number for the result set
     *               'PageSize' => The page size used
     *               'RecordsOnThisPage' => The number of records returned
     *               'TotalNumberOfRecords' => The total number of records available
     *               'NumberOfPages' => The total number of pages for this collection
     *               'Results' => array(
     *               {
     *               'EmailAddress' => The email address of the subscriber
     *               'Name' => The name of the subscriber
     *               'Date' => The date that the subscriber was unsubscribed from the list
     *               'State' => The current state of the subscriber, will be 'Unsubscribed'
     *               'CustomFields' => array (
     *               {
     *               'Key' => The personalisation tag of the custom field
     *               'Value' => The value of the custom field for this subscriber
     *               }
     *               )
     *               }
     *               )
     *               }
     */
    public function getDeletedSubscribers(
        string $listID,
        ?int $daysAgo = 3650,
        ?int $page = 1,
        ?int $pageSize = 999,
        ?string $sortByField = 'email',
        ?string $sortDirection = 'asc'
    ) {
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
     * Both the UnsubscribePage and the ConfirmationSuccessPage parameters are optional.
     *
     * @param string $title                     - the page to redirect subscribers to when they unsubscribeThe list title
     * @param string $unsubscribePage           - The page to redirect subscribers to when they unsubscribe
     * @param string $confirmationSuccessPage   - The page to redirect subscribers to when they confirm their subscription
     * @param string $unsubscribeSetting        - Unsubscribe setting must be CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS or CS_REST_LIST_UNSUBSCRIBE_SETTING_ONLY_THIS_LIST.  See the documentation for details: http://www.campaignmonitor.com/api/lists/#creating_a_list
     * @param bool   $confirmedOptIn            - Whether this list requires confirmation of subscription
     * @param bool   $addUnsubscribesToSuppList -  When UnsubscribeSetting is CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS, whether unsubscribes from this list should be added to the suppression list
     * @param bool   $scrubActiveWithSuppList   - When UnsubscribeSetting is CS_REST_LIST_UNSUBSCRIBE_SETTING_ALL_CLIENT_LISTS, whether active subscribers should be scrubbed against the suppression list
     *
     * @return mixed An unsuccessful response will be empty! A good result with contains something / be true
     */
    public function updateList(
        string $listID,
        string $title,
        string $unsubscribePage,
        string $confirmationSuccessPage,
        string $unsubscribeSetting,
        ?bool $confirmedOptIn = false,
        ?bool $addUnsubscribesToSuppList = true,
        ?bool $scrubActiveWithSuppList = true
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
     * Gets statistics for list subscriptions, deletions, bounces and unsubscriptions.
     *
     * @param string $listID
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'TotalActiveSubscribers'
     *               'NewActiveSubscribersToday'
     *               'NewActiveSubscribersYesterday'
     *               'NewActiveSubscribersThisWeek'
     *               'NewActiveSubscribersThisMonth'
     *               'NewActiveSubscribersThisYeay'
     *               'TotalUnsubscribes'
     *               'UnsubscribesToday'
     *               'UnsubscribesYesterday'
     *               'UnsubscribesThisWeek'
     *               'UnsubscribesThisMonth'
     *               'UnsubscribesThisYear'
     *               'TotalDeleted'
     *               'DeletedToday'
     *               'DeletedYesterday'
     *               'DeletedThisWeek'
     *               'DeletedThisMonth'
     *               'DeletedThisYear'
     *               'TotalBounces'
     *               'BouncesToday'
     *               'BouncesYesterday'
     *               'BouncesThisWeek'
     *               'BouncesThisMonth'
     *               'BouncesThisYear'
     *               }
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

    public function getListsAndSegments()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }
}
