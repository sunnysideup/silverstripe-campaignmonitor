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
use SilverStripe\Versioned\Versioned;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorSignupFieldProvider;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorSubscriptionLog;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;
/**
 * @author nicolaas [at] sunnysideup.co.nz
 * TO DO: only apply the on afterwrite to people in the subscriber group.
 *
 **/

class CampaignMonitorMemberDOD extends DataExtension
{

    use CampaignMonitorApiTrait;


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
    public function getCampaignMonitorSignupField($listPage = null, ?string $fieldName = '', ?string $fieldTitle = '')
    {
        $provider = $this->getCampaignMonitorSignupFieldProvider($listPage);
        return $provider->getCampaignMonitorSignupField($fieldName, $fieldTitle);
    }

    public function processCampaignMonitorSignupField($listPage, $data, $values): string
    {
        $provider = $this->getCampaignMonitorSignupFieldProvider($listPage);
        return $provider->processCampaignMonitorSignupField($data, $values);
    }

    protected function getCampaignMonitorSignupFieldProvider($listPage = null)
    {
        $provider = CampaignMonitorSignupFieldProvider::create();
        $provider->setMember($this->owner);
        $provider->setListPage($listPage);

        return $provider;
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

     */
    public function unsubscribeFromAllCampaignMonitorLists(?int $listId = 0)
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
        $stage = Versioned::get_stage() == Versioned::LIVE ? '_Live' : '';
        return CampaignMonitorSignupPage::get_ready_ones()
            ->where('MemberID = ' . $this->owner->ID)
            ->innerJoin('Group_Members', 'CampaignMonitorSignupPage'.$stage.'.GroupID = Group_Members.GroupID')
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
        if (is_string($listPage)) {
            $listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $listPage])->first();
        }

        CampaignMonitorSubscriptionLog ::log($this->owner, $listPage, 'Subscribe', $customFields);

        $a = $this->addToCampaignMonitorSecurityGroup($listPage, $customFields);
        $b = $this->addToCampaignMonitor($listPage, $customFields);
        $this->CampaignMonitorCustomFields = $customFields;

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

    protected function addToCampaignMonitor($listPage, ?array $customFields = []) : bool
    {
        if ($listPage && $listPage->ListID) {
            $errors = true;
            if ($this->isPartOfCampaignMonitorList($listPage)) {
                $errors = $this->getCMAPI()->updateSubscriber(
                    $listPage->ListID,
                    $this->owner,
                    $oldEmailAddress = '',
                    $customFields,
                    $resubscribe = true,
                    $restartSubscriptionBasedAutoResponders = false
                );
            } else {
                $errors = $this->getCMAPI()->addSubscriber(
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
        return $this->getCMAPI()->getSubscriber($listPage->ListID, $this->owner) ? true : false;
    }

    /**
     * remove from Group
     * remove from CM database...
     * @param CampaignMonitorSignupPage | Int $listPage
     * @return bool returns true if successful.
     */
    public function removeCampaignMonitorList($listPage) : bool
    {
        $outcome = 0;
        if (is_string($listPage)) {
            $listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $listPage])->first();
        }

        CampaignMonitorSubscriptionLog ::log($this->owner, $listPage, 'Unsubscribe');

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
            if (! $this->getCMAPI()->unsubscribeSubscriber($listPage->ListID, $this->owner)) {
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
        $lists = $this->getCMAPI()->getListsForEmail($this->owner);
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

}
