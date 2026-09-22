<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel;

use Datomatic\LaravelHubspotEmailNotificationChannel\Contracts\HasHubspotContact;
use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\CouldNotSendNotification;
use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\HubspotObjectNotFound;
use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\InvalidConfiguration;
use Illuminate\Http\Client\RequestException;
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
     * Send the given notification.
     *
     * @throws CouldNotSendNotification|InvalidConfiguration
     */
    public function send(mixed $notifiable, Notification $notification): ?array
    {
        if (! $notifiable instanceof HasHubspotContact) {
            throw CouldNotSendNotification::notifiableIsNotAHubspotContact(get_debug_type($notifiable));
        }

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
                    try {
                        $this->associate('company', $hubspotCompanyId, $hubspotEmail['id'], self::ASSOCIATION_COMPANY_TO_EMAIL);
                    } catch (HubspotObjectNotFound) {
                        // a stale associatedcompanyid is not worth failing the notification for:
                        // the email is already stored and associated to the contact
                    }
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

    /**
     * @throws CouldNotSendNotification|InvalidConfiguration
     */
    protected function callApi(string $url, string $method, array $params = []): array
    {
        if (is_null(config('hubspot.hubspot_owner_id')) || is_null(config('hubspot.access_token'))) {
            throw InvalidConfiguration::configurationNotSet();
        }

        $http = Http::acceptJson()
            ->withToken(config('hubspot.access_token'))
            ->retry(config('hubspot.retry.times', 3), config('hubspot.retry.sleep_milliseconds', 11 * 1000));

        try {
            $response = $http->$method($url, $params);
        } catch (RequestException $e) {
            // retry() makes the client throw on a failed response, and RequestException
            // truncates the body at 120 chars, so read the error off the response itself
            $response = $e->response;
        } catch (\Exception $e) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($url.' '.$e->getMessage());
        }

        if ($response->failed()) {
            $payload = is_array($response->json()) ? $response->json() : [];
            $message = $url.' '.$response->status().' '.$response->body();

            throw HubspotObjectNotFound::matches($payload)
                ? HubspotObjectNotFound::serviceRespondedWithAnError($message, $payload)
                : CouldNotSendNotification::serviceRespondedWithAnError($message, $payload);
        }

        return $response->json() ?? [];
    }
}
