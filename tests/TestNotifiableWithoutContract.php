<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class TestNotifiableWithoutContract
{
    use Notifiable;

    public function routeNotificationForMail(Notification $notification)
    {
        return 'email@email.com';
    }
}
