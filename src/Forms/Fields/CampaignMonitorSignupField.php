<?php

namespace Sunnysideup\CampaignMonitor\Forms\Fields;

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

class CampaignMonitorSignupField extends FormField
{

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
        if (! is_object($this->listPage)) {
            $this->listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $this->listPage])->first();
        }
        $field = null;
        if (! $fieldName) {
            $fieldName = Config::inst()->get(CampaignMonitorMemberDOD::class, 'campaign_monitor_signup_fieldname');
        }
        $api = $this->getCMAPI();
        $currentValues = null;
        if ($this->listPage) {
            if ($this->listPage->ReadyToReceiveSubscribtions()) {
                $currentSelection = 'Subscribe';
                $optionArray = [];
                $optionArray['Subscribe'] = _t('CampaignMonitrSignupPage.SUBSCRIBE_TO', 'subscribe to') . ' ' . $this->listPage->getListTitle();
                $hasUnsubscribe = Config::inst()->get(CampaignMonitorSignupPage::class, 'campaign_monitor_allow_unsubscribe');
                if ($hasUnsubscribe) {
                    $optionArray['Unsubscribe'] = _t('CampaignMonitorSignupPage.UNSUBSCRIBE_FROM', 'unsubscribe from ') . ' ' . $this->listPage->getListTitle();
                }
                if ($this->member->exists()) {
                    if ($api->getSubscriberCanReceiveEmailsForThisList($this->listPage->ListID, $this->member)) {
                        $currentValues = $api->getSubscriber($this->listPage->ListID, $this->member);
                        //$currentSelection = "Unsubscribe";
                    }
                }
                if (! $fieldTitle) {
                    $fieldTitle = _t('CampaignMonitorSignupPage.SIGNUP_FOR', 'Sign up for ') . ' ' . $this->listPage->getListTitle();
                }
                if (count($optionArray) === 1) {
                    $subscribeField = HiddenField::create(
                        $fieldName,
                        $currentSelection
                    );
                } else {
                    $subscribeField = OptionsetField::create($fieldName, $fieldTitle, $optionArray);
                }
                $subscribeField->setValue($currentSelection);
                $field = CompositeField::create($subscribeField);
                $field->addExtraClass('CMFieldsCustomFieldsHolder');
                //add custom fields
                $linkedMemberFields = Config::inst()->get(CampaignMonitorMemberDOD::class, 'custom_fields_member_field_or_method_map');
                $customFields = $this->listPage->CampaignMonitorCustomFields()->filter(['Visible' => 1]);
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
        } else {
            if (! $fieldTitle) {
                $fieldTitle = _t('CampaignMonitorMemberDOD.NEWSLETTERSIGNUP', 'Newsletter sign-up');
            }
            $lists = CampaignMonitorSignupPage::get_ready_ones();
            $array = [];
            foreach ($lists as $list) {
                $array[$list->ListID] = $list->getListTitle();
            }
            if (count($array)) {
                $field = new CheckboxSetField(
                    $fieldName,
                    $fieldTitle,
                    $array
                );
                $field->setDefaultItems($this->member->CampaignMonitorSignupPageIDs());
            }
        }
        if (! $field) {
            $field = ReadonlyField::create(
                $fieldName,
                $fieldTitle,
                _t('CampaignMonitorMemberDOD.NO_LISTS_AVAILABLE', 'No lists available right now.  Please come back soon.')
            );
        }
        return $field;
    }

    /**
     * action subscription form
     * @param CampaignMonitorSignUpPage $this->listPage
     * @param array $data
     * @param \SilverStripe\Forms\Form $form
     *
     * return string: can be subscribe / unsubscribe / error
     */
    public function processCampaignMonitorSignupField($data, $form): string
    {
        $typeOfAction = 'unsubscribe';
        //many choices
        if (isset($data['SubscribeManyChoices'])) {
            $this->listPages = CampaignMonitorSignupPage::get_ready_ones();
            foreach ($this->listPages as $this->listPage) {
                if (isset($data['SubscribeManyChoices'][$this->listPage->ListID]) && $data['SubscribeManyChoices'][$this->listPage->ListID]) {
                    $this->member->addCampaignMonitorList($this->listPage->ListID);
                    $typeOfAction = 'subscribe';
                } else {
                    $this->member->removeCampaignMonitorList($this->listPage->ListID);
                }
            }
        } elseif (isset($data['SubscribeChoice'])) {
            //one choice
            if ($data['SubscribeChoice'] === 'Subscribe') {
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
