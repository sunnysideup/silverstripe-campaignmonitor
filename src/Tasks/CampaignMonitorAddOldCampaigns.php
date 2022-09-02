<?php

namespace Sunnysideup\CampaignMonitor\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;

class CampaignMonitorAddOldCampaigns extends BuildTask
{
    protected $title = 'Retrieves a list of campaigns from Campaign Monitor.';

    protected $description = 'Retrieves a list of campaigns from Campaign Monitor for future display.';

    protected $verbose = true;

    public function setVerbose(bool $b)
    {
        $this->verbose = $b;
    }

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     *                                                   standard method
     */
    public function run($request)
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
                            DB::alteration_message('Adding ' . $campaign->Subject . ' sent ' . $campaign->SentDate, 'created');
                        }

                        $campaignMonitorCampaign = CampaignMonitorCampaign::create();
                    } elseif ($this->verbose) {
                        DB::alteration_message('already added ' . $campaign->Subject, 'edited');
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
                    DB::alteration_message('not adding ' . $campaign->Subject . ' because it has not been sent yet...', 'edited');
                }
            }
        } elseif ($this->verbose) {
            DB::alteration_message('there are no campaigns to be added', 'edited');
        }

        if ($this->verbose) {
            DB::alteration_message('<hr /><hr /><hr />Completed', 'edited');
        }
    }
}
