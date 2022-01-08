<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions;

use Exception;

class BaseException extends Exception
{
    final public function __construct(string $error)
    {
        parent::__construct($error);
    }
}
