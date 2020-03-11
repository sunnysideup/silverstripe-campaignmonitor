<?php

/**
 * @author nicolaas [at] sunnysideup.co.nz
 * TO DO: only apply the on afterwrite to people in the subscriber group.
 *
 **/

class CampaignMonitorMemberDOD extends DataExtension
{

    /**
     * name of the field to use for sign-ups
     * @var String
     */
    private static $campaign_monitor_signup_fieldname = "CampaignMonitorSubscriptions";

    /**
     * array of fields where the member value is set as the default for the
     * custom field ...
     * The should be like this
     *
     *     CustomFieldCode => MemberFieldOrMethod
     * @var array
     */
    private static $custom_fields_member_field_or_method_map = array();

    /**
     *
     *
     * @var null | CampaignMonitorAPIConnector
     *
     */
    private static $_api = null;

    /**
     *
     * @return CampaignMonitorAPIConnector
     */
    private function getCMAPI()
    {
        if (!self::$_api) {
            self::$_api = CampaignMonitorAPIConnector::create();
            self::$_api->init();
        }
        return self::$_api;
    }

    /**
     * returns a form field for signing up to all available lists
     * or if a list is provided, for that particular list.
     *
     * @param CampaignMonitorSignupPage | string | Null $listPage
     * @param string $fieldName
     * @param string $fieldTitle
     *
     * @return FormField
     */
    public function getCampaignMonitorSignupField($listPage = null, $fieldName = "", $fieldTitle = "")
    {
        if (!is_object($listPage)) {
            $listPage = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listPage))->first();
        }
        $field = null;
        if (!$fieldName) {
            $fieldName = Config::inst()->get("CampaignMonitorMemberDOD", "campaign_monitor_signup_fieldname");
        }
        $api = $this->getCMAPI();
        $currentValues = null;
        if ($listPage) {
            if ($listPage->ReadyToReceiveSubscribtions()) {
                $currentSelection = "Subscribe";
                $optionArray = array();
                $optionArray["Subscribe"] = _t("CampaignMonitorSignupPage.SUBSCRIBE_TO", "subscribe to")." ".$listPage->getListTitle();
                $optionArray["Unsubscribe"] = _t("CampaignMonitorSignupPage.UNSUBSCRIBE_FROM", "unsubscribe from ")." ".$listPage->getListTitle();
                if ($this->owner->exists()) {
                    if ($api->getSubscriberCanReceiveEmailsForThisList($listPage->ListID, $this->owner)) {
                        $currentValues = $api->getSubscriber($listPage->ListID, $this->owner);
                        //$currentSelection = "Unsubscribe";
                    }
                }
                if (!$fieldTitle) {
                    $fieldTitle = _t("CampaignMonitorSignupPage.SIGNUP_FOR", "Sign up for ")." ".$listPage->getListTitle();
                }
                $subscribeField = OptionsetField::create($fieldName, $fieldTitle, $optionArray, $currentSelection);
                $field = CompositeField::create($subscribeField);
                $field->addExtraClass("CMFieldsCustomFieldsHolder");
                //add custom fields
                $linkedMemberFields = Config::inst()->get("CampaignMonitorMemberDOD", "custom_fields_member_field_or_method_map");
                $customFields = $listPage->CampaignMonitorCustomFields()->filter(array("Visible" => 1));
                foreach ($customFields as $customField) {
                    $valueSet = false;
                    $customFormField = $customField->getFormField("CMCustomField");
                    if ($currentValues && isset($currentValues->CustomFields)) {
                        foreach ($currentValues->CustomFields as $customFieldObject) {
                            if ($customFieldObject->Key == $customField->Title) {
                                if ($value = $customFieldObject->Value) {
                                    $valueSet = true;
                                }
                                $customFormField->setValue($value);
                            }
                        }
                    }
                    if (isset($linkedMemberFields[$customFormField->Code]) && !$valueSet) {
                        $fieldOrMethod = $linkedMemberFields[$custom->Code];
                        if ($this->owner->hasMethod($fieldOrMethod)) {
                            $value = $this->owner->$fieldOrMethod();
                        } else {
                            $value = $this->owner->$fieldOrMethod;
                        }
                        if ($value) {
                            $customFormField->setValue($value);
                        }
                    }
                    $field->push($customFormField);
                }
            }
        } else {
            if (!$fieldTitle) {
                $fieldTitle = _t("CampaignMonitorMemberDOD.NEWSLETTERSIGNUP", "Newsletter sign-up");
            }
            $lists = CampaignMonitorSignupPage::get_ready_ones();
            $array = array();
            foreach ($lists as $list) {
                $array[$list->ListID] = $list->getListTitle();
            }
            if (count($array)) {
                $field = new CheckboxSetField(
                    $fieldName,
                    $fieldTitle,
                    $array
                );
                $field->setDefaultItems($this->owner->CampaignMonitorSignupPageIDs());
            }
        }
        if (!$field) {
            $field = ReadonlyField::create(
                $fieldName,
                $fieldTitle,
                _t("CampaignMonitorMemberDOD.NO_LISTS_AVAILABLE", "No lists available right now.  Please come back soon.")
            );
        }
        return $field;
    }


    /**
     * action subscription form
     * @param CampaignMonitorSignUpPage $page
     * @param Array $array
     * @param Form $form
     *
     * return string: can be subscribe / unsubscribe / error
     */
    public function processCampaignMonitorSignupField($listPage, $data, $form)
    {
        $typeOfAction = "unsubscribe";
        //many choices
        if (isset($data["SubscribeManyChoices"])) {
            $listPages = CampaignMonitorSignupPage::get_ready_ones();
            foreach ($listPages as $listPage) {
                if (isset($data["SubscribeManyChoices"][$listPage->ListID]) && $data["SubscribeManyChoices"][$listPage->ListID]) {
                    $this->owner->addCampaignMonitorList($listPage->ListID);
                    $typeOfAction = "subscribe";
                } else {
                    $this->owner->removeCampaignMonitorList($listPage->ListID);
                }
            }
        }
        //one choice
        elseif (isset($data["SubscribeChoice"])) {
            if ($data["SubscribeChoice"] == "Subscribe") {
                $customFields = $listPage->CampaignMonitorCustomFields()->filter(array("Visible" => 1));
                $customFieldsArray = array();
                foreach ($customFields as $customField) {
                    if (isset($data["CMCustomField".$customField->Code])) {
                        $customFieldsArray[$customField->Code] = $data["CMCustomField".$customField->Code];
                    }
                }
                $this->owner->addCampaignMonitorList($listPage->ListID, $customFieldsArray);
                $typeOfAction = "subscribe";
            } else {
                $this->owner->removeCampaignMonitorList($listPage->ListID);
            }
        } else {
            user_error("Subscriber field missing", E_USER_WARNING);
        }
        return $typeOfAction;
    }

    /**
     * immediately unsubscribe if you are logged in.
     * @param HTTPRequest
     */
    public function unsubscribe($request)
    {
        $member = Member::currentUser();
        if ($member) {
            $member->removeCampaignMonitorList($this->ListID);
            $this->Content = $member->Email." has been removed from this list: ".$this->getListTitle();
        } else {
            Security::permissionFailure($this, _t("CAMPAIGNMONITORSIGNUPPAGE.LOGINFIRST", "Please login first."));
        }
        return array();
    }

    /**
     * is this user currently signed up to one or more newsletters
     *
     * @return Boolean
     */
    public function IsCampaignMonitorSubscriber()
    {
        CampaignMonitorSignupPage::get_ready_ones()
            ->where("MemberID = ".$this->owner->ID)
            ->innerJoin("Group_Members", "CampaignMonitorSignupPage ON CampaignMonitorSignupPage.GroupID = Group_Members.GroupID")
            ->count() ? true : false;
    }

    /**
     * add to Group
     * add to CM database...
     * @param CampaignMonitorSignupPage | Int $listPage
     * @param array $customFields
     * @return Boolean - returns true on success
     */
    public function addCampaignMonitorList($listPage, $customFields = array())
    {
        $api = $this->getCMAPI();
        $outcome = 0;
        if (is_string($listPage)) {
            $listPage = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listPage))->first();
        }
        //internal database
        if ($listPage && $listPage->GroupID) {
            if ($gp = Group::get()->byID($listPage->GroupID)) {
                $groups = $this->getGroupsHackAlternative();
                if ($groups->count()) {
                    $this->addMemberToGroupHack($gp->ID);
                    $outcome++;
                }
            }
        }
        if ($listPage && $listPage->ListID) {
            if ($api->getSubscriber($listPage->ListID, $this->owner)) {
                if ($api->updateSubscriber(
                    $listPage->ListID,
                    $oldEmailAddress = "",
                    $this->owner,
                    $customFields,
                    $resubscribe = true,
                    $restartSubscriptionBasedAutoResponders = false
                )) {
                    $outcome++;
                }
            } elseif (!$api->addSubscriber(
                $listPage->ListID,
                $this->owner,
                $customFields,
                true,
                false
            )) {
                $outcome++;
            }
        }
        if ($outcome > 1) {
            return true;
        }
        return false;
    }

    /**
     *
     * remove from Group
     * remove from CM database...
     * @param CampaignMonitorSignupPage | Int $listPage
     * @return boolean returns true if successful.
     */
    public function removeCampaignMonitorList($listPage)
    {
        $api = $this->getCMAPI();
        $outcome = 0;
        if (is_string($listPage)) {
            $listPage = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listPage))->first();
        }
        if ($listPage->GroupID) {
            if ($gp = Group::get()->byID($listPage->GroupID)) {
                $groups = $this->getGroupsHackAlternative();
                if ($groups) {
                    $this->removeMemberFromGroupHack($gp->ID);
                    $outcome++;
                }
            }
        }
        if ($listPage->ListID) {
            if (!$api->unsubscribeSubscriber($listPage->ListID, $this->owner)) {
                $outcome++;
            }
        }
        if ($outcome > 1) {
            return true;
        }
        return false;
    }

    /**
     * $this->owner->Groups() throws errors with large numbers of members
     * This is the alternative, but it return a DataList rather than relationship list.
     * @return DataList
     */
    protected function getGroupsHackAlternative()
    {
        $sql = 'SELECT GroupID FROM Group_Members WHERE MemberID = '.$this->owner->ID;
        $rows = DB::query($sql);
        foreach ($rows as $row) {
            $ids[$row['GroupID']] = $row['GroupID'];
        }

        return Group::get()->filter(['ID' => $ids]);
    }

    /**
     * @param int $groupID
     * @return mixed
     */
    protected function removeMemberFromGroupHack(int $groupID)
    {
        $sql = 'DELETE FROM Group_Members WHERE MemberID = '.$this->owner->ID.' AND GroupID = '.$groupID;

        return DB::query($sql);
    }

    /**
     * @param int $groupID
     * @return mixed
     */
    protected function addMemberToGroupHack(int $groupID)
    {
        $sql = 'INSERT IGNORE INTO Group_Members (MemberID, GroupID) VALUES('.$this->owner->ID.','.$groupID.')';

        return DB::query($sql);
    }



    /**
     * returns a list of list IDs
     * that the user is currently subscribed to.
     *
     * @return Array
     */
    public function CampaignMonitorSignupPageIDs()
    {
        $api = $this->getCMAPI();
        $lists = $api->getListsForEmail($this->owner);
        $array = array();
        if ($lists && count($lists)) {
            foreach ($lists as $listArray) {
                if (in_array($listArray["SubscriberState"], array("Active", "Bounced"))) {
                    $array[$listArray["ListID"]] = $listArray["ListID"];
                }
            }
        }
        return $array;
    }
}
