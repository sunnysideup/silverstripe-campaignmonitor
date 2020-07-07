<?php

namespace Sunnysideup\CampaignMonitor;

use Page;































use SilverStripe\Security\Group;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorSegment;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCustomField;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\CampaignMonitor\Control\CampaignMonitorAPIConnector_TestController;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\HiddenField;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaignStyle;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Core\Config\Config;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Control\Controller;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Form;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Member;
use Sunnysideup\CampaignMonitor\Tasks\CampaignMonitorAddOldCampaigns;
use SilverStripe\ORM\DB;



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
     * standard SS variable
     * @Var String
     */
    private static $singular_name = "Newsletter sign-up page";
    public function i18n_singular_name()
    {
        return _t("AccountPage.NEWSLETTER_PAGE", "Newsletter sign-up page");
    }

    /**
     * standard SS variable
     * @Var String
     */
    private static $plural_name = "Newsletter sign-up pages";
    public function i18n_plural_name()
    {
        return _t("AccountPage.NEWSLETTER_PAGE", "Newsletter sign-up pages");
    }

    /**
     *
     * @inherited
     */
    private static $icon = "campaignmonitor/images/treeicons/CampaignMonitorSignupPage";

    /**
     *
     * @inherited
     */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * OLD: private static $db (case sensitive)
  * NEW: 
    private static $table_name = '[SEARCH_REPLACE_CLASS_NAME_GOES_HERE]';

    private static $db (COMPLEX)
  * EXP: Check that is class indeed extends DataObject and that it is not a data-extension!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    
    private static $table_name = 'CampaignMonitorSignupPage';

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
        'ShowAllNewsletterForSigningUp' => 'Boolean',

    );

    /**
     *
     * @inherited
     */
    private static $has_one = array(
        "Group" => Group::class
    );

    /**
     *
     * @inherited
     */
    private static $has_many = array(
        "CampaignMonitorSegments" => CampaignMonitorSegment::class,
        "CampaignMonitorCustomFields" => CampaignMonitorCustomField::class
    );

    /**
     *
     * @inherited
     */
    private static $belongs_many_many = array(
        "CampaignMonitorCampaigns" => CampaignMonitorCampaign::class
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
    protected static $_api = null;


    /**
     * Campaign monitor pages that are ready to receive "sign-ups"
     * @return ArrayList
     */
    public static function get_ready_ones()
    {
        $listPages = CampaignMonitorSignupPage::get();
        $array = array(0 => 0);
        foreach ($listPages as $listPage) {
            if ($listPage->ReadyToReceiveSubscribtions()) {
                $array[$listPage->ID] = $listPage->ID;
            }
        }
        return CampaignMonitorSignupPage::get()->filter(array("ID" => $array));
    }

    /**
     *
     * @inherited
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        if ($this->GroupID) {
            $groupLink = '<h2><a href="/admin/security/EditForm/field/Groups/item/'.$this->GroupID.'/edit">open related security group</a></h2>';
        } else {
            $groupLink = '<p>No Group has been selected yet.</p>';
        }
        $testControllerLink = Injector::inst()->get(CampaignMonitorAPIConnector_TestController::class)->Link();
        $campaignExample = CampaignMonitorCampaign::get()->Last();
        $campaignExampleLink = $this->Link();
        if ($campaignExample) {
            $campaignExampleLink = $this->Link("viewcampaign/".$campaignExample->CampaignID);
        }
        if ($this->ID) {
            $config = GridFieldConfig_RelationEditor::create();
            $campaignField = new GridField('CampaignList', 'Campaigns', $this->CampaignMonitorCampaigns(), $config);
        } else {
            $campaignField = new HiddenField("CampaignList");
        }
        $gridFieldTemplatesAvailable = new GridField('TemplatesAvailable', 'Templates Available', CampaignMonitorCampaignStyle::get(), GridFieldConfig_RecordEditor::create());
        $gridFieldTemplatesAvailable->setDescription("Ask your developer on how to add more templates");
        $fields->addFieldToTab(
            'Root.AlternativeContent',
            new TabSet(
                "AlternativeContentSubHeader",
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
                    new LiteralField('CreateNewCampaign', '<p>To create a new mail out go to <a href="'. Config::inst()->get(CampaignMonitorAPIConnector::class, "campaign_monitor_url") .'">Campaign Monitor</a> site.</p>'),
                    new LiteralField('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                    new DropdownField('ListID', 'Related List from Campaign Monitor (*)', array(0 => "-- please select --") + $this->makeDropdownListFromLists()),
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
                    new CheckboxSetField('CampaignMonitorCampaigns', 'Newsletters shown', CampaignMonitorCampaign::get()->filter("HasBeenSent", 1)->limit(10000)->map()->toArray()),
                    $campaignField,
                    $gridFieldTemplatesAvailable
                ),
                new Tab(
                    'Advanced',
                    new LiteralField('MyControllerTest', '<h3><a href="'.$testControllerLink.'">Test Connections</a></h3>'),
                    new LiteralField('MyStats', '<h3><a href="'.$this->Link("stats").'">Stats and Debug information</a></h3>'),
                    new LiteralField('MyCampaignReset', '<h3><a href="'.$this->Link("resetoldcampaigns").'">Delete All Campaigns from Website</a></h3>'),
                    new LiteralField('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="'.$campaignExampleLink.'">an example</a></h3>'),
                    $gridField = new GridField('Segments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordViewer::create()),
                    $gridField = new GridField('CustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordViewer::create()),
                    new LiteralField('GroupLink', $groupLink)
                )
            )
        );
        if (!Config::inst()->get("CampaignMonitorWrapper", "campaign_monitor_url")) {
            //$fields->removeFieldFromTab("Root.Newsletters.Options", "CreateNewCampaign");
        }
        return $fields;
    }

    /**
     *
     * @return CampaignMonitorAPIConnector
     */
    public function getAPI()
    {
        if (!self::$_api) {
            self::$_api = CampaignMonitorAPIConnector::create();
            self::$_api->init();
        }
        return self::$_api;
    }

    /**
     *
     * @var Null | Array
     */
    private static $drop_down_list = [];

    /**
     * returns available list for client
     * @return array
     */
    protected function makeDropdownListFromLists()
    {
        if (!isset(self::$drop_down_list[$this->ID])) {
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
                ->exclude("ID", $this->ID);
            foreach ($subscribePages as $page) {
                if (isset($array[$page->ListID])) {
                    unset($array[$page->ListID]);
                }
            }
            self::$drop_down_list[$this->ID] = $array;
        }
        return self::$drop_down_list[$this->ID];
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
    public function CampaignMonitorStartForm(Controller $controller, $formName = "CampaignMonitorStarterForm")
    {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: Session:: (case sensitive)
  * NEW: Controller::curr()->getRequest()->getSession()-> (COMPLEX)
  * EXP: If THIS is a controller than you can write: $this->getRequest(). You can also try to access the HTTPRequest directly. 
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
        if ($email = Controller::curr()->getRequest()->getSession()->get("CampaignMonitorStartForm_AjaxResult_".$this->ID)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: ->RenderWith( (ignore case)
  * NEW: ->RenderWith( (COMPLEX)
  * EXP: Check that the template location is still valid!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            return $this->RenderWith("CampaignMonitorStartForm_AjaxResult", array("Email" => $email));
        } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: THIRDPARTY_DIR . '/jquery/jquery.js' (case sensitive)
  * NEW: 'silverstripe/admin: thirdparty/jquery/jquery.js' (COMPLEX)
  * EXP: Check for best usage and inclusion of Jquery
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            Requirements::javascript('sunnysideup/campaignmonitor: silverstripe/admin: thirdparty/jquery/jquery.js');
            //Requirements::javascript(THIRDPARTY_DIR . '/jquery-form/jquery.form.js');
            Requirements::javascript(SS_CAMPAIGNMONITOR_DIR . '/javascript/CampaignMonitorStartForm.js');
            if (!$this->ReadyToReceiveSubscribtions()) {
                //user_error("You first need to setup a Campaign Monitor Page for this function to work.", E_USER_NOTICE);
                return false;
            }
            $fields = new FieldList(new EmailField("CampaignMonitorEmail", _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", Email::class)));
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
    public function addSubscriber($email)
    {
        if ($this->ReadyToReceiveSubscribtions()) {
            $listID = $this->ListID;
            $email = Convert::raw2sql($email);
            $member = Member::get()->filter(array("Email" => $email))->first();
            if ($member && $member->exists()) {
                //do nothing
            } else {
                $member = new Member();
                $member->Email = $email;
                $member->SetPassword = true;

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: create_new_password (ignore case)
  * NEW: create_new_password (COMPLEX)
  * EXP: This is depracated in SS4: https://github.com/silverstripe/silverstripe-framework/commit/f16d7e1838d834575738086326d1191db3a5cfd8, consider if there is a better way to implement this functionality
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $member->Password = Member::create_new_password();
                $member->write();
            }
            if ($group = $this->Group()) {
                $group->Members()->add($member);
            }
            $api = $this->getAPI();
            $result = $api->addSubscriber($listID, $member);
            if ($result == $email) {
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
    public function getListTitle()
    {
        if ($this->ListID) {
            $a = $this->makeDropdownListFromLists();
            if (isset($a[$this->ListID])) {
                return $a[$this->ListID];
            }
        }
        return "";
    }

    /**
     * tells us if the page is ready to receive subscriptions
     * @return Boolean
     */
    public function ReadyToReceiveSubscribtions()
    {
        return $this->ListID && $this->GroupID;
    }

    /**
     * check list and group IDs
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        //check list
        if (!$this->getListTitle()) {
            $this->ListID = 0;
        }
        $gp = null;
        //check group
        if ($this->GroupID) {
            $gp = $this->Group();
            if (!$gp || !$gp->exists()) {
                $this->GroupID = 0;
            }
        }

        //add group
        if ($this->ListID) {
            if (!$this->GroupID) {
                $gp = new Group();
                $this->GroupID = $gp->ID;
                $gp->write();
            }
            $title = _t("CampaignMonitor.NEWSLETTER", "NEWSLETTER");
            if ($myListName = $this->getListTitle()) {
                $title .= ": ".$myListName;
            }
            $gp->Title = (string)$title;
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
     *
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->ShowOldNewsletters) {
            $this->AddOldCampaigns();
        } else {
            $this->CampaignMonitorCampaigns()->filter(array("HasBeenSent" => 1))->removeAll();
        }
        //add segments
        $segmentsAdded = [];
        $segments = $this->api->getSegments($this->ListID);
        if ($segments && is_array($segments) && count($segments)) {
            foreach ($segments as $segment) {
                $segmentsAdded[$segment->SegmentID] = $segment->SegmentID;
                $filterArray = array("SegmentID" => $segment->SegmentID, "ListID" => $this->ListID, "CampaignMonitorSignupPageID" => $this->ID);
                $obj = CampaignMonitorSegment::get()->filter($filterArray)->first();
                if (!$obj) {
                    $obj = CampaignMonitorSegment::create($filterArray);
                }
                $obj->Title = $segment->Title;
                $obj->write();
            }
        }
        $unwantedSegments = CampaignMonitorSegment::get()->filter(array("ListID" => $this->ListID, "CampaignMonitorSignupPageID" => $this->ID))
            ->exclude(array("SegmentID" => $segmentsAdded));
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
        $unwantedCustomFields = CampaignMonitorCustomField::get()->filter(array("ListID" => $this->ListID, "CampaignMonitorSignupPageID" => $this->ID))
            ->exclude(array("Code" => $customCustomFieldsAdded));
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
            if (!$page->SignUpHeader) {
                $page->SignUpHeader = 'Sign Up Now';
                $update[]= "created default entry for SignUpHeader";
            }
            if (strlen($page->SignUpIntro) < strlen("<p> </p>")) {
                $page->SignUpIntro = '<p>Enter your email to sign up for our newsletter</p>';
                $update[]= "created default entry for SignUpIntro";
            }
            if (!$page->SignUpButtonLabel) {
                $page->SignUpButtonLabel = 'Register Now';
                $update[]= "created default entry for SignUpButtonLabel";
            }
            if (count($update)) {
                $page->writeToStage('Stage');
                $page->publish('Stage', 'Live');
                DB::alteration_message($page->ClassName." created/updated: ".implode(" --- ", $update), 'created');
            }
        }
    }
}

