<?php

namespace Sunnysideup\CampaignMonitor\Model;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;

/**
 * Class \Sunnysideup\CampaignMonitor\Model\CampaignMonitorSubscriptionLog
 *
 * @property string $Action
 * @property string $CustomFields
 * @property string $CampaignMonitorOutcome
 * @property string $ErrorDescription
 * @property int $MemberID
 * @property int $ListID
 * @method \SilverStripe\Security\Member Member()
 * @method \Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage List()
 */
class CampaignMonitorSubscriptionLog extends DataObject
{
    private static $table_name = 'CampaignMonitorLog';

    private static $db = [
        'Action' => 'Enum("Subscribe, Unsubscribe, Other, Error", "Subscribe")',
        'CustomFields' => 'Text',
        'CampaignMonitorOutcome' => 'Enum("Success, Error, Not Recorded", "Not Recorded")',
        'ErrorDescription' => 'Text',
    ];

    private static $singular_name = 'Sign-Up Log';

    private static $plural_name = 'Sign-Ups Log';

    private static $default_sort = 'ID DESC';

    private static $summary_fields = [
        'CampaignMonitorOutcome' => 'Outcome',
        'Created.Nice' => 'When',
        'Member.Title' => 'Member',
        'Member.Email' => 'Email',
        'List.Title' => 'List',
        'Action' => 'Action',
    ];

    private static $has_one = [
        'Member' => Member::class,
        'List' => CampaignMonitorSignupPage::class,
    ];

    public static function log_attempt(Member $member, CampaignMonitorSignupPage $list, ?string $action = 'Subscribe', $customFields = []): int
    {
        $obj = self::create();
        $obj->CustomFields = serialize($customFields);
        $obj->Action = $action;
        $obj->ListID = $list->ID;
        $obj->MemberID = $member->ID;

        return $obj->write();
    }

    public static function log_outcome(int $id, bool $success = false, ?string $errorDescription = ''): int
    {
        $obj = self::get_by_id($id);
        $obj->CampaignMonitorOutcome = ($success ? 'Success' : 'Error');
        if (! $success) {
            $obj->ErrorDescription = CampaignMonitorAPIConnector::get_last_error_code() . ': ' . CampaignMonitorAPIConnector::get_last_error_description();
        }

        return $obj->write();
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $member = $this->Member();
        $list = $this->List();
        if ($member && $member->exists()) {
            $memberLink = DBField::create_field('HTMLText', '<a href="/admin/security/users/EditForm/field/users/item/' . $member->ID . '/edit/">' . $member->Email . '</a>');
        } else {
            $memberLink = 'Member could not be found (id = ' . $this->MemberID . ').';
        }

        if ($list && $list->exists()) {
            $listLink = DBField::create_field('HTMLText', '<a href="admin/pages/edit/show/' . $this->ListID . '/edit/">' . $this->List()->Title . '</a>');
        } else {
            $listLink = 'List could not be found (id = ' . $this->ListID . ').';
        }

        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('MemberLink', 'User', $memberLink),
                ReadonlyField::create('ListLink', 'List', $listLink),
            ]
        );

        return $fields;
    }

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
