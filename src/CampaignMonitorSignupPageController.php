<?php

namespace Sunnysideup\CampaignMonitor;

use PageController;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DB;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorSignupFieldProvider;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;

class CampaignMonitorSignupPageController extends PageController
{
    use CampaignMonitorApiTrait;

    /**
     * @var bool
     */
    protected $isThankYou = false;

    /**
     * @var bool
     */
    protected $isUnsubscribe = false;

    /**
     * @var bool
     */
    protected $isConfirm = false;

    /**
     * @var bool
     */
    protected $isSignUp = true;

    /**
     * @var string
     */
    protected $email = '';

    /**
     * holder for selected campaign.
     *
     * @var CampaignMonitorCampaign
     */
    protected $campaign;

    /**
     * @var array
     */
    protected $fieldsForSignupFormCache = [];

    protected $memberDbValues = [];

    private static $allow_to_add_to_existing_member_without_logging_in = false;

    private static $sign_in_new_member_on_registration = false;

    private static $allowed_actions = [
        'SignupForm' => true,
        'subscribe' => true,
        'unsubscribe' => true,
        'thankyou' => true,
        'confirm' => true,
        'sadtoseeyougo' => true,
        'preloademail' => true,
        'viewcampaign' => true,
        'viewcampaigntextonly' => true,
        'previewcampaign' => 'ADMIN',
        'previewcampaigntextonly' => 'ADMIN',
        'stats' => 'ADMIN',
        'resetoldcampaigns' => 'ADMIN',
        'resetsignup' => true,
    ];

    public function ShowForm(): bool
    {
        return $this->isSignUp;
    }

    /**
     * creates a subscription form...
     *
     * @return Form|string
     */
    public function SignupForm()
    {
        if ($this->ReadyToReceiveSubscribtions()) {
            // Create fields
            $member = Security::getCurrentUser();

            $fields = new FieldList($this->getFieldsForSignupFormFormFields($member));

            $additionalFieldsAtStart = $this->getAdditionalFieldsAtStart();
            foreach (array_reverse($additionalFieldsAtStart) as $field) {
                $fields->unshift($field);
            }

            $additionalFieldsAtEnd = $this->getAdditionalFieldsAtEnd();
            foreach ($additionalFieldsAtEnd as $field) {
                $fields->push($field);
            }
            $allowUnsubscribe = Config::inst()->get(CampaignMonitorSignupFieldProvider::class, 'campaign_monitor_allow_unsubscribe');
            if ($allowUnsubscribe) {
                $action = _t('CAMPAIGNMONITORSIGNUPPAGE.UPDATE_SUBSCRIPTIONS', 'Update Subscriptions');
            } else {
                $action = _t('CAMPAIGNMONITORSIGNUPPAGE.SIGN_UP_NOW', 'Signup');
            }
            // Create action
            $actions = new FieldList([new FormAction('subscribe', $action)]);
            // Create Validators
            if ($this->MakeAllFieldsRequired) {
                $requiredList = $fields->dataFieldNames();
            } else {
                $requiredList = $this->getFieldsForSignupFormRequiredFields($member);
            }
            if (($key = array_search('CampaignMonitorEmail', $requiredList, true)) !== false) {
                unset($requiredList[$key]);
            }
            $validator = new RequiredFields($requiredList);
            $form = new Form($this, 'SignupForm', $fields, $actions, $validator);
            $data = $this->getRequest()->getSession()->get("FormData.{$form->getName()}.data");
            if ($data) {
                $form->loadDataFrom($data);
            } elseif ($member && $member->exists()) {
                foreach ($this->getFieldsForSignupFormFieldsIncluded(true) as $field) {
                    $value = $this->memberDbValues['CampaignMonitor' . $field] ?? '';
                    if ($value) {
                        $form->Fields()->fieldByName('CampaignMonitor' . $field)
                            ->setValue($value)
                        ;
                    }
                }
            }

            return $form;
        }

        return _t('CampaignMonitorSignupPage.NOTREADY', 'You can not suscribe to this newsletter at present.');
    }

    /**
     * we need this in controller and dataobject.
     */
    public function ReadyToReceiveSubscribtions(): bool
    {
        if ($this->CloseSubscriptions) {
            return false;
        }

        return $this->ListID && $this->GroupID;
    }

