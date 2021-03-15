<?php

namespace Sunnysideup\CampaignMonitor\Api;

use SilverStripe\Forms\FormField;
use SilverStripe\Security\Member;

use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
use Sunnysideup\CampaignMonitor\Forms\Fields\CampaignMonitorSignupField;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

class CampaignMonitorSignupFieldProvider
{

    use CampaignMonitorApiTrait;
    use Configurable;
    use Extensible;
    use Injectable;


    /**
     * name of the field to use for sign-ups
     * @var string
     */
    private static $campaign_monitor_allow_unsubscribe = true;

    /**
     * array of fields where the member value is set as the default for the
     * custom field ...
     * The should be like this
     *
     *     CustomFieldCode => MemberFieldOrMethod
     * @var array
     */
    private static $custom_fields_member_field_or_method_map = [];


    /**
     * name of the field to use for sign-ups
     * @var string
     */
    private static $campaign_monitor_signup_fieldname = 'CampaignMonitorSubscriptions';

    protected $member = null;

    public function setMember(Member $member)
    {
        $this->member = $member;
        return $this;
    }

    protected $listPage = null;

    public function setListPage($listPage)
    {
        $this->listPage = $listPage;
        return $this;
    }

    /**
     * returns a form field for signing up to all available lists
     * or if a list is provided, for that particular list.
     *
     * @param CampaignMonitorSignupPage | string | Null $this->listPage
     * @param string $fieldName
     * @param string $fieldTitle
     *
     * @return \SilverStripe\Forms\FormField
     */
    public function getCampaignMonitorSignupField($fieldName = '', $fieldTitle = '')
    {
        //get defaults
        if (! is_object($this->listPage)) {
            $this->listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $this->listPage])->first();
        }
        $field = null;
        if (! $fieldName) {
            $fieldName = $this->Config()->get('campaign_monitor_signup_fieldname');
        }
        $addCustomFields = false;
        $subscribeField = null;
        $typeFieldValue = 'none';
        if ($this->listPage) {
            $typeFieldValue = 'one';
            if ($this->listPage->ReadyToReceiveSubscribtions()) {

                if (! $fieldTitle) {
                    $fieldTitle = _t('CampaignMonitorSignupPage.SIGNUP_FOR', 'Sign up for ') . ' ' . $this->listPage->getListTitle();
                }
                $optionArray = $this->getOptionArray();
                $currentSelection = $this->getCurrentSelection();

                if (count($optionArray) === 1) {
                    $subscribeField = HiddenField::create(
                        $fieldName,
                        $currentSelection
                    );
                } elseif (count($optionArray) > 1)  {
                    $subscribeField = OptionsetField::create($fieldName, $fieldTitle, $optionArray);
                }
                $subscribeField->setValue($currentSelection);
                $addCustomFields = true;
            }
        } else {
            $typeFieldValue = 'many';
            if (! $fieldTitle) {
                $fieldTitle = _t('CampaignMonitorMemberDOD.NEWSLETTERSIGNUP', 'Newsletter sign-up');
            }
            $lists = CampaignMonitorSignupPage::get_ready_ones();
            $array = [];
            foreach ($lists as $list) {
                $array[$list->ListID] = $list->getListTitle();
            }
            if (count($array)) {
                $subscribeField = new CheckboxSetField(
                    $fieldName,
                    $fieldTitle,
                    $array
                );
                $subscribeField->setDefaultItems($this->member->CampaignMonitorSignupPageIDs());
            }
        }
        if (! $subscribeField) {
            $subscribeField = ReadonlyField::create(
                $fieldName,
                $fieldTitle,
                _t('CampaignMonitorMemberDOD.NO_LISTS_AVAILABLE', 'No sign-up available right now.  Please come back soon.')
            );

        }
        $parentField = CompositeField::create();
        $parentField->push($subscribeField);
        $parentField->push(HiddenField::create($fieldName . 'Type')->setValue($typeFieldValue));
        $parentField->addExtraClass('CMFieldsCustomFieldsHolder');
        if($addCustomFields) {
            $this->addCustomFieldsToField($parentField);
        }
        return $parentField;
    }

    protected function getOptionArray() : array
    {
        $optionArray = [];
        $optionArray['Subscribe'] = _t('CampaignMonitrSignupPage.SUBSCRIBE_TO', 'subscribe to') . ' ' . $this->listPage->getListTitle();
        $hasUnsubscribe = $this->Config()->get('campaign_monitor_allow_unsubscribe');
        if ($hasUnsubscribe) {
            $optionArray['Unsubscribe'] = _t('CampaignMonitorSignupPage.UNSUBSCRIBE_FROM', 'unsubscribe from ') . ' ' . $this->listPage->getListTitle();
        }

        return $optionArray;
    }

    protected function getCurrentSelection() : string
    {
        return 'Subscribe';
    }


    protected function addCustomFieldsToField($field)
    {
        //add custom fields
        $linkedMemberFields = $this->Config()->get('custom_fields_member_field_or_method_map');
        $customFields = $this->listPage->CampaignMonitorCustomFields()->filter(['Visible' => 1]);
        $currentValues = $this->getCurrentValues();
        foreach ($customFields as $customField) {
            $valueSet = false;
            $customFormField = $customField->getFormField('CMCustomField');
            if ($currentValues && isset($currentValues->CustomFields)) {
                foreach ($currentValues->CustomFields as $customFieldObject) {
                    if ($customFieldObject->Key === $customField->Title) {
                        if ($value = $customFieldObject->Value) {
                            $valueSet = true;
                        }
                        $customFormField->setValue($value);
                    }
                }
            }
            if (isset($linkedMemberFields[$customFormField->Code]) && ! $valueSet) {
                $fieldOrMethod = $linkedMemberFields[$customFormField->Code];
                if ($this->member->hasMethod($fieldOrMethod)) {
                    $value = $this->member->{$fieldOrMethod}();
                } else {
                    $value = $this->member->{$fieldOrMethod};
                }
                if ($value) {
                    $customFormField->setValue($value);
                }
            }
            $field->push($customFormField);
        }
    }

    protected function getCurrentValues() : array
    {
        $api = $this->getCMAPI();
        $currentValues = [];
        if($this->listPage->ListID) {
            if ($this->member && $this->member->exists()) {
                if ($api->getSubscriberCanReceiveEmailsForThisList($this->listPage->ListID, $this->member)) {
                    $currentValues = $api->getSubscriber($this->listPage->ListID, $this->member);
                    //$currentSelection = "Unsubscribe";
                }
            }
        }
        return $currentValues;
    }

    /**
     * action subscription form
     * @param array          $data
     * @param array|string   $values
     *
     * @return string: can be subscribe / unsubscribe / error
     */
    public function processCampaignMonitorSignupField($data, $values): string
    {
        $typeOfAction = 'unsubscribe';
        //many choices
        if (is_array($values)) {
            $listPages = CampaignMonitorSignupPage::get_ready_ones();
            foreach ($listPages as $listPage) {
                if (isset($values[$listPage->ListID]) && $values[$listPage->ListID]) {
                    $this->member->addCampaignMonitorList($listPage->ListID);
                    $typeOfAction = 'subscribe';
                } else {
                    $this->member->removeCampaignMonitorList($listPage->ListID);
                }
            }
        } elseif (is_string($values) && $values && $this->listPage->ListID) {
            //one choice
            if ($values === 'Subscribe') {
                $customFields = $this->listPage->CampaignMonitorCustomFields()->filter(['Visible' => 1]);
                $customFieldsArray = [];
                foreach ($customFields as $customField) {
                    if (isset($data['CMCustomField' . $customField->Code])) {
                        $customFieldsArray[$customField->Code] = $data['CMCustomField' . $customField->Code];
                    }
                }
                $this->member->addCampaignMonitorList($this->listPage->ListID, $customFieldsArray);
                $typeOfAction = 'subscribe';
            } else {
                $this->member->removeCampaignMonitorList($this->listPage->ListID);
            }
        } else {
            user_error('Subscriber field missing', E_USER_WARNING);
        }
        return $typeOfAction;
    }

}
