<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/requests-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=requests-for-laravel">
    <img src="art/hero.png" alt="Requests for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Requests for Laravel

Requests from entities with approvals. This package models a **request** (a claim or
application) submitted by an entity that needs sign-off before it is resolved. The approval
flow runs on [`approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel):
configurable rules (**unanimous / quorum / any / weighted**), **multi-stage pipelines**,
**named workflow presets**, **delegation**, per-decision reason + audit trail, and approval
expiry — while `requests` keeps its own `Status` lifecycle, events, and `Requests` facade.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`
- [`roundly-consulting/approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel) (the approval engine)
- [`roundly-consulting/enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel) (enum helpers on `Status`)

Both are hard dependencies and install automatically.

## Installation

```bash
composer require roundly-consulting/requests-for-laravel
```

Publish and run the migrations (the approvals engine ships its own):

```bash
php artisan vendor:publish --tag="requests-migrations"
php artisan vendor:publish --tag="approvals-migrations"
php artisan migrate
```

Optionally publish the config files or translations:

```bash
php artisan vendor:publish --tag="requests-config"
php artisan vendor:publish --tag="approvals-config"
php artisan vendor:publish --tag="requests-translations"
```

`requests` owns the `requests` table; the `approvals`, `approval_requests`,
`approval_request_stages`, and `approval_delegations` tables are owned by the approvals
engine. All migrations are auto-loaded, so the package works without publishing.

## Configuration

`config/requests.php`:

```php
return [
    'model' => RoundlyConsulting\Requests\Models\Request::class,
    'enforce_transitions' => false,
    'default_ttl' => null,
    'register_facade_alias' => true,
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `Request::class` | The request model resolved by `CreateRequest`. Point it at a subclass to extend behaviour. |
| `enforce_transitions` | `bool` | `false` | When `true`, illegal status moves (e.g. re-approving a rejected request) throw `InvalidStatusTransition`. Off by default to preserve the original toggle behaviour. |
| `default_ttl` | `?int` | `null` | When set (in minutes), requests created without an explicit expiry are stamped with `now()->addMinutes(ttl)`. `null` means requests never expire automatically. |
| `register_facade_alias` | `bool` | `true` | Register the short `Requests` class alias for the facade. Skipped automatically if the host has already aliased the name. The fully-qualified facade always works. |

## Concepts

- **Request** (`RoundlyConsulting\Requests\Models\Request`) — the claim/application. Has a
  polymorphic `author`, a `Status` enum, free-form `meta`, a `require_approvals_from` list of
  approver ids, and an optional `expires_at`. Soft-deletable.
- **Status** (`RoundlyConsulting\Requests\Enums\Status`) — `New`, `Approved`, `Rejected`,
  `Cancelled`, `Expired`. Carries `isOpen()`, `isTerminal()`, `canTransitionTo()`, plus the
  shared enum helpers from `enums-for-laravel` (`labels()`, `options()`, `validationRule()`,
  `label()`, …).
- **Approval engine** — a `Request` is an approvals **subject** (`RequiresApproval`). Declaring
  `requireApprovalsFrom([...])` opens an `ApprovalRequest`; its **rule** decides when the bar is
  met. Engine resolutions are mirrored back onto the request's `Status` by a listener, so the
  outcome moves the request and fires the requests events no matter how the decision arrived.

## Usage

### Make an actor able to give approvals

Any model (typically your `User`) that should approve requests uses the approvals engine's
actor trait and interface:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;

class User extends Model implements GivesApprovalsInterface
{
    use GivesApprovals;
}
```

The actor gains the full approvals API: `approve($model, $reason)`, `reject($model, $reason)`,
`cancelApproval($model)`, `delegateApprovalsTo($other)`, `hasApproved($model)`, and more.

### Make a model able to author requests

```php
use RoundlyConsulting\Requests\Concerns\HasRequests;
use RoundlyConsulting\Requests\Contracts\HasRequests as HasRequestsContract;

class User extends Model implements HasRequestsContract
{
    use HasRequests;
}

$user->requests;                 // morphMany relation
$user->openRequests()->get();    // status = New
$user->approvedRequests()->get();
$user->rejectedRequests()->get();
```

### Create a request — the facade (recommended)

The `Requests` facade is the discoverable entry point. The fully-qualified facade
`RoundlyConsulting\Requests\Facades\Requests` always works; a short `Requests` alias is also
registered when free.

```php
use RoundlyConsulting\Requests\Facades\Requests;

$request = Requests::make()
    ->author($user)
    ->type('Claim')
    ->title('Expense reimbursement')
    ->description('Travel costs for the client visit.')
    ->meta(['ip' => request()->ip()])
    ->requireApprovalsFrom([$alice, $bob])   // models OR ids — normalised to ids
    ->expiresAt(now()->addDays(7))
    ->create();
```

### Create a request — the action / DTO (still supported)

```php
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;

$request = (new CreateRequest())->execute(new CreateRequestDto(
    status: Status::New,
    author: $user,
    title: 'Expense reimbursement',
    meta: collect(['ip' => request()->ip()]),
    requireApprovalsFrom: collect([$alice->id, $bob->id]),
    expiresAt: now()->addDays(7),
));
```

`CreateRequest` dispatches a `RequestCreated` event.

### Resolve a request

```php
use RoundlyConsulting\Requests\Facades\Requests;

Requests::approve($request, $alice);                       // 1 of 2 — stays New
Requests::approve($request, $bob, reason: 'Looks good');   // 2 of 2 — becomes Approved
Requests::reject($request, $carol, reason: 'Out of policy');
Requests::reopen($request, $alice);                        // back to New, revokes the decision
```

Every decision is recorded through the approvals engine with its actor, reason, and timestamp,
so you get a full audit trail for free. The underlying `ResolveRequest` action also accepts a
`Status` and an optional reason directly:

```php
use RoundlyConsulting\Requests\Actions\ResolveRequest;

(new ResolveRequest())->execute($request, $alice, Status::Approved, reason: 'Signed off');
```

- A request with **no** declared approvers resolves immediately.
- Otherwise the approval rule decides when the request flips (default **unanimous** — every
  declared approver must approve, matching the original behaviour).

### Approval rules

Pick a rule (and quorum, where relevant) on the builder:

```php
use RoundlyConsulting\Approvals\Enums\ApprovalRule;

// Any 2 of 3 managers (first-past-the-post quorum):
Requests::make()
    ->requireApprovalsFrom([$a, $b, $c])
    ->rule(ApprovalRule::Quorum)
    ->quorum(2)
    ->create();

// First approver wins:
Requests::make()->requireApprovalsFrom([$a, $b])->rule(ApprovalRule::Any)->create();

// Weighted — a senior approver (ProvidesApprovalWeight) clears the threshold alone:
Requests::make()
    ->requireApprovalsFrom([$senior, $junior])
    ->rule(ApprovalRule::Weighted)
    ->quorum(3)
    ->create();
```

### Multi-stage pipelines

Define a sequential pipeline; each stage opens only once the previous one clears:

```php
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;

$request = Requests::make()
    ->title('Payout')
    ->stages([
        new StageDefinition([$manager],  ApprovalRule::Unanimous, name: 'manager'),
        new StageDefinition([$finance],  ApprovalRule::Unanimous, name: 'finance'),
        new StageDefinition([$director], ApprovalRule::Unanimous, name: 'director'),
    ])
    ->create();

$request->currentStage()?->name; // 'manager'
```

By default a rejection in any stage rejects the whole request; call
`->rejectOnStageRejection(false)` to skip the rejected stage and continue.

### Named workflow presets

Reuse rule/quorum/stage wiring from `config('approvals.workflows')`:

```php
// config/approvals.php
'workflows' => [
    'payout' => ['rule' => 'quorum', 'quorum' => 2, 'required_approvers' => 3],
    'release' => ['stages' => [
        ['rule' => 'unanimous', 'required_approvers' => 2, 'name' => 'engineering'],
        ['rule' => 'any', 'required_approvers' => 1, 'name' => 'product'],
    ]],
],
```

```php
// Flat preset — supply the approvers:
Requests::make()->workflow('payout')->requireApprovalsFrom([$a, $b, $c])->create();

// Staged preset — one approver group per stage:
Requests::make()->workflow('release')->stageApprovers([[$eng1, $eng2], [$product]])->create();
```

### Delegated approvers

An approver on leave can hand their authority to a stand-in via the approvals engine:

```php
use RoundlyConsulting\Approvals\Facades\Approvals;

$alice->delegateApprovalsTo($bob)->until(now()->addWeek());
// or: Approvals::delegate($alice, $bob);
```

While the delegation is active, `Requests::approve($request, $bob)` counts as Alice's decision.

### Cancel and expire

```php
Requests::cancel($request);   // status = Cancelled
Requests::expire($request);   // status = Expired
```

### Guarded transitions (opt-in)

Set `requests.enforce_transitions` to `true` to enforce the lifecycle graph. Illegal moves
(for example approving a rejected request) then throw `InvalidStatusTransition`:

```
New        -> Approved | Rejected | Cancelled | Expired
Approved   -> Rejected | New (reopen) | Cancelled
Rejected   -> New (reopen)
Expired    -> New (reopen)
Cancelled  -> (terminal)
```

With the flag off (the default) the guard is skipped and decisions resolve directly.

### Auto-expiry & the prune command

Stamp an expiry on creation (per request via `expiresAt`, or globally via `default_ttl`).
Expire stale open requests with the scheduled command:

```bash
php artisan requests:expire            # expire all open, past-due requests
php artisan requests:expire --dry-run  # report only, change nothing
php artisan requests:expire --chunk=1000
```

Only **open** (New) requests past their `expires_at` are expired; approved/rejected requests
are untouched. The command also lapses any pending approval **decisions** whose own expiry has
passed (via `Approvals::expire()`).

### Query scopes

```php
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;

Request::query()->withStatus(Status::New)->get();
Request::query()->pending()->get();
Request::query()->approved()->get();
Request::query()->rejected()->get();
Request::query()->cancelled()->get();
Request::query()->expired()->get();              // open + past expires_at
Request::query()->expiringBefore(now()->addDay())->get();
Request::query()->authoredBy($user)->get();
```

### Inspecting approvals

```php
$alice->hasApproved($request);          // bool
$alice->hasRejected($request);          // bool
$request->isApproved();                 // engine resolved to approved
$request->isPendingApproval();          // still awaiting decisions
$request->currentApprovalStatus();      // ApprovalStatus enum
$request->currentStage();               // open stage of a pipeline, if any
$request->isExpired();                  // open and past its deadline

$progress = $request->approvalProgress();
$progress?->approved;                   // e.g. 2
$progress?->required;                   // e.g. 3
$progress?->percentage();               // 67
```

### Events

Listen for these package events:

| Event | Payload |
|---|---|
| `RequestCreated` | `Request $request` |
| `RequestStatusChanged` | `Request $request` |
| `ApprovalRecorded` | `Request $request`, `Model $actor` |
| `ApprovalRevoked` | `Request $request`, `Model $actor` |
| `RequestRejected` | `Request $request`, `Model $actor` |
| `RequestCancelled` | `Request $request` |
| `RequestExpired` | `Request $request` |

All live under `RoundlyConsulting\Requests\Events`.

### Exceptions

All package exceptions extend `RoundlyConsulting\Requests\Exceptions\RequestException`, so you
can catch the whole hierarchy at once:

- `InvalidStatusTransition` — thrown by the guard when `enforce_transitions` is on and a move
  is illegal (carries `$from` / `$to`).
- `RequestAlreadyResolved` — for acting on a terminal request (carries `$status`).

Messages are translatable via the `requests::messages` namespace.

### Testing helpers

Swap the manager for a recording fake in tests — no database writes, no hand-rolled
`Event::fake()`:

```php
use RoundlyConsulting\Requests\Facades\Requests;

$fake = Requests::fake();

Requests::make()->title('Expense')->create();
Requests::approve($request, $alice);

$fake->assertCreated();
$fake->assertApproved($request, $alice);
$fake->assertNothingCreated();
```

Available assertions: `assertCreated()`, `assertNothingCreated()`, `assertApproved()`,
`assertRejected()`, `assertReopened()`, `assertCancelled()`, `assertExpired()`.

## Integrates with

This package builds on other roundly-consulting packages:

- **[`approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel)** —
  powers the whole approval flow: rules (unanimous / quorum / any / weighted), multi-stage
  pipelines, named workflow presets, delegation, per-decision reason + audit, and expiry. A
  `Request` is an approvals subject (`RequiresApproval`); resolutions are synced back to the
  request `Status` by `SyncRequestStatusFromApproval`.
- **[`enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel)** — the
  `Status` enum adopts the shared `Helpers` trait for `labels()`, `options()`,
  `validationRule()`, `values()`, and per-case `label()`.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
