<?php

namespace Sunnysideup\CampaignMonitor\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorSubscriptionLog;
use Sunnysideup\CampaignMonitor\Tasks\CampaignMonitorCreateLists;

/**
 * Class \Sunnysideup\CampaignMonitor\Admin\CampaignMonitorSignupPageAdmin
 *
 */
class CampaignMonitorSignupPageAdmin extends ModelAdmin
{
    private static $managed_models = [
        CampaignMonitorSignupPage::class,
        CampaignMonitorSubscriptionLog::class,
    ];

    private static $url_segment = 'campaign-monitor';

    private static $menu_title = 'Campaign Monitor';

    private static $menu_icon_class = 'font-icon-menu-security';

    protected function init()
    {
        parent::init();
        $request = Injector::inst()->get(HTTPRequest::class);
        $session = $request->getSession();
        $time = (int) $session->get('CampaignMonitorSignupPageAdminINIT') - 0;
        if ($time < time() - 3600 || isset($_GET['flush'])) {
            $session->set('CampaignMonitorSignupPageAdminINIT', time());
            (new CampaignMonitorCreateLists())
                ->setVerbose(false)
                ->run(null);
        }
    }
}
