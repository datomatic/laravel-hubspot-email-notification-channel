<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestToHubspotTextMailMethodNotification extends Notification
{
    public function via($notifiable)
    {
        return [HubspotEmailChannel::class];
    }

    public function toMail(TestNotifiable $notifiable)
    {
        return (new MailMessage)
            ->subject('Subject')
            ->from('from3@email.com', 'From3')
            ->view('hubspot-engagement::email_test_view', [])
            ->cc('cc@email.com', 'cc_name')
            ->bcc('bcc@email.com', 'bcc_name');
    }

    public function toHubspotTextMail(TestNotifiable $notifiable)
    {
        return 'test message';
    }
}
