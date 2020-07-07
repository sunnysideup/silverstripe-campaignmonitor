<?php

namespace Sunnysideup\CampaignMonitor\Model;

use DataObject;
use CampaignMonitorSignupPage;


/**
 * @author nicolaas [at] sunnysideup.co.nz
 *
 * @description: this represents a sub group of a list, otherwise known as a segment
 *
 **/

class CampaignMonitorCustomField extends DataObject
{
    private static $db = array(
        "Code" => "Varchar(64)",
        "Title" => "Varchar(64)",
        "Type" => "Varchar(32)",
        "Options" => "Text",
        "Visible" => "Boolean",
        "ListID" => "Varchar(32)"
    );

    private static $casting = array(
        "Key" => "Varchar"
    );

    private static $summary_fields = array(
        "Title" => "Title",
        "Visible.Nice" => "Visible"
    );

    private static $indexes = array(
        "ListID" => true,
        "Code" => true
    );

    private static $has_one = array(
        "CampaignMonitorSignupPage" => "CampaignMonitorSignupPage"
    );

    private static $default_sort = array(
        "Visible" => "DESC"
    );

    /**
     * form field matcher between CM and SS CMField => SSField
     * @return array
     */
    private static $field_translator = array(
        "MultiSelectOne" => "OptionsetField",
        "Text" => "TextField",
        "Number" => "NumericField",
        "MultiSelectMany" => "CheckboxSetField",
        "Date" => "DateField"
    );


    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canDelete($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null, $context = [])
    {
        return false;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Code = self::key_to_code($this->Code);
    }

    public function getKey()
    {
        return "[".$this->Code."]";
    }

    public function getOptionsAsArray()
    {
        return array("" => _t("CampaignMonitor.PLEASE_SELECT", "-- please select --"))+explode(",", $this->Options);
    }

    /**
     *
     * @return CampaignMonitorCustomField
     */
    public static function create_from_campaign_monitor_object($customFieldsObject, $listID)
    {
        $filterOptions = array(
            "ListID" => $listID,
            "Code" => self::key_to_code($customFieldsObject->Key)
        );
        $obj = CampaignMonitorCustomField::get()->filter($filterOptions)->first();
        if (!$obj) {
            $obj = CampaignMonitorCustomField::create($filterOptions);
        }
        $page = CampaignMonitorSignupPage::get()->filter(array("ListID" => $listID))->first();
        if ($page) {
            $obj->CampaignMonitorSignupPageID = $page->ID;
        }
        $obj->ListID = $listID;
        $obj->Code = self::key_to_code($customFieldsObject->Key);
        $obj->Title = $customFieldsObject->FieldName;
        $obj->Type = $customFieldsObject->DataType;
        $obj->Options = implode(",", $customFieldsObject->FieldOptions);
        $obj->Visible = $customFieldsObject->VisibleInPreferenceCenter;
        $obj->write();
        return $obj;
    }

    private static function key_to_code($key)
    {
        return str_replace(array("[", "]"), "", $key);
    }

    /**
     * @var array
     */
    private $_fieldTranslator = [];

    /**
     * @param string $namePrefix
     * @param string $nameAppendix
     * @param string $title
     * @param null | array $options
     */
    public function getFormField($namePrefix = "", $nameAppendix = "", $title = "", $options = null)
    {
        //sort out names, title
        if (!$title) {
            $title = $this->Title;
        }
        $name = $namePrefix.$this->Code.$nameAppendix;
        //create field
        if (!count($this->_fieldTranslator)) {
            $this->_fieldTranslator = $this->Config()->get("field_translator");
        }
        $fieldName = $this->_fieldTranslator[$this->Type];
        $field = $fieldName::create($name, $title);
        //add options
        if (!$options && $this->Options) {
            $optionsArray = explode(",", $this->Options);
            $optionsArray = array_combine($optionsArray, $optionsArray);
            $field->setSource($optionsArray);
        }
        return $field;
    }
}

