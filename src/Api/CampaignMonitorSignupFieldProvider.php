<?php

namespace Sunnysideup\CampaignMonitor\Api;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Security\Member;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;

class CampaignMonitorSignupFieldProvider
{
    use CampaignMonitorApiTrait;
    use Configurable;
    use Extensible;
    use Injectable;

    protected $member;

    protected $listPage;

    /**
     * @var bool
     */
    private static $campaign_monitor_allow_unsubscribe = true;

    /**
     * @var bool
     */
    private static $show_list_name_in_subcribe_field = true;

    /**
     * array of fields where the member value is set as the default for the
     * custom field ...
     * The should be like this.
     *
     *     CustomFieldCode => MemberFieldOrMethod
     *
     * @var array
     */
    private static $custom_fields_member_field_or_method_map = [];

    /**
     * name of the field to use for sign-ups.
     *
     * @var string
     */
    private static $campaign_monitor_signup_fieldname = 'CampaignMonitorSubscriptions';

    public function setMember(Member $member)
    {
        $this->member = $member;

        return $this;
    }

    public function setListPage($listPage)
    {
        $this->listPage = $listPage;

        return $this;
    }

    /**
     * returns a form field for signing up to all available lists
     * or if a list is provided, for that particular list.
     *
     * @param string $fieldName
     * @param string $fieldTitle
     *
     * @return \SilverStripe\Forms\CompositeField
     */
    public function getCampaignMonitorSignupField(?string $fieldName = '', ?string $fieldTitle = '')
    {
        //get defaults
        if (!is_object($this->listPage)) {
            $this->listPage = CampaignMonitorSignupPage::get()->filter(['ListID' => $this->listPage])->first();
        }

        if (!$fieldName) {
            $fieldName = $this->Config()->get('campaign_monitor_signup_fieldname');
        }

        $addCustomFields = false;
        $subscribeField = null;
        $typeFieldValue = 'none';
        if ($this->listPage) {
            $typeFieldValue = 'one';
            if ($this->listPage->ReadyToReceiveSubscribtions()) {
                if (!$fieldTitle) {
                    $fieldTitle = _t('CampaignMonitorSignupPage.SIGNUP', 'Sign up');
                    if ($this->Config()->get('show_list_name_in_subcribe_field')) {
                        $fieldTitle .= _t('CampaignMonitorSignupPage.FOR', ' for ') . ' ' . $this->listPage->getListTitle();
                    }
                }

                $optionArray = $this->getOptionArray();
                $currentSelection = $this->getCurrentSelection();

                if (1 === count($optionArray)) {
                    $subscribeField = HiddenField::create(
                        $fieldName,
                        $currentSelection
                    );
                } elseif (count($optionArray) > 1) {
                    $subscribeField = OptionsetField::create($fieldName, $fieldTitle, $optionArray);
                }

                $subscribeField->setValue($currentSelection);
                $addCustomFields = true;
            }
        } else {
            $typeFieldValue = 'many';
            if (!$fieldTitle) {
                $fieldTitle = _t('CampaignMonitorMemberDOD.NEWSLETTERSIGNUP', 'Newsletter sign-up');
            }

            $lists = CampaignMonitorSignupPage::get_ready_ones();
            $array = [];
            foreach ($lists as $list) {
                $array[$list->ListID] = $list->getListTitle();
            }

            if ([] !== $array) {
                $subscribeField = new CheckboxSetField(
                    $fieldName,
                    $fieldTitle,
                    $array
                );
                $subscribeField->setDefaultItems(array_keys($this->member->CampaignMonitorSignedUpArray()));
            }
        }

        if (!$subscribeField) {
            $subscribeField = ReadonlyField::create(
                $fieldName,
                $fieldTitle,
                _t('CampaignMonitorMemberDOD.NO_LISTS_AVAILABLE', 'No sign-up available right now.  Please come back soon.')
            );
        }

        $parentField = CompositeField::create();
        $parentField->push(HiddenField::create($fieldName . 'Type')->setValue($typeFieldValue));
        $parentField->addExtraClass('CMFieldsCustomFieldsHolder');
        if ($addCustomFields) {
            $this->addCustomFieldsToField($parentField);
        }
        $parentField->push($subscribeField);

        return $parentField;
    }

    /**
     * action subscription form.
     *
     * @param array        $data
     * @param array|string $values
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
            if ('Subscribe' === $values) {
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
            user_error('Subscriber field missing', E_USER_ERROR);
        }

        return $typeOfAction;
    }

    protected function getOptionArray(): array
    {
        $optionArray = [];
        $toAddSubscribe = '';
        $toAddUnsubscribe = '';
        if ($this->Config()->get('show_list_name_in_subcribe_field')) {
            $toAddSubscribe = 'to ' . $this->listPage->getListTitle();
            $toAddUnsubscribe = 'from ' . $this->listPage->getListTitle();
        }
        $optionArray['Subscribe'] = _t('CampaignMonitrSignupPage.SUBSCRIBE_TO', 'subscribe') . $toAddSubscribe;
        $hasUnsubscribe = $this->Config()->get('campaign_monitor_allow_unsubscribe');
        if ($hasUnsubscribe) {
            $optionArray['Unsubscribe'] = _t('CampaignMonitorSignupPage.UNSUBSCRIBE_FROM', 'unsubscribe ') . $toAddUnsubscribe;
        }

        return $optionArray;
    }

    protected function getCurrentSelection(): string
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
            $value = '';
            $customFormField = $customField->getFormField('CMCustomField');
            if ($currentValues && isset($currentValues['CustomFields']) && is_array($currentValues['CustomFields'])) {
                $fieldValues = [];
                foreach ($currentValues['CustomFields'] as $customFieldsArray) {
                    if ($customFieldsArray['Key'] === $customField->Title) {
                        $tmpValue = $customFieldsArray['Value'] ?? '';
                        $fieldValues[] = $tmpValue;
                    }
                }

                $finalValue = 'MultiSelectMany' === $customField->Type ? $fieldValues : implode('', $fieldValues);
                $customFormField->setValue($finalValue);
            }

            if (isset($linkedMemberFields[$customFormField->Code]) && !$value) {
                $fieldOrMethod = $linkedMemberFields[$customFormField->Code];
                $value = $this->member->hasMethod($fieldOrMethod) ? $this->member->{$fieldOrMethod}() : $this->member->{$fieldOrMethod};
                if ($value) {
                    $customFormField->setValue($value);
                }
            }

            $field->push($customFormField);
        }
    }

    protected function getCurrentValues(): array
    {
        $api = $this->getCMAPI();
        $currentValues = [];
        if ($api) {
            if ($this->listPage->ListID) {
                if ($this->member && $this->member->exists()) {
                    if ($api->getSubscriberCanReceiveEmailsForThisList($this->listPage->ListID, $this->member)) {
                        $currentValues = $api->getSubscriber($this->listPage->ListID, $this->member);
                        //$currentSelection = "Unsubscribe";
                    }
                }
            }

            $currentValues = json_decode(json_encode($currentValues), true);
        }

        return $currentValues;
    }
}
