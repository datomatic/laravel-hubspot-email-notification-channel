<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class TestNotifiable
{
    use Notifiable;

    /**
     * @return int
     */
    public function routeNotificationForMail(Notification $notification)
    {
        return 'email@email.com';
    }

    public function getHubspotContactId()
    {
        return 987654321;
    }
}
