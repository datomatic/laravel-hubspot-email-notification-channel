<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions;

class InvalidConfiguration extends BaseException
{
    public static function configurationNotSet(): self
    {
        return new static('In order to send notification via Hubspot Email you need to set credentials in .env file.');
    }
}
