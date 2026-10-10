# Changelog

All notable changes to `requests-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Fixed

- A request made with a raw `Request::create()` is `New` in memory right away, so `isExpired()`
  and `Requests::approve()` no longer crash on a null status.
- Rejecting a request without approvers writes `Rejected` before `RequestRejected` fires, so its
  listeners see the rejection (`RequestStatusChanged` now comes first, as on the approval-round
  path).
- `Requests::reopen()` announces only what it changed: `RequestStatusChanged` only when the status
  moved, and `ApprovalRevoked` only when a decision was withdrawn or the request was reopened.
  Reopening a `New` request the actor never decided fires neither. **Behaviour change.**
- `Requests::approve()` and `reject()` on a request past its `expires_at` no longer decide it
  before `requests:expire` runs. The request is expired on the spot (`RequestExpired` fires once)
  and the decision is refused with `RequestAlreadyResolved` (status `Expired`), with or without
  approvers. Flat and staged approval rounds (also the fresh round `reopen()` opens) now carry the
  request's `expires_at`, so the approvals engine stops taking decisions at the same deadline.
  **Behaviour change.**
- Reopening a request whose `expires_at` already passed (it expired on its deadline, or was
  decided before the deadline went by) no longer brings it back already overdue, to be expired
  again by the next sweep. Its deadline restarts at `requests.default_ttl` from now when that is
  set, and is cleared otherwise. A deadline still in the future is kept. **Behaviour change.**
- `Requests::fake()`'s `create()` returns the configured `requests.model` (not always the packaged
  `Request`), with the author, `meta`, declared approvers' keys and default-TTL `expires_at` set
  exactly as the real create sets them, still unsaved. Host code typed against its own request
  model no longer throws a `TypeError` under the fake only. A bare-id approver is refused with
  `InvalidApprover`, as in the real create.
- `Requests::fake()`'s `assertApproved()` / `assertRejected()` / `assertReopened()` /
  `assertCancelled()` / `assertExpired()` no longer treat two unsaved requests (what the fake's
  `create()` returns) as the same one: without a key, only the very same instance matches.
- Status writes no longer trust a stale copy of the request. `cancel()`, `expire()`, `reopen()`,
  `approve()` / `reject()` on a request without approvers, and the approval-sync listener re-read
  the request's row under a lock (`lockForUpdate()`) and check it there. A copy loaded before a
  concurrent decision, cancel or sweep can no longer overwrite the newer status (for example
  expire an approved request, or approve, reopen or un-cancel a cancelled one). Events fire only
  when the status really changed, so repeating a no-approver request's current outcome no longer
  re-fires `RequestStatusChanged` / `RequestRejected`. **Behaviour change.**
- `Requests::expireDue()` and `requests:expire` expire a request only if it is still open and
  past due when its turn comes. A request decided, cancelled or expired by an overlapping sweep
  after its chunk was read is left alone and not counted, so overlapping sweeps announce each
  request once.

### Security

- `Requests::reopen()` asks the approvals authorization gate (`approvals.authorization.enabled` /
  `ability`) whatever state the approval round is in. An actor the gate denies could reopen an
  approved or rejected request, because the gate was reached only through the withdrawal, which
  is skipped once the round is over. Now they get `UnauthorizedApprovalException` and nothing
  changes. **Behaviour change.**

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Requests (claims, applications) authored by any model, with a `Status` lifecycle, free-form
  meta and optional expiry.
- A fluent `Requests` facade (`Requests::make()->author(...)->requireApprovalsFrom(...)->create()`)
  plus Action classes and a DTO.
- Approval flows on `approvals-for-laravel`: unanimous, quorum, any or weighted rules, with every
  decision's actor, reason and time recorded. Only the declared approvers (or their delegates)
  may decide; anyone else gets `UnauthorizedApprovalException`.
- Multi-stage approval pipelines via `stages()`, and named workflow presets via `workflow()`.
- Delegated approvers: a stand-in's decision counts for the approver who delegated.
- `Requests::approve()`, `reject()`, `reopen()`, `cancel()` and `expire()`, with optional guarded
  transitions (`requests.enforce_transitions`). `reopen()` opens a fresh approval round once the
  last one resolved; `cancel()` / `expire()` close the open round; a cancelled or expired request
  stays closed (`RequestAlreadyResolved`).
- Auto-expiry with a default TTL, `Requests::expireDue(dryRun:, chunk:)` (the `ExpireDueRequests`
  action) and the `requests:expire` command (`--dry-run`, `--chunk`) that wraps it and lapses
  expired approval decisions on requests only (`Approvals::expire()` scoped to the request model).
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
- Approvers are models: `CreateRequestDto::$approvers` replaces the id-only
  `$requireApprovalsFrom`, and `requireApprovalsFrom()` refuses a bare id (`InvalidApprover`).
- `requests.default_ttl` and the boolean flags accept `env()` strings.

### Fixed

- `Requests::fake()` no longer drops the `$reason` of `approve()`, `reject()` and `reopen()`.
