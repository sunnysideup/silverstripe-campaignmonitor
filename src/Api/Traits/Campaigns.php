<?php

namespace Sunnysideup\CampaignMonitor\Api\Traits;

use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;

trait Campaigns
{
    /**
     * @return mixed A successful response will be an object of the form
     *               array(
     *               {
     *               'WebVersionURL' => The web version url of the campaign
     *               'WebVersionTextURL' => The web version url of the text version of the campaign
     *               'CampaignID' => The id of the campaign
     *               'Subject' => The campaign subject
     *               'Name' => The name of the campaign
     *               'FromName' => The from name for the campaign
     *               'FromEmail' => The from email address for the campaign
     *               'ReplyTo' => The reply to email address for the campaign
     *               'SentDate' => The sent data of the campaign
     *               'TotalRecipients' => The number of recipients of the campaign
     *               }
     */
    public function getCampaigns()
    {
        //require_once '../../csrest_clients.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_clients.php';
        $wrap = new \CS_REST_Clients($this->Config()->get('client_id'), $this->getAuth());
        $result = $wrap->get_campaigns();

        return $this->returnResult(
            $result,
            'GET /api/v3.1/clients/{id}/campaigns',
            'Got sent campaigns'
        );
    }

    /**
     * @return mixed
     */
    public function getDrafts()
    {
        //require_once '../../csrest_clients.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_clients.php';
        $wrap = new \CS_REST_Clients($this->Config()->get('client_id'), $this->getAuth());
        $result = $wrap->get_drafts();

        return $this->returnResult(
            $result,
            'GET /api/v3.1/clients/{id}/drafts',
            'Got draft campaigns'
        );
    }

    public function getScheduled()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    // create campaigns

    /**
     * @param array  $listIDs
     * @param array  $segmentIDs
     * @param string $templateID      - OPTIONAL!
     * @param array  $templateContent - OPTIONAL!
     */
    public function createCampaign(
        CampaignMonitorCampaign $campaignMonitorCampaign,
        $listIDs = [],
        $segmentIDs = [],
        $templateID = '',
        $templateContent = []
    ) {
        //require_once '../../csrest_lists.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_lists.php';
        $siteConfig = SiteConfig::current_site_config();

        $subject = $campaignMonitorCampaign->Subject;
        if (! $subject) {
            $subject = 'no subject set';
        }

        $name = $campaignMonitorCampaign->Name;
        if (! $name) {
            $name = 'no name set';
        }

        $fromName = $campaignMonitorCampaign->FromName;
        if (! $fromName) {
            $fromName = $siteConfig->Title;
        }

        $fromEmail = $campaignMonitorCampaign->FromEmail;
        if (! $fromEmail) {
            $fromEmail = Config::inst()->get(Email::class, 'admin_email');
        }

        $replyTo = $campaignMonitorCampaign->ReplyTo;
        if (! $replyTo) {
            $replyTo = $fromEmail;
        }
        $page = $campaignMonitorCampaign->Pages()->first();
        if ($page) {
            $listID = $campaignMonitorCampaign->Pages()->first()->ListID;

            $wrap = new \CS_REST_Campaigns(null, $this->getAuth());
            if ($templateID) {
                $result = $wrap->create_from_template(
                    $this->Config()->get('client_id'),
                    [
                        'Subject' => $subject,
                        'Name' => $name,
                        'FromName' => $fromName,
                        'FromEmail' => $fromEmail,
                        'ReplyTo' => $replyTo,
                        'ListIDs' => [$listID],
                        'SegmentIDs' => [],
                        'TemplateID' => $templateID,
                        'TemplateContent' => $templateContent,
                    ]
                );
            } else {
                $result = $wrap->create(
                    $this->Config()->get('client_id'),
                    [
                        'Subject' => $subject,
                        'Name' => $name,
                        'FromName' => $fromName,
                        'FromEmail' => $fromEmail,
                        'ReplyTo' => $replyTo,
                        'HtmlUrl' => $campaignMonitorCampaign->PreviewLink(),
                        'TextUrl' => $campaignMonitorCampaign->PreviewLink('textonly'),
                        'ListIDs' => [$listID],
                    ]
                );
            }
            if (null !== $result->http_status_code && (201 === $result->http_status_code || 201 === $result->http_status_code)) {
                $code = $result->response;
                $campaignMonitorCampaign->CreateFromWebsite = false;
                $campaignMonitorCampaign->CreatedFromWebsite = true;
                $campaignMonitorCampaign->CampaignID = $code;
            } else {
                $campaignMonitorCampaign->CreateFromWebsite = false;
                $campaignMonitorCampaign->CreatedFromWebsite = false;
                $code = 'Error';
                if (is_object($result->response)) {
                    $code = $result->response->Code . ':' . $result->response->Message;
                }
                $campaignMonitorCampaign->MessageFromNewsletterServer = $code;
            }
            $campaignMonitorCampaign->write();
        } else {
            $result = 'ERROR: no campagn monitor page with list id created yet.';
        }

        return $this->returnResult(
            $result,
            'CREATE /api/v3/campaigns/{clientID}',
            'Created Campaign'
        );
    }

