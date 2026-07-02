<?php

namespace Sunnysideup\CampaignMonitor;

use Override;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\ArrayInput;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ManyManyList;
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
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use Exception;

/**
 * Page for Signing Up to Campaign Monitor List.
 *
 * Each page relates to one CM list.
 *
 * @property bool $CloseSubscriptions
 * @property bool $MakeAllFieldsRequired
 * @property string $ListID
 * @property string $ConfirmTitle
 * @property string $ConfirmMenuTitle
 * @property string $ConfirmMessage
 * @property string $ThankYouTitle
 * @property string $ThankYouMenuTitle
 * @property string $ThankYouMessage
 * @property string $SadToSeeYouGoTitle
 * @property string $SadToSeeYouGoMenuTitle
 * @property string $SadToSeeYouGoMessage
 * @property string $SignUpHeader
 * @property string $SignUpIntro
 * @property string $SignUpButtonLabel
 * @property bool $ShowOldNewsletters
 * @property bool $ShowAllNewsletterForSigningUp
 * @property bool $ShowFirstNameFieldInForm
 * @property bool $ShowSurnameFieldInForm
 * @property bool $ShowPermissionToTrackFieldInForm
 * @property string $PermissionToTrackLabelField
 * @property bool $MustBeLoggedInToEditSubscription
 * @property bool $SignInNewMemberOnRegistration
 * @property int $GroupID
 * @method Group Group()
 * @method DataList|CampaignMonitorSegment[] CampaignMonitorSegments()
 * @method DataList|CampaignMonitorCustomField[] CampaignMonitorCustomFields()
 * @method DataList|CampaignMonitorSubscriptionLog[] CampaignMonitorSubscriptionLogs()
 * @method ManyManyList|CampaignMonitorCampaign[] CampaignMonitorCampaigns()
 */
class CampaignMonitorSignupPage extends Page
{
    use CampaignMonitorApiTrait;

    private static array $scaffold_cms_fields_settings = [
        'ignoreFields' => [
            'ListID',
            'SignInNewMemberOnRegistration',
            'MustBeLoggedInToEditSubscription',
            'MakeAllFieldsRequired',
            'ShowListNameInSubscribeToField',
            'AllowUnsubscribeInForm',
            'ShowFirstNameFieldInForm',
            'ShowSurnameFieldInForm',
            'ShowPermissionToTrackFieldInForm',
            'PermissionToTrackLabelField',
            'ShowAllNewsletterForSigningUp',
            'CloseSubscriptions',
            'ThankYouTitle',
            'ThankYouMenuTitle',
            'ThankYouMessage',
            'SadToSeeYouGoTitle',
            'SadToSeeYouGoMenuTitle',
            'SadToSeeYouGoMessage',
            'ConfirmTitle',
            'ConfirmMenuTitle',
            'ConfirmMessage',
            'SignUpHeader',
            'SignUpIntro',
            'SignUpButtonLabel',
            'ShowOldNewsletters',
        ],
        'ignoreRelations' => [
            'CampaignMonitorSegments',
            'CampaignMonitorCustomFields',
            'CampaignMonitorCampaigns',
            'CampaignMonitorSubscriptionLogs',
            'Group',
        ],
    ];

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
    private static $cms_icon = 'sunnysideup/campaignmonitor: client/images/treeicons/CampaignMonitorSignupPage-file.gif';

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

