<?php

namespace Sunnysideup\CampaignMonitor\Decorators;

use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\Forms\Fields\CampaignMonitorSignupField;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;

/**
 * @author nicolaas [at] sunnysideup.co.nz
 * TO DO: only apply the on afterwrite to people in the subscriber group.
 *
 **/

class CampaignMonitorMemberDOD extends DataExtension
{
    /**
     * name of the field to use for sign-ups
     * @var string
     */
    private static $campaign_monitor_signup_fieldname = 'CampaignMonitorSubscriptions';

    /**
     * name of the field to use for sign-ups
     * @var string
     */
    private static $campaign_monitor_allow_unsubscribe = true;

    /**
     * array of fields where the member value is set as the default for the
     * custom field ...
     * The should be like this
     *
     *     CustomFieldCode => MemberFieldOrMethod
     * @var array
     */
    private static $custom_fields_member_field_or_method_map = [];

    /**
     * @var CampaignMonitorAPIConnector|null
     */
    private static $_api = null;

    /**
     * returns a form field for signing up to all available lists
     * or if a list is provided, for that particular list.
     *
     * @param CampaignMonitorSignupPage | string | Null $listPage
     * @param string $fieldName
     * @param string $fieldTitle
     *
     * @return \SilverStripe\Forms\FormField
     */
    public function getCampaignMonitorSignupField($listPage = null, $fieldName = '', $fieldTitle = '')
    {
        $field = new CampaigMonitorSignUpField($fieldName, $fieldTitle);
        $field->setMember($this->owner);
        $field->setListPage($listPage);
        $field->getCampaignMonitorSignupField();

        return $field;
    }

    public function processCampaignMonitorSignupField($data, $form): string
    {
        $field = $this->getCampaignMonitorSignupField();

        return $field->processCampaignMonitorSignupField($data, $form);
    }

    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;
        $fields->addFieldsToTab(
            'Root.Newsletter',
            [
                ReadonlyField::create(
                    'IsCampaignMonitorSubscriberNice',
                    'Has subcribed to any list - ever?',
                    $this->IsCampaignMonitorSubscriber() ? 'yes' : 'no'
                ),
                ReadonlyField::create(
                    'CampaignMonitorSignupPageIDsNice',
                    'Currently Subscribed to',
                    implode(',', $this->CampaignMonitorSignupPageIDs())
                ),
            ]
        );

    }

    /**
     * remove from all lists...
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function unsubscribe($request, ?int $listId = 0)
    {
        $lists = CampaignMonitorSignupPage::get_ready_ones();
        foreach ($lists as $list) {
            if($listId === 0 || $list->ListID === $listId) {
                $this->owner->removeCampaignMonitorList($list->ListID);
            }
        }
    }

    /**
     * is this user currently signed up to one or more newsletters
     *
     * @return bool
     */
    public function IsCampaignMonitorSubscriber() : bool
    {
        CampaignMonitorSignupPage::get_ready_ones()
            ->where('MemberID = ' . $this->owner->ID)
            ->innerJoin('Group_Members', 'CampaignMonitorSignupPage ON CampaignMonitorSignupPage.GroupID = Group_Members.GroupID')
            ->count() ? true : false;
    }

    /**
     * add to Group
     * add to CM database...
     * @param CampaignMonitorSignupPage | Int $listPage
     * @param array $customFields
     * @return bool - returns true on success
     */
    public function addCampaignMonitorList($listPage, $customFields = []) : bool
    {
        $api = $this->getCMAPI();
        $stepsCompleted = 0;
        if (is_string($listPage)) {
            $listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $listPage])->first();
        }
        $a += $this->addToCampaignMonitorSecurityGroup($listPage);
        $b += $this->addToCampaignMonitor($listPage);

        if ($a && $b) {
            return true;
        }
        return false;
    }

    protected function addToCampaignMonitorSecurityGroup($listPage) : bool
    {
        //internal database
        if ($listPage && $listPage->GroupID) {
            if ($gp = Group::get()->byID($listPage->GroupID)) {
                $groups = $this->owner->Groups();
                if ($groups) {
                    $this->owner->Groups()->add($gp);
                    return true;
                }
            }
        }
        return false;
    }

    protected function addToCampaignMonitor($listPage) : bool
    {
        if ($listPage && $listPage->ListID) {
            $$errors = true;
            if ($this->isPartOfCampaignMonitorList($listPage)) {
                $errors = $api->updateSubscriber(
                    $listPage->ListID,
                    $this->owner,
                    $oldEmailAddress = '',
                    $customFields,
                    $resubscribe = true,
                    $restartSubscriptionBasedAutoResponders = false
                );
            } else {
                $errors = $api->addSubscriber(
                    $listPage->ListID,
                    $this->owner,
                    $customFields,
                    true,
                    false
                );
            }
            if(empty($errors)) {
                return true;
            }
        }
        return false;
    }

    protected function isPartOfCampaignMonitorList($listPage) : bool
    {
        return $api->getSubscriber($listPage->ListID, $this->owner);
    }

    /**
     * remove from Group
     * remove from CM database...
     * @param CampaignMonitorSignupPage | Int $listPage
     * @return bool returns true if successful.
     */
    public function removeCampaignMonitorList($listPage) : bool
    {
        $api = $this->getCMAPI();
        $outcome = 0;
        if (is_string($listPage)) {
            $listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $listPage])->first();
        }
        if ($listPage->GroupID) {
            if ($gp = Group::get()->byID($listPage->GroupID)) {
                $groups = $this->owner->Groups();
                if ($groups) {
                    $this->owner->Groups()->remove($gp);
                    $outcome++;
                }
            }
        }
        if ($listPage->ListID) {
            if (! $api->unsubscribeSubscriber($listPage->ListID, $this->owner)) {
                $outcome++;
            }
        }
        if ($outcome > 1) {
            return true;
        }
        return false;
    }

    /**
     * returns a list of list IDs
     * that the user is currently subscribed to.
     *
     * @return array
     */
    public function CampaignMonitorSignupPageIDs() : array
    {
        $api = $this->getCMAPI();
        $lists = $api->getListsForEmail($this->owner);
        $array = [];
        if ($lists && count($lists)) {
            foreach ($lists as $listArray) {
                if (in_array($listArray['SubscriberState'], ['Active', 'Bounced'], true)) {
                    $array[$listArray['ListID']] = $listArray['ListID'];
                }
            }
        }
        return $array;
    }

    /**
     * @return CampaignMonitorAPIConnector
     */
    private function getCMAPI()
    {
        if (! self::$_api) {
            self::$_api = CampaignMonitorAPIConnector::create();
            self::$_api->init();
        }
        return self::$_api;
    }
}
