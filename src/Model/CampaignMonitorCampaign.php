<?php

namespace Sunnysideup\CampaignMonitor\Model;

use Pelago\Emogrifier\CssInliner;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
use Sunnysideup\CampaignMonitor\Traits\CampaignMonitorApiTrait;

/**
 * Class \Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign
 *
 * @property bool $HasBeenSent
 * @property string $MessageFromNewsletterServer
 * @property bool $CreateAsTemplate
 * @property bool $CreateFromWebsite
 * @property bool $CreatedFromWebsite
 * @property string $TemplateID
 * @property string $CampaignID
 * @property string $Name
 * @property string $Subject
 * @property string $FromName
 * @property string $FromEmail
 * @property string $ReplyTo
 * @property string $SentDate
 * @property string $WebVersionURL
 * @property string $WebVersionTextURL
 * @property bool $Hide
 * @property string $Content
 * @property string $Hash
 * @property int $CampaignMonitorCampaignStyleID
 * @method \Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaignStyle CampaignMonitorCampaignStyle()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage[] Pages()
 */
class CampaignMonitorCampaign extends DataObject
{
    use CampaignMonitorApiTrait;

    protected $countOfWrites = 0;

    /**
     * @var string
     */
    private static $default_template = CampaignMonitorCampaign::class;

    private static $table_name = 'CampaignMonitorCampaign';

    private static $db = [
        'HasBeenSent' => 'Boolean',
        'MessageFromNewsletterServer' => 'Text',
        'CreateAsTemplate' => 'Boolean',
        'CreateFromWebsite' => 'Boolean',
        'CreatedFromWebsite' => 'Boolean',
        'TemplateID' => 'Varchar(40)',
        'CampaignID' => 'Varchar(40)',
        'Name' => 'Varchar(100)',
        'Subject' => 'Varchar(100)',
        'FromName' => 'Varchar(100)',
        'FromEmail' => 'Varchar(100)',
        'ReplyTo' => 'Varchar(100)',
        'SentDate' => 'DBDatetime',
        'WebVersionURL' => 'Varchar(255)',
        'WebVersionTextURL' => 'Varchar(255)',
        'Hide' => 'Boolean',
        'Content' => 'HTMLText',
        'Hash' => 'Varchar(32)',
    ];

    private static $indexes = [
        'TemplateID' => true,
        'CampaignID' => true,
        'Hide' => true,
        'Hash' => true,
    ];

    private static $field_labels = [
        'CreateFromWebsite' => 'Create on newsletter server',
    ];

    private static $has_one = [
        'CampaignMonitorCampaignStyle' => CampaignMonitorCampaignStyle::class,
    ];

    private static $many_many = [
        'Pages' => CampaignMonitorSignupPage::class,
    ];

    private static $searchable_fields = [
        'Subject' => 'PartialMatchFilter',
        'Content' => 'PartialMatchFilter',
        'Hide' => 'ExactMatchFilter',
    ];

    private static $summary_fields = [
        'Subject' => 'Subject',
        'SentDate' => 'Sent Date',
    ];

    private static $singular_name = 'Campaign';

    private static $plural_name = 'Campaigns';

    private static $default_sort = 'Hide ASC, SentDate DESC';

    private $_hasBeenSent;

    private $_existsOnCampaignMonitorCheck;

