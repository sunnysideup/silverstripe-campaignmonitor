<?php

namespace Sunnysideup\CampaignMonitor\Traits;

use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;

trait CampaignMonitorApiTrait
{
    private static $cm_api = null;

    /**
     * @return CampaignMonitorAPIConnector
     */
    public function getCMAPI()
    {
        if (self::$cm_api === null) {
            self::$cm_api = CampaignMonitorAPIConnector::create();
            self::$cm_api->init();
        }
        return self::$cm_api;
    }
}
