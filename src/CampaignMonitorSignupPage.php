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
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\View\Requirements;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\Control\CampaignMonitorAPIConnectorTestController;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaignStyle;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCustomField;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorSegment;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorSubscriptionLog;
use Sunnysideup\CampaignMonitor\Tasks\CampaignMonitorAddOldCampaigns;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;

/**
 * Page for Signing Up to Campaign Monitor List.
 *
 * Each page relates to one CM list.
 *
 * @author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorSignupPage extends Page
{
    use CampaignMonitorApiTrait;

    private static $controller_name = CampaignMonitorSignupPageController::class;

    /**
     * standard SS variable.
     *
     * @var string
     */
    private static $singular_name = 'Newsletter sign-up page';

    /**
     * standard SS variable.
     *
     * @var string
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
        'CloseSubscriptions' => 'Boolean',
        'MakeAllFieldsRequired' => 'Boolean',

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

        'ShowFirstNameFieldInForm' => 'Boolean',
        'ShowSurnameFieldInForm' => 'Boolean',

        'MustBeLoggedInToEditSubscription' => 'Boolean',
        'SignInNewMemberOnRegistration' => 'Boolean',
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
        'CampaignMonitorSubscriptionLogs' => CampaignMonitorSubscriptionLog::class,
    ];

    /**
     * @inherited
     */
    private static $belongs_many_many = [
        'CampaignMonitorCampaigns' => CampaignMonitorCampaign::class,
    ];

    private static $indexes = [
        'ListID' => true,
    ];

    private static $defaults = [
        'ShowFirstNameFieldInForm' => true,
        'ShowSurnameFieldInForm' => true,
    ];

    /**
     * @inherited
     */
    private static $description = 'Page to suscribe and review newsletter list(s)';

    /**
     * @var array
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
     * Campaign monitor pages that are ready to receive "sign-ups".
     *
     * @return \SilverStripe\ORM\DataList
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

        $fields->addFieldToTab(
            'Root.Log',
            GridField::create(
                'CampaignMonitorSubscriptionLogs',
                'Logs',
                $this->CampaignMonitorSubscriptionLogs(),
                GridFieldConfig_RelationEditor::create()
            ),
        );

        if ($this->GroupID) {
            $groupLink = '<h2><a href="/admin/security/EditForm/field/Groups/item/' . $this->GroupID . '/edit">Open Related Security Group</a></h2>';
        } else {
            $groupLink = '<p>No Group has been selected yet.</p>';
        }
        $testControllerLink = Injector::inst()->get(CampaignMonitorAPIConnectorTestController::class)->Link();
        $campaignExample = CampaignMonitorCampaign::get()->Last();
        if ($campaignExample && $campaignExample->CampaignID) {
            $campaignExampleLink = $this->Link('viewcampaign/' . $campaignExample->CampaignID);
        } else {
            $campaignExampleLink = 'error-not-available';
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
            'Root.SubscriptionFeedback',
            new TabSet(
                'AlternativeContentSubHeader',
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
                ),
                new Tab(
                    'Updating Subscription',
                    new LiteralField('ConfirmExplanation', '<p>Thank you for updating your subscription</p>'),
                    new TextField('ConfirmTitle', 'Title'),
                    new TextField('ConfirmMenuTitle', 'Menu Title'),
                    new HTMLEditorField('ConfirmMessage', 'Message (e.g. thank you for confirming)')
                ),
            )
        );
        $fields->addFieldToTab(
            'Root.Newsletters',
            new TabSet(
                'Options',
                new Tab(
                    'MainSettings',
                    new LiteralField('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                    new DropdownField('ListID', 'Related List from Campaign Monitor (*)', [0 => '-- please select --'] + $this->makeDropdownListFromLists()),
                    new ReadonlyField('ListIDNice', 'List ID', $this->ListID),
                    new CheckboxField('SignInNewMemberOnRegistration', 'Sign-in newly created user on registration?'),
                    new CheckboxField('MustBeLoggedInToEditSubscription', 'User must be logged in to edit their registations?'),
                    new CheckboxField('MakeAllFieldsRequired', 'Make all fields mandatory'),
                    new CheckboxField('ShowFirstNameFieldInForm', 'Show First Name Field in form?'),
                    new CheckboxField('ShowSurnameFieldInForm', 'Show Surname Field in form?'),
                    new CheckboxField('ShowAllNewsletterForSigningUp', 'Allow users to sign up to all lists'),
                    new CheckboxField('CloseSubscriptions', 'Close subscription'),
                ),
                new Tab(
                    'StartForm',
                    new LiteralField('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they go through to another page (the actual CampaignMonitorSignupPage) to complete all the details.'),
                    new TextField('SignUpHeader', 'Sign up header (e.g. sign up now)'),
                    new HTMLEditorField('SignUpIntro', 'Sign up form intro (e.g. sign up for our monthly newsletter ...'),
                    new TextField('SignUpButtonLabel', 'Sign up button label for start form (e.g. register now)')
                ),
                new Tab(
                    'Campaigns',
                    new LiteralField('CreateNewCampaign', '<p>To create a new mail out go to <a href="' . Config::inst()->get(CampaignMonitorAPIConnector::class, 'campaign_monitor_url') . '">Campaign Monitor</a> site.</p>'),
                    new CheckboxField('ShowOldNewsletters', 'Show old newsletters? Set to "NO" to remove all old newsletters links to this page. Set to "YES" to retrieve all old newsletters.'),
                    new LiteralField('CampaignExplanation', '<h3>Unfortunately, newsletter lists are not automatically linked to individual newsletters, you can link them here...</h3>'),
                    new CheckboxSetField('CampaignMonitorCampaigns', 'Newsletters shown', CampaignMonitorCampaign::get()->filter('HasBeenSent', 1)->limit(500)->map()->toArray()),
                    $campaignField,
                    $gridFieldTemplatesAvailable,
                    new LiteralField('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'),
                    new LiteralField('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>'),
                ),
                new Tab(
                    'Segments',
                    new GridField('Segments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordViewer::create()),
                ),
                new Tab(
                    'CustomFields',
                    new GridField('CustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordViewer::create()),
                ),
                new Tab(
                    'Advanced',
                    new LiteralField('GroupLink', $groupLink),
                    new LiteralField('MyControllerTest', '<h3><a href="' . $testControllerLink . '">Test Connections</a></h3>'),
                    new LiteralField('MyStats', '<h3><a href="' . $this->Link('stats') . '">Stats and Debug information</a></h3>'),
                    new LiteralField('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'),
                    new LiteralField('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>'),
                )
            )
        );
        if (false === $this->HasCampaigns()) {
            $fields->removeByName([
                'MyCampaignReset',
                'MyCampaignInfo',
                'Campaigns',
            ]);
        }
        if (! Config::inst()->get(CampaignMonitorAPIConnector::class, 'campaign_monitor_url')) {
            $fields->removeByName('CreateNewCampaign');
        }

        return $fields;
    }

    public function HasCampaigns(): bool
    {
        return CampaignMonitorCampaign::get()->filter(['HasBeenSent' => true])->exists();
    }

    /**
     * you can add this function to other pages to have a form
     * that starts the basic after which the client needs to complete the rest.
     *
     * Or does a basic sign up if ajax submitted.
     *
     * @return null|DBHTMLText|Form
     */
    public function CampaignMonitorStartForm(Controller $controller, ?string $formName = 'CampaignMonitorStarterForm')
    {
        if ($email = Controller::curr()->getRequest()->getSession()->get('CampaignMonitorStartForm_AjaxResult_' . $this->ID)) {
            // @return DBHTMLText
            return $this->RenderWith('Sunnysideup\CampaignMonitor\Includes\CampaignMonitorStartForm_AjaxResult', ['Email' => $email]);
        }
        Requirements::javascript('silverstripe/admin: thirdparty/jquery/jquery.js');
        //Requirements::javascript(THIRDPARTY_DIR . '/jquery-form/jquery.form.js');
        Requirements::javascript('sunnysideup/campaignmonitor: client/javascript/CampaignMonitorStartForm.js');
        if (! $this->ReadyToReceiveSubscribtions()) {
            //user_error("You first need to setup a Campaign Monitor Page for this function to work.", E_USER_NOTICE);
            return null;
        }
        $fields = FieldList::create(EmailField::create('CampaignMonitorEmail', _t('CAMPAIGNMONITORSIGNUPPAGE.EMAIL', 'Email')));
        $actions = FieldList::create(FormAction::create('campaignmonitorstarterformstartaction', $this->SignUpButtonLabel));
        $form = Form::create(
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
     * @returns
     */
    public function addSubscriber(string $email)
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
            $api = $this->getCMAPI();
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
     */
    public function getListTitle(): string
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
     * tells us if the page is ready to receive subscriptions.
     */
    public function ReadyToReceiveSubscribtions(): bool
    {
        if ($this->CloseSubscriptions) {
            return false;
        }

        return $this->ListID && $this->GroupID;
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
     * check list and group IDs.
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        //check list
        if (! $this->getListTitle()) {
            $this->ListID = 0;
        }
        $this->addOrRemoveGroup();
    }

    /**
     * add old campaings or remove them
     * depending on the setting.
     *
     * add / remove segments ...
     */
    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->ShowOldNewsletters) {
            $this->AddOldCampaigns();
        } else {
            $this->CampaignMonitorCampaigns()->filter(['HasBeenSent' => 1])->removeAll();
        }
        // //add segments
        $segmentsAdded = [];
        $segments = $this->getCMAPI()->getSegments($this->ListID);
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
        if (count($segmentsAdded)) {
            $unwantedSegments = CampaignMonitorSegment::get()->filter(['ListID' => $this->ListID, 'CampaignMonitorSignupPageID' => $this->ID])
                ->exclude(['SegmentID' => $segmentsAdded])
            ;
            foreach ($unwantedSegments as $unwantedSegment) {
                $unwantedSegment->delete();
            }
        }
        // //add custom fields
        $customCustomFieldsAdded = [];
        $customCustomFields = $this->getCMAPI()->getListCustomFields($this->ListID);
        if ($customCustomFields && is_array($customCustomFields) && count($customCustomFields)) {
            foreach ($customCustomFields as $customCustomField) {
                $obj = CampaignMonitorCustomField::create_from_campaign_monitor_object($customCustomField, $this->ListID);
                $customCustomFieldsAdded[$obj->Code] = $obj->Code;
            }
        }
        if (count($customCustomFieldsAdded)) {
            $unwantedCustomFields = CampaignMonitorCustomField::get()->filter(['ListID' => $this->ListID, 'CampaignMonitorSignupPageID' => $this->ID])
                ->exclude(['Code' => $customCustomFieldsAdded])
            ;
            foreach ($unwantedCustomFields as $unwantedCustomField) {
                $unwantedCustomField->delete();
            }
        }
    }

    protected function addOrRemoveGroup()
    {
        $gp = null;
        //check group
        if ($this->GroupID) {
            $gp = $this->Group();
            if (! ($gp && $gp->exists())) {
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
     * returns available list for client.
     *
     * @return array
     */
    protected function makeDropdownListFromLists()
    {
        if (! isset(self::$drop_down_list[$this->ID])) {
            $array = [];
            $api = $this->getCMAPI();
            $lists = $api->getLists();
            if (is_array($lists) && count($lists)) {
                foreach ($lists as $list) {
                    $array[$list->ListID] = $list->Name;
                }
            }
            //remove subscription list IDs from other pages
            $subscribePages = CampaignMonitorSignupPage::get()
                ->exclude('ID', $this->ID)
            ;
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
