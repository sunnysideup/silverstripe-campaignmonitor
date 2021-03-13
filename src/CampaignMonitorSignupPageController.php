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
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DB;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorSignupFieldProvider;
class CampaignMonitorSignupPageController extends PageController
{
    use CampaignMonitorApiTrait;

    /**
     * @var boolean
     */
    protected $isThankYou = false;

    /**
     * @var boolean
     */
    protected $isUnsubscribe = false;

    /**
     * @var boolean
     */
    protected $isConfirm = false;

    /**
     * @var boolean
     */
    protected $isSignUp = true;

    /**
     * @var string
     */
    protected $email = '';

    /**
     * holder for selected campaign
     *
     * @var CampaignMonitorCampaign|null
     */
    protected $campaign = '';

    /**
     * @var array
     */
    protected $fieldsForSignupFormCache = [];

    protected $memberDbValues = [];

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

    /**
     * tells us if the page is ready to receive subscriptions
     * @return bool
     */
    public function ReadyToReceiveSubscribtions()
    {
        return $this->ListID && $this->GroupID;
    }

    public function ShowForm(): bool
    {
        return $this->isSignUp;
    }

    /**
     * creates a subscription form...
     * @return Form
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
            $actions = new FieldList(
                new FormAction('subscribe', $action)
            );
            // Create Validators
            $validator = new RequiredFields($this->getFieldsForSignupFormRequiredFields($member));
            $form = new Form($this, 'SignupForm', $fields, $actions, $validator);
            if ($member && $member->exists()) {
                $form->loadDataFrom($member);
            } else {
                foreach ($this->getFieldsForSignupFormFieldsIncluded(true) as $field) {
                    $form->Fields()->fieldByName('CampaignMonitor' . $field)
                        ->setValue($this->memberDbValues[$field] ?? '');
                }
            }
            return $form;
        }
        return _t('CampaignMonitorSignupPage.NOTREADY', 'You can not suscribe to this newsletter at present.');
    }

    /**
     * action subscription form
     * @param array $data
     * @param Form $form
     *
     * return redirect
     */
    public function subscribe($data, $form)
    {
        if ($this->ReadyToReceiveSubscribtions()) {
            //true until proven otherwise.
            $newlyCreatedMember = false;
            //$api = $this->getCMAPI();
            $member = Security::getCurrentUser();
            $isConfirm = false;
            $isSubscribe = false;
            //subscribe or unsubscribe?
            if (isset($data['SubscribeManyChoices'])) {
                $isConfirm = true;
            } else {
                $isSubscribe = isset($data['SubscribeChoice']) && $data['SubscribeChoice'] === 'Subscribe';
            }

            //no member logged in: if the member already exists then you can't sign up.
            if (! $member) {
                //$memberAlreadyLoggedIn = false;
                $filter = ['Email' => Convert::raw2sql($data['CampaignMonitorEmail'])];
                $existingMember = Member::get()->filter($filter)->First();
                //if($isSubscribe && $existingMember){
                //$form->addErrorMessage('Email', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use. Please log in for this email or try another email address."), 'warning');
                //$this->redirectBack();
                //return;
                //}
                $member = $existingMember;
                if (! $member) {
                    $newlyCreatedMember = true;
                    $member = Member::create($filter);
                }
            }
            //logged in: if the member already as someone else then you can't sign up.
            //$memberAlreadyLoggedIn = true;
            //$existingMember = Member::get()
            //	->filter(array("Email" => Convert::raw2sql($data["CampaignMonitorEmail"])))
            //	->exclude(array("ID" => $member->ID))
            //	->First();
            //if($isSubscribe && $existingMember) {
            //$form->addErrorMessage('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use by someone else. Please log in for this email or try another email address."), 'warning');
            //$this->redirectBack();
            //return;
            //}

            //are any choices being made
            if (! isset($data['SubscribeChoice']) && ! isset($data['SubscribeManyChoices'])) {
                $form->sessionError('Please choose your subscription');
                $this->redirectBack();
                return;
            }

            //if this is a new member then we save the member

            $form->saveInto($member);
            $fields = $this->getFieldsForSignupFormFieldsIncluded(true);

            foreach ($fields as $field) {
                if ($field !== 'Email') {
                    if (isset($data['CampaignMonitor' . $field])) {
                        $member->{$field} = Convert::raw2sql($data['CampaignMonitor' . $field]);
                    }
                }
            }
            //$member->SetPassword = true;
            //$member->Password = Member::create_new_password();

            if ($isSubscribe) {
                if ($newlyCreatedMember) {
                    $member->write();
                    Security::setCurrentUser($member);
                    $identityStore = Injector::inst()->get(IdentityStore::class);
                    $identityStore->logIn($member, $rememberMe = false, null);
                }
            }
            $loggedInMember = Security::getCurrentUser();
            if ($loggedInMember && $loggedInMember->ID === $member->ID) {
                $member->write();
            }

            $outcome = $member->processCampaignMonitorSignupField($this->dataRecord, $data, $form);
            if ($isConfirm) {
                return $this->redirect($this->link('confirm'));
            } elseif ($isSubscribe && $outcome === 'subscribe') {
                return $this->redirect($this->link('thankyou'));
            } elseif (! $isSubscribe && $outcome === 'unsubscribe') {
                return $this->redirect($this->link('sadtoseeyougo'));
            }
            user_error('Could not process data succecssfully.');
        }
        user_error('No list to subscribe to', E_USER_WARNING);
    }

