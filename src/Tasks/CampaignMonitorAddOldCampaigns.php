<?php

namespace Sunnysideup\CampaignMonitor\Tasks;





use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;



class CampaignMonitorAddOldCampaigns extends BuildTask
{
    protected $title = "Retrieves a list of campaigns from Campaign Monitor.";

    protected $description = "Retrieves a list of campaigns from Campaign Monitor for future display.";

    protected $verbose = true;

    public function setVerbose($b)
    {
        $this->verbose = $b;
    }

    /**
     * @param SS_HTTP_Request
     * standard method
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
                    $campaignMonitorCampaign = CampaignMonitorCampaign::get()->filter(array("CampaignID" => $campaign->CampaignID))->first();
                    if (!$campaignMonitorCampaign) {
                        if ($this->verbose) {
                            DB::alteration_message("Adding ".$campaign->Subject." sent ".$campaign->SentDate, "created");
                        }
                        $campaignMonitorCampaign = CampaignMonitorCampaign::create();
                    } else {
                        if ($this->verbose) {
                            DB::alteration_message("already added ".$campaign->Subject, "edited");
                        }
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
                } else {
                    if ($this->verbose) {
                        DB::alteration_message("not adding ".$campaign->Subject." because it has not been sent yet...", "edited");
                    }
                }
            }
        } else {
            if ($this->verbose) {
                DB::alteration_message("there are no campaigns to be added", "edited");
            }
        }
        if ($this->verbose) {
            DB::alteration_message("<hr /><hr /><hr />Completed", "edited");
        }
    }
}
