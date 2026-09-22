<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions;

class HubspotObjectNotFound extends CouldNotSendNotification
{
    /**
     * Since the 2026-09 write validation, Hubspot rejects an association to an
     * object id it cannot resolve instead of silently accepting it.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function matches(array $payload): bool
    {
        return ($payload['category'] ?? null) === 'VALIDATION_ERROR'
            && ! empty($payload['context']['INVALID_OBJECT_IDS']);
    }
}
