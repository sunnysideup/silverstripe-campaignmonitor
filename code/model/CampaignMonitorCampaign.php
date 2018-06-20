<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaign extends DataObject
{

    /**
     *
     * @var array
     */
    private static $emogrifier_add_allowed_media_types = array(
        "screen"
    );

    /**
     *
     * @var array
     */
    private static $emogrifier_remove_allowed_media_types = array();

    /**
     *
     * @var string
     */
    private static $default_template = "CampaignMonitorCampaign";
    
    private static $db = array(
        "HasBeenSent" => "Boolean",
        "MessageFromNewsletterServer" => "Text",
        "CreateAsTemplate" => "Boolean",
        "CreateFromWebsite" => "Boolean",
        "CreatedFromWebsite" => "Boolean",
        "TemplateID" => "Varchar(40)",
        "CampaignID" => "Varchar(40)",
        "Name" => "Varchar(100)",
        "Subject" => "Varchar(100)",
        "FromName" => "Varchar(100)",
        "FromEmail" => "Varchar(100)",
        "ReplyTo" => "Varchar(100)",
        "SentDate" => "SS_Datetime",
        "WebVersionURL" => "Varchar(255)",
        "WebVersionTextURL" => "Varchar(255)",
        "Hide" => "Boolean",
        "Content" => "HTMLText",
        "Hash" => "Varchar(32)"
    );

    private static $indexes = array(
        "TemplateID" => true,
        "CampaignID" => true,
        "Hide" => true,
        "Hash" => true
    );

    private static $field_labels = array(
        "CreateFromWebsite" => "Create on newsletter server"
    );

    private static $has_one = array(
        "CampaignMonitorCampaignStyle" => "CampaignMonitorCampaignStyle"
    );

    private static $many_many = array(
        "Pages" => "CampaignMonitorSignupPage"
    );

    private static $searchable_fields = array(
        "Subject" => "PartialMatchFilter",
        "Content" => "PartialMatchFilter",
        "Hide" => "ExactMatch"
    );

    private static $summary_fields = array(
        "Subject" => "Subject",
        "SentDate" => "Sent Date"
    );

    private static $singular_name = "Campaign";

    private static $plural_name = "Campaigns";

    private static $default_sort = "Hide ASC, SentDate DESC";

    public function canDelete($member = null)
    {
        return $this->HasBeenSentCheck() ? false : parent::canDelete($member);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        //readonly
        $fields->makeFieldReadonly("MessageFromNewsletterServer");
        $fields->makeFieldReadonly("CampaignID");
        $fields->makeFieldReadonly("TemplateID");
        $fields->makeFieldReadonly("WebVersionURL");
        $fields->makeFieldReadonly("WebVersionTextURL");
        $fields->makeFieldReadonly("SentDate");
        $fields->makeFieldReadonly("HasBeenSent");
        $fields->makeFieldReadonly("Hash");
        //removed
        $fields->removeFieldFromTab("Root.Main", "CreatedFromWebsite");
        $fields->removeFieldFromTab("Root.Main", "CreatedTemplateFromWebsite");
        $fields->removeFieldFromTab("Root.Main", "SecurityCode");
        //pages
        $pages = CampaignMonitorSignupPage::get()->map("ID", "Title")->toArray();
        $fields->removeFieldFromTab("Root.Main", "Pages");
        $fields->replaceField("CreateAsTemplate", new OptionsetField("CreateAsTemplate", "Type", array(0 => "Create as Campaign", 1 => "Create as Template")));
        if (count($pages)) {
            $fields->addFieldToTab("Root.Pages", new CheckboxSetField("Pages", "Shown on the following pages ...", $pages));
        }
        if ($this->ExistsOnCampaignMonitorCheck()) {
            $fields->removeFieldFromTab("Root.Main", "CreateAsTemplate");
            if (!$this->CreateAsTemplate) {
                $fields->removeFieldFromTab("Root.Main", "CreateFromWebsite");
                if (!$this->HasBeenSentCheck()) {
                    $fields->addFieldToTab("Root.Main", new LiteralField("CreateFromWebsiteRemake", "<h2>To edit this newsletter, please first delete it from your newsletter server</h2>"), "CampaignID");
                }
            }
            $fields->removeFieldFromTab("Root.Main", "Hash");
            $fields->removeFieldFromTab("Root.Main", "CampaignMonitorCampaignStyleID");

            $fields->makeFieldReadonly("Name");
            $fields->makeFieldReadonly("Subject");
            $fields->makeFieldReadonly("FromName");
            $fields->makeFieldReadonly("FromEmail");
            $fields->makeFieldReadonly("ReplyTo");
            $fields->makeFieldReadonly("SentDate");
            $fields->makeFieldReadonly("WebVersionURL");
            $fields->makeFieldReadonly("WebVersionTextURL");
            $fields->makeFieldReadonly("Content");
        } else {
            $this->CampaignID = null;
            $this->TemplateID = null;
        }
        if ($this->HasBeenSentCheck()) {
            $fields->addFieldToTab("Root.Main", new LiteralField("Link", "<h2><a target=\"_blank\" href=\"".$this->Link()."\">Link</a></h2>"), "CampaignID");
        } else {
            $fields->removeFieldFromTab("Root.Main", "Hide");
            if ($this->exists()) {
                if ($this->ExistsOnCampaignMonitorCheck()) {
                    $fields->removeFieldFromTab("Root.Main", "CreateFromWebsite");
                } else {
                    $fields->addFieldToTab("Root.Main", new LiteralField("PreviewLink", "<h2><a target=\"_blank\" href=\"".$this->PreviewLink()."\">Preview Link</a></h2>"), "CampaignID");
                }
            } else {
                $fields->removeFieldFromTab("Root.Main", "CreateFromWebsite");
            }
        }
        return $fields;
    }

    /**
     * returns link to view campaign
     * @var return
     */
    public function Link($action = "")
    {
        if ($page = $this->Pages()->First()) {
            $link = $page->Link("viewcampaign".$action."/".$this->ID."/");
            return Director::absoluteURL($link);
        }
        return "#";
    }

    /**
     * returns link to view preview campaign
     * this link is used to create templates / campaigns on Campaign Monitor
     * @var return
     */
    public function PreviewLink($action = "")
    {
        if ($page = $this->Pages()->First()) {
            $link = $page->Link("previewcampaign".$action."/".$this->ID."/?hash=".$this->Hash);
            return Director::absoluteURL($link);
        }
        return "";
    }
    
    /**
     * html for newsletter to be created
     * @var return
     */
    public function getNewsletterContent()
    {
        $extension = $this->extend("updateNewsletterContent", $content);
        if (is_array($extension) && count($extension)) {
            return $extension[0];
        }
        $html = "";
        if (class_exists('\Pelago\Emogrifier')) {
            $allCSS = "";
            $cssFileLocations = $this->getCSSFileLocations();
            foreach ($cssFileLocations as $cssFileLocation) {
                $cssFileHandler = fopen($cssFileLocation, 'r');
                $allCSS .= fread($cssFileHandler, filesize($cssFileLocation));
                fclose($cssFileHandler);
            }
            $isThemeEnabled = Config::inst()->get('SSViewer', 'theme_enabled');
            if (!$isThemeEnabled) {
                Config::inst()->update('SSViewer', 'theme_enabled', true);
            }
            Requirements::clear();
            $templateName = $this->getRenderWithTemplate();
            $html = $this->renderWith($templateName);
            if (!$isThemeEnabled) {
                Config::inst()->update('SSViewer', 'theme_enabled', false);
            }
            $emogrifier = new \Pelago\Emogrifier($html, $allCSS);
            $addMediaTypes = $this->Config()->get("emogrifier_add_allowed_media_types");
            foreach ($addMediaTypes as $type) {
                //$emogrifier->addAllowedMediaType($type);
            }
            $removeMediaTypes = $this->Config()->get("emogrifier_remove_allowed_media_types");
            foreach ($removeMediaTypes as $type) {
                $emogrifier->removeAllowedMediaType($type);
            }
            $html = $emogrifier->emogrify();
        } else {
            user_error("Please include Emogrifier module");
        }
        return $html;
    }

    /**
     * provide template used for RenderWith
     * @return string
     */
    public function getRenderWithTemplate()
    {
        if ($style = $this->CampaignMonitorCampaignStyle()) {
            if ($style->exists() && $style->TemplateName) {
                return $style->TemplateName;
            }
        }
        return $this->Config()->get("default_template");
    }

    /**
     * @return array
     */
    protected function getCSSFileLocations()
    {
        if ($style = $this->CampaignMonitorCampaignStyle()) {
            return $style->getCSSFilesAsArray();
        }
        return array();
    }

    /**
     * @return array
     */
    public function getHTMLContent()
    {
        if ($style = $this->CampaignMonitorCampaignStyle()) {
            return $style->getHTMLContent($this);
        }
        return $this->renderWith("CampaignMonitorCampaign");
    }

    protected $countOfWrites = 0;

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->Hash) {
            $this->Hash = substr(hash("md5", uniqid()), 0, 7);
        }
        if (!$this->ExistsOnCampaignMonitorCheck($forceRecheck = true)) {
            if ($this->CreateAsTemplate) {
                $this->TemplateID = null;
            } else {
                $this->CampaignID = null;
            }
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->Pages()->count() == 0) {
            if ($page = CampaignMonitorSignupPage::get()->first()) {
                $this->Pages()->add($page);
            }
        }
        $this->countOfWrites++;
        if (!$this->ExistsOnCampaignMonitorCheck($forceRecheck = true) && $this->CreateFromWebsite && $this->countOfWrites < 3) {
            $api = $this->getAPI();
            if ($this->CreateAsTemplate) {
                if ($this->TemplateID) {
                    $api->updateTemplate($this, $this->TemplateID);
                } else {
                    $api->createTemplate($this);
                }
            } else {
                $api->createCampaign($this);
            }
        }
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        if ($this->HasBeenSentCheck()) {
            //do nothing
        } else {
            if ($this->ExistsOnCampaignMonitorCheck($forceRecheck = true)) {
                $api = $this->getAPI();
                if ($this->CreateAsTemplate) {
                    $api->deleteTemplate($this->TemplateID);
                } else {
                    $api->deleteCampaign($this->CampaignID);
                }
            }
        }
    }

    private static $_api = null;

    /**
     *
     * @return CampaignMonitorAPIConnector
     */
    protected function getAPI()
    {
        if (!self::$_api) {
            self::$_api = CampaignMonitorAPIConnector::create();
            self::$_api->init();
        }
        return self::$_api;
    }

    private $_hasBeenSent = null;

    public function HasBeenSentCheck()
    {
        //lazy check
        if ($this->HasBeenSent || $this->WebVersionURL) {
            return true;
        }
        //real check
        if ($this->_hasBeenSent === null) {
            if (!$this->CampaignID) {
                $this->_hasBeenSent = false;
            } elseif (!$this->HasBeenSent) {
                $api = $this->getAPI();
                $result = $this->api->getCampaigns();
                if (isset($result)) {
                    foreach ($result as $key => $campaign) {
                        if ($this->CampaignID == $campaign->CampaignID) {
                            $this->HasBeenSent = true;
                            $this->HasBeenSent->write();
                            $this->_hasBeenSent = true;
                            break;
                        }
                    }
                }
            } else {
                $this->_hasBeenSent = $this->HasBeenSent;
            }
        }
        return $this->_hasBeenSent;
    }

    private $_existsOnCampaignMonitorCheck = null;

    /**
     * checks if the template and/or the campaign exists
     * @return boolean
     */
    public function ExistsOnCampaignMonitorCheck($forceRecheck = false)
    {
        //lazy check
        if ($this->HasBeenSent) {
            return true;
        }
        //real check
        if ($this->_existsOnCampaignMonitorCheck === null || $forceRecheck) {
            $this->_existsOnCampaignMonitorCheck = false;
            if ($this->CreateAsTemplate) {
                $field = "TemplateID";
                $apiMethod1 = "getTemplates";
                $apiMethod2 = "";
            } else {
                $field = "CampaignID";
                $apiMethod1 = "getDrafts";
                $apiMethod2 = "getCampaigns";
            }
            if (!$this->$field) {
                //do nothing
            } else {
                $api = $this->getAPI();
                //check drafts
                $result = $this->api->$apiMethod1();
                if (isset($result)) {
                    foreach ($result as $key => $campaign) {
                        if ($this->$field == $campaign->$field) {
                            $this->_existsOnCampaignMonitorCheck = true;
                            break;
                        }
                    }
                } elseif ($apiMethod2) {
                    //check sent ones
                    $result = $this->api->$apiMethod2();
                    if (isset($result)) {
                        foreach ($result as $key => $campaign) {
                            if ($this->$field == $campaign->$field) {
                                $this->_existsOnCampaignMonitorCheck = true;
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $this->_existsOnCampaignMonitorCheck;
    }
}
