<?php

namespace Sunnysideup\CampaignMonitor\Api;

use Sunnysideup\CampaignMonitor\Api\Traits\Campaigns;
use Sunnysideup\CampaignMonitor\Api\Traits\Lists;
use Sunnysideup\CampaignMonitor\Api\Traits\Subscribers;
use Sunnysideup\CampaignMonitor\Api\Traits\Templates;
use Sunnysideup\CampaignMonitorApi\Api\CampaignMonitorAPIConnectorBase;

class CampaignMonitorAPIConnector extends CampaignMonitorAPIConnectorBase
{
    use Campaigns;
    use Lists;
    use Subscribers;
    use Templates;
}
