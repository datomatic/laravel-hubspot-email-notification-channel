# Changelog

All notable changes to `hubspot-engagement` will be documented in this file

## 1.6.1 - 2026-09-22

Follow-up to 1.6.0. Fixes a credential leak and makes HubSpot's write-validation errors usable.

### Fixed

- **Security:** the `hubspot.api_key` is no longer included in `CouldNotSendNotification` messages. 1.6.0 moved the key into the query string and then interpolated that URL into the exception text, so any failed API call wrote the key to the application log and to any connected error tracker. Rotate the key if 1.6.0 ran in production with `hubspot.api_key` set.
- Failed API responses no longer lose their error body. `retry()` makes the HTTP client throw a `RequestException`, whose message truncates the body at 120 characters; the response is now read back off the exception, so the full HubSpot error payload reaches `CouldNotSendNotification`.
- An invalid `associatedcompanyid` no longer fails the whole notification. The email is already stored and associated to the contact at that point, so a `VALIDATION_ERROR` naming only the company id is swallowed. Any other association error is still thrown.

### Added

- `CouldNotSendNotification::payload()`, `category()`, `correlationId()`, `invalidObjectIds()` and `hasInvalidObjectIds()`, exposing HubSpot's decoded error body. Since the 2026-09 write validation, an association to a stale or deleted object id is rejected with `category: VALIDATION_ERROR` and `context.INVALID_OBJECT_IDS` (e.g. `"CONTACT=838442890479 is not valid"`); catch the exception and read `invalidObjectIds()` to detect and clear a stale contact id.

### Changed

- **Breaking:** minimum PHP version raised to 8.0, required by the new union type hints on `associate()`.
- `associate()` now type-hints `$fromObjectId` and `$emailId` as `int|string`.

### Known issues

- `POST /crm/v3/objects/emails` runs before the association, so a rejected association leaves an orphaned email object in HubSpot. These are not cleaned up automatically.

## 1.6.0 - 2026-09-22

Compatibility release for HubSpot's CRM API Write Validation Enforcement (API version 2026-09, rolled out on 2026-09-08). Upgrading is required: the association calls made by previous versions are rejected by the new validation.

### Changed

- **Breaking:** associations are now written through `PUT /crm/v4/objects/{fromObjectType}/{fromObjectId}/associations/email/{emailId}` with a JSON list body of `{associationCategory, associationTypeId}` objects. The previous `/associations/default/...` endpoint with an `['associationTypeId' => ...]` body no longer passes write validation.
- When authenticating with `hubspot.api_key`, the key is now appended to the query string for every non-`GET` call, because the association request body must stay a JSON list.
- `callApi()` returns an empty array instead of `null` when the response has no JSON body.

### Added

- `HubspotEmailChannel::ASSOCIATION_CONTACT_TO_EMAIL` (197) and `HubspotEmailChannel::ASSOCIATION_COMPANY_TO_EMAIL` (185) constants, replacing the inline association type ids.
- `HubspotEmailChannel::associate()`, a protected helper wrapping the v4 association call.

## 1.0.0 - 2021-02-22

- initial release
