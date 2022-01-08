<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions;

class CouldNotSendNotification extends BaseException
{
    public static function serviceRespondedWithAnError(string $response): self
    {
        return new static($response);
    }
}
