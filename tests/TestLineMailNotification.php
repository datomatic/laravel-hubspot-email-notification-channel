<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel;

class TestLineMailNotification extends Notification
{
    public function via($notifiable)
    {
        return [HubspotEmailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Subject')
            ->greeting('Greeting')
            ->line('Line')
            ->action('button', 'https://www.google.it');
    }
}
