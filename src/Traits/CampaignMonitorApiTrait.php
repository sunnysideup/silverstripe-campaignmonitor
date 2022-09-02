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
            if (self::$cm_api->isAvailable()) {
                self::$cm_api->init();
            } else {
                self::$cm_api = false;
            }
        }

        return self::$cm_api;
    }
}
