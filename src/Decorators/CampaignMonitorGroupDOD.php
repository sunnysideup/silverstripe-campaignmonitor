<?php

namespace Sunnysideup\CampaignMonitor\Decorators;

use SilverStripe\ORM\DataExtension;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;

/**
 * @author nicolaas [at] sunnysideup.co.nz
 *
 **/

class CampaignMonitorGroupDOD extends DataExtension
{
    /**
     * Is this a group for newsletter signing up.
     * @return boolean
     */
    public function IsCampaignMonitorSubscriberGroup()
    {
        return CampaignMonitorSignupPage::get()->filter(['GroupID' => $this->owner->ID])->count() ? true : false;
    }
}
