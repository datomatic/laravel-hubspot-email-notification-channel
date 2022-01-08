<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions;

class InvalidConfiguration extends BaseException
{
    public static function configurationNotSet(): self
    {
        return new static('In order to send notification via Hubspot Engagement you need to add credentials in the `hubspot` key of `config.services`.');
    }
}
