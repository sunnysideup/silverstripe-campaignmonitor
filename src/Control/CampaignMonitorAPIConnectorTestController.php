<?php

namespace Sunnysideup\CampaignMonitor\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Security\Member;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;

/**
 * simple class to see that everything is working .
 *
 * ..
 *
 */
class CampaignMonitorAPIConnectorTestController extends Controller
{
    use CampaignMonitorApiTrait;

    /**
     * example data.
     *
     * @var array
     */
    protected $egData = [
        'limit' => 10,
        'listID' => '',
        'listIDtoDelete' => '',
        'campaignID' => '',
        'templateID' => '',
        'listTitle' => 'Test List 9',
        'unsubscribePage' => 'http://unsub',
        'confirmedOptIn' => false,
        'confirmationSuccessPage' => 'http://confirmed',
        'unsubscribeSetting' => '',
        'addUnsubscribesToSuppList' => true,
        'scrubActiveWithSuppList' => true,
        'oldEmailAddress' => 'oldemail@test.nowhere',
        'newEmailAddress' => 'newemail@test.nowhere',
    ];

    /**
     * contains API once started.
     *
     * @var CampaignMonitorAPIConnector
     */
    protected $api;

    /**
     * should we show as much as possible?
     *
     * @var bool
     */
    protected $showAll = false;

    private static $url_segment = 'create-send-test';

    private static $allowed_actions = [
        'index' => 'ADMIN',
        'testall' => 'ADMIN',
        'testlists' => 'ADMIN',
        'testcampaigns' => 'ADMIN',
        'testsubscribers' => 'ADMIN',
    ];

    /**
     * link for controller
     * we add baseURL to make it work for all set ups.
     *
     * @param null|string $action
     */
    public function Link($action = null)
    {
        $link = Director::baseURL() . $this->Config()->get('url_segment') . '/';
        if ($action) {
            $link .= $action . '/';
        }

        return $link;
    }

    /**
     * run all tests.
     */
    public function testall()
    {
        if (! $this->isConfirmed()) {
            return $this->confirmForm('testall');
        }
        $this->testlists();
        $this->testcampaigns();
        $this->testsubscribers();
        $this->index();
        die('<h1>THE END</h1>');
    }

    public function index()
    {
        // these tests create and delete lists, subscribers and campaigns - they must be confirmed (POST with security token).
        echo '
            <hr /><hr /><hr /><hr /><hr />
            <ul>
                <li>' . $this->confirmFormHtml('testlists', 'test lists') . '</li>
                <li>' . $this->confirmFormHtml('testcampaigns', 'test campaigns') . '</li>
                <li>' . $this->confirmFormHtml('testsubscribers', 'test subscribers') . '</li>
                <li>' . $this->confirmFormHtml('testall', 'test all') . '</li>
            </ul>
            <hr /><hr /><hr /><hr /><hr />
        ';
    }

    /**
     * the tests write to the Campaign Monitor account: only run them for a POST request with a valid security token.
     */
    protected function isConfirmed(): bool
    {
        $request = $this->getRequest();

        return $request->isPOST() && SecurityToken::inst()->checkRequest($request);
    }

    protected function confirmFormHtml(string $action, string $title): string
    {
        $tokenField = SecurityToken::inst()->getName();

        return '
            <form method="post" action="' . Convert::raw2att($this->Link($action)) . '">
                <input type="hidden" name="' . Convert::raw2att($tokenField) . '" value="' . Convert::raw2att(SecurityToken::inst()->getValue()) . '" />
                <input type="submit" value="' . Convert::raw2att($title) . '" />
            </form>';
    }

    protected function confirmForm(string $action): string
    {
        return '<h1>Please confirm</h1>
            <p>This test creates and deletes lists, subscribers and campaigns in the connected Campaign Monitor account.</p>
            ' . $this->confirmFormHtml($action, 'Run ' . $action);
    }

