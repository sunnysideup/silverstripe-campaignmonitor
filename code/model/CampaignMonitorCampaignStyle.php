<?php

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaignStyle extends DataObject
{
    private static $db = array(
        "Title" => "Varchar(100)",
        "TemplateName" => "Varchar(200)",
        "CSSFiles" => "Text"
    );

    private static $indexes = array(
        "Title" => true
    );

    private static $has_many = array(
        "CampaignMonitorCampaigns" => "CampaignMonitorCampaign"
    );

    private static $searchable_fields = array(
        "Title" => "PartialMatchFilter"
    );

    private static $summary_fields = array(
        "Title" => "Title"
    );

    private static $singular_name = "Campaign Template";

    private static $plural_name = "Campaign Templates";

    private static $default_template = "CampaignMonitorCampaign";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab("Root.Debug", TextField::create("TemplateName"));
        $fields->addFieldToTab("Root.Debug", ReadonlyField::create("FileLocation"));
        $fields->addFieldToTab("Root.Debug", ReadonlyField::create("CSSFiles"));
        $fields->addFieldToTab("Root.Debug", ReadonlyField::create("CampaignMonitorCampaigns", "Used in ", implode(",", $this->CampaignMonitorCampaigns()->map()->toArray())));
        $fields->removeFieldFromTab("Root", "CampaignMonitorCampaigns");
        return $fields;
    }

    public function canCreate($member = null)
    {
        return false;
    }

    /**
     * @return array
     */
    public function getFoldersToSearch()
    {
        $array = array(
            Director::baseFolder() ."/".SSViewer::get_theme_folder()."_campaignmonitor/templates/Email/",
            Director::baseFolder()."/campaignmonitor/templates/Email/"
        );
        foreach ($array as $key => $folder) {
            if (!file_exists($folder)) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * @return array
     */
    public function getCSSFoldersToSearch()
    {
        $array = array(
            Director::baseFolder() ."/".SSViewer::get_theme_folder()."_campaignmonitor/css/",
            Director::baseFolder()."/campaignmonitor/css/"

        );
        foreach ($array as $key => $folder) {
            if (!file_exists($folder)) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     *
     * @return string | null
     */
    public function getFileLocation()
    {
        if (!$this->TemplateName) {
            $this->TemplateName = "CampaignMonitorCampaign";
        }
        foreach ($this->getFoldersToSearch() as $folder) {
            $fileLocation = $folder.$this->TemplateName.".ss";
            if (file_exists($fileLocation)) {
                return $fileLocation;
            } else {
                //just try the next one ...
            }
        }
        user_error("can not find template, last one tried: $fileLocation");
    }

    public function getCSSFiles()
    {
        return implode(", ", $this->getCSSFilesAsArray());
    }

    public function getCSSFilesAsArray()
    {
        $dom = new DOMDocument();
        $cssFiles = array();
        $fileLocation = $this->getFileLocation();
        if ($fileLocation) {
            @$dom->loadHTMLFile($fileLocation);
            $linkTags = $dom->getElementsByTagName('link');
            foreach ($linkTags as $linkTag) {
                if (strtolower($linkTag->getAttribute("rel")) == "stylesheet") {
                    $file = Director::baseFolder()."/".$linkTag->getAttribute("href");
                    if (file_exists($file)) {
                        $cssFiles[$file] = $file;
                    } else {
                        user_error("can find css file $file");
                    }
                }
                 // if $link_tag rel == stylesheet
                 //   get href value and load CSS
            }
        } else {
            user_error("Can not find template file");
        }
        if (count($cssFiles) == 0) {
            foreach ($this->getCSSFoldersToSearch() as $folder) {
                $file = $folder."CampaignMonitorCampaign.css";
                if (file_exists($file)) {
                    $cssFiles[$file] = $file;
                    break;
                }
            }
        }
        return $cssFiles;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $templates = array();
        foreach ($this->getFoldersToSearch() as $folder) {
            $finder = new SS_FileFinder();
            $finder->setOption('name_regex', '/^.*\.ss$/');
            $found = $finder->find($folder);
            foreach ($found as $key => $value) {
                $template = pathinfo($value);
                $templates[$template['filename']] = $template['filename'];
            }
        }
        foreach ($templates as $template) {
            $filter = array("TemplateName" => $template);
            $obj = CampaignMonitorCampaignStyle::get()->filter($filter)->first();
            if (!$obj) {
                $obj = CampaignMonitorCampaignStyle::create($filter+array("Title" => $template));
                $obj->write();
            }
        }
        $excludes = $obj = CampaignMonitorCampaignStyle::get()->exclude(array("TemplateName" => $templates));
        foreach ($excludes as $exclude) {
            $exclude->delete();
        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->TemplateName == "CampaignMonitorCampaign") {
            $this->Title = "Default Template";
        }
    }
}
