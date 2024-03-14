# Hubspot Email Notifications Channel for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/datomatic/laravel-hubspot-email-notification-channel.svg?style=flat-square)](https://packagist.org/packages/datomatic/laravel-hubspot-email-notification-channel)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Quality Score](https://img.shields.io/scrutinizer/g/datomatic/laravel-hubspot-email-notification-channel.svg?style=flat-square)](https://scrutinizer-ci.com/g/datomatic/laravel-hubspot-email-notification-channel)
[![Total Downloads](https://img.shields.io/packagist/dt/datomatic/laravel-hubspot-email-notification-channel.svg?style=flat-square)](https://packagist.org/packages/datomatic/laravel-hubspot-email-notification-channel)

This package makes it easy to log notifications
to [Hubspot Email Engagement V3](https://developers.hubspot.com/docs/api/crm/email) with Laravel >= 8.x

## Contents

- [Hubspot Email Notifications Channel for Laravel](#hubspot-email-notifications-channel-for-laravel)
  - [Contents](#contents)
  - [Installation](#installation)
    - [Setting up the HubspotEmail service](#setting-up-the-hubspotemail-service)
  - [Usage](#usage)
      - [Email notification](#email-notification)
    - [Example](#example)
      - [Notification example](#notification-example)
      - [Model example](#model-example)
  - [Changelog](#changelog)
  - [Testing](#testing)
  - [Security](#security)
  - [Contributing](#contributing)
  - [Credits](#credits)
  - [License](#license)

## Installation

You can install the package via composer:

```bash
composer require datomatic/laravel-hubspot-email-notification-channel
```

### Setting up the HubspotEmail service

Generate an [API Key](https://knowledge.hubspot.com/integrations/how-do-i-get-my-hubspot-api-key)
or a [Private App](https://developers.hubspot.com/docs/api/private-apps) from Hubspot.
**Important!** From November 30th 2022 Hubspot will require you to use only private apps. If you have both API Key and Private App configured, to switch using only Private App just remove `HUBSPOT_API_KEY` from your .env file.

Configure your Hubspot API on .env
```dotenv
HUBSPOT_API_KEY=XXXXXXXX 
# or
HUBSPOT_ACCESS_TOKEN=XXXXXXXX
HUBSPOT_OWNER_ID=XXX //an Hubspot owner id to save as email creator
```

To publish the config file to config/newsletter.php run:
```bash
php artisan vendor:publish --provider="Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailServiceProvider"
```
This will publish a file hubspot.php in your config directory with the following contents:

```php
// config/hubspot.php

return [
    'api_key' => env('HUBSPOT_API_KEY'),
    'access_token' => env('HUBSPOT_API_KEY'),
    'hubspot_owner_id' => env('HUBSPOT_OWNER_ID',null)
];
```

## Usage

You can now use the channel in your `via()` method inside the Notification class.

#### Email notification
Your Notification class must have toMail method.
The package accepts: MailMessage lines notifications, MailMessage view notifications and Markdown mail notifications.

Data stored on Hubspot:
- Hubspot Contact Id => The Notifiable Model must have **getHubspotContactId(\Illuminate\Notifications\Notification $notification)** function
- Send at timestamp
- subject
- html body

### Example

#### Notification example

```php
use Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification
{
    ...
    public function via($notifiable)
    {
        return ['mail', HubspotEmailChannel::class]];
    }

    public function toMail($notifiable)
    {
        $message = (new MailMessage)
            ->subject(__('order.order_confirm', ['code' => $this->order->code]));

        return $message->view(
            'emails.order', [
                'title' => __('order.order_confirm', ['code' => $this->order->code]),
                'order' => $this->order
            ]
        );
    }
    ...
}
```

#### Model example
```php
namespace App\Models;

class User extends Authenticatable{
    ...
    public function getHubspotContactId(\Illuminate\Notifications\Notification $notification){
        return $this->hubspot_contact_id;
    }
    ...
}
```

#### Dynamic Contact Owner
```php
use Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel;
use Illuminate\Notifications\Notification;

class PersonalMessage extends Notification
{
    ...

    public function via($notifiable)
    {
        return ['mail', HubspotEmailChannel::class]];
    }

    public function toMail($notifiable)
    {
        $message = (new MailMessage)
            ->subject(__('messages.personal_subject'))
            ->from($this->employee->email, $this->employee->name)
            ->metadata('hubspot_owner_id', $this->employee->hubspot_owner_id);

        return $message->view(
            'messages.personal', [
                'title' => __('messages.personal_welcome', ['recipient' => $notifiable->name]),
                'employee' => $this->employee
            ]
        );
    }

    ...
}
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Security

If you discover any security related issues, please email info@albertoperipolli.com instead of using the issue tracker.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Alberto Peripolli](https://github.com/trippo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
