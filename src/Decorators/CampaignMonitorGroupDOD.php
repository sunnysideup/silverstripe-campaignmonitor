<?php

namespace Sunnysideup\CampaignMonitor\Decorators;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\FieldList;
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
     * @return bool
     */
    public function IsCampaignMonitorSubscriberGroup() : bool
    {
        return CampaignMonitorSignupPage::get()->filter(['GroupID' => $this->owner->ID])->count() ? true : false;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Newsletter',
            [
                ReadonlyField::create(
                    'IsCampaignMonitorSubscriberGroupNice',
                    'Is newsletter group',
                    $this->owner->IsCampaignMonitorSubscriberGroup() ?  'yes' : 'no'
                )
            ]
        );
    }

    public function canEdit($member = null)
    {
        if($this->IsCampaignMonitorSubscriberGroup()) {
            return false;
        }
    }
}
