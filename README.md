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

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="requests-config"
```

The package ships two tables: `requests` and `approvals`. Both migrations are auto-loaded, so
the package works without publishing — publish only if you want to customise the schema.

## Configuration

`config/requests.php`:

```php
return [
    // The Eloquent model used when creating requests. Override with your own
    // subclass of RoundlyConsulting\Requests\Models\Request to add behaviour.
    'model' => RoundlyConsulting\Requests\Models\Request::class,
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `Request::class` | The request model resolved by `CreateRequest`. Point it at a subclass to extend behaviour. |

## Concepts

- **Request** (`RoundlyConsulting\Requests\Models\Request`) — the claim/application. Has a
  polymorphic `author`, a `Status` enum, free-form `meta`, and a `require_approvals_from`
  list of approver ids. Soft-deletable.
- **Status** (`RoundlyConsulting\Requests\Enums\Status`) — `New`, `Approved`, `Rejected`.
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

### Create a request

```php
use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;

$request = (new CreateRequest())->execute(new CreateRequestDto(
    status: Status::New,
    author: $user,                                  // optional polymorphic author
    type: 'Claim',
    title: 'Expense reimbursement',
    description: 'Travel costs for the client visit.',
    meta: collect(['ip' => request()->ip()]),
    requireApprovalsFrom: collect([$alice->id, $bob->id]), // both must approve
));
```

`CreateRequest` dispatches a `RequestCreated` event.

### Resolve a request

`ResolveRequest` toggles the actor's approval and only flips the request to `Approved` once
**all** required approvers have approved:

```php
use RoundlyConsulting\Requests\Actions\ResolveRequest;
use RoundlyConsulting\Requests\Enums\Status;

$resolve = new ResolveRequest();

$resolve->execute($request, $alice, Status::Approved); // 1 of 2 — stays New
$resolve->execute($request, $bob, Status::Approved);   // 2 of 2 — becomes Approved
```

- Approving with no `require_approvals_from` resolves immediately.
- Resolving with `Status::Rejected` or `Status::New` revokes the actor's prior approval and
  sets the request status accordingly.

When the status changes, `ResolveRequest` dispatches `RequestStatusChanged`.

### Inspecting approvals

```php
$request->hasBeenApprovedBy($alice);          // bool
$alice->hasApproved($request);                // bool
$alice->toggleApproval($request);             // true = added, false = removed
```

### Events

Listen for these package events:

- `RoundlyConsulting\Requests\Events\RequestCreated` — `public Request $request`
- `RoundlyConsulting\Requests\Events\RequestStatusChanged` — `public Request $request`

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
