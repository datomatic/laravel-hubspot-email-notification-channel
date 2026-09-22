# Changelog

All notable changes to `hubspot-engagement` will be documented in this file

## 1.6.0 - 2026-09-22

Compatibility release for HubSpot's CRM API Write Validation Enforcement (API version 2026-09, rolled out on 2026-09-08). Upgrading is required: the association calls made by previous versions are rejected by the new validation.

### Changed

- **Breaking:** associations are now written through `PUT /crm/v4/objects/{fromObjectType}/{fromObjectId}/associations/email/{emailId}` with a JSON list body of `{associationCategory, associationTypeId}` objects. The previous `/associations/default/...` endpoint with an `['associationTypeId' => ...]` body no longer passes write validation.
- **Breaking:** minimum PHP version raised to 8.0.
- When authenticating with `hubspot.api_key`, the key is now appended to the query string for every non-`GET` call, because the association request body must stay a JSON list.
- `callApi()` returns an empty array instead of `null` when the response has no JSON body.

### Added

- `HubspotEmailChannel::ASSOCIATION_CONTACT_TO_EMAIL` (197) and `HubspotEmailChannel::ASSOCIATION_COMPANY_TO_EMAIL` (185) constants, replacing the inline association type ids.
- `HubspotEmailChannel::associate()`, a protected helper wrapping the v4 association call.

## 1.0.0 - 2021-02-22

- initial release
