<?php

namespace Sunnysideup\CampaignMonitor;

use Page;































use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\View\Requirements;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\Control\CampaignMonitorAPIConnectorTestController;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaignStyle;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCustomField;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorSegment;
use Sunnysideup\CampaignMonitor\Tasks\CampaignMonitorAddOldCampaigns;

/**
 * Page for Signing Up to Campaign Monitor List
 *
 * Each page relates to one CM list.
 *
 * @author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorSignupPage extends Page
{
    /**
     * @var CampaignMonitorAPIConnector | Null
     */
    protected static $_api = null;

    /**
     * standard SS variable
     * @Var String
     */
    private static $singular_name = 'Newsletter sign-up page';

    /**
     * standard SS variable
     * @Var String
     */
    private static $plural_name = 'Newsletter sign-up pages';

    /**
     * @inherited
     */
    private static $icon = 'sunnysideup/campaignmonitor: client/images/treeicons/CampaignMonitorSignupPage-file.gif';

    /**
     * @inherited
     */
    private static $table_name = 'CampaignMonitorSignupPage';

    private static $db = [
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
        'ShowAllNewsletterForSigningUp' => 'Boolean',

    ];

    /**
     * @inherited
     */
    private static $has_one = [
        'Group' => Group::class,
    ];

    /**
     * @inherited
     */
    private static $has_many = [
        'CampaignMonitorSegments' => CampaignMonitorSegment::class,
        'CampaignMonitorCustomFields' => CampaignMonitorCustomField::class,
    ];

    /**
     * @inherited
     */
    private static $belongs_many_many = [
        'CampaignMonitorCampaigns' => CampaignMonitorCampaign::class,
    ];

    /**
     * @inherited
     */
    private static $description = 'Page to suscribe and review newsletter list(s)';

    /**
     * @var array|null
     */
    private static $drop_down_list = [];

    public function i18n_singular_name()
    {
        return _t('AccountPage.NEWSLETTER_PAGE', 'Newsletter sign-up page');
    }

    public function i18n_plural_name()
    {
        return _t('AccountPage.NEWSLETTER_PAGE', 'Newsletter sign-up pages');
    }

    /**
     * Campaign monitor pages that are ready to receive "sign-ups"
     * @return \SilverStripe\ORM\ArrayList
     */
    public static function get_ready_ones()
    {
        $listPages = CampaignMonitorSignupPage::get();
        $array = [0 => 0];
        foreach ($listPages as $listPage) {
            if ($listPage->ReadyToReceiveSubscribtions()) {
                $array[$listPage->ID] = $listPage->ID;
            }
        }
        return CampaignMonitorSignupPage::get()->filter(['ID' => $array]);
    }

    /**
     * @inherited
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        if ($this->GroupID) {
            $groupLink = '<h2><a href="/admin/security/EditForm/field/Groups/item/' . $this->GroupID . '/edit">open related security group</a></h2>';
        } else {
            $groupLink = '<p>No Group has been selected yet.</p>';
        }
        $testControllerLink = Injector::inst()->get(CampaignMonitorAPIConnectorTestController::class)->Link();
        $campaignExample = CampaignMonitorCampaign::get()->Last();
        $campaignExampleLink = $this->Link();
        if ($campaignExample) {
            $campaignExampleLink = $this->Link('viewcampaign/' . $campaignExample->CampaignID);
        }
        if ($this->ID) {
            $config = GridFieldConfig_RelationEditor::create();
            $campaignField = new GridField('CampaignList', 'Campaigns', $this->CampaignMonitorCampaigns(), $config);
        } else {
            $campaignField = new HiddenField('CampaignList');
        }
        $gridFieldTemplatesAvailable = new GridField('TemplatesAvailable', 'Templates Available', CampaignMonitorCampaignStyle::get(), GridFieldConfig_RecordEditor::create());
        $gridFieldTemplatesAvailable->setDescription('Ask your developer on how to add more templates');
        $fields->addFieldToTab(
            'Root.AlternativeContent',
            new TabSet(
                'AlternativeContentSubHeader',
                new Tab(
                    'Confirm',
                    new TextField('ConfirmTitle', 'Title'),
                    new TextField('ConfirmMenuTitle', 'Menu Title'),
                    new HTMLEditorField('ConfirmMessage', 'Message (e.g. thank you for confirming)')
                ),
                new Tab(
                    'ThankYou',
                    new TextField('ThankYouTitle', 'Title'),
                    new TextField('ThankYouMenuTitle', 'Menu Title'),
                    new HTMLEditorField('ThankYouMessage', 'Thank you message after submitting form')
                ),
                new Tab(
                    'SadToSeeYouGo',
                    new TextField('SadToSeeYouGoTitle', 'Title'),
                    new TextField('SadToSeeYouGoMenuTitle', 'Menu Title'),
                    new HTMLEditorField('SadToSeeYouGoMessage', 'Sad to see you  go message after submitting form')
                )
            )
        );
        $fields->addFieldToTab(
            'Root.Newsletters',
            new TabSet(
                'Options',
                new Tab(
                    'MainSettings',
                    new LiteralField('CreateNewCampaign', '<p>To create a new mail out go to <a href="' . Config::inst()->get(CampaignMonitorAPIConnector::class, 'campaign_monitor_url') . '">Campaign Monitor</a> site.</p>'),
                    new LiteralField('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                    new DropdownField('ListID', 'Related List from Campaign Monitor (*)', [0 => '-- please select --'] + $this->makeDropdownListFromLists()),
                    new CheckboxField('ShowAllNewsletterForSigningUp', 'Allow users to sign up to all lists')
                ),
                new Tab(
                    'StartForm',
                    new LiteralField('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they go through to another page (the actual CampaignMonitorSignUpPage) to complete all the details.'),
                    new TextField('SignUpHeader', 'Sign up header (e.g. sign up now)'),
                    new HTMLEditorField('SignUpIntro', 'Sign up form intro (e.g. sign up for our monthly newsletter ...'),
                    new TextField('SignUpButtonLabel', 'Sign up button label for start form (e.g. register now)')
                ),
                new Tab(
                    'Newsletters',
                    new CheckboxField('ShowOldNewsletters', 'Show old newsletters? Set to "NO" to remove all old newsletters links to this page. Set to "YES" to retrieve all old newsletters.'),
                    new LiteralField('CampaignExplanation', '<h3>Unfortunately, newsletter lists are not automatically linked to individual newsletters, you can link them here...</h3>'),
                    new CheckboxSetField('CampaignMonitorCampaigns', 'Newsletters shown', CampaignMonitorCampaign::get()->filter('HasBeenSent', 1)->limit(10000)->map()->toArray()),
                    $campaignField,
                    $gridFieldTemplatesAvailable
                ),
                new Tab(
                    'Advanced',
                    new LiteralField('MyControllerTest', '<h3><a href="' . $testControllerLink . '">Test Connections</a></h3>'),
                    new LiteralField('MyStats', '<h3><a href="' . $this->Link('stats') . '">Stats and Debug information</a></h3>'),
                    new LiteralField('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'),
                    new LiteralField('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>'),
                    $gridField = new GridField('Segments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordViewer::create()),
                    $gridField = new GridField('CustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordViewer::create()),
                    new LiteralField('GroupLink', $groupLink)
                )
            )
        );
        if (! Config::inst()->get('CampaignMonitorWrapper', 'campaign_monitor_url')) {
            //$fields->removeFieldFromTab("Root.Newsletters.Options", "CreateNewCampaign");
        }
        return $fields;
    }

    /**
     * @return CampaignMonitorAPIConnector
     */
    public function getAPI()
    {
        if (! self::$_api) {
            self::$_api = CampaignMonitorAPIConnector::create();
            self::$_api->init();
        }
        return self::$_api;
    }

    /**
     * you can add this function to other pages to have a form
     * that starts the basic after which the client needs to complete the rest.
     *
     * Or does a basic sign up if ajax submitted.
     *
     * @param Controller $controller
     * @param string $formName
     *
     * @return Form
     */
    public function CampaignMonitorStartForm(Controller $controller, $formName = 'CampaignMonitorStarterForm')
    {
        if ($email = Controller::curr()->getRequest()->getSession()->get('CampaignMonitorStartForm_AjaxResult_' . $this->ID)) {

            /**
             * ### @@@@ START REPLACEMENT @@@@ ###
             * WHY: automated upgrade
             * OLD: ->RenderWith( (ignore case)
             * NEW: ->RenderWith( (COMPLEX)
             * EXP: Check that the template location is still valid!
             * ### @@@@ STOP REPLACEMENT @@@@ ###
             */
            return $this->RenderWith('CampaignMonitorStartForm_AjaxResult', ['Email' => $email]);
        }
        Requirements::javascript('silverstripe/admin: thirdparty/jquery/jquery.js');
        //Requirements::javascript(THIRDPARTY_DIR . '/jquery-form/jquery.form.js');
        Requirements::javascript('sunnysideup/campaignmonitor: client/javascript/CampaignMonitorStartForm.js');
        if (! $this->ReadyToReceiveSubscribtions()) {
            //user_error("You first need to setup a Campaign Monitor Page for this function to work.", E_USER_NOTICE);
            return false;
        }
        $fields = new FieldList(new EmailField('CampaignMonitorEmail', _t('CAMPAIGNMONITORSIGNUPPAGE.EMAIL', 'Email')));
        $actions = new FieldList(new FormAction('campaignmonitorstarterformstartaction', $this->SignUpButtonLabel));
        $form = new Form(
            $controller,
            $formName,
            $fields,
            $actions
        );
        $form->setFormAction($this->Link('preloademail'));
        return $form;
    }

    /**
     * adds a subcriber to the list without worrying about making it a user ...
     *
     * @param string $email
     *
     * @returns
     */
    public function addSubscriber($email)
    {
        if ($this->ReadyToReceiveSubscribtions()) {
            $listID = $this->ListID;
            $email = Convert::raw2sql($email);
            $member = Member::get()->filter(['Email' => $email])->first();
            if ($member && $member->exists()) {
                //do nothing
            } else {
                $member = new Member();
                $member->Email = $email;
                //$member->SetPassword = true;
                //$member->Password = Member::create_new_password();
                $member->write();
            }
            if ($group = $this->Group()) {
                $group->Members()->add($member);
            }
            $api = $this->getAPI();
            $result = $api->addSubscriber($listID, $member);
            if ($result === $email) {
                return null;
            }
            return 'ERROR: could not subscribe';
        }
        return 'ERROR: not ready';
    }

    /**
     * name of the list connected to.
     * @return string
     */
    public function getListTitle()
    {
        if ($this->ListID) {
            $a = $this->makeDropdownListFromLists();
            if (isset($a[$this->ListID])) {
                return $a[$this->ListID];
            }
        }
        return '';
    }

    /**
     * tells us if the page is ready to receive subscriptions
     * @return boolean
     */
    public function ReadyToReceiveSubscribtions()
    {
        return $this->ListID && $this->GroupID;
    }

    /**
     * check list and group IDs
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        //check list
        if (! $this->getListTitle()) {
            $this->ListID = 0;
        }
        $gp = null;
        //check group
        if ($this->GroupID) {
            $gp = $this->Group();
            if (! $gp || ! $gp->exists()) {
                $this->GroupID = 0;
            }
        }

        //add group
        if ($this->ListID) {
            if (! $this->GroupID) {
                $gp = new Group();
                $this->GroupID = $gp->ID;
                $gp->write();
            }
            $title = _t('CampaignMonitor.NEWSLETTER', 'NEWSLETTER');
            if ($myListName = $this->getListTitle()) {
                $title .= ': ' . $myListName;
            }
            $gp->Title = (string) $title;
            $gp->write();
        }
        if ($gp) {
            $this->GroupID = $gp->ID;
        }
    }

    /**
     * add old campaings or remove them
     * depending on the setting
     *
     * add / remove segments ...
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->ShowOldNewsletters) {
            $this->AddOldCampaigns();
        } else {
            $this->CampaignMonitorCampaigns()->filter(['HasBeenSent' => 1])->removeAll();
        }
        //add segments
        $segmentsAdded = [];
        $segments = $this->api->getSegments($this->ListID);
        if ($segments && is_array($segments) && count($segments)) {
            foreach ($segments as $segment) {
                $segmentsAdded[$segment->SegmentID] = $segment->SegmentID;
                $filterArray = ['SegmentID' => $segment->SegmentID, 'ListID' => $this->ListID, 'CampaignMonitorSignupPageID' => $this->ID];
                $obj = CampaignMonitorSegment::get()->filter($filterArray)->first();
                if (! $obj) {
                    $obj = CampaignMonitorSegment::create($filterArray);
                }
                $obj->Title = $segment->Title;
                $obj->write();
            }
        }
        $unwantedSegments = CampaignMonitorSegment::get()->filter(['ListID' => $this->ListID, 'CampaignMonitorSignupPageID' => $this->ID])
            ->exclude(['SegmentID' => $segmentsAdded]);
        foreach ($unwantedSegments as $unwantedSegment) {
            $unwantedSegment->delete();
        }
        //add custom fields
        $customCustomFieldsAdded = [];
        $customCustomFields = $this->api->getListCustomFields($this->ListID);
        if ($customCustomFields && is_array($customCustomFields) && count($customCustomFields)) {
            foreach ($customCustomFields as $customCustomField) {
                $obj = CampaignMonitorCustomField::create_from_campaign_monitor_object($customCustomField, $this->ListID);
                $customCustomFieldsAdded[$obj->Code] = $obj->Code;
            }
        }
        $unwantedCustomFields = CampaignMonitorCustomField::get()->filter(['ListID' => $this->ListID, 'CampaignMonitorSignupPageID' => $this->ID])
            ->exclude(['Code' => $customCustomFieldsAdded]);
        foreach ($unwantedCustomFields as $unwantedCustomField) {
            $unwantedCustomField->delete();
        }
    }

    public function AddOldCampaigns()
    {
        $task = CampaignMonitorAddOldCampaigns::create();
        $task->setVerbose(false);
        $task->run(null);
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $update = [];
        $page = CampaignMonitorSignupPage::get()->First();

        if ($page) {
            if (! $page->SignUpHeader) {
                $page->SignUpHeader = 'Sign Up Now';
                $update[] = 'created default entry for SignUpHeader';
            }
            if (strlen($page->SignUpIntro) < strlen('<p> </p>')) {
                $page->SignUpIntro = '<p>Enter your email to sign up for our newsletter</p>';
                $update[] = 'created default entry for SignUpIntro';
            }
            if (! $page->SignUpButtonLabel) {
                $page->SignUpButtonLabel = 'Register Now';
                $update[] = 'created default entry for SignUpButtonLabel';
            }
            if (count($update)) {
                $page->writeToStage('Stage');
                $page->publish('Stage', 'Live');
                DB::alteration_message($page->ClassName . ' created/updated: ' . implode(' --- ', $update), 'created');
            }
        }
    }

    /**
     * returns available list for client
     * @return array
     */
    protected function makeDropdownListFromLists()
    {
        if (! isset(self::$drop_down_list[$this->ID])) {
            $array = [];
            $api = $this->getAPI();
            $lists = $api->getLists();
            if (is_array($lists) && count($lists)) {
                foreach ($lists as $list) {
                    $array[$list->ListID] = $list->Name;
                }
            }
            //remove subscription list IDs from other pages
            $subscribePages = CampaignMonitorSignupPage::get()
                ->exclude('ID', $this->ID);
            foreach ($subscribePages as $page) {
                if (isset($array[$page->ListID])) {
                    unset($array[$page->ListID]);
                }
            }
            self::$drop_down_list[$this->ID] = $array;
        }
        return self::$drop_down_list[$this->ID];
    }
}
