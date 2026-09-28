# Changelog

All notable changes to `requests-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Requests (claims, applications) authored by any model, with a `Status` lifecycle, free-form
  meta and optional expiry.
- A fluent `Requests` facade (`Requests::make()->author(...)->requireApprovalsFrom(...)->create()`)
  plus Action classes and a DTO.
- Approval flows on `approvals-for-laravel`: unanimous, quorum, any or weighted rules, with every
  decision's actor, reason and time recorded.
- Multi-stage approval pipelines via `stages()`, and named workflow presets via `workflow()`.
- Delegated approvers: a stand-in's decision counts for the approver who delegated.
- `Requests::approve()`, `reject()`, `reopen()`, `cancel()` and `expire()`, with optional guarded
  transitions (`requests.enforce_transitions`).
- Auto-expiry with a default TTL, `Requests::expireDue(dryRun:, chunk:)` (the `ExpireDueRequests`
  action) and the `requests:expire` command (`--dry-run`, `--chunk`) that wraps it.
- `Requests::canTransition($request, $status)` asks the lifecycle graph whether a move is allowed.
- Query scopes such as `pending()`, `approved()`, `expiringBefore()` and `authoredBy()`, and
  approval-progress helpers on the model.
- Events for creation, status changes, recorded and revoked approvals, rejection, cancellation
  and expiry.
- `Requests::fake()` for tests: a `RequestManager` subtype installed behind the facade and the
  container (so injected managers and the builder are recorded too), with `assert*()` and
  `assertNothing*()` for create, approve, reject, reopen (actor and reason), cancel, expire and
  `expireDue()`.

### Changed

- `RequestManager` resolves every action from the container and is no longer `final`.
- `RequestBuilder` takes the `RequestManager` instead of a `CreatesRequests` implementation; the
  `Contracts\CreatesRequests` interface is removed.
- Adapted to the `approvals-for-laravel` API (`Approvals::request($subject)->workflow()->open()`).

### Fixed

- `Requests::fake()` no longer drops the `$reason` of `approve()`, `reject()` and `reopen()`.