    /**
     * action subscription form.
     *
     * @param array $data
     * @param Form  $form
     *
     * return redirect
     */
    public function subscribe($data, $form)
    {
        if ($this->ReadyToReceiveSubscribtions()) {
            $session = $this->getRequest()->getSession();
            $loggedInMember = Security::getCurrentUser();
            if ($loggedInMember) {
                $data['CampaignMonitorEmail'] = $loggedInMember->Email;
            } else {
                $data['CampaignMonitorEmail'] = Convert::raw2sql($data['CampaignMonitorEmail']);
            }
            $session->set("FormData.{$form->getName()}.data", $data);

            //true until proven otherwise.
            $newlyCreatedMember = false;
            //$api = $this->getCMAPI();
            $fieldName = Config::inst()->get(CampaignMonitorSignupFieldProvider::class, 'campaign_monitor_signup_fieldname');
            $type = $data[$fieldName . 'Type'] ?? 'none';
            $isMany = false;
            $isOne = false;
            $doLogin = true;
            //subscribe or unsubscribe?
            if ('many' === $type) {
                $values = $data[$fieldName] ?? [];
                $isMany = true;
            } elseif ('one' === $type) {
                $values = $data[$fieldName] ?? 'error';
                $isOne = true;
            } else {
                $values = '';
                $form->sessionError('You can not subscribe right now.', 'error');
                $this->redirectBack();

                return;
            }

            //no member logged in: if the member already exists then you can't sign up.

            $memberFilter = ['Email' => $data['CampaignMonitorEmail']];
            $submittedMember = Member::get()->filter($memberFilter)->First();
            $memberToEdit = null;
            $newlyCreatedMember = true;
            if ($loggedInMember) {
                if ($submittedMember) {
                    if ($loggedInMember->ID === $submittedMember->ID) {
                        $memberToEdit = $loggedInMember;
                    } else {
                        $form->sessionError(
                            _t(
                                'CAMPAIGNMONITORSIGNUPPAGE.LOG_OUT_FIRST',
                                'Please log out first. You can not be logged in and sign-up someone else.'
                            ),
                            'error'
                        );
                        $this->redirectBack();

                        return;
                    }
                } else {
                    $form->sessionError(
                        _t(
                            'CAMPAIGNMONITORSIGNUPPAGE.NON_MATCH_ERROR',
                            'Please log out first. You can not be logged in and sign-up someone new.'
                        ),
                        'error'
                    );
                    $this->redirectBack();

                    return;
                }
            } else {
                if ($submittedMember) {
                    if ($this->MustBeLoggedInToAddSubscription) {
                        $form->sessionError(
                            _t(
                                'CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS',
                                'Please log in for this email or try another email address.'
                            ),
                            'error'
                        );
                        $this->redirectBack();

                        return;
                    }
                    $memberToEdit = $submittedMember;
                    $doLogin = false;
                    $newlyCreatedMember = false;
                } else {
                    $newlyCreatedMember = true;
                    $memberToEdit = Member::create($memberFilter);
                }
            }

            //if this is a new member then we save the member
            if ($memberToEdit) {
                $fields = $this->getFieldsForSignupFormFieldsIncluded(true);

                foreach ($fields as $field) {
                    if ('Email' !== $field) {
                        if (! empty($data['CampaignMonitor' . $field])) {
                            $memberToEdit->{$field} = Convert::raw2sql($data['CampaignMonitor' . $field]);
                        }
                    }
                }
                $memberToEdit->write();
                if ($newlyCreatedMember) {
                    if ($this->SignInNewMemberOnRegistration && $doLogin) {
                        Security::setCurrentUser($memberToEdit);
                        $identityStore = Injector::inst()->get(IdentityStore::class);
                        $identityStore->logIn($memberToEdit, $rememberMe = false, null);
                    }
                }
            }

            $outcome = $memberToEdit->processCampaignMonitorSignupField($this->dataRecord, $data, $values);
            $session->clear("FormData.{$form->getName()}.data");
            if ($isMany) {
                return $this->redirect($this->link('confirm'));
            }
            if ($isOne && 'subscribe' === $outcome) {
                return $this->redirect($this->link('thankyou'));
            }
            if ($isOne && 'unsubscribe' === $outcome) {
                return $this->redirect($this->link('sadtoseeyougo'));
            }
            user_error('Could not process data succecssfully.');
        }
        user_error('No list to subscribe to', E_USER_WARNING);
    }

