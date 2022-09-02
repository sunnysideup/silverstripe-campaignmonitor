<?php

namespace Sunnysideup\CampaignMonitor\Decorators;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;

/**
 * @author nicolaas [at] sunnysideup.co.nz
 */
class CampaignMonitorGroupDOD extends DataExtension
{
    /**
     * Is this a group for newsletter signing up.
     *
     * @return null|CampaignMonitorSignupPage
     */
    public function CampaignMonitorSubscriberGroupPage()
    {
        // @var CampaignMonitorSignupPage
        return CampaignMonitorSignupPage::get()->filter(['GroupID' => $this->getOwner()->ID])->first();
    }

    /**
     * Is this a group for newsletter signing up.
     */
    public function IsCampaignMonitorSubscriberGroup(): bool
    {
        return (bool) $this->CampaignMonitorSubscriberGroupPage();
    }

    public function updateCMSFields(FieldList $fields)
    {
        if ($this->IsCampaignMonitorSubscriberGroup()) {
            /** @var CampaignMonitorSignupPage $page */
            $page = $this->CampaignMonitorSubscriberGroupPage();
            if ($page instanceof CampaignMonitorSignupPage) {
                $value =
                DBField::create_field(
                    'HTMLText',
                    'Yes, <a href="' . $page->CMSEditLink() . '">
                    See ' . $page->Title . ' Page
                    </a>'
                );
            }
        } else {
            $value = 'no';
        }

        $fields->addFieldsToTab(
            'Root.Newsletter',
            [
                ReadonlyField::create(
                    'IsCampaignMonitorSubscriberGroupNice',
                    'Is newsletter group',
                    $value
                ),
            ]
        );
    }

    /**
     * @param Member $member (optional)
     *
     * @return mixed
     */
    public function canEdit($member = null)
    {
        if ($this->IsCampaignMonitorSubscriberGroup()) {
            return false;
        }

        return null;
    }
}
