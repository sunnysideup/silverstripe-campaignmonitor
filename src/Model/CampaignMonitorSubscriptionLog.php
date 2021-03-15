<?php

namespace Sunnysideup\CampaignMonitor\Model;

use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;

/**
 * @author nicolaas [at] sunnysideup.co.nz
 *
 * @description: this represents a sub group of a list, otherwise known as a segment
 *
 **/

class CampaignMonitorSubscriptionLog extends DataObject
{
    private static $table_name = 'CampaignMonitorLog';

    public static function log(Member $member, CampaignMonitorSignupPage $list, ?string $action = 'Subscribe', $customFields = [])
    {
        $obj = self::create();
        $obj->CustomFields = serialize($customFields);
        $obj->Action = $action;
        $obj->ListID = $list->ID;
        $obj->MemberID = $member->ID;
        $obj->write();
    }

    private static $db = [
        'CustomFields' => 'Varchar(64)',
        'Action' => 'Enum("Subscribe, Unsubscribe, Other, Error", "Subscribe")',
    ];


    private static $summary_fields = [
        'Title' => 'Title',
        'List' => CampaignMonitorSignupPage::class,
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return false;
    }
}