    /**
     * immediately unsubscribe if you are logged in.
     *
     * @param HTTPRequest $request
     */
    public function unsubscribe($request)
    {
        $member = Security::getCurrentUser();
        if ($member) {
            $member->removeCampaignMonitorList($this->ListID);
            $this->Content = $member->Email . ' has been removed from this list: ' . $this->getListTitle();
        } else {
            Security::permissionFailure($this, _t('CAMPAIGNMONITORSIGNUPPAGE.LOGINFIRST', 'Please login first.'));
        }

        return [];
    }

    /**
     * action.
     *
     * @param HTTPRequest $request
     */
    public function confirm($request)
    {
        $this->Title = $this->ConfirmTitle;
        $this->MenuTitle = $this->ConfirmMenuTitle;
        $this->Content = $this->ConfirmMessage;
        $this->isConfirm = true;
        $this->isSignUp = false;

        return [];
    }

    /**
     * action.
     *
     * @param HTTPRequest $request
     */
    public function thankyou($request)
    {
        $this->Title = $this->ThankYouTitle;
        $this->MenuTitle = $this->ThankYouMenuTitle;
        $this->Content = $this->ThankYouMessage;
        $this->isThankYou = true;
        $this->isSignUp = false;

        return [];
    }

    /**
     * action.
     *
     * @param HTTPRequest $request
     */
    public function sadtoseeyougo($request)
    {
        $this->Title = $this->SadToSeeYouGoTitle;
        $this->MenuTitle = $this->SadToSeeYouGoMenuTitle;
        $this->Content = $this->SadToSeeYouGoMessage;
        $this->isUnsubscribe = true;
        $this->isSignUp = false;

        return [];
    }

    /**
     * action.
     */
    public function preloademail(HTTPRequest $request)
    {
        $data = $request->requestVars();
        if (isset($data['CampaignMonitorEmail'])) {
            $email = Convert::raw2sql($data['CampaignMonitorEmail']);
            if ($email) {
                $this->memberDbValues['CampaignMonitorEmail'] = $email;
                if (Director::is_ajax()) {
                    if (! $this->addSubscriber($email)) {
                        $this->getRequest()->getSession()->set('CampaignMonitorStartForm_AjaxResult_' . $this->ID, $data['CampaignMonitorEmail']);

                        return $this->RenderWith('Sunnysideup\CampaignMonitor\Includes\CampaignMonitorStartForm_AjaxResult');
                    }

                    return 'ERROR';
                }
            }
        } elseif ($m = Security::getCurrentUser()) {
            $this->memberDbValues['CampaignMonitorEmail'] = $m->Email;
        }

        return [];
    }

    /**
     * action to show one campaign...
     *
     * @param mixed $request
     */
    public function viewcampaign($request)
    {
        $id = (int) $request->param('ID');
        // @var CampaignMonitorCampaign|null $this->campaign
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if (! $this->campaign) {
            return $this->httpError(404, _t('CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND', 'Message not found.'));
        }

        return [];
    }

    /**
     * action to show one campaign TEXT ONLY...
     *
     * @param mixed $request
     */
    public function viewcampaigntextonly($request)
    {
        $id = (int) $request->param('ID');
        // @var CampaignMonitorCampaign|null $this->campaign
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if (! $this->campaign) {
            return $this->httpError(404, _t('CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND', 'Message not found.'));
        }

        return [];
    }

    /**
     * action to preview one campaign...
     *
     * @param mixed $request
     */
    public function previewcampaign($request)
    {
        $id = (int) $request->param('ID');
        // @var CampaignMonitorCampaign|null $this->campaign
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if ($this->campaign) {
            if (isset($_GET['hash']) && 7 === strlen($_GET['hash']) && $_GET['hash'] === $this->campaign->Hash) {
                return HTTP::absoluteURLs($this->campaign->getNewsletterContent());
            }
        }

        return $this->httpError(404, _t('CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND', 'No preview available.'));
    }

    /**
     * action to preview one campaign TEXT ONLY...
     *
     * @param mixed $request
     */
    public function previewcampaigntextonly($request)
    {
        $id = (int) $request->param('ID');
        // @var CampaignMonitorCampaign|null $this->campaign
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if ($this->campaign) {
            return HTTP::absoluteURLs(strip_tags($this->campaign->getNewsletterContent()));
        }

        return $this->httpError(404, _t('CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND', 'No preview available.'));
    }

    /**
     * @return string
     */
    public function Email()
    {
        return $this->memberDbValues['CampaignMonitorEmail'];
    }

    /**
     * @return bool
     */
    public function IsThankYou()
    {
        return $this->isThankYou;
    }