    public function testlists()
    {
        if (! $this->isConfirmed()) {
            return $this->confirmForm('testlists');
        }
        $this->setupTests();

        //create list
        $result = $this->api->createList(
            $this->egData['listTitle'],
            $this->egData['unsubscribePage'],
            $this->egData['confirmationSuccessPage'],
            $this->egData['confirmedOptIn'],
            $this->egData['unsubscribeSetting']
        );
        $this->egData['listIDtoDelete'] = $result;

        //update list
        $this->api->updateList(
            $this->egData['listIDtoDelete'],
            $this->egData['listTitle'] . 'updated_22',
            $this->egData['unsubscribePage'] . 'updated',
            $this->egData['confirmationSuccessPage'] . 'updated',
            $this->egData['unsubscribeSetting'],
            $this->egData['confirmedOptIn'],
            $addUnsubscribesToSuppList = true,
            $scrubActiveWithSuppList = true
        );

        //delete list
        if ($this->egData['listIDtoDelete']) {
            $this->api->deleteList($this->egData['listIDtoDelete']);
        }

        //getList
        $this->api->getList($this->egData['listID']);

        $this->api->getActiveSubscribers(
            $this->egData['listID'],
            $daysAgo = 3650,
            $page = 1,
            $pageSize = $this->egData['limit'],
            $sortByField = 'DATE',
            $sortDirection = 'DESC'
        );

        $this->api->getUnconfirmedSubscribers(
            $this->egData['listID'],
            $daysAgo = 3650,
            $page = 1,
            $pageSize = $this->egData['limit'],
            $sortByField = 'DATE',
            $sortDirection = 'DESC'
        );

        $this->api->getBouncedSubscribers(
            $this->egData['listID'],
            $daysAgo = 3650,
            $page = 1,
            $pageSize = $this->egData['limit'],
            $sortByField = 'DATE',
            $sortDirection = 'DESC'
        );

        $this->api->getUnsubscribedSubscribers(
            $this->egData['listID'],
            $daysAgo = 3650,
            $page = 1,
            $pageSize = $this->egData['limit'],
            $sortByField = 'DATE',
            $sortDirection = 'DESC'
        );

        $this->api->getSegments($this->egData['listID']);

        $this->api->getListStats($this->egData['listID']);

        $this->api->getListCustomFields($this->egData['listID']);

        echo '<h2>end of list tests</h2>';
        $this->index();
    }

    public function testcampaigns()
    {
        if (! $this->isConfirmed()) {
            return $this->confirmForm('testcampaigns');
        }
        $this->setupTests();

        $this->api->getTemplates();
        flush();
        ob_flush();

        //campaign summary

        $this->api->getCampaigns();

        $this->api->getDrafts();

        $this->api->getSummary($this->egData['campaignID']);

        $this->api->getEmailClientUsage($this->egData['campaignID']);

        $this->api->getUnsubscribes(
            $this->egData['campaignID'],
            $daysAgo = 3650,
            $page = 1,
            $pageSize = $this->egData['limit'],
            $sortByField = 'EMAIL',
            $sortDirection = 'ASC'
        );

        echo '<h3>creating a campaign without template</h3>';
        $obj = CampaignMonitorCampaign::create();
        $randNumber = rand();
        $obj->Name = 'test only ' . $randNumber;
        $obj->Subject = 'test only ' . $randNumber;
        $obj->CreateAsTemplate = false;
        $obj->CreateFromWebsite = true;
        $obj->write();
        $this->api->getSummary($obj->CampaignID);
        echo '<h3>deleting campaign without template</h3>';
        $obj->delete();

        echo '<h3>creating a campaign with template</h3>';
        $obj = CampaignMonitorCampaign::create();
        $randNumber = rand();
        $obj->Name = 'test only ' . $randNumber;
        $obj->Subject = 'test only ' . $randNumber;
        $obj->CreateAsTemplate = true;
        $obj->CreateFromWebsite = true;
        $obj->write();
        $this->api->getSummary($obj->TemplateID);
        echo '<h3>deleting campaign with template</h3>';
        $obj->delete();

        echo '<h2>end of campaign tests</h2>';
        $this->index();
    }