    public function canDelete($member = null)
    {
        return $this->HasBeenSentCheck() ? false : parent::canDelete($member);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        //readonly
        $fields->makeFieldReadonly('MessageFromNewsletterServer');
        $fields->makeFieldReadonly('CampaignID');
        $fields->makeFieldReadonly('TemplateID');
        $fields->makeFieldReadonly('WebVersionURL');
        $fields->makeFieldReadonly('WebVersionTextURL');
        $fields->makeFieldReadonly('SentDate');
        $fields->makeFieldReadonly('HasBeenSent');
        $fields->makeFieldReadonly('Hash');
        //removed
        $fields->removeFieldFromTab('Root.Main', 'CreatedFromWebsite');
        $fields->removeFieldFromTab('Root.Main', 'CreatedTemplateFromWebsite');
        $fields->removeFieldFromTab('Root.Main', 'SecurityCode');
        //pages
        $pages = CampaignMonitorSignupPage::get()->map('ID', 'Title')->toArray();
        $fields->removeFieldFromTab('Root.Main', 'Pages');
        $fields->replaceField('CreateAsTemplate', new OptionsetField('CreateAsTemplate', 'Type', [0 => 'Create as Campaign', 1 => 'Create as Template']));
        if ([] !== $pages) {
            $fields->addFieldToTab('Root.Pages', new CheckboxSetField('Pages', 'Shown on the following pages ...', $pages));
        }

        if ($this->ExistsOnCampaignMonitorCheck()) {
            $fields->removeFieldFromTab('Root.Main', 'CreateAsTemplate');
            if (! $this->CreateAsTemplate) {
                $fields->removeFieldFromTab('Root.Main', 'CreateFromWebsite');
                if (! $this->HasBeenSentCheck()) {
                    $fields->addFieldToTab('Root.Main', new LiteralField('CreateFromWebsiteRemake', '<h2>To edit this newsletter, please first delete it from your newsletter server</h2>'), 'CampaignID');
                }
            }

            $fields->removeFieldFromTab('Root.Main', 'Hash');
            $fields->removeFieldFromTab('Root.Main', 'CampaignMonitorCampaignStyleID');

            $fields->makeFieldReadonly('Name');
            $fields->makeFieldReadonly('Subject');
            $fields->makeFieldReadonly('FromName');
            $fields->makeFieldReadonly('FromEmail');
            $fields->makeFieldReadonly('ReplyTo');
            $fields->makeFieldReadonly('SentDate');
            $fields->makeFieldReadonly('WebVersionURL');
            $fields->makeFieldReadonly('WebVersionTextURL');
            $fields->makeFieldReadonly('Content');
        } else {
            $this->CampaignID = null;
            $this->TemplateID = null;
        }

        if ($this->HasBeenSentCheck()) {
            $fields->addFieldToTab('Root.Main', new LiteralField('Link', '<h2><a target="_blank" href="' . $this->Link() . '">Link</a></h2>'), 'CampaignID');
        } else {
            $fields->removeFieldFromTab('Root.Main', 'Hide');
            if ($this->exists()) {
                if ($this->ExistsOnCampaignMonitorCheck()) {
                    $fields->removeFieldFromTab('Root.Main', 'CreateFromWebsite');
                } else {
                    $fields->addFieldToTab('Root.Main', new LiteralField('PreviewLink', '<h2><a target="_blank" href="' . $this->PreviewLink() . '">Preview Link</a></h2>'), 'CampaignID');
                }
            } else {
                $fields->removeFieldFromTab('Root.Main', 'CreateFromWebsite');
            }
        }

        return $fields;
    }

    /**
     * returns link to view campaign.
     *
     * @param mixed $action
     */
    public function Link($action = ''): string
    {
        /**
         * @var CampaignMonitorSignupPage $page
         */
        $page = $this->Pages()->First();
        if ($page) {
            $link = $page->Link('viewcampaign' . $action . '/' . $this->ID . '/');

            return Director::absoluteURL($link);
        }

        return '#';
    }

    /**
     * returns link to view preview campaign
     * this link is used to create templates / campaigns on Campaign Monitor.
     *
     * @param mixed $action
     */
    public function PreviewLink($action = ''): string
    {
        /**
         * @var CampaignMonitorSignupPage $page
         */
        $page = $this->Pages()->First();
        if ($page) {
            $link = $page->Link('previewcampaign' . $action . '/' . $this->ID . '/?hash=' . $this->Hash);

            return Director::absoluteURL($link);
        }

        return '';
    }

    /**
     * html for newsletter to be created.
     */
    public function getNewsletterContent(): string
    {
        $extension = $this->extend('updateNewsletterContent', $content);
        if (is_array($extension) && count($extension)) {
            return $extension[0];
        }

        $html = '';

        if (class_exists(CssInliner::class)) {
            $allCSS = '';
            $cssFileLocations = $this->getCSSFileLocations();
            foreach ($cssFileLocations as $cssFileLocation) {
                $cssFileHandler = fopen($cssFileLocation, 'r');
                $allCSS .= fread($cssFileHandler, filesize($cssFileLocation));
                fclose($cssFileHandler);
            }

            $isThemeEnabled = Config::inst()->get(SSViewer::class, 'theme_enabled');
            if (! $isThemeEnabled) {
                Config::modify()->set(SSViewer::class, 'theme_enabled', true);
            }

            Requirements::clear();
            $templateName = $this->getRenderWithTemplate();

            $html = $this->RenderWith($templateName);
            if (! $isThemeEnabled) {
                Config::modify()->set(SSViewer::class, 'theme_enabled', false);
            }

            $html = CssInliner::fromHtml($html)
                ->inlineCss($allCSS)
                ->render()
            ;
        } else {
            user_error('Please include Emogrifier module');
        }

        return $html;
    }

