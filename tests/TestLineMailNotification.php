<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestLineMailNotification extends Notification
{
    public function via($notifiable)
    {
        return [HubspotEmailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->subject('Subject')
            ->greeting('Greeting')
            ->line('Line')
            ->action('button', 'https://www.google.it');
    }
}
