<?php

namespace Sunnysideup\CampaignMonitor;

use PageController;
use Requirements;
use Member;
use ReadonlyField;
use EmailField;
use FieldList;
use FormAction;
use RequiredFields;
use Form;
use Convert;
use Security;
use SS_HTTPRequest;
use Director;
use Controller;
use CampaignMonitorCampaign;
use HTTP;
use Permission;
use DB;



/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: _Controller extends Page_Controller (case sensitive)
  * NEW: Controller extends PageController (COMPLEX)
  * EXP: Remove the underscores in your classname - check all references!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class CampaignMonitorSignupPageController extends PageController
{

    /**
     * retains email for processing
     * @var boolean
     */
    protected $isThankYou = false;

    /**
     * retains email for processing
     * @var boolean
     */
    protected $isUnsubscribe = false;

    /**
     * retains email for processing
     * @var boolean
     */
    protected $isConfirm = false;

    /**
     * retains email for processing
     * @var String
     */
    protected $email = '';

    /**
     * holder for selected campaign
     *
     * @var Null | CampaignMonitorCampaign
     */
    protected $campaign = '';

    private static $allowed_actions = array(
        "SignupForm" => true,
        "subscribe" => true,
        "unsubscribe" => true,
        "thankyou" => true,
        "confirm" => true,
        "sadtoseeyougo" => true,
        "preloademail" => true,
        "viewcampaign" => true,
        "viewcampaigntextonly" => true,
        "previewcampaign" => true,
        "previewcampaigntextonly" => true,
        "stats" => true,
        "resetoldcampaigns" => true,
        "resetsignup" => true
    );


/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * OLD:     public function init() (ignore case)
  * NEW:     protected function init() (COMPLEX)
  * EXP: Controller init functions are now protected  please check that is a controller.
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
    protected function init()
    {
        parent::init();
        Requirements::themedCSS("sunnysideup/campaignmonitor: CampaignMonitorSignupPage", "campaignmonitor");
    }

    /**
     * creates a subscription form...
     * @return Form
     */
    public function SignupForm()
    {
        if ($this->ReadyToReceiveSubscribtions()) {
            // Create fields
            $member = Member::currentUser();
            $emailField = null;
            $emailRequired = true;
            if (!$member) {
                $member = new Member();
            } else {
                $this->email = $member->Email;
                if ($this->email) {
                    $emailRequired = false;
                    $emailField = new ReadonlyField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", 'Email'), $this->email);
                }
            }
            if (!$emailField) {
                $emailField = new EmailField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", 'Email'), $this->email);
            }
            if ($this->ShowAllNewsletterForSigningUp) {
                $signupField = $member->getCampaignMonitorSignupField(null, "SubscribeManyChoices");
            } else {
                $signupField = $member->getCampaignMonitorSignupField($this->ListID, "SubscribeChoice");
            }
            $fields = new FieldList(
                $emailField,
                $signupField
            );
            // Create action
            $actions = new FieldList(
                new FormAction('subscribe', _t("CAMPAIGNMONITORSIGNUPPAGE.UPDATE_SUBSCRIPTIONS", "Update Subscriptions"))
            );
            // Create Validators
            if ($emailRequired) {
                $validator = new RequiredFields('CampaignMonitorEmail');
            } else {
                $validator = new RequiredFields();
            }
            $form = new Form($this, 'SignupForm', $fields, $actions, $validator);
            if ($member->exists()) {
                $form->loadDataFrom($member);
            } else {
                $form->Fields()->fieldByName("CampaignMonitorEmail")->setValue($this->email);
            }
            return $form;
        } else {
            return _t("CampaignMonitorSignupPage.NOTREADY", "You can not suscribe to this newsletter at present.");
        }
    }

    /**
     * action subscription form
     * @param Array $array
     * @param Form $form
     *
     * return redirect
     */
    public function subscribe($data, $form)
    {
        if ($this->ReadyToReceiveSubscribtions()) {
            //true until proven otherwise.
            $newlyCreatedMember = false;
            $api = $this->getAPI();
            $member = Member::currentUser();

            //subscribe or unsubscribe?
            if (isset($data["SubscribeManyChoices"])) {
                $isSubscribe = true;
            } else {
                $isSubscribe = isset($data["SubscribeChoice"]) && $data["SubscribeChoice"] == "Subscribe";
            }

            //no member logged in: if the member already exists then you can't sign up.
            if (!$member) {
                $memberAlreadyLoggedIn = false;
                $filter = array("Email" => Convert::raw2sql($data["CampaignMonitorEmail"]));
                $existingMember = Member::get()->filter($filter)->First();
                //if($isSubscribe && $existingMember){
                //$form->addErrorMessage('Email', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use. Please log in for this email or try another email address."), 'warning');
                //$this->redirectBack();
                //return;
                //}
                $member = $existingMember;
                if (!$member) {
                    $newlyCreatedMember = true;
                    $member = Member::create($filter);
                }
            }

            //logged in: if the member already as someone else then you can't sign up.
            else {
                $memberAlreadyLoggedIn = true;
                //$existingMember = Member::get()
                //	->filter(array("Email" => Convert::raw2sql($data["CampaignMonitorEmail"])))
                //	->exclude(array("ID" => $member->ID))
                //	->First();
                //if($isSubscribe && $existingMember) {
                    //$form->addErrorMessage('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL_EXISTS", "This email is already in use by someone else. Please log in for this email or try another email address."), 'warning');
                    //$this->redirectBack();
                    //return;
                //}
            }

            //are any choices being made
            if (!isset($data["SubscribeChoice"]) && !isset($data["SubscribeManyChoices"])) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: $form->addErrorMessage( (case sensitive)
  * NEW: $form->sessionError( (COMPLEX)
  * EXP: SilverStripe\Forms\Form->addErrorMessage(): Removed. Use `sessionMessage()` or `sessionError()` to add a form level message, throw a `ValidationException` during submission, or add a custom validator.
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $form->sessionError('SubscribeChoice', _t("CAMPAIGNMONITORSIGNUPPAGE.NO_NAME", "Please choose your subscription."), 'warning');
                $this->redirectBack();
                return;
            }

            //if this is a new member then we save the member
            if ($isSubscribe) {
                if ($newlyCreatedMember) {
                    $form->saveInto($member);
                    $member->Email = Convert::raw2sql($data["CampaignMonitorEmail"]);
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
                    $member->logIn($keepMeLoggedIn = false);
                }
            }
            $outcome = $member->processCampaignMonitorSignupField($this->dataRecord, $data, $form);
            if ($outcome == "subscribe") {
                return $this->redirect($this->link("thankyou"));
            } else {
                return $this->redirect($this->link("sadtoseeyougo"));
            }
        } else {
            user_error("No list to subscribe to", E_USER_WARNING);
        }
    }

    /**
     * immediately unsubscribe if you are logged in.
     * @param HTTPRequest
     */
    public function unsubscribe($request)
    {
        $member = Member::currentUser();
        if ($member) {
            $member->removeCampaignMonitorList($this->ListID);
            $this->Content = $member->Email." has been removed from this list: ".$this->getListTitle();
        } else {
            Security::permissionFailure($this, _t("CAMPAIGNMONITORSIGNUPPAGE.LOGINFIRST", "Please login first."));
        }
        return array();
    }

    /**
     * action
     * @param HTTPRequest
     */
    public function confirm($request)
    {
        $this->Title = $this->ConfirmTitle;
        $this->MenuTitle = $this->ConfirmMenuTitle;
        $this->Content = $this->ConfirmMessage;
        $this->isConfirm = true;
        return array();
    }

    /**
     * action
     * @param HTTPRequest
     */
    public function thankyou($request)
    {
        $this->Title = $this->ThankYouTitle;
        $this->MenuTitle = $this->ThankYouMenuTitle;
        $this->Content = $this->ThankYouMessage;
        $this->isThankYou = true;
        return array();
    }

    /**
     * action
     * @param HTTPRequest
     */
    public function sadtoseeyougo($request)
    {
        $this->Title = $this->SadToSeeYouGoTitle;
        $this->MenuTitle = $this->SadToSeeYouGoMenuTitle;
        $this->Content = $this->SadToSeeYouGoMessage;
        $this->isUnsubscribe = true;
        return array();
    }

    /**
     * action
     * @param HTTPRequest
     */
    public function preloademail(SS_HTTPRequest $request)
    {
        $data = $request->requestVars();
        if (isset($data["CampaignMonitorEmail"])) {
            $email = Convert::raw2sql($data["CampaignMonitorEmail"]);
            if ($email) {
                $this->email = $email;
                if (Director::is_ajax()) {
                    if (!$this->addSubscriber($email)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: Session:: (case sensitive)
  * NEW: Controller::curr()->getRequest()->getSession()-> (COMPLEX)
  * EXP: If THIS is a controller than you can write: $this->getRequest(). You can also try to access the HTTPRequest directly. 
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                        Controller::curr()->getRequest()->getSession()->set("CampaignMonitorStartForm_AjaxResult_".$this->ID, $data["CampaignMonitorEmail"]);

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: ->RenderWith( (ignore case)
  * NEW: ->RenderWith( (COMPLEX)
  * EXP: Check that the template location is still valid!
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                        return $this->RenderWith("CampaignMonitorStartForm_AjaxResult");
                    } else {
                        return "ERROR";
                    }
                }
            }
        } else {
            if ($m = Member::currentUser()) {
                $this->email = $m->Email;
            }
        }
        return array();
    }

    /**
     *
     * action to show one campaign...
     */
    public function viewcampaign($request)
    {
        $id = intval($request->param("ID"));
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if (!$this->campaign) {
            return $this->httpError(404, _t("CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND", "Message not found."));
        }
        return array();
    }

    /**
     *
     * action to show one campaign TEXT ONLY...
     */
    public function viewcampaigntextonly($request)
    {
        $id = intval($request->param("ID"));
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if (!$this->campaign) {
            return $this->httpError(404, _t("CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND", "Message not found."));
        }
        return array();
    }

    /**
     *
     * action to preview one campaign...
     */
    public function previewcampaign($request)
    {
        $id = intval($request->param("ID"));
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if ($this->campaign) {
            if (isset($_GET["hash"]) && strlen($_GET["hash"]) == 7 &&  $_GET["hash"] == $this->campaign->Hash) {
                return HTTP::absoluteURLs($this->campaign->getNewsletterContent());
            }
        }
        return $this->httpError(404, _t("CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND", "No preview available."));
    }

    /**
     *
     * action to preview one campaign TEXT ONLY...
     */
    public function previewcampaigntextonly($request)
    {
        $id = intval($request->param("ID"));
        $this->campaign = CampaignMonitorCampaign::get()->byID($id);
        if ($this->campaign) {
            return HTTP::absoluteURLs(strip_tags($this->campaign->getNewsletterContent()));
        }
        return $this->httpError(404, _t("CAMPAIGNMONITORSIGNUPPAGE.CAMPAIGN_NOT_FOUND", "No preview available."));
    }


    /**
     *
     * @return String
     */
    public function Email()
    {
        return $this->email;
    }

    /**
     *
     * @return Boolean
     */
    public function IsThankYou()
    {
        return $this->isThankYou;
    }

    /**
     *
     * @return Boolean
     */
    public function IsConfirm()
    {
        return $this->isConfirm;
    }

    /**
     *
     * @return Boolean
     */
    public function IsUnsubscribe()
    {
        return $this->isUnsubscribe;
    }

    /**
     *
     * @return Boolean
     */
    public function HasCampaign()
    {
        return $this->campaign ? true : false;
    }

    /**
     *
     * @return Null | CampaignMonitorCampaign
     */
    public function Campaign()
    {
        return $this->campaign;
    }


    /**
     * same as $this->CampaignMonitorCampaigns()
     * but sorted correctly.
     *
     * @return DataSet of CampaignMonitorCampaigns
     */
    public function PreviousCampaignMonitorCampaigns()
    {
        if ($this->ShowOldNewsletters) {
            $campaigns = $this->CampaignMonitorCampaigns();
            return CampaignMonitorCampaign::get()
                ->filter(
                    array(
                        "ID" => $campaigns->map("ID", "ID")->toArray(),
                        "Hide" => 0,
                        "HasBeenSent" => 1
                    )
                );
        }
    }

    /**
     * action for admins only to see a whole bunch of
     * stats
     */
    public function stats()
    {
        if (Permission::check("Admin")) {
            //run tests here
            $api = $this->getAPI();
            $html = "<div id=\"CampaignMonitorStats\">";
            $html .= "<h1>Debug Response</h1>";
            $html .= "<h2>Main Client Stuff</h2>";
            $html .= "<h3>Link to confirm page</h3>".Director::absoluteUrl($this->Link("confirm"))."";
            $html .= "<h3>Link to thank-you-page</h3>".Director::absoluteUrl($this->Link("thankyou"))."";
            $html .= "<h3>Link to sad-to-see-you-go page</h3>".Director::absoluteUrl($this->Link("sadtoseeyougo"))."";
            $html .= "<h3><a href=\"#\">All Campaigns</a></a></h3><pre>".print_r($api->getCampaigns(), 1)."</pre>";
            $html .= "<h3><a href=\"#\">All Lists</a></h3><pre>".print_r($api->getLists(), 1)."</pre>";
            if ($this->ListID) {
                $html .= "<h2>List</h2";
                $html .= "<h3><a href=\"#\" id=\"MyListStatsAreHere\">List Stats</a></h3><pre>".print_r($api->getListStats($this->ListID), 1)."</pre>";
                $html .= "<h3><a href=\"#\">List Details</a></h3><pre>".print_r($api->getList($this->ListID), 1)."</pre>";
                $html .= "<h3><a href=\"#\">Active Subscribers (latest ones)</a></h3><pre>".print_r($api->getActiveSubscribers($this->ListID), 1)."</pre>";
                $html .= "<h3><a href=\"#\">Unconfirmed Subscribers (latest ones)</a></h3><pre>".print_r($api->getUnconfirmedSubscribers($this->ListID), 1)."</pre>";
                $html .= "<h3><a href=\"#\">Bounced Subscribers (latest ones)</a></h3><pre>".print_r($api->getBouncedSubscribers($this->ListID), 1)."</pre>";
                $html .= "<h3><a href=\"#\">Unsubscribed Subscribers (latest ones)</a></h3><pre>".print_r($api->getUnsubscribedSubscribers($this->ListID), 1)."</pre>";
            } else {
                $html .= "<h2 style=\"color: red;\">ERROR: No Lists selected</h2";
            }
            Requirements::customScript($this->JSHackForPreSections(), "CampaignMonitorStats");
            $html .= "</div>";
            $this->Content = $html;
        } else {
            Security::permissionFailure($this, _t("CAMPAIGNMONITORSIGNUPPAGE.TESTFAILURE", "This function is only available for administrators"), 1);
        }
        return array();
    }

    /**
     * returns a bunch of stats about a campaign
     * IF the user is an admin AND a campaign is selected
     * @return String (html) | false
     */
    public function CampaignStats()
    {
        if (Permission::check("CMS_ACCESS_CMSMain")) {
            if ($this->campaign) {
                //run tests here
                $api = $this->getAPI();
                $html = "<div id=\"CampaignMonitorStats\">";
                $html .= "<h2>Campaign Stats</h2>";
                $html .= "<h3><a href=\"#\">Campaign: ".$this->campaign->Subject."</a></h3>";
                $html .= "<h3><a href=\"#\">Summary</a></h3><pre>".print_r($api->getSummary($this->campaign->CampaignID), 1)."</pre>";
                $html .= "<h3><a href=\"#\">Email Client Usage</a></h3><pre>".print_r($api->getEmailClientUsage($this->campaign->CampaignID), 1)."</pre>";
                $html .= "<h3><a href=\"#\">Unsubscribes</a></h3><pre>".print_r($api->getUnsubscribes($this->campaign->CampaignID), 1)."</pre>";
                Requirements::customScript($this->JSHackForPreSections(), "CampaignMonitorStats");
                $html .= "</div>";
                $this->Content = $html;
            }
        } else {
            return false;
        }
    }

    /**
     * action
     * @param HTTPRequest
     */
    public function resetsignup($request)
    {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: Session:: (case sensitive)
  * NEW: Controller::curr()->getRequest()->getSession()-> (COMPLEX)
  * EXP: If THIS is a controller than you can write: $this->getRequest(). You can also try to access the HTTPRequest directly. 
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
        Controller::curr()->getRequest()->getSession()->clear("CampaignMonitorStartForm_AjaxResult_".$this->ID);
        return array();
    }

    /**
     * removes all campaigns
     * so that they can be re-imported.
     */
    public function resetoldcampaigns()
    {
        if (!Permission::check("CMS_ACCESS_CMSMain")) {
            Security::permissionFailure($this, _t('Security.PERMFAILURE', ' This page is secured and you need CMS rights to access it. Enter your credentials below and we will send you right along.'));
        } else {
            DB::query("DELETE FROM \"CampaignMonitorCampaign\";");
            DB::query("DELETE FROM \"CampaignMonitorCampaign_Pages\";");
            die("old campaigns have been deleted");
        }
    }


    /**
     * @return String
     */
    private function JSHackForPreSections()
    {
        $js = <<<javascript
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
        return $js;
    }
}