        'ShowListNameInSubscribeToField' => 'Boolean(0)',
        'AllowUnsubscribeInForm' => 'Boolean(1)',
        'ShowFirstNameFieldInForm' => 'Boolean',
        'ShowSurnameFieldInForm' => 'Boolean',
        'ShowPermissionToTrackFieldInForm' => 'Boolean',
        'PermissionToTrackLabelField' => 'HTMLText',

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
        'ShowListNameInSubscribeToField' => true,
        'AllowUnsubscribeInForm' => true,
        'ShowFirstNameFieldInForm' => true,
        'ShowSurnameFieldInForm' => true,
        'ShowPermissionToTrackFieldInForm' => false,
    ];

    /**
     * @inherited
     */
    private static $class_description = 'Page to suscribe and review newsletter list(s)';

    /**
     * @var array
     */
    private static $drop_down_list = [];

    #[Override]
    public function i18n_singular_name()
    {
        return _t('AccountPage.NEWSLETTER_PAGE', 'Newsletter sign-up page');
    }

    #[Override]
    public function plural_name()
    {
        return _t('AccountPage.NEWSLETTER_PAGE', 'Newsletter sign-up pages');
    }

    /**
     * Campaign monitor pages that are ready to receive "sign-ups".
     *
     * @return DataList
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
    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName([
            'CampaignMonitorSegments',
            'CampaignMonitorCustomFields',
            'CampaignMonitorSubscriptionLogs',
            'CampaignMonitorCampaigns',
            'Group',
        ]);
        $fields->addFieldsToTab(
            'Root.Log',
            [
                GridField::create(
                    'CampaignMonitorSubscriptionLogs',
                    'Logs',
                    $this->CampaignMonitorSubscriptionLogs(),
                    GridFieldConfig_RecordViewer::create()
                ),
            ]
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
            $campaignField = GridField::create('CampaignList', 'Campaigns', $this->CampaignMonitorCampaigns(), $config);
        } else {
            $campaignField = HiddenField::create('CampaignList');
        }

        $gridFieldTemplatesAvailable = GridField::create('TemplatesAvailable', 'Templates Available', CampaignMonitorCampaignStyle::get(), GridFieldConfig_RecordEditor::create());
        $gridFieldTemplatesAvailable->setDescription('Ask your developer on how to add more templates');
        $fields->addFieldsToTab(
            'Root.SubscriptionFeedback',
            [
                TabSet::create(
                    'AlternativeContentSubHeader',
                    Tab::create(
                        'ThankYou',
                        $fields->dataFieldByName('ThankYouTitle'),
                        $fields->dataFieldByName('ThankYouMenuTitle'),
                        $fields->dataFieldByName('ThankYouMessage')
                    ),
                    Tab::create(
                        'SadToSeeYouGo',
                        $fields->dataFieldByName('SadToSeeYouGoTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMenuTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMessage')
                    ),
                    Tab::create(
                        'Updating Subscription',
                        LiteralField::create('ConfirmExplanation', '<p>Thank you for updating your subscription</p>'),
                        $fields->dataFieldByName('ConfirmTitle'),
                        $fields->dataFieldByName('ConfirmMenuTitle'),
                        $fields->dataFieldByName('ConfirmMessage')
                    )
                ),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Newsletters',
            [
                TabSet::create(
                    'Options',
                    Tab::create(
                        'MainSettings',
                        LiteralField::create('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                        DropdownField::create('ListID', 'Related List from Campaign Monitor (*)', [0 => '-- please select --'] + $this->makeDropdownListFromLists()),
                        ReadonlyField::create('ListIDNice', 'List ID', $this->ListID),
                        $fields->dataFieldByName('SignInNewMemberOnRegistration')->setTitle('Sign-in newly created user on registration?'),
                        $fields->dataFieldByName('MustBeLoggedInToEditSubscription')->setTitle('User must be logged in to edit their registations?'),
                        $fields->dataFieldByName('MakeAllFieldsRequired')->setTitle('Make all fields mandatory (except consent field)'),
                        $fields->dataFieldByName('ShowListNameInSubscribeToField')->setTitle('Show name of the list in the subscribe to field?'),
                        $fields->dataFieldByName('AllowUnsubscribeInForm')->setTitle('Allow unsubscribe in form?'),
                        $fields->dataFieldByName('ShowFirstNameFieldInForm')->setTitle('Show First Name Field in form?'),
                        $fields->dataFieldByName('ShowSurnameFieldInForm')->setTitle('Show Surname Field in form?'),
                        $fields->dataFieldByName('ShowPermissionToTrackFieldInForm')->setTitle('Show Consent checkbox?')->setDescription('if the Consent checkbox is not shown in the sign-up form - <i>Permission to track</i> for each new subscriber set to <b>Unknown (Unchanged)</b> by default. For more info, please check <a href="https://help.campaignmonitor.com/permission-to-track" target="_blank">this page</a>.'),
                        $fields->dataFieldByName('PermissionToTrackLabelField')->setTitle('Consent label')->setRows(2)->setDescription('HTML restriction: only link tag <b>&lt;a&gt;</b> allowed to be used in this field'),
                        $fields->dataFieldByName('ShowAllNewsletterForSigningUp')->setTitle('Allow users to sign up to all lists'),
                        $fields->dataFieldByName('CloseSubscriptions')->setTitle('Close subscription')
                    ),
                    Tab::create(
                        'StartForm',
                        LiteralField::create('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they will be redirected to the full form to enter more details.'),
                        $fields->dataFieldByName('SignUpHeader'),
                        $fields->dataFieldByName('SignUpIntro'),
                        $fields->dataFieldByName('SignUpButtonLabel')
                    ),
                    Tab::create(
                        'Shown on Site',
                        $fields->dataFieldByName('ShowOldNewsletters')->setTitle('Show previously sent Newsletters'),
                        $campaignField
                    ),
                    Tab::create('Templates', $gridFieldTemplatesAvailable),
                    Tab::create(
                        'Segments',
                        LiteralField::create('SegmentsExplanation', '<p>Segments are smart lists within your main list that are created in Campaign Monitor.  You can add them here for your own reference.</p>'),
                        GridField::create('MySegments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordEditor::create())
                    ),
                    Tab::create(
                        'CustomFields',
                        GridField::create('MyCustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordEditor::create())
                            ->addComponent(GridFieldSortableRows::create('SortOrder'))
                    ),
                    Tab::create(
                        'Advanced',
                        $fields->dataFieldByName('GroupID'),
                        LiteralField::create('GroupLink', $groupLink),
                        LiteralField::create('MyControllerTest', '<h3><a href="' . $testControllerLink . '">Test Connections</a></h3>'),
                        LiteralField::create('MyStats', '<h3><a href="' . $this->Link('stats') . '">Stats and Debug information</a></h3>'),
                        LiteralField::create('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'),
                        LiteralField::create('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>')
                    )
                ),
            ]
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
            $campaignField = GridField::create('CampaignList', 'Campaigns', $this->CampaignMonitorCampaigns(), $config);
        } else {
            $campaignField = HiddenField::create('CampaignList');
        }

        $gridFieldTemplatesAvailable = GridField::create('TemplatesAvailable', 'Templates Available', CampaignMonitorCampaignStyle::get(), GridFieldConfig_RecordEditor::create());
        $gridFieldTemplatesAvailable->setDescription('Ask your developer on how to add more templates');
        $fields->addFieldsToTab(
            'Root.SubscriptionFeedback',
            [
                TabSet::create(
                    'AlternativeContentSubHeader',
                    Tab::create('ThankYou',
                        $fields->dataFieldByName('ThankYouTitle'),
                        $fields->dataFieldByName('ThankYouMenuTitle'),
                        $fields->dataFieldByName('ThankYouMessage')
                    ),
                    Tab::create('SadToSeeYouGo',
                        $fields->dataFieldByName('SadToSeeYouGoTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMenuTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMessage')
                    ),
                    Tab::create('Updating Subscription',
                        LiteralField::create('ConfirmExplanation', '<p>Thank you for updating your subscription</p>'),
                        $fields->dataFieldByName('ConfirmTitle'),
                        $fields->dataFieldByName('ConfirmMenuTitle'),
                        $fields->dataFieldByName('ConfirmMessage')
                    )
                ),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Newsletters',
            [
                TabSet::create(
                    'Options',
                    Tab::create(
                        'MainSettings',
                        LiteralField::create('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                        DropdownField::create('ListID', 'Related List from Campaign Monitor (*)', [0 => '-- please select --'] + $this->makeDropdownListFromLists()),
                        ReadonlyField::create('ListIDNice', 'List ID', $this->ListID),
                        $fields->dataFieldByName('SignInNewMemberOnRegistration')->setTitle('Sign-in newly created user on registration?'),
                        $fields->dataFieldByName('MustBeLoggedInToEditSubscription')->setTitle('User must be logged in to edit their registations?'),
                        $fields->dataFieldByName('MakeAllFieldsRequired')->setTitle('Make all fields mandatory (except consent field)'),
                        $fields->dataFieldByName('ShowListNameInSubscribeToField')->setTitle('Show name of the list in the subscribe to field?'),
                        $fields->dataFieldByName('AllowUnsubscribeInForm')->setTitle('Allow unsubscribe in form?'),
                        $fields->dataFieldByName('ShowFirstNameFieldInForm')->setTitle('Show First Name Field in form?'),
                        $fields->dataFieldByName('ShowSurnameFieldInForm')->setTitle('Show Surname Field in form?'),
                        $fields->dataFieldByName('ShowPermissionToTrackFieldInForm')->setTitle('Show Consent checkbox?')->setDescription('if the Consent checkbox is not shown in the sign-up form - <i>Permission to track</i> for each new subscriber set to <b>Unknown (Unchanged)</b> by default. For more info, please check <a href="https://help.campaignmonitor.com/permission-to-track" target="_blank">this page</a>.'),
                        $fields->dataFieldByName('PermissionToTrackLabelField')->setTitle('Consent label')->setRows(2)->setDescription('HTML restriction: only link tag <b>&lt;a&gt;</b> allowed to be used in this field'),
                        $fields->dataFieldByName('ShowAllNewsletterForSigningUp')->setTitle('Allow users to sign up to all lists'),
                        $fields->dataFieldByName('CloseSubscriptions')->setTitle('Close subscription')
                    ),
                    Tab::create(
                        'StartForm',
                        LiteralField::create('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they will be redirected to the full form to enter more details.'),
                        $fields->dataFieldByName('SignUpHeader'),
                        $fields->dataFieldByName('SignUpIntro'),
                        $fields->dataFieldByName('SignUpButtonLabel')
                    ),
                    Tab::create(
                        'Shown on Site',
                        $fields->dataFieldByName('ShowOldNewsletters')->setTitle('Show previously sent Newsletters'),
                        $campaignField
                    ),
                    Tab::create('Templates', $gridFieldTemplatesAvailable),
                    Tab::create(
                        'Segments',
                        LiteralField::create('SegmentsExplanation', '<p>Segments are smart lists within your main list that are created in Campaign Monitor.  You can add them here for your own reference.</p>'),
                        GridField::create('MySegments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordEditor::create())
                    ),
                    Tab::create(
                        'CustomFields',
                        GridField::create('MyCustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordEditor::create())
                            ->addComponent(GridFieldSortableRows::create('SortOrder'))
                    ),
                    Tab::create(
                        'Advanced',
                        $fields->dataFieldByName('GroupID'),
                        LiteralField::create('GroupLink', $groupLink),
                        LiteralField::create('MyControllerTest', '<h3><a href="' . $testControllerLink . '">Test Connections</a></h3>'),
                        LiteralField::create('MyStats', '<h3><a href="' . $this->Link('stats') . '">Stats and Debug information</a></h3>'),
                        LiteralField::create('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'),
                        LiteralField::create('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>')
                    )
                ),
            ]
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
            $campaignField = GridField::create('CampaignList', 'Campaigns', $this->CampaignMonitorCampaigns(), $config);
        } else {
            $campaignField = HiddenField::create('CampaignList');
        }

        $gridFieldTemplatesAvailable = GridField::create('TemplatesAvailable', 'Templates Available', CampaignMonitorCampaignStyle::get(), GridFieldConfig_RecordEditor::create());
        $gridFieldTemplatesAvailable->setDescription('Ask your developer on how to add more templates');
        $fields->addFieldsToTab(
            'Root.SubscriptionFeedback',
            [
                TabSet::create(
                    'AlternativeContentSubHeader',
                    Tab::create('ThankYou',
                        $fields->dataFieldByName('ThankYouTitle'),
                        $fields->dataFieldByName('ThankYouMenuTitle'),
                        $fields->dataFieldByName('ThankYouMessage')
                    ),
                    Tab::create('SadToSeeYouGo',
                        $fields->dataFieldByName('SadToSeeYouGoTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMenuTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMessage')
                    ),
                    Tab::create('Updating Subscription',
                        LiteralField::create('ConfirmExplanation', '<p>Thank you for updating your subscription</p>'),
                        $fields->dataFieldByName('ConfirmTitle'),
                        $fields->dataFieldByName('ConfirmMenuTitle'),
                        $fields->dataFieldByName('ConfirmMessage')
                    )
                ),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Newsletters',
            [
                TabSet::create(
                    'Options',
                    Tab::create(
                        'MainSettings',
                        LiteralField::create('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                        DropdownField::create('ListID', 'Related List from Campaign Monitor (*)', [0 => '-- please select --'] + $this->makeDropdownListFromLists()),
                        ReadonlyField::create('ListIDNice', 'List ID', $this->ListID),
                        $fields->dataFieldByName('SignInNewMemberOnRegistration')->setTitle('Sign-in newly created user on registration?'),
                        $fields->dataFieldByName('MustBeLoggedInToEditSubscription')->setTitle('User must be logged in to edit their registations?'),
                        $fields->dataFieldByName('MakeAllFieldsRequired')->setTitle('Make all fields mandatory (except consent field)'),
                        $fields->dataFieldByName('ShowListNameInSubscribeToField')->setTitle('Show name of the list in the subscribe to field?'),
                        $fields->dataFieldByName('AllowUnsubscribeInForm')->setTitle('Allow unsubscribe in form?'),
                        $fields->dataFieldByName('ShowFirstNameFieldInForm')->setTitle('Show First Name Field in form?'),
                        $fields->dataFieldByName('ShowSurnameFieldInForm')->setTitle('Show Surname Field in form?'),
                        $fields->dataFieldByName('ShowPermissionToTrackFieldInForm')->setTitle('Show Consent checkbox?')->setDescription('if the Consent checkbox is not shown in the sign-up form - <i>Permission to track</i> for each new subscriber set to <b>Unknown (Unchanged)</b> by default. For more info, please check <a href="https://help.campaignmonitor.com/permission-to-track" target="_blank">this page</a>.'),
                        $fields->dataFieldByName('PermissionToTrackLabelField')->setTitle('Consent label')->setRows(2)->setDescription('HTML restriction: only link tag <b>&lt;a&gt;</b> allowed to be used in this field'),
                        $fields->dataFieldByName('ShowAllNewsletterForSigningUp')->setTitle('Allow users to sign up to all lists'),
                        $fields->dataFieldByName('CloseSubscriptions')->setTitle('Close subscription')
                    ),
                    Tab::create(
                        'StartForm',
                        LiteralField::create('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they will be redirected to the full form to enter more details.'),
                        $fields->dataFieldByName('SignUpHeader'),
                        $fields->dataFieldByName('SignUpIntro'),
                        $fields->dataFieldByName('SignUpButtonLabel')
                    ),
                    Tab::create(
                        'Shown on Site',
                        $fields->dataFieldByName('ShowOldNewsletters')->setTitle('Show previously sent Newsletters'),
                        $campaignField
                    ),
                    Tab::create('Templates', $gridFieldTemplatesAvailable),
                    Tab::create(
                        'Segments',
                        LiteralField::create('SegmentsExplanation', '<p>Segments are smart lists within your main list that are created in Campaign Monitor.  You can add them here for your own reference.</p>'),
                        GridField::create('MySegments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordEditor::create())
                    ),
                    Tab::create(
                        'CustomFields',
                        GridField::create('MyCustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordEditor::create())
                            ->addComponent(GridFieldSortableRows::create('SortOrder'))
                    ),
                    Tab::create(
                        'Advanced',
                        $fields->dataFieldByName('GroupID'),
                        LiteralField::create('GroupLink', $groupLink),
                        LiteralField::create('MyControllerTest', '<h3><a href="' . $testControllerLink . '">Test Connections</a></h3>'),
                        LiteralField::create('MyStats', '<h3><a href="' . $this->Link('stats') . '">Stats and Debug information</a></h3>'),
                        LiteralField::create('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'),
                        LiteralField::create('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>')
                    )
                ),
            ]
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
            $campaignField = GridField::create('CampaignList', 'Campaigns', $this->CampaignMonitorCampaigns(), $config);
        } else {
            $campaignField = HiddenField::create('CampaignList');
        }

        $gridFieldTemplatesAvailable = GridField::create('TemplatesAvailable', 'Templates Available', CampaignMonitorCampaignStyle::get(), GridFieldConfig_RecordEditor::create());
        $gridFieldTemplatesAvailable->setDescription('Ask your developer on how to add more templates');
        $fields->addFieldsToTab(
            'Root.SubscriptionFeedback',
            [
                TabSet::create(
                    'AlternativeContentSubHeader',
                    Tab::create('ThankYou',
                        $fields->dataFieldByName('ThankYouTitle'),
                        $fields->dataFieldByName('ThankYouMenuTitle'),
                        $fields->dataFieldByName('ThankYouMessage')
                    ),
                    Tab::create('SadToSeeYouGo',
                        $fields->dataFieldByName('SadToSeeYouGoTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMenuTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMessage')
                    ),
                    Tab::create('Updating Subscription',
                        LiteralField::create('ConfirmExplanation', '<p>Thank you for updating your subscription</p>'),
                        $fields->dataFieldByName('ConfirmTitle'),
                        $fields->dataFieldByName('ConfirmMenuTitle'),
                        $fields->dataFieldByName('ConfirmMessage')
                    )
                ),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Newsletters',
            [
                TabSet::create(
                    'Options',
                    Tab::create(
                        'MainSettings',
                        LiteralField::create('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                        DropdownField::create('ListID', 'Related List from Campaign Monitor (*)', [0 => '-- please select --'] + $this->makeDropdownListFromLists()),
                        ReadonlyField::create('ListIDNice', 'List ID', $this->ListID),
                        $fields->dataFieldByName('SignInNewMemberOnRegistration')->setTitle('Sign-in newly created user on registration?'),
                        $fields->dataFieldByName('MustBeLoggedInToEditSubscription')->setTitle('User must be logged in to edit their registations?'),
                        $fields->dataFieldByName('MakeAllFieldsRequired')->setTitle('Make all fields mandatory (except consent field)'),
                        $fields->dataFieldByName('ShowListNameInSubscribeToField')->setTitle('Show name of the list in the subscribe to field?'),
                        $fields->dataFieldByName('AllowUnsubscribeInForm')->setTitle('Allow unsubscribe in form?'),
                        $fields->dataFieldByName('ShowFirstNameFieldInForm')->setTitle('Show First Name Field in form?'),
                        $fields->dataFieldByName('ShowSurnameFieldInForm')->setTitle('Show Surname Field in form?'),
                        $fields->dataFieldByName('ShowPermissionToTrackFieldInForm')->setTitle('Show Consent checkbox?')->setDescription('if the Consent checkbox is not shown in the sign-up form - <i>Permission to track</i> for each new subscriber set to <b>Unknown (Unchanged)</b> by default. For more info, please check <a href="https://help.campaignmonitor.com/permission-to-track" target="_blank">this page</a>.'),
                        $fields->dataFieldByName('PermissionToTrackLabelField')->setTitle('Consent label')->setRows(2)->setDescription('HTML restriction: only link tag <b>&lt;a&gt;</b> allowed to be used in this field'),
                        $fields->dataFieldByName('ShowAllNewsletterForSigningUp')->setTitle('Allow users to sign up to all lists'),
                        $fields->dataFieldByName('CloseSubscriptions')->setTitle('Close subscription')
                    ),
                    Tab::create(
                        'StartForm',
                        LiteralField::create('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they will be redirected to the full form to enter more details.'),
                        $fields->dataFieldByName('SignUpHeader'),
                        $fields->dataFieldByName('SignUpIntro'),
                        $fields->dataFieldByName('SignUpButtonLabel')
                    ),
                    Tab::create(
                        'Shown on Site',
                        $fields->dataFieldByName('ShowOldNewsletters')->setTitle('Show previously sent Newsletters'),
                        $campaignField
                    ),
                    Tab::create('Templates', $gridFieldTemplatesAvailable),
                    Tab::create(
                        'Segments',
                        LiteralField::create('SegmentsExplanation', '<p>Segments are smart lists within your main list that are created in Campaign Monitor.  You can add them here for your own reference.</p>'),
                        GridField::create('MySegments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordEditor::create())
                    ),
                    Tab::create(
                        'CustomFields',
                        GridField::create('MyCustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordEditor::create())
                            ->addComponent(GridFieldSortableRows::create('SortOrder'))
                    ),
                    Tab::create(
                        'Advanced',
                        $fields->dataFieldByName('GroupID'),
                        LiteralField::create('GroupLink', $groupLink),
                        LiteralField::create('MyControllerTest', '<h3><a href="' . $testControllerLink . '">Test Connections</a></h3>'),
                        LiteralField::create('MyStats', '<h3><a href="' . $this->Link('stats') . '">Stats and Debug information</a></h3>'),
                        LiteralField::create('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'),
                        LiteralField::create('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>')
                    )
                ),
            ]
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
            $campaignField = GridField::create('CampaignList', 'Campaigns', $this->CampaignMonitorCampaigns(), $config);
        } else {
            $campaignField = HiddenField::create('CampaignList');
        }

        $gridFieldTemplatesAvailable = GridField::create('TemplatesAvailable', 'Templates Available', CampaignMonitorCampaignStyle::get(), GridFieldConfig_RecordEditor::create());
        $gridFieldTemplatesAvailable->setDescription('Ask your developer on how to add more templates');
        $fields->addFieldsToTab(
            'Root.SubscriptionFeedback',
            [
                TabSet::create(
                    'AlternativeContentSubHeader',
                    Tab::create('ThankYou',
                        $fields->dataFieldByName('ThankYouTitle'),
                        $fields->dataFieldByName('ThankYouMenuTitle'),
                        $fields->dataFieldByName('ThankYouMessage')
                    ),
                    Tab::create('SadToSeeYouGo',
                        $fields->dataFieldByName('SadToSeeYouGoTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMenuTitle'),
                        $fields->dataFieldByName('SadToSeeYouGoMessage')
                    ),
                    Tab::create('Updating Subscription',
                        LiteralField::create('ConfirmExplanation', '<p>Thank you for updating your subscription</p>'),
                        $fields->dataFieldByName('ConfirmTitle'),
                        $fields->dataFieldByName('ConfirmMenuTitle'),
                        $fields->dataFieldByName('ConfirmMessage')
                    )
                ),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Newsletters',
            [
                TabSet::create(
                    'Options',
                    Tab::create(
                        'MainSettings',
                        LiteralField::create('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                        DropdownField::create('ListID', 'Related List from Campaign Monitor (*)', [0 => '-- please select --'] + $this->makeDropdownListFromLists()),
                        ReadonlyField::create('ListIDNice', 'List ID', $this->ListID),
                        $fields->dataFieldByName('SignInNewMemberOnRegistration')->setDescription('Sign-in newly created user on registration?'),
                        $fields->dataFieldByName('MustBeLoggedInToEditSubscription')->setDescription('User must be logged in to edit their registations?'),
                        $fields->dataFieldByName('MakeAllFieldsRequired')->setDescription('Make all fields mandatory (except consent field)'),
                        $fields->dataFieldByName('ShowListNameInSubscribeToField')->setDescription('Show name of the list in the subscribe to field?'),
                        $fields->dataFieldByName('AllowUnsubscribeInForm')->setDescription('Allow unsubscribe in form?'),
                        $fields->dataFieldByName('ShowFirstNameFieldInForm')->setDescription('Show First Name Field in form?'),
                        $fields->dataFieldByName('ShowSurnameFieldInForm')->setDescription('Show Surname Field in form?'),
                        $fields->dataFieldByName('ShowPermissionToTrackFieldInForm')->setDescription('Show Consent checkbox? - if the Consent checkbox is not shown in the sign-up form - <i>Permission to track</i> for each new subscriber set to <b>Unknown (Unchanged)</b> by default. For more info, please check <a href="https://help.campaignmonitor.com/permission-to-track" target="_blank">this page</a>.'),
                        $fields->dataFieldByName('PermissionToTrackLabelField')->setRows(2)->setDescription('Consent label - HTML restriction: only link tag <b>&lt;a&gt;</b> allowed to be used in this field'),
                        $fields->dataFieldByName('ShowAllNewsletterForSigningUp')->setDescription('Allow users to sign up to all lists'),
                        $fields->dataFieldByName('CloseSubscriptions')->setDescription('Close subscription')
                    ),
                    Tab::create(
                        'StartForm',
                        LiteralField::create('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they will be redirected to the full form to enter more details.'),
                        $fields->dataFieldByName('SignUpHeader'),
                        $fields->dataFieldByName('SignUpIntro'),
                        $fields->dataFieldByName('SignUpButtonLabel')
                    ),
                    Tab::create(
                        'Shown on Site',
                        $fields->dataFieldByName('ShowOldNewsletters')->setDescription('Show previously sent Newsletters'),
                        $campaignField
                    ),
                    Tab::create('Templates', $gridFieldTemplatesAvailable),
                    Tab::create(
                        'Segments',
                        LiteralField::create('SegmentsExplanation', '<p>Segments are smart lists within your main list that are created in Campaign Monitor.  You can add them here for your own reference.</p>'),
                        GridField::create('MySegments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordEditor::create())
                    ),
                    Tab::create(
                        'CustomFields',
                        GridField::create('MyCustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordEditor::create())
                            ->addComponent(GridFieldSortableRows::create('SortOrder'))
                    ),
                    Tab::create(
                        'Advanced',
                        $fields->dataFieldByName('GroupID'),
                        LiteralField::create('GroupLink', $groupLink),
                        LiteralField::create('MyControllerTest', '<h3><a href="' . $testControllerLink . '">Test Connections</a></h3>'),
                        LiteralField::create('MyStats', '<h3><a href="' . $this->Link('stats') . '">Stats and Debug information</a></h3>'),
                        LiteralField::create('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'),
                        LiteralField::create('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>')
                    )
                ),
            ]
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

        $testControllerLink = Injector::inst()->get(CampaignMonitorAPIConnectorTestController::class)->Link();
        $campaignExample = CampaignMonitorCampaign::get()->Last();
        if ($campaignExample && $campaignExample->CampaignID) {
            $campaignExampleLink = $this->Link('viewcampaign/' . $campaignExample->CampaignID);
        } else {
            $campaignExampleLink = 'error-not-available';
        }

        if ($this->ID) {
            $config = GridFieldConfig_RelationEditor::create();
            $campaignField = GridField::create('CampaignList', 'Campaigns', $this->CampaignMonitorCampaigns(), $config);
        } else {
            $campaignField = HiddenField::create('CampaignList');
        }

        $gridFieldTemplatesAvailable = GridField::create('TemplatesAvailable', 'Templates Available', CampaignMonitorCampaignStyle::get(), GridFieldConfig_RecordEditor::create());
        $gridFieldTemplatesAvailable->setDescription('Ask your developer on how to add more templates');

        $fields->addFieldToTab(
            'Root.SubscriptionFeedback',
            TabSet::create('AlternativeContentSubHeader', Tab::create('ThankYou', TextField::create('ThankYouTitle', 'Title'), TextField::create('ThankYouMenuTitle', 'Menu Title'), HTMLEditorField::create('ThankYouMessage', 'Thank you message after submitting form')), Tab::create('SadToSeeYouGo', TextField::create('SadToSeeYouGoTitle', 'Title'), TextField::create('SadToSeeYouGoMenuTitle', 'Menu Title'), HTMLEditorField::create('SadToSeeYouGoMessage', 'Sad to see you  go message after submitting form')), Tab::create('Updating Subscription', LiteralField::create('ConfirmExplanation', '<p>Thank you for updating your subscription</p>'), TextField::create('ConfirmTitle', 'Title'), TextField::create('ConfirmMenuTitle', 'Menu Title'), HTMLEditorField::create('ConfirmMessage', 'Message (e.g. thank you for confirming)')))
        );

        $fields->addFieldToTab(
            'Root.Newsletters',
            TabSet::create('Options', Tab::create('MainSettings', LiteralField::create('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'), DropdownField::create('ListID', 'Related List from Campaign Monitor (*)', [0 => '-- please select --'] + $this->makeDropdownListFromLists()), ReadonlyField::create('ListIDNice', 'List ID', $this->ListID), CheckboxField::create('SignInNewMemberOnRegistration', 'Sign-in newly created user on registration?'), CheckboxField::create('MustBeLoggedInToEditSubscription', 'User must be logged in to edit their registations?'), CheckboxField::create('MakeAllFieldsRequired', 'Make all fields mandatory (except consent field)'), CheckboxField::create('ShowListNameInSubscribeToField', 'Show name of the list in the subscribe to field?'), CheckboxField::create('AllowUnsubscribeInForm', 'Allow unsubscribe in form?'), CheckboxField::create('ShowFirstNameFieldInForm', 'Show First Name Field in form?'), CheckboxField::create('ShowSurnameFieldInForm', 'Show Surname Field in form?'), CheckboxField::create('ShowPermissionToTrackFieldInForm', 'Show Consent checkbox?')->setDescription('if the Consent checkbox is not shown in the sign-up form - <i>Permission to track</i> for each new subscriber set to <b>Unknown (Unchanged)</b> by default. For more info, please check <a href="https://help.campaignmonitor.com/permission-to-track" target="_blank">this page</a>.'), HTMLEditorField::create('PermissionToTrackLabelField', 'Consent label')->setRows(2)->setDescription('HTML restriction: only link tag <b>&lt;a&gt;</b> allowed to be used in this field'), CheckboxField::create('ShowAllNewsletterForSigningUp', 'Allow users to sign up to all lists'), CheckboxField::create('CloseSubscriptions', 'Close subscription')), Tab::create('StartForm', LiteralField::create('StartFormExplanation', 'A start form is a form where people are just required to enter their email address and nothing else.  After completion they go through to another page (the actual CampaignMonitorSignupPage) to complete all the details.'), TextField::create('SignUpHeader', 'Sign up header (e.g. sign up now)'), HTMLEditorField::create('SignUpIntro', 'Sign up form intro (e.g. sign up for our monthly newsletter ...'), TextField::create('SignUpButtonLabel', 'Sign up button label for start form (e.g. register now)')), Tab::create('Campaigns', LiteralField::create('CreateNewCampaign', '<p>To create a new mail out go to <a href="' . Config::inst()->get(CampaignMonitorAPIConnector::class, 'campaign_monitor_url') . '">Campaign Monitor</a> site.</p>'), CheckboxField::create('ShowOldNewsletters', 'Show old newsletters? Set to "NO" to remove all old newsletters links to this page. Set to "YES" to retrieve all old newsletters.'), LiteralField::create('CampaignExplanation', '<h3>Unfortunately, newsletter lists are not automatically linked to individual newsletters, you can link them here...</h3>'), CheckboxSetField::create('CampaignMonitorCampaigns', 'Newsletters shown', CampaignMonitorCampaign::get()->filter(['HasBeenSent' => 1])->limit(500)->map()->toArray()), $campaignField, $gridFieldTemplatesAvailable, LiteralField::create('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'), LiteralField::create('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>')), Tab::create('Segments', GridField::create('Segments', 'Segments', $this->CampaignMonitorSegments(), GridFieldConfig_RecordViewer::create())), Tab::create('CustomFields', GridField::create('CustomFields', 'Custom Fields', $this->CampaignMonitorCustomFields(), GridFieldConfig_RecordViewer::create()
                ->addComponent(GridFieldSortableRows::create('SortOrder')))), Tab::create('Advanced', LiteralField::create('GroupLink', $groupLink), LiteralField::create('MyControllerTest', '<h3><a href="' . $testControllerLink . '">Test Connections</a></h3>'), LiteralField::create('MyStats', '<h3><a href="' . $this->Link('stats') . '">Stats and Debug information</a></h3>'), LiteralField::create('MyCampaignReset', '<h3><a href="' . $this->Link('resetoldcampaigns') . '">Delete All Campaigns from Website</a></h3>'), LiteralField::create('MyCampaignInfo', '<h3>You can also view individual campaigns - here is <a href="' . $campaignExampleLink . '">an example</a></h3>')))
        );
        if (false === $this->HasCampaigns()) {
            $fields->removeByName([
                'MyCampaignReset',
                'MyCampaignInfo',
                'Campaigns',
            ]);
        }

        if (!Config::inst()->get(CampaignMonitorAPIConnector::class, 'campaign_monitor_url')) {
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
        $email = Controller::curr()->getRequest()->getSession()->get('CampaignMonitorStartForm_AjaxResult_' . $this->ID);
        if ($email) {
            // @return DBHTMLText
            return $this->RenderWith('Sunnysideup\CampaignMonitor\Includes\CampaignMonitorStartForm_AjaxResult', ['Email' => $email]);
        }

        Requirements::javascript('https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js');
        //Requirements::javascript(THIRDPARTY_DIR . '/jquery-form/jquery.form.js');
        Requirements::javascript('sunnysideup/campaignmonitor: client/javascript/CampaignMonitorStartForm.js');
        if (!$this->ReadyToReceiveSubscribtions()) {
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
                $member = Member::create();
                $member->Email = $email;
                //$member->SetPassword = true;
                //$member->Password = Member::create_new_password();
                $member->write();
            }

            $group = $this->Group();
            if ($group) {
                $group->Members()->add($member);
            }

            $api = $this->getCMAPI();
            if ($api) {
                $result = $api->addSubscriber($listID, $member);
                if ($result === $email) {
                    return null;
                }
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
        $definition = new InputDefinition($task->getOptions());
        $input = new ArrayInput(['Verbose' => false], $definition);
        $output = PolyOutput::create(PolyOutput::FORMAT_ANSI);
        $definition = new InputDefinition($task->getOptions());
        $input = new ArrayInput([], $definition);
        $output = PolyOutput::create(PolyOutput::FORMAT_ANSI);
        $task->run($input, $output);
    }

    #[Override]
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $update = [];
        $page = CampaignMonitorSignupPage::get()->First();

        if ($page) {
            if (!$page->SignUpHeader) {
                $page->SignUpHeader = 'Sign Up Now';
                $update[] = 'created default entry for SignUpHeader';
            }

            if (strlen((string) $page->SignUpIntro) < strlen('<p> </p>')) {
                $page->SignUpIntro = '<p>Enter your email to sign up for our newsletter</p>';
                $update[] = 'created default entry for SignUpIntro';
            }

            if (!$page->SignUpButtonLabel) {
                $page->SignUpButtonLabel = 'Register Now';
                $update[] = 'created default entry for SignUpButtonLabel';
            }

            if ($update !== []) {
                $page->writeToStage('Stage');
                $page->publish('Stage', 'Live');
                DB::alteration_message($page->ClassName . ' created/updated: ' . implode(' --- ', $update), 'created');
            }
        }
    }

    /**
     * check list and group IDs.
     */
    #[Override]
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        //check list
        if ($this->getListTitle() === '' || $this->getListTitle() === '0') {
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
    #[Override]
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
        $api = $this->getCMAPI();
        if ($api) {
            $segments = $api->getSegments($this->ListID);
            if ($segments && is_array($segments) && count($segments)) {
                foreach ($segments as $segment) {
                    $segmentsAdded[$segment->SegmentID] = $segment->SegmentID;
                    $filterArray = ['SegmentID' => $segment->SegmentID, 'ListID' => $this->ListID, 'CampaignMonitorSignupPageID' => $this->ID];
                    $obj = CampaignMonitorSegment::get()->filter($filterArray)->first();
                    if (!$obj) {
                        $obj = CampaignMonitorSegment::create($filterArray);
                    }

                    $obj->Title = $segment->Title;
                    $obj->write();
                }
            }

            if ([] !== $segmentsAdded) {
                $unwantedSegments = CampaignMonitorSegment::get()->filter(['ListID' => $this->ListID, 'CampaignMonitorSignupPageID' => $this->ID])
                    ->exclude(['SegmentID' => $segmentsAdded]);
                foreach ($unwantedSegments as $unwantedSegment) {
                    $unwantedSegment->delete();
                }
            }

            // //add custom fields
            $customCustomFieldsAdded = [];
            $customCustomFields = $api->getListCustomFields($this->ListID);
            if ($customCustomFields && is_array($customCustomFields) && count($customCustomFields)) {
                foreach ($customCustomFields as $customCustomField) {
                    $obj = CampaignMonitorCustomField::create_from_campaign_monitor_object($customCustomField, $this->ListID);
                    $customCustomFieldsAdded[$obj->Code] = $obj->Code;
                }
            }

            if ([] !== $customCustomFieldsAdded) {
                $unwantedCustomFields = CampaignMonitorCustomField::get()->filter(['ListID' => $this->ListID, 'CampaignMonitorSignupPageID' => $this->ID])
                    ->exclude(['Code' => $customCustomFieldsAdded]);
                foreach ($unwantedCustomFields as $unwantedCustomField) {
                    $unwantedCustomField->delete();
                }
            }
        }
    }

    protected function addOrRemoveGroup()
    {
        $gp = null;
        // get grouip
        if ($this->GroupID) {
            $gp = $this->Group();
            if (!($gp && $gp->exists())) {
                $this->GroupID = 0;
                $gp = null;
            }
        }

        // add group if there is a list.
        if ($this->ListID) {

            // get title
            $title = _t('CampaignMonitor.NEWSLETTER', 'NEWSLETTER');
            $myListName = $this->getListTitle();
            if ($myListName !== '' && $myListName !== '0') {
                $title .= ': ' . $myListName;
            }

            // if there is an other group - check if there are
            // create or find the group if we do not have the group
            if (!$gp) {
                $filter = ['Title' => $title];
                $gp = Group::get()->filter($filter)->first();
                if (!$gp) {
                    $gp = Group::create($filter);
                }
            }

            $gp->Title = (string) $title;
            try {
                $gp->write();
            } catch (Exception) {
            }

            if ($gp) {
                $this->GroupID = $gp->ID;
            }
        }
    }

    /**
     * returns available list for client.
     *
     * @return array
     */
    protected function makeDropdownListFromLists()
    {
        if (!isset(self::$drop_down_list[$this->ID])) {
            self::$drop_down_list[$this->ID] = [];
            $array = [];
            $api = $this->getCMAPI();
            if ($api) {
                $lists = $api->getLists();
                if (is_array($lists) && count($lists)) {
                    foreach ($lists as $list) {
                        $array[$list->ListID] = $list->Name;
                    }
                }

                //remove subscription list IDs from other pages
                $subscribePages = CampaignMonitorSignupPage::get()->exclude(['ID' => $this->ID]);
                foreach ($subscribePages as $page) {
                    if (isset($array[$page->ListID])) {
                        unset($array[$page->ListID]);
                    }
                }

                self::$drop_down_list[$this->ID] = $array;
            }
        }

        return self::$drop_down_list[$this->ID];
    }
}
