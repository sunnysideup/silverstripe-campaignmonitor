<?php

namespace Sunnysideup\CampaignMonitor\Model;

use DataObject;


/**
 * @author nicolaas [at] sunnysideup.co.nz
 *
 * @description: this represents a sub group of a list, otherwise known as a segment
 *
 **/

class CampaignMonitorSegment extends DataObject
{
    private static $db = array(
        "Title" => "Varchar(64)",
        "SegmentID" => "Varchar(32)",
        "ListID" => "Varchar(32)"
    );

    private static $summary_fields = array(
        "Title" => "Title"
    );

    private static $indexes = array(
        "SegmentID" => true,
        "ListID" => true
    );

    private static $has_one = array(
        "CampaignMonitorSignupPage" => "CampaignMonitorSignupPage"
    );


    public function canCreate($member = null)
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
