<?php

namespace Sunnysideup\CampaignMonitor\Decorators;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use SilverStripe\Versioned\Versioned;
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

    private static $has_many = [
        'CampaignMonitorSubscriptionLogs' => CampaignMonitorSubscriptionLog::class,
    ];

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

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'CampaignMonitorSubscriptionLogs',
        ]);
        $fields->addFieldsToTab(
            'Root.Newsletter',
            [
                ReadonlyField::create(
                    'IsCampaignMonitorSubscriberNice',
                    'Has subcribed to any list - ever?',
                    $this->IsCampaignMonitorSubscriber() ? 'yes' : 'no'
                ),
                ReadonlyField::create(
                    'CampaignMonitorSignedUpArrayNice',
                    'Currently Subscribed to',
                    implode(',', $this->CampaignMonitorSignedUpArray())
                ),
                GridField::create(
                    'CampaignMonitorSubscriptionLogs',
                    'Logs',
                    $this->owner->CampaignMonitorSubscriptionLogs(),
                    GridFieldConfig_RelationEditor::create()
                ),
            ]
        );
    }

    public function unsubscribeFromAllCampaignMonitorLists(?int $listId = 0)
    {
        $lists = CampaignMonitorSignupPage::get_ready_ones();
        foreach ($lists as $list) {
            if ($listId === 0 || $list->ListID === $listId) {
                $this->owner->removeCampaignMonitorList($list->ListID);
            }
        }
    }

    /**
     * is this user currently signed up to one or more newsletters
     *
     * @return bool
     */
    public function IsCampaignMonitorSubscriber(): bool
    {
        $stage = Versioned::get_stage() === Versioned::LIVE ? '_Live' : '';
        return CampaignMonitorSignupPage::get_ready_ones()
            ->where('MemberID = ' . $this->owner->ID)
            ->innerJoin('Group_Members', 'CampaignMonitorSignupPage' . $stage . '.GroupID = Group_Members.GroupID')
            ->count() ? true : false;
    }

    /**
     * add to Group
     * add to CM database...
     * @param CampaignMonitorSignupPage | Int $listPage
     * @param array $customFields
     * @return bool - returns true on success
     */
    public function addCampaignMonitorList($listPage, $customFields = []): bool
    {
        if (is_string($listPage)) {
            /** @var CampaignMonitorSignupPage */
            $listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $listPage])->first();
        } else {
            /** @var CampaignMonitorSignupPage */
            $listPage = $listPage;
        }

        $logId = CampaignMonitorSubscriptionLog ::log_attempt($this->owner, $listPage, 'Subscribe', $customFields);

        $successForGroups = $this->addToCampaignMonitorSecurityGroup($listPage);
        $successForCm = $this->addToCampaignMonitor($listPage, $customFields);

        CampaignMonitorSubscriptionLog::log_outcome($logId, $successForCm);

        if ($successForGroups && $successForCm) {
            return true;
        }
        return false;
    }

    /**
     * remove from Group
     * remove from CM database...
     * @param CampaignMonitorSignupPage | Int $listPage
     * @return bool returns true if successful.
     */
    public function removeCampaignMonitorList($listPage): bool
    {
        if (is_string($listPage)) {
            /** @var CampaignMonitorSignupPage */
            $listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $listPage])->first();
        } else {
            /** @var CampaignMonitorSignupPage */
            $listPage = $listPage;
        }

        $logId = CampaignMonitorSubscriptionLog ::log_attempt($this->owner, $listPage, 'Unsubscribe');
        $successForGroups = false;
        $successForCm = false;
        if ($listPage->GroupID) {
            if ($gp = Group::get()->byID($listPage->GroupID)) {
                $groups = $this->owner->Groups();
                if ($groups) {
                    $this->owner->Groups()->remove($gp);
                    $successForGroups = true;
                }
            }
        }
        if ($listPage->ListID) {
            $successForCm = $this->getCMAPI()->unsubscribeSubscriber($listPage->ListID, $this->owner);
            CampaignMonitorSubscriptionLog::log_outcome($logId, $successForCm);
        }
        if ($successForGroups && $successForCm) {
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
    public function CampaignMonitorSignedUpArray(): array
    {
        $lists = $this->getCMAPI()->getListsForEmail($this->owner);
        $array = [];
        if ($lists && count($lists)) {
            foreach ($lists as $listArray) {
                if (in_array($listArray->SubscriberState, ['Active', 'Bounced'], true)) {
                    $array[$listArray->ListID] = $listArray->ListName;
                }
            }
        }
        return $array;
    }

    protected function getCampaignMonitorSignupFieldProvider($listPage = null)
    {
        $provider = CampaignMonitorSignupFieldProvider::create();
        $provider->setMember($this->owner);
        $provider->setListPage($listPage);

        return $provider;
    }

    /**
     * returns true on success
     * @param  CampaignMonitorSignupPage $listPage
     *
     * @return bool
     */
    protected function addToCampaignMonitorSecurityGroup($listPage): bool
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
        } else {
            user_error('Error, no subscription page supplied for group.');
        }
        return false;
    }

    /**
     * returns true on success
     * @param  CampaignMonitorSignupPage $listPage
     * @param  array                     $customFields
     *
     * @return bool
     */
    protected function addToCampaignMonitor($listPage, ?array $customFields = []): bool
    {
        $success = false;
        if ($listPage && $listPage->ListID) {
            if ($this->isPartOfCampaignMonitorList($listPage)) {
                $success = $this->getCMAPI()->updateSubscriber(
                    $listPage->ListID,
                    $this->owner,
                    $oldEmailAddress = '',
                    $customFields,
                    $resubscribe = true,
                    $restartSubscriptionBasedAutoResponders = false
                ) ? true : false;
            } else {
                $success = $this->getCMAPI()->addSubscriber(
                    $listPage->ListID,
                    $this->owner,
                    $customFields,
                    true,
                    false
                ) ? true : false;
            }
        } else {
            user_error('Error, no subscription page supplied for campaign monitor subscription.');
        }
        return $success;
    }

    protected function isPartOfCampaignMonitorList($listPage): bool
    {
        return $this->getCMAPI()->getSubscriber($listPage->ListID, $this->owner) ? true : false;
    }
}
