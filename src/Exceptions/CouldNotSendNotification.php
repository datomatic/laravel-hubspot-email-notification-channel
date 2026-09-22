<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions;

use Datomatic\LaravelHubspotEmailNotificationChannel\Contracts\HasHubspotContact;

class CouldNotSendNotification extends BaseException
{
    /** @var array<string, mixed> */
    protected array $payload = [];

    /**
     * @param  array<string, mixed>  $payload  decoded HubSpot error body, when the response carried one
     */
    public static function serviceRespondedWithAnError(string $response, array $payload = []): static
    {
        $exception = new static($response);
        $exception->payload = $payload;

        return $exception;
    }

    public static function notifiableIsNotAHubspotContact(string $notifiable): self
    {
        return new CouldNotSendNotification(
            $notifiable.' must implement '.HasHubspotContact::class.' to be notified through the Hubspot channel.'
        );
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function category(): ?string
    {
        $category = $this->payload['category'] ?? null;

        return is_string($category) ? $category : null;
    }

    public function correlationId(): ?string
    {
        $correlationId = $this->payload['correlationId'] ?? null;

        return is_string($correlationId) ? $correlationId : null;
    }

    /**
     * Object ids HubSpot refused, as returned since the 2026-09 CRM write validation enforcement.
     * Example entry: "CONTACT=838442890479 is not valid".
     *
     * @return array<int, string>
     */
    public function invalidObjectIds(): array
    {
        $invalidObjectIds = $this->payload['context']['INVALID_OBJECT_IDS'] ?? [];

        return is_array($invalidObjectIds) ? array_values(array_filter($invalidObjectIds, 'is_string')) : [];
    }

    public function hasInvalidObjectIds(): bool
    {
        return $this->invalidObjectIds() !== [];
    }
}