    public function deleteCampaign($campaignID)
    {
        $wrap = new \CS_REST_Campaigns($campaignID, $this->getAuth());
        $result = $wrap->delete();

        return $this->returnResult(
            $result,
            'DELETE /api/v3/campaigns/{id}',
            'Deleted Campaign'
        );
    }

    // information about the campaigns

    public function getBounces()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    public function getClicks()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    /**
     * Gets a summary of all campaign reporting statistics.
     *
     * @param int $campaignID
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'Recipients' => The total recipients of the campaign
     *               'TotalOpened' => The total number of opens recorded
     *               'Clicks' => The total number of recorded clicks
     *               'Unsubscribed' => The number of recipients who unsubscribed
     *               'Bounced' => The number of recipients who bounced
     *               'UniqueOpened' => The number of recipients who opened
     *               'WebVersionURL' => The url of the web version of the campaign
     *               'WebVersionTextURL' => The url of the web version of the text version of the campaign
     *               'WorldviewURL' => The public Worldview URL for the campaign
     *               'Forwards' => The number of times the campaign has been forwarded to a friend
     *               'Likes' => The number of times the campaign has been 'liked' on Facebook
     *               'Mentions' => The number of times the campaign has been tweeted about
     *               'SpamComplaints' => The number of recipients who marked the campaign as spam
     *               }
     */
    public function getSummary($campaignID)
    {
        $wrap = new \CS_REST_Campaigns($campaignID, $this->getAuth());
        $result = $wrap->get_summary();

        return $this->returnResult(
            $result,
            'GET /api/v3.1/campaigns/{id}/summary',
            'Got Summary'
        );
    }

    /**
     * Gets the email clients that subscribers used to open the campaign.
     *
     * @param int $campaignID
     *
     * @return mixed A successful response will be an object of the form
     *               array(
     *               {
     *               Client => The email client name
     *               Version => The email client version
     *               Percentage => The percentage of subscribers who used this email client
     *               Subscribers => The actual number of subscribers who used this email client
     *               }
     *               )
     */
    public function getEmailClientUsage($campaignID)
    {
        $wrap = new \CS_REST_Campaigns($campaignID, $this->getAuth());
        $result = $wrap->get_email_client_usage();

        return $this->returnResult(
            $result,
            'GET /api/v3.1/campaigns/{id}/emailclientusage',
            'Got email client usage'
        );
    }

    public function getOpens()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    public function getRecipients()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    public function getSpam()
    {
        user_error('This method is still to be implemented, see samples for an example');
    }

    /**
     * Gets all unsubscribes recorded for a campaign since the provided date.
     *
     * @param int    $campaignID    ID of the Campaign
     * @param int    $daysAgo       The date to start getting subscribers from
     * @param int    $page          The page number to get
     * @param int    $pageSize      The number of records per page
     * @param string $sortByField   ('EMAIL', 'NAME', 'DATE')
     * @param string $sortDirection ('ASC', 'DESC')
     *
     * @return mixed A successful response will be an object of the form
     *               {
     *               'ResultsOrderedBy' => The field the results are ordered by
     *               'OrderDirection' => The order direction
     *               'PageNumber' => The page number for the result set
     *               'PageSize' => The page size used
     *               'RecordsOnThisPage' => The number of records returned
     *               'TotalNumberOfRecords' => The total number of records available
     *               'NumberOfPages' => The total number of pages for this collection
     *               'Results' => array(
     *               {
     *               'EmailAddress' => The email address of the subscriber who unsubscribed
     *               'ListID' => The list id of the list containing the subscriber
     *               'Date' => The date of the unsubscribe
     *               'IPAddress' => The ip address where the unsubscribe originated
     *               }
     *               )
     *               }
     */
    public function getUnsubscribes(
        int $campaignID,
        ?int $daysAgo = 3650,
        ?int $page = 1,
        ?int $pageSize = 999,
        ?string $sortByField = 'EMAIL',
        ?string $sortDirection = 'ASC'
    ) {
        //require_once '../../csrest_campaigns.php';
        require_once BASE_PATH . '/vendor/campaignmonitor/createsend-php/csrest_campaigns.php';
        $wrap = new \CS_REST_Campaigns($campaignID, $this->getAuth());
        $result = $wrap->get_unsubscribes(
            date('Y-m-d', strtotime('-' . $daysAgo . ' days')),
            $page,
            $pageSize,
            $sortByField,
            $sortDirection
        );

        return $this->returnResult(
            $result,
            'GET /api/v3.1/campaigns/{id}/unsubscribes',
            'Got unsubscribes'
        );
    }
}
