# Hubspot Email Notifications Channel for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/datomatic/laravel-hubspot-email-notification-channel.svg?style=flat-square)](https://packagist.org/packages/datomatic/laravel-hubspot-email-notification-channel)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Quality Score](https://img.shields.io/scrutinizer/g/datomatic/laravel-hubspot-email-notification-channel.svg?style=flat-square)](https://scrutinizer-ci.com/g/datomatic/laravel-hubspot-email-notification-channel)
[![Total Downloads](https://img.shields.io/packagist/dt/datomatic/laravel-hubspot-email-notification-channel.svg?style=flat-square)](https://packagist.org/packages/datomatic/laravel-hubspot-email-notification-channel)

This package makes it easy to log notifications
to [Hubspot Email Engagement V3](https://developers.hubspot.com/docs/api/crm/email) with Laravel >= 12.x

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
      - [Handling a stale contact id](#handling-a-stale-contact-id)
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

Create a [Private App](https://developers.hubspot.com/docs/api/private-apps) in Hubspot and copy its access token.
Hubspot API keys were sunset on November 30th 2022 and are no longer accepted.

Configure your Hubspot API on .env
```dotenv
HUBSPOT_ACCESS_TOKEN=XXXXXXXX
HUBSPOT_OWNER_ID=XXX # an Hubspot owner id to save as email creator
```

To publish the config file to config/hubspot.php run:
```bash
php artisan vendor:publish --provider="Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailServiceProvider"
```
This will publish a file hubspot.php in your config directory with the following contents:

```php
// config/hubspot.php

return [
    'access_token' => env('HUBSPOT_ACCESS_TOKEN'),
    'hubspot_owner_id' => env('HUBSPOT_OWNER_ID'),
    'company_email_associations' => true,
    'retry' => [
        'times' => env('HUBSPOT_RETRY_TIMES', 3),
        'sleep_milliseconds' => env('HUBSPOT_RETRY_SLEEP_MILLISECONDS', 11 * 1000),
    ],
];
```

Hubspot enforces its rate limit over a ten second window, so the default retry waits eleven seconds
between attempts. Three attempts means a failing call can block for over twenty seconds, which matters
inside a queued job: lower `HUBSPOT_RETRY_TIMES` to `1` to disable retrying altogether.

## Usage

You can now use the channel in your `via()` method inside the Notification class.

#### Email notification
Your Notification class must have toMail method.
The package accepts: MailMessage lines notifications, MailMessage view notifications and Markdown mail notifications.

Data stored on Hubspot:
- Hubspot Contact Id => The Notifiable Model must implement **Datomatic\LaravelHubspotEmailNotificationChannel\Contracts\HasHubspotContact**
- Send at timestamp
- subject
- mail text (the html of the email or the toHubspotTextMail method of notification) 

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

    //Optional text method
    public function toHubspotTextMail($notifiable):string
    {
        return 'text of message to put on hubspot';
    }
    ...
}
```
#### Send text version of html email
An example of use of `toHubspotTextMail` method is to send the text version of the email.

```php

use Soundasleep\Html2Text;
class OrderConfirmation extends Notification
{
    ...

    public function toHubspotTextMail(mixed $notifiable): string
    {
        return Html2Text::convert($this->toMail($notifiable)->render());
    }
}

```


#### Model example
```php
namespace App\Models;

use Datomatic\LaravelHubspotEmailNotificationChannel\Contracts\HasHubspotContact;
use Illuminate\Notifications\Notification;

class User extends Authenticatable implements HasHubspotContact
{
    ...
    public function getHubspotContactId(Notification $notification): int|string|null
    {
        return $this->hubspot_contact_id;
    }
    ...
}
```

Returning `null` skips logging the notification to Hubspot.

#### Handling a stale contact id

Since the 2026-09 write validation, Hubspot rejects an association to a contact id it cannot resolve
instead of silently accepting it. That surfaces as a `HubspotObjectNotFound`, which carries the
decoded error body:

```php
use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\HubspotObjectNotFound;

try {
    $user->notify(new OrderConfirmation($order));
} catch (HubspotObjectNotFound $e) {
    // ["CONTACT=838442890479 is not valid"]
    logger()->warning('Stale Hubspot contact', $e->invalidObjectIds());

    $user->update(['hubspot_contact_id' => null]);
}
```

`HubspotObjectNotFound` extends `CouldNotSendNotification` and also exposes `payload()`, `category()`
and `correlationId()`. Note that the email object is created before the association is attempted, so a
rejected association leaves an orphaned email in Hubspot.

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
