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

    // endpoint: PUT /crm/v4/objects/{fromObjectType}/{fromObjectId}/associations/{toObjectType}/{toObjectId};
    // api ref: https://developers.hubspot.com/docs/api/crm/associations

    public const HUBSPOT_URL_V3 = 'https://api.hubapi.com/crm/v3/objects/';

    public const HUBSPOT_URL_V4 = 'https://api.hubapi.com/crm/v4/objects/';

    public const ASSOCIATION_CONTACT_TO_EMAIL = 197;

    public const ASSOCIATION_COMPANY_TO_EMAIL = 185;

    /**
     * HubspotEngagementChannel constructor.
     */
    public function __construct() {}

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     *
     * @throws CouldNotSendNotification|InvalidConfiguration
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

        $text = method_exists($notification, 'toHubspotTextMail')
            ? $notification->toHubspotTextMail($notifiable) : (string) $message->render();

        $params = [
            'properties' => [
                'hs_timestamp' => round(microtime(true) * 1000),
                'hubspot_owner_id' => $message->metadata['hubspot_owner_id'] ?? config('hubspot.hubspot_owner_id'),
                'hs_email_direction' => 'EMAIL',
                'hs_email_status' => 'SENT',
                'hs_email_subject' => $message->subject,
                'hs_email_text' => $text,
            ],
        ];

        $hubspotEmail = $this->callApi(self::HUBSPOT_URL_V3.'emails', 'post', $params);

        if (! empty($hubspotEmail['id'])) {

            $this->associate('contact', $hubspotContactId, $hubspotEmail['id'], self::ASSOCIATION_CONTACT_TO_EMAIL);

            if (config('hubspot.company_email_associations')) {
                $contactResp = $this->callApi(
                    self::HUBSPOT_URL_V3.'contacts/'.$hubspotContactId,
                    'get',
                    ['properties' => 'associatedcompanyid']
                );

                $hubspotCompanyId = $contactResp['properties']['associatedcompanyid'] ?? null;

                if ($hubspotCompanyId) {
                    $this->associate('company', $hubspotCompanyId, $hubspotEmail['id'], self::ASSOCIATION_COMPANY_TO_EMAIL);
                }
            }
        }

        return $hubspotEmail;
    }

    /**
     * @throws CouldNotSendNotification|InvalidConfiguration
     */
    protected function associate(string $fromObjectType, int|string $fromObjectId, int|string $emailId, int $associationTypeId): array
    {
        return $this->callApi(
            self::HUBSPOT_URL_V4.$fromObjectType.'/'.$fromObjectId.'/associations/email/'.$emailId,
            'put',
            [
                [
                    'associationCategory' => 'HUBSPOT_DEFINED',
                    'associationTypeId' => $associationTypeId,
                ],
            ]
        );
    }

    protected function callApi(string $baseUrl, string $method, array $params = []): array
    {
        if (is_null(config('hubspot.hubspot_owner_id'))) {
            throw InvalidConfiguration::configurationNotSet();
        }

        $apiKey = config('hubspot.api_key');
        if ($apiKey) {
            // association calls send a JSON list body, so the key can only travel in the query string
            if ($method === 'get') {
                $params['hapikey'] = $apiKey;
            } else {
                $baseUrl .= (strpos($baseUrl, '?') === false ? '?' : '&').'hapikey='.urlencode($apiKey);
            }
        }

        $http = Http::acceptJson()->retry(3, 11 * 1000);

        if (is_null($apiKey)) {
            if (is_null(config('hubspot.access_token'))) {
                throw InvalidConfiguration::configurationNotSet();
            }
            $http = $http->withToken(config('hubspot.access_token'));
        }

        try {
            $response = $http->$method($baseUrl, $params);
        } catch (\Exception $e) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($baseUrl.' '.$e->getMessage());
        }

        if ($response->failed()) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($baseUrl.' '.$response->status().' '.$response->body());
        }

        return $response->json() ?? [];
    }
}
