# Requests for Laravel

Requests from entities with approvals. This package models a **request** (a claim or
application) submitted by an entity that needs one or more approvers to sign off before it is
resolved. A request only flips to `Approved` once **every** required approver has approved it.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`

## Installation

```bash
composer require roundly-consulting/requests-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="requests-migrations"
php artisan migrate
```

Optionally publish the config file or translations:

```bash
php artisan vendor:publish --tag="requests-config"
php artisan vendor:publish --tag="requests-translations"
```

The package ships two tables: `requests` and `approvals`. Both migrations are auto-loaded, so
the package works without publishing — publish only if you want to customise the schema.

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
  `Cancelled`, `Expired`. Carries `isOpen()`, `isTerminal()`, `label()`, and
  `canTransitionTo()`.
- **Approvals** — a request `HasApprovals`; any actor model that `GivesApprovals` can toggle
  an approval on a request. A request is only resolved to `Approved` once every actor in
  `require_approvals_from` has approved it.

## Usage

### Make an actor able to give approvals

Any model (typically your `User`) that should approve requests implements the contract and
uses the trait:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Requests\Approvals\Concerns\GivesApprovals;
use RoundlyConsulting\Requests\Approvals\Contracts\GivesApprovals as GivesApprovalsContract;

class User extends Model implements GivesApprovalsContract
{
    use GivesApprovals;
}
```

> Note the trait/contract split: the **approver** side lives under
> `RoundlyConsulting\Requests\Approvals\Concerns` / `…\Approvals\Contracts`, while the
> **author** side (below) lives under the top-level `RoundlyConsulting\Requests\Concerns` /
> `…\Contracts`.

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

Requests::approve($request, $alice); // 1 of 2 — stays New
Requests::approve($request, $bob);   // 2 of 2 — becomes Approved
Requests::reject($request, $carol);  // rejects and revokes prior approval
Requests::reopen($request, $alice);  // back to New, revokes approval
```

The underlying `ResolveRequest` action is unchanged and still accepts a `Status` directly:

```php
use RoundlyConsulting\Requests\Actions\ResolveRequest;

(new ResolveRequest())->execute($request, $alice, Status::Approved);
```

- Approving with no `require_approvals_from` resolves immediately.
- A request only flips to `Approved` once **all** required approvers have approved.

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

With the flag off (the default) behaviour is identical to previous versions.

### Auto-expiry & the prune command

Stamp an expiry on creation (per request via `expiresAt`, or globally via `default_ttl`).
Expire stale open requests with the scheduled command:

```bash
php artisan requests:expire            # expire all open, past-due requests
php artisan requests:expire --dry-run  # report only, change nothing
php artisan requests:expire --chunk=1000
```

Only **open** (New) requests past their `expires_at` are expired; approved/rejected requests
are untouched.

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
$request->hasBeenApprovedBy($alice);   // bool
$alice->hasApproved($request);         // bool
$alice->toggleApproval($request);      // true = added, false = removed
$request->isExpired();                 // open and past its deadline
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

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
