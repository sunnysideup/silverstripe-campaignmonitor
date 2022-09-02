<?php

namespace Sunnysideup\CampaignMonitor\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;

class CampaignMonitorCreateLists extends BuildTask
{
    use CampaignMonitorApiTrait;

    protected $title = 'Create Campaign Monitor Sign-up Pages';

    protected $description = 'Goes through all the Campaign Monitor lists on Campaign Monitor and adds them to Silverstripe.';

    protected $enabled = true;

    protected $verbose = true;

    protected static $required_default_records_has_been_done = false;

    private static $class_name_for_page = CampaignMonitorSignupPage::class;

    private static $drop_down_list_for_campaign_monitor = [];

    public function setVerbose(bool $b): self
    {
        $this->verbose = $b;

        return $this;
    }

    public function run($request)
    {
        if (false === self::$required_default_records_has_been_done) {
            self::$required_default_records_has_been_done = true;
            $list = $this->getCampaignMonitorLists();
            $className = $this->Config()->get('class_name_for_page');
            $currentlyListed = $className::get()->column('ListID');
            if (empty($currentlyListed)) {
                $currentlyListed = [0 => 0];
            }

            $currentlyListed = array_combine($currentlyListed, $currentlyListed);
            foreach ($list as $listId => $listName) {
                unset($currentlyListed[$listId]);
                if ($this->verbose) {
                    if ($this->getCampaignMonitorPageForListIdExists($listId)) {
                        DB::alteration_message('Updating page for ' . $listId . ' - ' . $listName, 'updated');
                    } else {
                        DB::alteration_message('Creating page for ' . $listId . ' - ' . $listName, 'created');
                    }
                }

                $this->createCampaignMonitorPage($listId, $listName);
            }

            foreach ($currentlyListed as $listId) {
                $page = $className::get()->filter(['ListID' => $listId])->first();
                if ($page && $page->exists()) {
                    if ($this->verbose) {
                        DB::alteration_message('Archiving ' . $listId . ' sign-up page: ' . $page->Title, 'deleted');
                    }

                    $page->doArchive();
                }
            }
        }
    }

    /**
     * returns available list for client.
     *
     * @return array
     */
    protected function getCampaignMonitorLists()
    {
        if (empty(self::$drop_down_list_for_campaign_monitor)) {
            $array = [];
            self::$drop_down_list_for_campaign_monitor = [];
            $api = $this->getCMAPI();
            if ($api) {
                $lists = $api->getLists();
                if (is_array($lists) && count($lists)) {
                    foreach ($lists as $list) {
                        $array[$list->ListID] = $list->Name;
                    }
                }
            }

            self::$drop_down_list_for_campaign_monitor = $array;
        }

        return self::$drop_down_list_for_campaign_monitor;
    }

    protected function getCampaignMonitorPageForListId(string $listId): ?CampaignMonitorSignupPage
    {
        $className = $this->Config()->get('class_name_for_page');

        return $className::get()
            ->filter('ListID', $listId)->first();
    }

    protected function getCampaignMonitorPageForListIdExists(string $listId): ?bool
    {
        $className = $this->Config()->get('class_name_for_page');

        return (bool) $className::get()
            ->filter('ListID', $listId)->exists();
    }

    /**
     * returns ID for page.
     *
     * @return int
     */
    protected function createCampaignMonitorPage(string $listId, string $listName)
    {
        $className = $this->Config()->get('class_name_for_page');
        $page = $this->getCampaignMonitorPageForListId($listId);
        if (! $page) {
            $page = $className::create();
        }

        $page->ListID = $listId;
        $page->ShowInSearch = true;
        $page->ShowInMenus = false;
        if (! $page->Title) {
            $page->Title = 'Sign up for ' . $listName;
        }

        $page->MenuTitle = $listName;
        $page->writeToStage(Versioned::DRAFT);
        $page->publishRecursive();

        return $page->ID;
    }
}
