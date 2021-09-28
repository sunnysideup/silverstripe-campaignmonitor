<?php

namespace Sunnysideup\CampaignMonitor\Api;

use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnectorBase;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
use Sunnysideup\CampaignMonitor\Api\Traits\Campaigns;
use Sunnysideup\CampaignMonitor\Api\Traits\Lists;
use Sunnysideup\CampaignMonitor\Api\Traits\Subscribers;
use Sunnysideup\CampaignMonitor\Api\Traits\Templates;

class CampaignMonitorAPIConnector extends CampaignMonitorAPIConnectorBase
{
    use Campaigns;
    use Lists;
    use Subscribers;
    use Templates;
}