    /**
     * @return bool
     */
    public function IsConfirm()
    {
        return $this->isConfirm;
    }

    /**
     * @return bool
     */
    public function IsUnsubscribe()
    {
        return $this->isUnsubscribe;
    }

    /**
     * @return bool
     */
    public function HasCampaign()
    {
        return (bool) $this->campaign;
    }

    public function Campaign()
    {
        return $this->campaign;
    }

    /**
     * same as $this->CampaignMonitorCampaigns()
     * but sorted correctly.
     *
     * @return null|DataList -  CampaignMonitorCampaigns
     */
    public function PreviousCampaignMonitorCampaigns(): ?DataList
    {
        if ($this->ShowOldNewsletters) {
            $campaigns = $this->CampaignMonitorCampaigns();

            return CampaignMonitorCampaign::get()
                ->filter(
                    [
                        'ID' => $campaigns->columnUnique(),
                        'Hide' => 0,
                        'HasBeenSent' => 1,
                    ]
                )
            ;
        }

        return null;
    }

    /**
     * action for admins only to see a whole bunch of
     * stats.
     */
    public function stats()
    {
        if (Permission::check('ADMIN')) {
            //run tests here
            $api = $this->getCMAPI();
            $html = '<div id="CampaignMonitorStats">';
            $html .= '<h1>Debug Response</h1>';
            $html .= '<h2>Main Client Stuff</h2>';
            $html .= '<h3>Link to confirm page</h3>' . Director::absoluteUrl($this->Link('confirm')) . '';
            $html .= '<h3>Link to thank-you-page</h3>' . Director::absoluteUrl($this->Link('thankyou')) . '';
            $html .= '<h3>Link to sad-to-see-you-go page</h3>' . Director::absoluteUrl($this->Link('sadtoseeyougo')) . '';
            $html .= '<h3><a href="#">All Campaigns</a></a></h3><pre>' . print_r($api->getCampaigns(), 1) . '</pre>';
            $html .= '<h3><a href="#">All Lists</a></h3><pre>' . print_r($api->getLists(), 1) . '</pre>';
            if ($this->ListID) {
                $html .= '<h2>List</h2';
                $html .= '<h3><a href="#" id="MyListStatsAreHere">List Stats</a></h3><pre>' . print_r($api->getListStats($this->ListID), 1) . '</pre>';
                $html .= '<h3><a href="#">List Details</a></h3><pre>' . print_r($api->getList($this->ListID), 1) . '</pre>';
                $html .= '<h3><a href="#">Active Subscribers (latest ones)</a></h3><pre>' . print_r($api->getActiveSubscribers($this->ListID), 1) . '</pre>';
                $html .= '<h3><a href="#">Unconfirmed Subscribers (latest ones)</a></h3><pre>' . print_r($api->getUnconfirmedSubscribers($this->ListID), 1) . '</pre>';
                $html .= '<h3><a href="#">Bounced Subscribers (latest ones)</a></h3><pre>' . print_r($api->getBouncedSubscribers($this->ListID), 1) . '</pre>';
                $html .= '<h3><a href="#">Unsubscribed Subscribers (latest ones)</a></h3><pre>' . print_r($api->getUnsubscribedSubscribers($this->ListID), 1) . '</pre>';
            } else {
                $html .= '<h2 style="color: red;">ERROR: No Lists selected</h2';
            }
            Requirements::customScript($this->JSHackForPreSections(), 'CampaignMonitorStats');
            $html .= '</div>';
            $this->Content = $html;
        } else {
            Security::permissionFailure($this, _t('CAMPAIGNMONITORSIGNUPPAGE.TESTFAILURE', 'This function is only available for administrators'));
        }

        return [];
    }

    /**
     * returns a bunch of stats about a campaign
     * IF the user is an admin AND a campaign is selected.
     */
    public function CampaignStats()
    {
        if (Permission::check('Admin')) {
            if ($this->campaign) {
                //run tests here
                $api = $this->getCMAPI();
                $html = '<div id="CampaignMonitorStats">';
                $html .= '<h2>Campaign Stats</h2>';
                $html .= '<h3><a href="#">Campaign: ' . $this->campaign->Subject . '</a></h3>';
                $html .= '<h3><a href="#">Summary</a></h3><pre>' . print_r($api->getSummary($this->campaign->CampaignID), 1) . '</pre>';
                $html .= '<h3><a href="#">Email Client Usage</a></h3><pre>' . print_r($api->getEmailClientUsage($this->campaign->CampaignID), 1) . '</pre>';
                $html .= '<h3><a href="#">Unsubscribes</a></h3><pre>' . print_r($api->getUnsubscribes($this->campaign->CampaignID), 1) . '</pre>';
                Requirements::customScript($this->JSHackForPreSections(), 'CampaignMonitorStats');
                $html .= '</div>';
                $this->Content = $html;
            }
        }
    }

