<?php

namespace Sunnysideup\CampaignMonitor\Model;

use DOMDocument;

use SilverStripe\Assets\FileFinder;
use SilverStripe\Control\Director;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 *@author nicolaas [at] sunnysideup.co.nz
 *
 *
 **/

class CampaignMonitorCampaignStyle extends DataObject
{
    private static $folders_to_search = [];

    private static $css_folders_to_search = [];

    private static $db = [
        'Title' => 'Varchar(100)',
        'TemplateName' => 'Varchar(200)',
        'CSSFiles' => 'Text',
    ];

    private static $indexes = [
        'Title' => true,
    ];

    private static $has_many = [
        'CampaignMonitorCampaigns' => CampaignMonitorCampaign::class,
    ];

    private static $searchable_fields = [
        'Title' => 'PartialMatchFilter',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
    ];

    private static $singular_name = 'Campaign Template';

    private static $plural_name = 'Campaign Templates';

    private static $default_template = CampaignMonitorCampaign::class;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab('Root.Debug', TextField::create('TemplateName'));
        $fields->addFieldToTab('Root.Debug', ReadonlyField::create('FileLocation'));
        $fields->addFieldToTab('Root.Debug', ReadonlyField::create('CSSFiles'));
        $fields->addFieldToTab('Root.Debug', ReadonlyField::create('CampaignMonitorCampaigns', 'Used in ', implode(',', $this->CampaignMonitorCampaigns()->map()->toArray())));
        $fields->removeFieldFromTab('Root', 'CampaignMonitorCampaigns');
        return $fields;
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * @return array
     */
    public function getFoldersToSearch() : array
    {
        $array = $this->Config()->folders_to_search;
        if(empty($array)) {
            $array = [];
        }
        foreach($array as $key => $folder) {
            $array[$key] = Director::baseFolder() . '/'.$folder;
        }

        return $array;
    }

    /**
     * @return array
     */
    public function getCSSFoldersToSearch() : array
    {
        $array = $this->Config()->css_folders_to_search;
        if(empty($array)) {
            $array = [];
        }
        foreach($array as $key => $folder) {
            $array[$key] = Director::baseFolder() . '/'.$folder;
        }

        return $array;
    }

    /**
     * @return string | null
     */
    public function getFileLocation()
    {
        if (! $this->TemplateName) {
            $this->TemplateName = CampaignMonitorCampaign::class;
        }
        foreach ($this->getFoldersToSearch() as $folder) {
            $fileLocation = $folder . $this->TemplateName . '.ss';
            if (file_exists($fileLocation)) {
                return $fileLocation;
            }
            //just try the next one ...
        }
        user_error("can not find template, last one tried: ${fileLocation}");
    }

    public function getCSSFiles()
    {
        return implode(', ', $this->getCSSFilesAsArray());
    }

    public function getCSSFilesAsArray()
    {
        $dom = new DOMDocument();
        $cssFiles = [];
        $fileLocation = $this->getFileLocation();
        if ($fileLocation) {
            @$dom->loadHTMLFile($fileLocation);
            $linkTags = $dom->getElementsByTagName('link');
            foreach ($linkTags as $linkTag) {
                if (strtolower($linkTag->getAttribute('rel')) === 'stylesheet') {
                    $file = Director::baseFolder() . '/' . $linkTag->getAttribute('href');
                    if (file_exists($file)) {
                        $cssFiles[$file] = $file;
                    } else {
                        user_error("can find css file ${file}");
                    }
                }
                // if $link_tag rel == stylesheet
                 //   get href value and load CSS
            }
        } else {
            user_error('Can not find template file');
        }
        if (count($cssFiles) === 0) {
            foreach ($this->getCSSFoldersToSearch() as $folder) {
                $file = $folder . 'CampaignMonitorCampaign.css';
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
        $templates = [];
        foreach ($this->getFoldersToSearch() as $folder) {
            $finder = new FileFinder();
            $finder->setOption('name_regex', '/^.*\.ss$/');
            $found = $finder->find($folder);
            foreach ($found as $key => $value) {
                $template = pathinfo($value);
                $templates[$template['filename']] = $template['filename'];
            }
        }
        foreach ($templates as $template) {
            $filter = ['TemplateName' => $template];
            $obj = self::get()->filter($filter)->first();
            if (! $obj) {
                $obj = self::create($filter + ['Title' => $template]);
                $obj->write();
            }
        }
        $excludes = $obj = self::get()->exclude(['TemplateName' => $templates]);
        foreach ($excludes as $exclude) {
            $exclude->delete();
        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->TemplateName === CampaignMonitorCampaign::class) {
            $this->Title = 'Default Template';
        }
    }
}
