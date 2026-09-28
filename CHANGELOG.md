# Changelog

All notable changes to this package are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Update this file in the same change as [`doc/api.md`](doc/api.md) and the public PHP API.

## [Unreleased]

## [1.1.0] - 2026-09-28

### Added

- `Email::addTo()`, `cc()`, and `bcc()` for multiple recipients. Together with `to`, a message can include at most 50 addresses.
- `Email::withoutReplyTo()` sends `reply_to: []` so the product Reply-To is omitted. `replyTo()` appends an address.
- `Email::header()` and `headers()` for custom headers.
- `QuotaExceededException` for `quota_exceeded` and `MessageTooLargeException` for `message_too_large`.

### Changed

- `reply_to` is sent as an array of addresses.
- Tag names and values must already match `[A-Za-z0-9_-]`. Invalid tags throw `validation_error` instead of being stripped. Names are at most 64 characters, values at most 256, at most 48 tags, and `team_id` and `product_id` are reserved.
- The SDK no longer rejects more than 10 attachments. The team's plan sets that limit (the default is 10). The 7 MB decoded platform ceiling is still enforced locally. Inline `content_id` values are normalized, and empty or duplicate ids are rejected.
- Document that `product_not_ready` also covers a paused team (and a team with no SES tenant). An archived product's tokens are `unauthenticated`.

## [1.0.0] - 2026-09-04

### Added

- `Client` with default API root `https://mailgazelle.com/api/v1` and Bearer authentication.
- `emails()->send()` for `POST /emails` (text, HTML, From, Reply-To, tags, idempotency, attachments).
- `emails()->get()` for `GET /emails/{id}` status records.
- `domains()->list()` for `GET /domains`.
- Typed exceptions for every documented API error code.
- Pluggable `TransportInterface` with a default `CurlTransport`.