    /**
     * action.
     *
     * @param HTTPRequest $request
     */
    public function resetsignup($request)
    {
        $this->getRequest()->getSession()->clear('CampaignMonitorStartForm_AjaxResult_' . $this->ID);

        return [];
    }

    /**
     * removes all campaigns
     * so that they can be re-imported.
     */
    public function resetoldcampaigns()
    {
        if (Permission::check('Admin')) {
            DB::query('DELETE FROM "CampaignMonitorCampaign";');
            DB::query('DELETE FROM "CampaignMonitorCampaign_Pages";');
            die('old campaigns have been deleted');
        }
        Security::permissionFailure($this, _t('Security.PERMFAILURE', ' This page is secured and you need CMS rights to access it. Enter your credentials below and we will send you right along.'));
    }

    protected function getFieldsForSignupFormFormFields(?Member $member): array
    {
        $array = $this->getFieldsForSignupForm($member);

        return $array['Fields'];
    }

    protected function getFieldsForSignupFormRequiredFields(?Member $member): array
    {
        $array = $this->getFieldsForSignupForm($member);

        return array_keys($array['Required']);
    }

    protected function getFieldsForSignupForm(?Member $member): array
    {
        if ($member) {
            $memberId = $member->ID;
        } else {
            $memberId = 0;
            $member = new Member();
        }

        $fieldArray = [];
        foreach ($this->getFieldsForSignupFormFieldsIncluded() as $field => $title) {
            $fieldName = 'CampaignMonitor' . $field;
            $fieldArray['Fields'][$fieldName] = null;
            $fieldArray['Required'][$fieldName] = true;
            $fieldType = TextField::class;
            $this->memberDbValues[$fieldName] = $member->{$field};
            $disabledEmailPhrase = '';
            if ('Email' === $field) {
                if ($memberId) {
                    $disabledEmailPhrase = 'disabled';
                    $fieldArray['Required'][$fieldName] = false;
                }
            }
            //do not set values here!
            $fieldArray['Fields'][$fieldName] = (new $fieldType(
                $fieldName,
                $title
            ));
            if ($disabledEmailPhrase) {
                $fieldArray['Fields'][$fieldName]->setAttribute('disabled', $disabledEmailPhrase);
            }
        }
        if ($this->ShowAllNewsletterForSigningUp) {
            $fieldArray['Fields']['SignupField'] = $member->getCampaignMonitorSignupField(null);
        } else {
            $fieldArray['Fields']['SignupField'] = $member->getCampaignMonitorSignupField($this->ListID);
        }

        return $fieldArray;
    }

    protected function getFieldsForSignupFormFieldsIncluded(?bool $keysOnly = false): array
    {
        $array = [
            'Email' => 'Email',
        ];
        if ($this->ShowFirstNameFieldInForm) {
            $array['FirstName'] = _t('CAMPAIGNMONITORSIGNUPPAGE.FIRSTNAME', 'First Name');
        }
        if ($this->ShowSurnameFieldInForm) {
            $array['Surname'] = _t('CAMPAIGNMONITORSIGNUPPAGE.SIRNAME', 'Surname');
        }
        if ($keysOnly) {
            return array_keys($array);
        }

        return $array;
    }

    protected function getAdditionalFieldsAtStart(): array
    {
        return [];
    }

    protected function getAdditionalFieldsAtEnd(): array
    {
        return [];
    }

    // protected function init()
    // {
    //     parent::init();
    //     //UPGRADE TO DO: fix this
    //     Requirements::themedCSS('client/css/CampaignMonitorSignupPage');
    // }

    /**
     * @return string
     */
    protected function JSHackForPreSections()
    {
        return <<<'javascript'
            jQuery(document).ready(
                function(){
                    jQuery('pre').hide();
                    jQuery('#CampaignMonitorStats').on(
                        'click',
                        'a',
                        function(event){
                            event.preventDefault();
                            jQuery(this).parent().next('pre').slideToggle();
                        }
                    );
                    jQuery("#MyListStatsAreHere").click();
                }
            );

javascript;
    }
}
