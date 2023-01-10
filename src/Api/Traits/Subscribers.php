<?php

namespace Sunnysideup\CampaignMonitor\Api\Traits;

use SilverStripe\Control\Email\Email;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;

trait Subscribers
{
    private static $_get_subscriber = [];

    /*
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
     */

    /**
     * Gets the lists across a client to which a subscriber with a particular
     * email address belongs.
     *
     * @param Member|string $member Subscriber's email address (or Member)
     *
     * @return mixed A successful response will be an object of the form
     *               array(
     *               {
     *               'ListID' => The id of the list
     *               'ListName' => The name of the list
     *               'SubscriberState' => The state of the subscriber in the list
     *               'DateSubscriberAdded' => The date the subscriber was added
     *               }
     *               )
     */
    public function getListsForEmail($member)
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $email = $member instanceof Member ? $member->Email : $member;
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        //require_once '../../csrest_clients.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_clients.php';
        $wrap = new \CS_REST_Clients($this->Config()->get('client_id'), $this->getAuth());
        $result = $wrap->get_lists_for_email($email);

        $result = $this->returnResult(
            $result,
            '/api/v3.1/clients/{id}/listsforemail',
            'Got lists to which email address ' . $email . ' is subscribed'
        );
        if (true === $result) {
            return [];
        }