    public function testsubscribers()
    {
        if (! $this->isConfirmed()) {
            return $this->confirmForm('testsubscribers');
        }
        $this->setupTests();

        //create list
        $result = $this->api->createList(
            $this->egData['listTitle'],
            $this->egData['unsubscribePage'],
            $this->egData['confirmationSuccessPage'],
            $this->egData['confirmedOptIn'],
            $this->egData['unsubscribeSetting']
        );
        $this->egData['tempListID'] = $result;

        $customFieldKey = $this->api->createCustomField(
            $this->egData['tempListID'],
            $visible = true,
            $type = 'multi_select_one',
            $title = 'are you happy?',
            $options = ['YES', 'NO']
        );
        $member = [];
        for ($i = 0; $i < 5; ++$i) {
            $member[$i] = new Member();
            $email = 'test_' . $i . '_' . $this->egData['oldEmailAddress'];
            $member[$i] = Member::get()->filter(['Email' => $email])->First();
            if (! $member[$i]) {
                $member[$i] = new Member();
                $member[$i]->Email = $email;
                $member[$i]->FirstName = "First Name {$i}";
                $member[$i]->Surname = "Surname {$i}";
                $member[$i]->write();
            }

            $this->api->addSubscriber(
                $this->egData['tempListID'],
                $member[$i],
                $customFields = [$customFieldKey => 'NO'],
                $resubscribe = true,
                $restartSubscriptionBasedAutoResponders = false
            );
            $this->api->updateSubscriber(
                $this->egData['tempListID'],
                $member[$i],
                $email,
                $customFields = [$customFieldKey => 'YES'],
                $resubscribe = true,
                $restartSubscriptionBasedAutoResponders = false
            );
            sleep(1);
        }

        /*
        $result = $this->api->addSubscribers(
            $this->egData["tempListID"],
            $membersSet,
            $resubscribe,
            $customFields = array(),
            $queueSubscriptionBasedAutoResponders = false,
            $restartSubscriptionBasedAutoResponders = false
        );
        */

        $this->api->deleteSubscriber(
            $this->egData['tempListID'],
            $member[2]
        );

        $this->api->unsubscribeSubscriber(
            $this->egData['tempListID'],
            $member[3]
        );

        for ($i = 0; $i < 5; ++$i) {
            $this->api->getSubscriberExistsForThisList(
                $this->egData['tempListID'],
                $member[$i]
            );

            $this->api->getListsForEmail($member[$i]);

            $this->api->getSubscriberCanReceiveEmailsForThisList(
                $this->egData['tempListID'],
                $member[$i]
            );

            $this->api->getSubscriberCanNoLongerReceiveEmailsForThisList(
                $this->egData['tempListID'],
                $member[$i]
            );

            $this->api->getSubscriber(
                $this->egData['tempListID'],
                $member[$i]
            );

            $this->api->getHistory(
                $this->egData['tempListID'],
                $member[$i]
            );
            $this->api->deleteSubscriber(
                $this->egData['tempListID'],
                $member[$i]
            );
            $member[$i]->delete();
            sleep(1);
        }

        //delete list
        if ($this->egData['tempListID']) {
            $this->api->deleteCustomField($this->egData['tempListID'], $customFieldKey);
            $this->api->deleteList($this->egData['tempListID']);
        }

        echo '<h2>end of subscriber tests</h2>';
        $this->index();
    }

    protected function init()
    {
        parent::init();
        // never run against a live site: it creates and deletes lists and subscribers.
        if (Director::isLive()) {
            $this->httpError(404, 'Not found');
        }
        if (! Config::inst()->get(CampaignMonitorAPIConnector::class, 'client_id')) {
            user_error('To use the campaign monitor module you must set the basic authentication credentials such as CampaignMonitorAPIConnector.client_id');
        }

        $this->egData['listTitle'] .= rand();
    }

    protected function setupTests()
    {
        $this->api = $this->getCMAPI();
        if (! $this->api) {
            user_error('Api not enabled!');

            return;
        }

        if ($this->showAll) {
            $this->egData['limit'] = 100;
        }

        //getLists
        $result = $this->api->getLists();
        $this->egData['listID'] = $result[0]->ListID;

        //getCampaigns
        $result = $this->api->getCampaigns();
        if (isset($result[0])) {
            $this->egData['campaignID'] = $result[0]->CampaignID;
        }

        $this->api->setDebug(true);
    }
}
