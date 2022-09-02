<?php

namespace Sunnysideup\CampaignMonitor\Api\Traits;

use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;

trait Templates
{
    /**
     * @param mixed $templatID
     *
     * @return mixed
     */
    public function getTemplate($templatID)
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $wrap = new \CS_REST_Templates(
            $templatID,
            $this->getAuth()
        );
        $result = $wrap->get();

        return $this->returnResult(
            $result,
            'GET /api/v3/templates/{ID}',
            'Got Summary'
        );
    }

    /**
     * @todo check 201 / 201!!!
     *
     * @return mixed
     */
    public function createTemplate(CampaignMonitorCampaign $campaignMonitorCampaign)
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $name = 'Template for ' . $campaignMonitorCampaign->Name;
        if (! $name) {
            $name = 'no name set';
        }

        $wrap = new \CS_REST_Templates(null, $this->getAuth());
        $result = $wrap->create(
            $this->Config()->get('client_id'),
            [
                'Name' => $name,
                'HtmlPageURL' => $campaignMonitorCampaign->PreviewLink(),
                'ZipFileURL' => '',
            ]
        );
        if (null !== $result->http_status_code && (201 === $result->http_status_code || 201 === $result->http_status_code)) {
            $code = $result->response;
            $campaignMonitorCampaign->CreateFromWebsite = false;
            $campaignMonitorCampaign->CreatedFromWebsite = true;
            $campaignMonitorCampaign->TemplateID = $code;
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

        return $this->returnResult(
            $result,
            'POST /api/v3/templates/{clientID}',
            'Created Template'
        );
    }

    /**
     * @param string $templateID
     *
     * @return mixed
     */
    public function updateTemplate(CampaignMonitorCampaign $campaignMonitorCampaign, $templateID)
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $name = 'Template for ' . $campaignMonitorCampaign->Name;
        if (! $name) {
            $name = 'no name set';
        }

        $wrap = new \CS_REST_Templates($templateID, $this->getAuth());
        $result = $wrap->create(
            $this->Config()->get('client_id'),
            [
                'Name' => $name,
                'HtmlPageURL' => $campaignMonitorCampaign->PreviewLink(),
                'ZipFileURL' => '',
            ]
        );
        if (null !== $result->http_status_code && (201 === $result->http_status_code || 201 === $result->http_status_code)) {
            $code = $result->response;
            $campaignMonitorCampaign->CreateFromWebsite = false;
            $campaignMonitorCampaign->CreatedFromWebsite = true;
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

        return $this->returnResult(
            $result,
            'PUT /api/v3/templates/{ID}',
            'Updated Template'
        );
    }

    /**
     * @param int|string $templateID
     *
     * @return mixed
     */
    public function deleteTemplate($templateID)
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $wrap = new \CS_REST_Templates($templateID, $this->getAuth());
        $result = $wrap->delete();

        return $this->returnResult(
            $result,
            'DELETE /api/v3/templates/{ID}',
            'Deleted Template'
        );
    }
}
