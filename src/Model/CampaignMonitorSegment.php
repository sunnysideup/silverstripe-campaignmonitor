<?php

namespace Sunnysideup\CampaignMonitor\Model;

use SilverStripe\ORM\DataObject;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;

/**
 * Class \Sunnysideup\CampaignMonitor\Model\CampaignMonitorSegment
 *
 * @property string $Title
 * @property string $SegmentID
 * @property string $ListID
 * @property int $CampaignMonitorSignupPageID
 * @method \Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage CampaignMonitorSignupPage()
 */
class CampaignMonitorSegment extends DataObject
{
    private static $table_name = 'CampaignMonitorSegment';

    private static $db = [
        'Title' => 'Varchar(64)',
        'SegmentID' => 'Varchar(32)',
        'ListID' => 'Varchar(32)',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
    ];

    private static $indexes = [
        'SegmentID' => true,
        'ListID' => true,
    ];

    private static $has_one = [
        'CampaignMonitorSignupPage' => CampaignMonitorSignupPage::class,
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
