<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Contracts;

use Illuminate\Notifications\Notification;

interface HasHubspotContact
{
    /**
     * Id of the Hubspot contact the notification should be logged against,
     * or null to skip logging it.
     */
    public function getHubspotContactId(Notification $notification): int|string|null;
}
