<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel;

use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\CouldNotSendNotification;
use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\InvalidConfiguration;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class HubspotEmailChannel
{
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
        $hubspotContactId = $notifiable->getHubspotContactId();

        if (empty($hubspotContactId)) {
            return null;
        }

        if (! method_exists($notification, 'toMail')) {
            return null;
        }

        $message = $notification->toMail($notifiable);
        $apiKey = config('hubspot.api_key');
        if (is_null($apiKey) || is_null(config('hubspot.hubspot_owner_id'))) {
            throw InvalidConfiguration::configurationNotSet();
        }

        $response = Http::post(
            self::HUBSPOT_URL.'?hapikey=' . $apiKey,
            [
                "properties" => [
                    "hs_timestamp" => now()->getPreciseTimestamp(3),
                    "hubspot_owner_id" => config('hubspot.hubspot_owner_id'),
                    "hs_email_direction" => "EMAIL",
                    "hs_email_status" => "SENT",
                    "hs_email_subject" => $message->subject,
                    "hs_email_text" => (string) $message->render(), ],
            ]
        );
        $hubspotEmail = $response->json();

        if ($response->status() == 201 && ! empty($hubspotEmail['id'])) {
            $newResp = Http::put(self::HUBSPOT_URL.'/'. $hubspotEmail['id'] . '/associations/contacts/' . $hubspotContactId . '/198?hapikey=' . $apiKey);

            if ($newResp->status() != 200) {
                throw CouldNotSendNotification::serviceRespondedWithAnError($newResp->body());
            }
            $hubspotEmail['associations'] = $newResp['associations'];
        } else {
            throw CouldNotSendNotification::serviceRespondedWithAnError($response->body());
        }

        return $hubspotEmail;
    }
}
