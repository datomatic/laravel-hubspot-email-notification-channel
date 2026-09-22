<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Datomatic\LaravelHubspotEmailNotificationChannel\Contracts\HasHubspotContact;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class TestNotifiableWithoutContactId implements HasHubspotContact
{
    use Notifiable;

    /**
     * @return int
     */
    public function routeNotificationForMail(Notification $notification)
    {
        return 'email@email.com';
    }

    public function getHubspotContactId(Notification $notification): int|string|null
    {
        return null;
    }
}
