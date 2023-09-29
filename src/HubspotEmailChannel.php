<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel;

use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\CouldNotSendNotification;
use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\InvalidConfiguration;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class HubspotEmailChannel
{
    // HUBSPOT API CALLS:

    // endpoint: POST /crm/v3/objects/emails;
    // api ref: https://developers.hubspot.com/docs/api/crm/email
    // Standard scope(s)	sales-email-read
    // Granular scope(s)	crm.objects.contacts.write

    // endpoint: PUT /crm/v3/objects/emails/{emailId}/associations/{toObjectType}/{toObjectId}/{associationType};
    // api ref: https://developers.hubspot.com/docs/api/crm/email
    // Standard scope(s)	sales-email-read
    // Granular scope(s)	crm.objects.contacts.write

    public const HUBSPOT_URL = 'https://api.hubapi.com/crm/v3/objects/emails';

    /**
     * HubspotEngagementChannel constructor.
     */
    public function __construct()
    {
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     *
     * @throws \Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\CouldNotSendNotification|InvalidConfiguration
     */
    public function send($notifiable, Notification $notification): ?array
    {
        $hubspotContactId = $notifiable->getHubspotContactId($notification);

        if (empty($hubspotContactId)) {
            return null;
        }

        if (! method_exists($notification, 'toMail')) {
            return null;
        }

        $message = $notification->toMail($notifiable);

        $params = [
            "properties" => [
                "hs_timestamp" => now()->getPreciseTimestamp(3),
                "hubspot_owner_id" => $message->metadata['hubspot_owner_id'] ?? config('hubspot.hubspot_owner_id'),
                "hs_email_direction" => "EMAIL",
                "hs_email_status" => "SENT",
                "hs_email_subject" => $message->subject,
                "hs_email_text" => (string) $message->render(), ],
            ];

        $response = $this->callApi(self::HUBSPOT_URL, 'post', $params);

        $hubspotEmail = $response->json();

        if ($response->status() == 201 && ! empty($hubspotEmail['id'])) {
            $url = self::HUBSPOT_URL.'/'.$hubspotEmail['id'].'/associations/contacts/'.$hubspotContactId.'/198';
            $newResp = $this->callApi($url, 'put');

            if ($newResp->status() != 200) {
                throw CouldNotSendNotification::serviceRespondedWithAnError($newResp->body());
            }
            $hubspotEmail['associations'] = $newResp['associations'];

            
            $url = 'https://api.hubapi.com/crm/v3/objects/contacts/'.$hubspotContactId.'?associations=company';
            $contactResp = $this->callApi($url, 'get');
            
            if ($contactResp->status() != 200) {
                throw CouldNotSendNotification::serviceRespondedWithAnError($newResp->body());
            }
            
            if ($hubspotCompanyId = $contactResp['associations']['companies']['results'][0]['id'] ?? null) {
                $url = self::HUBSPOT_URL.'/'.$hubspotEmail['id'].'/associations/companies/'.$hubspotCompanyId.'/185';
                $newResp = $this->callApi($url, 'put');
                
                if ($newResp->status() != 200) {
                    throw CouldNotSendNotification::serviceRespondedWithAnError($newResp->body());
                }
                $hubspotEmail['associations'] = array_merge($hubspotEmail['associations'], $newResp['associations']);
            }
        } else {
            throw CouldNotSendNotification::serviceRespondedWithAnError($response->body());
        }

        return $hubspotEmail;
    }

    protected function callApi($baseUrl, $method, $params = [])
    {
        $apiKey = config('hubspot.api_key');
        if (is_null(config('hubspot.hubspot_owner_id'))) {
            throw InvalidConfiguration::configurationNotSet();
        }

        $url = $baseUrl.'?hapikey='.$apiKey;
        $http = Http::acceptJson();

        if (is_null($apiKey)) {
            if (is_null(config('hubspot.access_token'))) {
                throw InvalidConfiguration::configurationNotSet();
            }
            $url = $baseUrl;
            $http = $http->withToken(config('hubspot.access_token'));
        }

        return $http->$method($url, $params);
    }
}
