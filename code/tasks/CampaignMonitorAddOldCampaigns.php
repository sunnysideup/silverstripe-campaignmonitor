	<?php



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
        $api = CampaignMonitorAPIConnector::create();
        $api->init();
        $campaigns = $api->getCampaigns();
        if (is_array($campaigns)) {
            foreach ($campaigns as $campaign) {
                if ($campaign->SentDate) {
                    if (!CampaignMonitorCampaign::get()->filter(array("CampaignID" => $campaign->CampaignID))->count()) {
                        $campaignMonitorCampaign = new CampaignMonitorCampaign();
                        $campaignMonitorCampaign->CampaignID = $campaign->CampaignID;
                        $campaignMonitorCampaign->Subject = $campaign->Subject;
                        $campaignMonitorCampaign->Name = $campaign->Name;
                        $campaignMonitorCampaign->SentDate = $campaign->SentDate;
                        $campaignMonitorCampaign->WebVersionURL = $campaign->WebVersionURL;
                        $campaignMonitorCampaign->WebVersionTextURL = $campaign->WebVersionTextURL;
                        //$CampaignMonitorCampaign->ParentID = $this->ID;
                        $campaignMonitorCampaign->write();
                        if ($this->verbose) {
                            DB::alteration_message("Adding ".$campaign->Subject." sent ".$campaign->SentDate, "created");
                        }
                    } else {
                        if ($this->verbose) {
                            DB::alteration_message("already added ".$campaign->Subject, "edited");
                        }
                    }
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
