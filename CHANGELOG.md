# Changelog

All notable changes to this package are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Update this file in the same change as [`doc/api.md`](doc/api.md) and the public PHP API.

## [Unreleased]

### Changed

- Document that `product_not_ready` also covers a paused team (and a team with no SES tenant). Same exception class and HTTP status; send payload unchanged.

## [1.0.0] - 2026-09-04

### Added

- `Client` with default API root `https://mailgazelle.com/api/v1` and Bearer authentication.
- `emails()->send()` for `POST /emails` (text, HTML, From, Reply-To, tags, idempotency, attachments).
- `emails()->get()` for `GET /emails/{id}` status records.
- `domains()->list()` for `GET /domains`.
- Typed exceptions for every documented API error code.
- Pluggable `TransportInterface` with a default `CurlTransport`.
