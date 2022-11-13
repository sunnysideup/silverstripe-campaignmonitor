<?php

namespace Sunnysideup\CampaignMonitor\Model;

use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;

/**
 * @author nicolaas [at] sunnysideup.co.nz
 *
 * @description: this represents a sub group of a list, otherwise known as a segment
 */
class CampaignMonitorCustomField extends DataObject
{
    private static $table_name = 'CampaignMonitorCustomField';

    private static $db = [
        'Code' => 'Varchar(64)',
        'Title' => 'Varchar(64)',
        'Type' => 'Varchar(32)',
        'Options' => 'Text',
        'Visible' => 'Boolean',
        'ListID' => 'Varchar(32)',
    ];

    private static $casting = [
        'Key' => 'Varchar',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'Type' => 'Type',
        'Options' => 'Options',
        'Visible.Nice' => 'Visible',
    ];

    private static $indexes = [
        'ListID' => true,
        'Code' => true,
    ];

    private static $has_one = [
        'CampaignMonitorSignupPage' => CampaignMonitorSignupPage::class,
    ];

    private static $default_sort = [
        'Visible' => 'DESC',
        'SortOrder' => 'ASC',
    ];

    /**
     * form field matcher between CM and SS CMField => SSField.
     *
     * @return array
     */
    private static $field_translator = [
        'MultiSelectOne' => OptionsetField::class,
        'Text' => TextField::class,
        'Number' => NumericField::class,
        'MultiSelectMany' => CheckboxSetField::class,
        'Date' => DateField::class,
    ];

    /**
     * @var array
     */
    private $_fieldTranslator = [];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $readOnlyFields = [
            'Code',
            'Title',
            'Type',
            'Options',
            'Visible',
            'ListID',
        ];
        foreach ($readOnlyFields as $readOnlyField) {
            $fields->replaceField(
                $readOnlyField,
                $fields->dataFieldByName($readOnlyField)->performReadonlyTransformation()
            );
        }

        return $fields;
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function canEdit($member = null, $context = [])
    {
        return parent::canEdit();
    }

    public function getKey()
    {
        return '[' . $this->Code . ']';
    }

    public function getOptionsAsArray()
    {
        return ['' => _t('CampaignMonitor.PLEASE_SELECT', '-- please select --')] + explode(',', $this->Options);
    }

    /**
     * @param mixed $customFieldsObject
     * @param mixed $listID
     *
     * @return CampaignMonitorCustomField
     */
    public static function create_from_campaign_monitor_object($customFieldsObject, $listID)
    {
        $filterOptions = [
            'ListID' => $listID,
            'Code' => self::key_to_code($customFieldsObject->Key),
        ];
        /** @var null|CampaignMonitorCustomField $obj */
        $obj = CampaignMonitorCustomField::get()->filter($filterOptions)->first();
        if (! $obj) {
            $obj = CampaignMonitorCustomField::create($filterOptions);
        }

        $page = CampaignMonitorSignupPage::get()->filter(['ListID' => $listID])->first();
        if ($page) {
            $obj->CampaignMonitorSignupPageID = $page->ID;
        }

        $obj->ListID = $listID;
        $obj->Code = self::key_to_code($customFieldsObject->Key);
        $obj->Title = $customFieldsObject->FieldName;
        $obj->Type = $customFieldsObject->DataType;
        $obj->Options = implode(',', $customFieldsObject->FieldOptions);
        $obj->Visible = $customFieldsObject->VisibleInPreferenceCenter;
        $obj->write();

        return $obj;
    }

    /**
     * @param string     $namePrefix
     * @param string     $nameAppendix
     * @param string     $title
     * @param null|array $options
     */
    public function getFormField($namePrefix = '', $nameAppendix = '', $title = '', $options = null)
    {
        //sort out names, title
        if (! $title) {
            $title = $this->Title;
        }

        $name = $namePrefix . $this->Code . $nameAppendix;
        //create field
        if ([] === $this->_fieldTranslator) {
            $this->_fieldTranslator = $this->Config()->get('field_translator');
        }

        $fieldName = $this->_fieldTranslator[$this->Type];
        $field = $fieldName::create($name, $title);
        //add options
        if (! $options && $this->Options) {
            $optionsArray = explode(',', $this->Options);
            $optionsArray = array_combine($optionsArray, $optionsArray);
            $field->setSource($optionsArray);
        }

        return $field;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Code = self::key_to_code($this->Code);
    }

    private static function key_to_code($key)
    {
        return str_replace(['[', ']'], '', $key);
    }
}