    /**
     * provide template used for RenderWith.
     *
     * @return string
     */
    public function getRenderWithTemplate()
    {
        $style = $this->CampaignMonitorCampaignStyle();
        if ($style) {
            if ($style->exists() && $style->TemplateName) {
                return $style->TemplateName;
            }
        }

        return $this->Config()->get('default_template');
    }

    /**
     * @return DBHTMLText
     */
    public function getHTMLContent()
    {
        $style = $this->CampaignMonitorCampaignStyle();
        if ($style) {
            return $style->getHTMLContent($this);
        }

        return $this->RenderWith(CampaignMonitorCampaign::class);
    }

    public function HasBeenSentCheck()
    {
        //lazy check
        if ($this->HasBeenSent || $this->WebVersionURL) {
            return true;
        }

        //real check
        if (null === $this->_hasBeenSent) {
            if (! $this->CampaignID) {
                $this->_hasBeenSent = false;
            } elseif (! $this->HasBeenSent) {
                //$api = $this->getCMAPI();
                $result = $this->api->getCampaigns();
                if (isset($result)) {
                    foreach ($result as $campaign) {
                        if ($this->CampaignID === $campaign->CampaignID) {
                            $this->HasBeenSent = true;
                            $this->write();
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

    /**
     * checks if the template and/or the campaign exists.
     *
     * @param mixed $forceRecheck
     *
     * @return bool
     */
    public function ExistsOnCampaignMonitorCheck($forceRecheck = false)
    {
        //lazy check
        if ($this->HasBeenSent) {
            return true;
        }

        //real check
        if (null === $this->_existsOnCampaignMonitorCheck || $forceRecheck) {
            $this->_existsOnCampaignMonitorCheck = false;
            if ($this->CreateAsTemplate) {
                $field = 'TemplateID';
                $apiMethod1 = 'getTemplates';
                $apiMethod2 = '';
            } else {
                $field = 'CampaignID';
                $apiMethod1 = 'getDrafts';
                $apiMethod2 = 'getCampaigns';
            }

            if (! $this->{$field}) {
                //do nothing
            } else {
                //check drafts
                $result = $this->api->{$apiMethod1}();
                if (isset($result)) {
                    foreach ($result as $campaign) {
                        if ($this->{$field} === $campaign->{$field}) {
                            $this->_existsOnCampaignMonitorCheck = true;

                            break;
                        }
                    }
                } elseif ($apiMethod2) {
                    //check sent ones
                    $result = $this->api->{$apiMethod2}();
                    if (isset($result)) {
                        foreach ($result as $campaign) {
                            if ($this->{$field} === $campaign->{$field}) {
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

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (! $this->Hash) {
            $this->Hash = substr(hash('md5', uniqid()), 0, 7);
        }

        $test = $this->ExistsOnCampaignMonitorCheck($forceRecheck = true);
        if (! $test) {
            if ($this->CreateAsTemplate) {
                $this->TemplateID = null;
            } else {
                $this->CampaignID = null;
            }
        }
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if (! $this->Pages()->exists()) {
            $page = CampaignMonitorSignupPage::get()->first();
            if ($page) {
                $this->Pages()->add($page);
            }
        }

        ++$this->countOfWrites;
        $testA = $this->ExistsOnCampaignMonitorCheck($forceRecheck = true);
        $testB = $this->CreateFromWebsite;
        $testC = $this->countOfWrites < 3;
        if (! $testA && $testB && $testC) {
            $api = $this->getCMAPI();
            if ($api) {
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
    }

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        $this->ExistsOnCampaignMonitorCheck($forceRecheck = true);
        if ($this->HasBeenSentCheck()) {
            //do nothing
        } elseif ($this) {
            $api = $this->getCMAPI();
            if ($api) {
                if ($this->CreateAsTemplate) {
                    $api->deleteTemplate($this->TemplateID);
                } else {
                    $api->deleteCampaign($this->CampaignID);
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function getCSSFileLocations()
    {
        $style = $this->CampaignMonitorCampaignStyle();
        if ($style) {
            return $style->getCSSFilesAsArray();
        }

        return [];
    }
}
