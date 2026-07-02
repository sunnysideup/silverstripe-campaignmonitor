<?php

namespace Sunnysideup\CampaignMonitor\Tasks;

use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;

class CampaignMonitorAddOldCampaigns extends BuildTask
{
    protected string $title = 'Retrieves a list of campaigns from Campaign Monitor.';

    protected static string $description = 'Retrieves a list of campaigns from Campaign Monitor for future display.';

    protected static string $commandName = 'campaignmonitor:add-old-campaigns';

    private static $segment = 'CampaignMonitorAddOldCampaigns';

    protected $verbose = true;

    public function setVerbose(bool $b)
    {
        $this->verbose = $b;
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $faultyOnes = CampaignMonitorCampaign::get()->where("(\"CampaignID\" = '' OR \"CampaignID\" IS NULL) AND (\"WebVersionURL\" IS NOT NULL && \"WebVersionURL\" <> '')");
        foreach ($faultyOnes as $faultyOne) {
            $faultyOne->delete();
        }

        $api = CampaignMonitorAPIConnector::create();
        $api->init();

        $campaigns = $api->getCampaigns();
        if (is_array($campaigns)) {
            foreach ($campaigns as $campaign) {
                if ($campaign->SentDate) {
                    $campaignMonitorCampaign = CampaignMonitorCampaign::get()->filter(['CampaignID' => $campaign->CampaignID])->first();
                    if (! $campaignMonitorCampaign) {
                        if ($this->verbose) {
                            $output->writeln('Adding ' . $campaign->Subject . ' sent ' . $campaign->SentDate);
                        }

                        $campaignMonitorCampaign = CampaignMonitorCampaign::create();
                    } elseif ($this->verbose) {
                        $output->writeln('already added ' . $campaign->Subject);
                    }

                    $campaignMonitorCampaign->HasBeenSent = true;
                    $campaignMonitorCampaign->CampaignID = $campaign->CampaignID;
                    $campaignMonitorCampaign->Subject = $campaign->Subject;
                    $campaignMonitorCampaign->Name = $campaign->Name;
                    $campaignMonitorCampaign->SentDate = $campaign->SentDate;
                    $campaignMonitorCampaign->WebVersionURL = $campaign->WebVersionURL;
                    $campaignMonitorCampaign->WebVersionTextURL = $campaign->WebVersionTextURL;
                    //$CampaignMonitorCampaign->ParentID = $this->ID;
                    $campaignMonitorCampaign->write();
                } elseif ($this->verbose) {
                    $output->writeln('not adding ' . $campaign->Subject . ' because it has not been sent yet...');
                }
            }
        } elseif ($this->verbose) {
            $output->writeln('there are no campaigns to be added');
        }

        if ($this->verbose) {
            $output->writeForHtml('<hr /><hr /><hr />Completed');
        }

        return Command::SUCCESS;
    }
}
