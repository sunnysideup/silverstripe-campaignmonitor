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
 * @todoonly apply the on afterwrite to people in the subscriber group.
 */
class CampaignMonitorMemberDOD extends DataExtension
{
    use CampaignMonitorApiTrait;

    private static $db = [
        'CM_PermissionToTrack' => 'Enum("Yes, No, Unchanged", "Unchanged")',
    ];

    private static $has_many = [
        'CampaignMonitorSubscriptionLogs' => CampaignMonitorSubscriptionLog::class,
    ];

    /**
     * returns a form field for signing up to all available lists
     * or if a list is provided, for that particular list.
     *
     * @param null|CampaignMonitorSignupPage|string $listPage
     * @param string                                $fieldName
     * @param string                                $fieldTitle
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
                    $this->getOwner()->CampaignMonitorSubscriptionLogs(),
                    GridFieldConfig_RelationEditor::create()
                ),
            ]
        );
    }

    public function unsubscribeFromAllCampaignMonitorLists(?int $listId = 0)
    {
        $lists = CampaignMonitorSignupPage::get_ready_ones();
        foreach ($lists as $list) {
            if (0 === $listId || $list->ListID === $listId) {
                $this->getOwner()->removeCampaignMonitorList($list->ListID);
            }
        }
    }

    /**
     * is this user currently signed up to one or more newsletters.
     */
    public function IsCampaignMonitorSubscriber(): bool
    {
        $stage = Versioned::LIVE === Versioned::get_stage() ? '_Live' : '';

        return (bool) CampaignMonitorSignupPage::get_ready_ones()
            ->where('MemberID = ' . $this->getOwner()->ID)
            ->innerJoin('Group_Members', 'CampaignMonitorSignupPage' . $stage . '.GroupID = Group_Members.GroupID')
            ->count()
        ;
    }

    /**
     * add to Group
     * add to CM database...
     *
     * @param CampaignMonitorSignupPage|string $listPage
     * @param array                            $customFields
     *
     * @return bool - returns true on success
     */
    public function addCampaignMonitorList($listPage, $customFields = []): bool
    {
        if (is_string($listPage)) {
            /** @var CampaignMonitorSignupPage $listPage */
            $listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $listPage])->first();
        }

        $logId = CampaignMonitorSubscriptionLog::log_attempt($this->owner, $listPage, 'Subscribe', $customFields);

        $successForGroups = $this->addToCampaignMonitorSecurityGroup($listPage);
        $successForCm = $this->addToCampaignMonitor($listPage, $customFields);

        CampaignMonitorSubscriptionLog::log_outcome($logId, $successForCm);

        return $successForGroups && $successForCm;
    }

    /**
     * remove from Group
     * remove from CM database...
     *
     * @param CampaignMonitorSignupPage|string $listPage
     *
     * @return bool returns true if successful
     */
    public function removeCampaignMonitorList($listPage): bool
    {
        if (! ($listPage instanceof CampaignMonitorSignupPage)) {
            /** @var CampaignMonitorSignupPage $listPage */
            $listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => (string) $listPage])->first();
        }

        $logId = CampaignMonitorSubscriptionLog::log_attempt($this->owner, $listPage, 'Unsubscribe');
        $successForGroups = false;
        $successForCm = false;
        if ($listPage->GroupID) {
            if ($gp = Group::get()->byID($listPage->GroupID)) {
                $groups = $this->getOwner()->Groups();
                if ($groups) {
                    $this->getOwner()->Groups()->remove($gp);
                    $successForGroups = true;
                }
            }
        }
        if ($listPage->ListID) {
            $successForCm = $this->getCMAPI()->unsubscribeSubscriber($listPage->ListID, $this->owner);
            CampaignMonitorSubscriptionLog::log_outcome($logId, $successForCm);
        }

        return $successForGroups && $successForCm;
    }

    /**
     * returns a list of list IDs
     * that the user is currently subscribed to.
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
     * returns true on success.
     *
     * @param CampaignMonitorSignupPage $listPage
     */
    protected function addToCampaignMonitorSecurityGroup($listPage): bool
    {
        //internal database
        if ($listPage && $listPage->GroupID) {
            if ($gp = Group::get()->byID($listPage->GroupID)) {
                $groups = $this->getOwner()->Groups();
                if ($groups) {
                    $this->getOwner()->Groups()->add($gp);

                    return true;
                }
            }
        } else {
            user_error('Error, no subscription page supplied for group.');
        }

        return false;
    }

    /**
     * returns true on success.
     *
     * @param CampaignMonitorSignupPage $listPage
     * @param array                     $customFields
     */
    protected function addToCampaignMonitor($listPage, ?array $customFields = []): bool
    {
        $success = false;
        if ($listPage && $listPage->ListID) {
            if ($this->isPartOfCampaignMonitorList($listPage)) {
                $success = (bool) $this->getCMAPI()->updateSubscriber(
                    $listPage->ListID,
                    $this->owner,
                    $oldEmailAddress = '',
                    $customFields,
                    $resubscribe = true,
                    $restartSubscriptionBasedAutoResponders = false
                );
            } else {
                $success = (bool) $this->getCMAPI()->addSubscriber(
                    $listPage->ListID,
                    $this->owner,
                    $customFields,
                    true,
                    false
                );
            }
        } else {
            user_error('Error, no subscription page supplied for campaign monitor subscription.');
        }

        return $success;
    }

    protected function isPartOfCampaignMonitorList($listPage): bool
    {
        return (bool) $this->getCMAPI()->getSubscriber($listPage->ListID, $this->owner);
    }
}
