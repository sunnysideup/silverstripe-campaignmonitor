<?php

declare(strict_types=1);

namespace Sunnysideup\CampaignMonitor\Model;

use Override;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;

/**
 * Class \Sunnysideup\CampaignMonitor\Model\CampaignMonitorSegment
 *
 * @property string $Title
 * @property string $SegmentID
 * @property string $ListID
 * @property int $CampaignMonitorSignupPageID
 * @method CampaignMonitorSignupPage CampaignMonitorSignupPage()
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

    #[Override]
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    #[Override]
    public function canDelete($member = null)
    {
        return false;
    }

    #[Override]
    public function canEdit($member = null)
    {
        return false;
    }
}
