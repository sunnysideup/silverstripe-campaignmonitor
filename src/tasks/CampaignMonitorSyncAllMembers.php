<?php

/**
 * Moves all Members to a Campaign Monitor List
 *
 * Requires the Member Object
 * to have a method `IsBlackListed`
 * to unsubscribe anyone you do not want to add
 * to newsletter.
 *
 * You can extend this basic task to
 * add more functionality
 *
 */

class CampaignMonitorSyncAllMembers extends BuildTask
{


    /**
     * The default page of where the members are added.
     * @var Int
     */
    private static $mailing_list_id = "";

    protected $title = "Export Newsletter to Campaign Monitor";

    protected $description = "Moves all the Members to campaign monitor";

    /**
     * @var Boolean
     */
    protected $debug = true;

    /**
     * @var Boolean
     */
    protected $enabled = false;

    /**
     * @var array
     */
    protected $previouslyExported = array();

    /**
     * @var array
     */
    protected $previouslyUnsubscribedSubscribers = array();

    /**
     * @var array
     */
    protected $previouslyBouncedSubscribers = array();

    /**
     *
     */
    public function run($request)
    {
        increase_time_limit_to(3600);
        increase_memory_limit_to('5120M');
        $this->getUnsubscribedSubscribers();
        $this->getExistingFolkListed();
        $this->getBouncedSubscribers();
        DB::alteration_message("Number of active recipients already exported: ".count($this->previouslyExported), "created");
        DB::alteration_message("Number of recipients already unsubscribed: ".count($this->previouslyUnsubscribedSubscribers), "created");
        DB::alteration_message("Number of recipients already bounced: ".count($this->previouslyBouncedSubscribers), "created");

        if (Director::isLive()) {
            $this->debug = false;
        }
        if ($this->debug) {
            $limit = 20;
            $maxIterations = 20;
            DB::alteration_message("Running in debug mode going to check $maxIterations loops of $limit records.");
        } else {
            $limit = 400;
            $maxIterations = 1000000;
            DB::alteration_message("Running in live mode going to check $maxIterations loops of $limit records.");
        }
        $customFields = array();
        $memberArray = array();
        $unsubscribeArray = array();
        $alreadyCompleted = array();
        for ($i = 0; $i < $maxIterations; $i++) {
            $members = Member::get()
                ->limit($limit, $i * $limit);
            if ($this->debug) {
                $members = $members->sort("RAND()");
            }
            if ($members->count()) {
                foreach ($members as $member) {
                    if (isset($this->previouslyUnsubscribedSubscribers[$member->Email])) {
                        DB::alteration_message("already blacklisted: ".$member->Email, "deleted");
                    } elseif (isset($this->previouslyBouncedSubscribers[$member->Email])) {
                        DB::alteration_message("deleting bounced member: ".$member->Email, "deleted");
                        if (!$this->debug) {
                            $api->deleteSubscriber(Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"), $member->Email);
                        }
                    } else {
                        if (!isset($alreadyCompleted[$member->Email])) {
                            $alreadyCompleted[$member->Email] = true;
                            $customFields[$member->Email] = array(
                                "Email" => $member->Email,
                                "FirstName" =>  $member->FirstName,
                                "Surname" =>  $member->Surname
                            );
                            if (!$member instanceof Member) {
                                user_error("Member not instance of Member");
                            }
                            $memberArray[$member->Email] = $member;
                        }
                        if ($member->Email && $member->hasMethod("IsBlackListed") && $member->IsBlackListed()) {
                            $unsubscribeArray[$member->Email] = $member;
                            DB::alteration_message("Blacklisting: ".$member->Email, "deleted");
                        }
                    }
                }
                $this->exportNow($memberArray, $customFields, $unsubscribeArray);
                $customFields = array();
                $memberArray = array();
                $unsubscribeArray = array();
            } else {
                $i = $maxIterations + 1;
            }
        }
        DB::alteration_message("<h1>== THE END ==</h1>");
    }

    private static $_api = null;

    /**
     *
     * @return CampaignMonitorAPIConnector
     */
    public function getAPI()
    {
        if (!self::$_api) {
            self::$_api = CampaignMonitorAPIConnector::create();
            self::$_api->init();
        }
        return self::$_api;
    }

    /**
     * updates the previouslyExported variable
     * @return null
     */
    private function getExistingFolkListed()
    {
        $array = array();
        $api = $this->getAPI();
        for ($i = 1; $i < 100; $i++) {
            $list = $api->getActiveSubscribers(
                $listID = Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"),
                $daysAgo = 3650,
                $page = $i,
                $pageSize = 999,
                $sortByField = "Email",
                $sortDirection = "ASC"
            );
            if (isset($list->NumberOfPages) && $list->NumberOfPages) {
                if ($i > $list->NumberOfPages) {
                    $i = 999999;
                }
            }
            if (isset($list->Results)) {
                foreach ($list->Results as $obj) {
                    $finalCustomFields = array();
                    foreach ($obj->CustomFields as $customFieldObject) {
                        $finalCustomFields[str_replace(array("[", "]"), "", $customFieldObject->Key)] = $customFieldObject->Value;
                    }
                    $this->previouslyExported[$obj->EmailAddress] = $finalCustomFields;
                }
            } else {
                return;
            }
        }
    }


    /**
     * updates previouslyBouncedSubscribers variable
     *
     * @return null
     */
    private function getBouncedSubscribers()
    {
        $array = array();
        $api = $this->getAPI();
        for ($i = 1; $i < 100; $i++) {
            $list = $api->getBouncedSubscribers(
                $listID = Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"),
                $daysAgo = 3650,
                $page = $i,
                $pageSize = 999,
                $sortByField = "Email",
                $sortDirection = "ASC"
            );
            if (isset($list->NumberOfPages) && $list->NumberOfPages) {
                if ($i > $list->NumberOfPages) {
                    $i = 999999;
                }
            }
            if (isset($list->Results)) {
                foreach ($list->Results as $obj) {
                    $this->previouslyBouncedSubscribers[$obj->EmailAddress] = true;
                }
            } else {
                return;
            }
        }
    }

    /**
     * updates previouslyUnsubscribedSubscribers variable
     *
     * @return null
     */
    private function getUnsubscribedSubscribers()
    {
        $array = array();
        $api = $this->getAPI();
        for ($i = 1; $i < 100; $i++) {
            $list = $api->getUnsubscribedSubscribers(
                $listID = Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"),
                $daysAgo = 3650,
                $page = $i,
                $pageSize = 999,
                $sortByField = "Email",
                $sortDirection = "ASC"
            );
            if (isset($list->NumberOfPages) && $list->NumberOfPages) {
                if ($i > $list->NumberOfPages) {
                    $i = 999999;
                }
            }
            if (isset($list->Results)) {
                foreach ($list->Results as $obj) {
                    $this->previouslyUnsubscribedSubscribers[$obj->EmailAddress] = true;
                }
            } else {
                return;
            }
        }
    }

    /**
     * @param array $memberArray
     * @param array $customFields
     * @param array $unsubscribeArray
     *
     * @return null
     */
    private function exportNow($memberArray, $customFields, $unsubscribeArray)
    {
        $api = $this->getAPI();
        PWUpdateGetData::flush("<hr />", "deleted");
        if (count($memberArray)) {
            if (count($memberArray) == count($customFields)) {
                $finalCustomFields = array();
                foreach ($customFields as $email => $valuesArray) {
                    $updateDetails = false;
                    $alreadyListed = false;
                    if (isset($this->previouslyExported[$email])) {
                        $alreadyListed = true;
                        DB::alteration_message("".$email." is already listed");
                        foreach ($valuesArray as $key => $value) {
                            if ($key != "Email") {
                                if (!isset($this->previouslyExported[$email][$key])) {
                                    if ($value == "tba" || $value == "No" || strlen(trim($value)) < 1) {
                                        //do nothing
                                    } else {
                                        $updateDetails = true;
                                        DB::alteration_message(" - - - Missing value for $key - current value $value", "created");
                                    }
                                } elseif ($this->previouslyExported[$email][$key] != $value) {
                                    DB::alteration_message(" - - - Update for ".$email." for $key $value that is not the same as previous value: ".$this->previouslyExported[$email][$key], "created");
                                    $updateDetails = true;
                                }
                            }
                        }
                    } else {
                        DB::alteration_message("Adding entry: ".implode("; ", $customFields[$email]).".", "created");
                    }
                    $finalCustomFields[$email] = array();
                    $k = 0;
                    foreach ($valuesArray as $key => $value) {
                        $finalCustomFields[$email][$k]["Key"] = $key;
                        $finalCustomFields[$email][$k]["Value"] = $value;
                        $k++;
                    }
                    if ($updateDetails) {
                        if (!$this->debug) {
                            $api->updateSubscriber(
                                $listID = Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"),
                                $oldEmailAddress = $email,
                                $memberArray[$email],
                                $finalCustomFields[$email],
                                $resubscribe = true,
                                $restartSubscriptionBasedAutoResponders = false
                            );
                        }
                        unset($finalCustomFields[$email]);
                        unset($customFields[$email]);
                        unset($memberArray[$email]);
                    } elseif ($alreadyListed) {
                        unset($finalCustomFields[$email]);
                        unset($customFields[$email]);
                        unset($memberArray[$email]);
                    }
                }
                if (count($memberArray)) {
                    if (count($memberArray) == count($finalCustomFields)) {
                        DB::alteration_message("<h3>adding: ".count($memberArray)." subscribers</h3>", "created");
                        if (!$this->debug) {
                            $api->addSubscribers(Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"), $memberArray, $finalCustomFields, true, false, false);
                        }
                    } else {
                        DB::alteration_message("Error, memberArray (".count($memberArray).") count is not the same as finalCustomFields (".count($finalCustomFields).") count.", "deleted");
                    }
                } else {
                    DB::alteration_message("adding: ".count($memberArray)." subscribers");
                }
                foreach ($unsubscribeArray as $email => $member) {
                    DB::alteration_message("Now doing Blacklisting: ".$member->Email, "deleted");
                    if (!$this->debug) {
                        $api->unsubscribeSubscriber(Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"), $member);
                    }
                }
            } else {
                DB::alteration_message("Error, memberArray (".count($memberArray).") count is not the same as customFields (".count($customFields).") count.", "deleted");
            }
        } else {
            DB::alteration_message("adding: ".count($memberArray)." subscribers");
        }
    }
}
