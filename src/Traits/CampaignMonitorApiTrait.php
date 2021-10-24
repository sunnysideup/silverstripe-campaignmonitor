<?php

namespace Sunnysideup\CampaignMonitor\Traits;

use SilverStripe\Core\Injector\Injector;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;

trait CampaignMonitorApiTrait
{
    private static $cm_api;

    /**
     * @return CampaignMonitorAPIConnector
     */
    public function getCMAPI()
    {
        if (null === self::$cm_api) {
            self::$cm_api = Injector::inst()->get(CampaignMonitorAPIConnector::class);
            self::$cm_api->init();
        }

        return self::$cm_api;
    }
}
