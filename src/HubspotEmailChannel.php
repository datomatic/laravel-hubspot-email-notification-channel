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

    public const HUBSPOT_URL_V3 = 'https://api.hubapi.com/crm/v3/objects/';
    public const HUBSPOT_URL_V4 = 'https://api.hubapi.com/crm/v4/';

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
    public function send($notifiable, Notification $notification): void
    {
        $hubspotContactId = $notifiable->getHubspotContactId($notification);

        if (empty($hubspotContactId)) {
            return;
        }

        if (!method_exists($notification, 'toMail')) {
            return;
        }

        $message = $notification->toMail($notifiable);

        $params = [
            "properties" => [
                "hs_timestamp" => now()->getPreciseTimestamp(3),
                "hubspot_owner_id" => $message->metadata['hubspot_owner_id'] ?? config('hubspot.hubspot_owner_id'),
                "hs_email_direction" => "EMAIL",
                "hs_email_status" => "SENT",
                "hs_email_subject" => $message->subject,
                "hs_email_text" => (string)$message->render(),
            ],
        ];

        $hubspotEmail = $this->callApi(self::HUBSPOT_URL_V3 . 'emails', 'post', $params);

        if (!empty($hubspotEmail['id'])) {

            $this->callApi(
                self::HUBSPOT_URL_V4 . 'contact/'. $hubspotContactId .'/associations/default/email/' . $hubspotEmail['id'],
                'put',
                ["associationTypeId" => 197]
            );;

            $contactResp = $this->callApi(
                self::HUBSPOT_URL_V3 . 'contacts/' . $hubspotContactId,
                'get',
                ['associations' => 'company']
            );

            if ($hubspotCompanyId = $contactResp['associations']['companies']['results'][0]['id'] ?? null) {
                $this->callApi(
                    self::HUBSPOT_URL_V4 . 'company/'. $hubspotCompanyId .'/associations/default/email/' . $hubspotEmail['id'],
                    'put',
                    ["associationTypeId" => 185]
                );
            }
        }

    }

    protected function callApi(string $baseUrl, string $method, array $params = []) :array
    {
        if (is_null(config('hubspot.hubspot_owner_id'))) {
            throw InvalidConfiguration::configurationNotSet();
        }

        $apiKey = config('hubspot.api_key');
        if($apiKey){
            $params['hapikey'] = $apiKey;
        }

        $http = Http::acceptJson();

        if (is_null($apiKey)) {
            if (is_null(config('hubspot.access_token'))) {
                throw InvalidConfiguration::configurationNotSet();
            }
            $http = $http->withToken(config('hubspot.access_token'));
        }

        $response = $http->$method($baseUrl, $params);

        if ($response->failed()) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($response->body());
        }

        return $response->json();
    }
}