        return $result;
    }

    /**
     * Adds a new subscriber to the specified list.
     *
     * @param Member $member                                 (Member or standard object with Email, FirstName, Surname properties)
     * @param array  $customFields
     * @param array  $customFields                           the subscriber details to use during creation
     * @param bool   $resubscribe                            Whether we should resubscribe this subscriber if they already exist in the list
     * @param bool   $restartSubscriptionBasedAutoResponders Whether we should restart subscription based auto responders which are sent when the subscriber first subscribes to a list.
     *
     * NOTE that for the custom fields they need to be formatted like this:
     *    Array(
     *        'Key' => The custom fields personalisation tag
     *        'Value' => The value for this subscriber
     *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
     *    )
     *
     * @return mixed A bad response will be empty
     */
    public function addSubscriber(
        string $listID,
        Member $member,
        ?array $customFields = [],
        ?bool $resubscribe = true,
        ?bool $restartSubscriptionBasedAutoResponders = false
    ) {
        if (! $this->isAvailable()) {
            return null;
        }

        //require_once '../../csrest_subscribers.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_subscribers.php';
        $wrap = new \CS_REST_Subscribers($listID, $this->getAuth());
        $customFields = $this->cleanCustomFields($customFields);
        $request = [
            'EmailAddress' => $member->Email,
            'Name' => trim($member->FirstName . ' ' . $member->Surname),
            'CustomFields' => $customFields,
            'Resubscribe' => $resubscribe,
            'RestartSubscriptionBasedAutoResponders' => $restartSubscriptionBasedAutoResponders,
            'ConsentToTrack' => $member->CM_PermissionToTrack ?: 'Unchanged',
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
     * @param string             $listID
     * @param Member             $member                                 (Member or standard object with Email, FirstName, Surname properties)
     * @param null|string        $oldEmailAddress
     * @param null|array         $customFields                           the subscriber details to use during creation
     * @param null|bool          $resubscribe                            Whether we should resubscribe this subscriber if they already exist in the list
     * @param null|bool          $restartSubscriptionBasedAutoResponders Whether we should restart subscription based auto responders which are sent when the subscriber first subscribes to a list.
     *
     * NOTE that for the custom fields they need to be formatted like this:
     *    Array(
     *        'Key' => The custom fields personalisation tag
     *        'Value' => The value for this subscriber
     *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
     *    )
     *
     * @return mixed An unsuccessful response will be empty! A good result with contains something / be true
     */
    public function updateSubscriber(
        $listID,
        Member $member,
        $oldEmailAddress = '',
        $customFields = [],
        $resubscribe = true,
        $restartSubscriptionBasedAutoResponders = false
    ) {
        if (! $this->isAvailable()) {
            return null;
        }

        if (! $oldEmailAddress) {
            $oldEmailAddress = $member->Email;
        }

        $customFields = $this->cleanCustomFields($customFields);
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
                'ConsentToTrack' => $member->CM_PermissionToTrack ?: 'Unchanged',
            ]
        );

        return $this->returnResult(
            $result,
            'PUT /api/v3.1/subscribers/{list id}.{format}?email={email}',
            "updated with email {$oldEmailAddress} ..."
        );
    }

    /**
     * Updates an existing subscriber (email, name, state, or custom fields) in the specified list.
     * The update is performed even for inactive subscribers, but will return an error in the event of the
     * given email not existing in the list.
     *
     * @param string         $listID
     * @param array|dataList $membersSet                             - list of Member|object with Email, FirstName, Surname fields
     * @param array          $customFields                           The subscriber details to use during creation. Each array item needs to have the same key as the member ID - e.g. array( 123 => array( [custom fields here] ), 456 => array( [custom fields here] ) )
     * @param bool           $resubscribe                            Whether we should resubscribe any existing subscribers
     * @param bool           $queueSubscriptionBasedAutoResponders   By default, subscription based auto responders do not trigger during an import. Pass a value of true to override this behaviour
     * @param bool           $restartSubscriptionBasedAutoResponders By default, subscription based auto responders will not be restarted
     *
     * NOTE that for the custom fields they need to be formatted like this:
     *    Array(
     *        'Key' => The custom fields personalisation tag
     *        'Value' => The value for this subscriber
     *        'Clear' => true/false (pass true to remove this custom field. in the case of a [multi-option, select many] field, pass an option in the 'Value' field to clear that option or leave Value blank to remove all options)
     *    )
     *
     * @return mixed A bad response will be empty
     */
    public function addSubscribers(
        $listID,
        $membersSet,
        $resubscribe,
        $customFields = [],
        $queueSubscriptionBasedAutoResponders = false,
        $restartSubscriptionBasedAutoResponders = false
    ) {
        if (! $this->isAvailable()) {
            return null;
        }

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

            $customFieldsForMember = $this->cleanCustomFields($customFieldsForMember);
            if ($member instanceof Member) {
                $importArray[] = [
                    'EmailAddress' => $member->Email,
                    'Name' => trim($member->FirstName . ' ' . $member->Surname),
                    'CustomFields' => $customFieldsForMember,
                    'ConsentToTrack' => $member->CM_PermissionToTrack ?: 'Unchanged',
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
     * @param string        $listID
     * @param Member|string $member - email address or Member Object
     *
     * @return mixed An unsuccessful response will be empty! A good result with contains something / be true
     */
    public function deleteSubscriber($listID, $member)
    {
        if (! $this->isAvailable()) {
            return null;
        }

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
     * Unsubscribes the given subscriber from the current list.
     *
     * @param string        $listID
     * @param Member|string $member
     *
     * @return mixed An unsuccessful response will be empty! A good result with contains something / be true
     */
    public function unsubscribeSubscriber($listID, $member)
    {
        if (! $this->isAvailable()) {
            return null;
        }

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
     * @param string        $listID
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
        if ($outcome && (property_exists($outcome, 'State') && null !== $outcome->State)) {
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
     * @param string        $listID
     * @param Member|string $member
     *
     * @return bool
     */
    public function getSubscriberCanReceiveEmailsForThisList($listID, $member)
    {
        if ($member instanceof Member) {
            $member = $member->Email;
        }

        $outcome = $this->getSubscriber($listID, $member);
        if ($outcome && (property_exists($outcome, 'State') && null !== $outcome->State)) {
            if ('Active' === $outcome->State) {
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
     * @param string        $listID
     * @param Member|string $member
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
     * Gets a subscriber details, including custom fields.
     *
     * @param string        $listID
     * @param Member|string $member
     * @param mixed         $cacheIsOK
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'EmailAddress' => The subscriber email address
     *               'Name' => The subscribers name
     *               'Date' => The date the subscriber was added to the list
     *               'State' => The current state of the subscriber
     *               'CustomFields' => array(
     *               {
     *               'Key' => The custom fields personalisation tag
     *               'Value' => The custom field value for this subscriber
     *               }
     *               )
     *               }
     */
    public function getSubscriber($listID, $member, $cacheIsOK = true)
    {
        if (! $this->isAvailable()) {
            return null;
        }

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
     * Gets a subscriber details, including custom fields.
     *
     * @param string        $listID
     * @param Member|string $member
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'EmailAddress' => The subscriber email address
     *               'Name' => The subscribers name
     *               'Date' => The date the subscriber was added to the list
     *               'State' => The current state of the subscriber
     *               'CustomFields' => array(
     *               {
     *               'Key' => The custom fields personalisation tag
     *               'Value' => The custom field value for this subscriber
     *               }
     *               )
     *               }
     */
    public function getHistory($listID, $member)
    {
        if (! $this->isAvailable()) {
            return null;
        }

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
}