    /**
     * immediately unsubscribe if you are logged in.
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
     * action
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
     * action
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
     * action
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
     * action
     * @param HTTPRequest $request
     */
    public function preloademail(HTTPRequest $request)
    {
        $data = $request->requestVars();
        if (isset($data['CampaignMonitorEmail'])) {
            $email = Convert::raw2sql($data['CampaignMonitorEmail']);
            if ($email) {
                $this->memberDbValues['Email'] = $email;
                if (Director::is_ajax()) {
                    if (! $this->addSubscriber($email)) {
                        $this->getRequest()->getSession()->set('CampaignMonitorStartForm_AjaxResult_' . $this->ID, $data['CampaignMonitorEmail']);
                        return $this->RenderWith('Sunnysideup\CampaignMonitor\Includes\CampaignMonitorStartForm_AjaxResult');
                    }
                    return 'ERROR';
                }
            }
        } else {
            if ($m = Security::getCurrentUser()) {
                $this->memberDbValues['Email'] = $m->Email;
            }
        }
        return [];
    }

    /**
     * action to show one campaign...
     */
    public function viewcampaign($request)
    {
        $id = intval($request->param('ID'));
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if (! $this->campaign) {
            return $this->httpError(404, _t('CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND', 'Message not found.'));
        }
        return [];
    }

    /**
     * action to show one campaign TEXT ONLY...
     */
    public function viewcampaigntextonly($request)
    {
        $id = intval($request->param('ID'));
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if (! $this->campaign) {
            return $this->httpError(404, _t('CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND', 'Message not found.'));
        }
        return [];
    }

    /**
     * action to preview one campaign...
     */
    public function previewcampaign($request)
    {
        $id = intval($request->param('ID'));
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if ($this->campaign) {
            if (isset($_GET['hash']) && strlen($_GET['hash']) === 7 && $_GET['hash'] === $this->campaign->Hash) {
                return HTTP::absoluteURLs($this->campaign->getNewsletterContent());
            }
        }
        return $this->httpError(404, _t('CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND', 'No preview available.'));
    }

    /**
     * action to preview one campaign TEXT ONLY...
     */
    public function previewcampaigntextonly($request)
    {
        $id = intval($request->param('ID'));
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
        return $this->memberDbValues['Email'];
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
        return $this->campaign ? true : false;
    }

    public function Campaign()
    {
        return $this->campaign;
    }

    /**
     * same as $this->CampaignMonitorCampaigns()
     * but sorted correctly.
     *
     * @return \SilverStripe\ORM\DataList -  CampaignMonitorCampaigns
     */
    public function PreviousCampaignMonitorCampaigns()
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
                );
        }
    }

    /**
     * action for admins only to see a whole bunch of
     * stats
     */
    public function stats()
    {
        if (Permission::check('Admin')) {
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
     * IF the user is an admin AND a campaign is selected
     * @return string (html) | false
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
        } else {
            return false;
        }
    }

    /**
     * action
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
        if (empty($this->fieldsForSignupFormCache[$memberId])) {
            $this->fieldsForSignupFormCache[$memberId] = [];
            $fieldArray = [];
            foreach ($this->getFieldsForSignupFormFieldsIncluded() as $field => $title) {
                $fieldArray['Fields'][$field] = null;
                $fieldArray['Required'][$field] = true;
                $fieldType = TextField::class;
                $this->memberDbValues[$field] = $member->{$field};
                if ($field === 'Email') {
                    if ($memberId) {
                        $fieldArray['Required'][$field] = false;
                        $fieldType = ReadonlyField::class;
                    } else {
                        $fieldType = EmailField::class;
                    }
                }
                $fieldArray['Fields'][$field] = new $fieldType(
                    'CampaignMonitor' . $field,
                    $title,
                    $this->memberDbValues[$field]
                );
            }
            if ($this->ShowAllNewsletterForSigningUp) {
                $fieldArray['Fields']['SignupField'] = $member->getCampaignMonitorSignupField(null, 'SubscribeManyChoices');
            } else {
                $fieldArray['Fields']['SignupField'] = $member->getCampaignMonitorSignupField($this->ListID, 'SubscribeChoice');
            }
            $this->fieldsForSignupFormCache[$memberId] = $fieldArray;
        }

        return $this->fieldsForSignupFormCache[$memberId];
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
        return <<<javascript
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
