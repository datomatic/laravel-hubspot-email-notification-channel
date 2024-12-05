<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestMarkdownMailNotification extends Notification
{
    public function via($notifiable)
    {
        return [HubspotEmailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Subject')
            ->from('from2@email.com')
            ->cc('cc@email.com', 'cc_name')
            ->cc('cc2@email.com')
            ->bcc('bcc@email.com')
            ->bcc('bcc2@email.com', 'bcc2_name')
            ->markdown('hubspot-engagement::email_test_markdown', []);
    }
}
