<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCancelled;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

/*
|--------------------------------------------------------------------------
| A closed request takes no action, whatever enforce_transitions says
|--------------------------------------------------------------------------
|
| Cancelled is final; Expired can only be reopened. Acting otherwise throws
| RequestAlreadyResolved, as the README documents — it used to be thrown nowhere.
|
*/

it('refuses to decide a cancelled request', function (string $verb): void {
    $alice = User::create();
    $request = Request::factory()->cancelled()->create();

    expect(fn () => Requests::{$verb}($request, $alice))
        ->toThrow(fn (RequestAlreadyResolved $e) => expect($e->status)->toBe(Status::Cancelled))
        ->and($request->fresh()?->status)->toBe(Status::Cancelled);
})->with(['approve', 'reject', 'reopen']);

it('refuses to decide an expired request', function (string $verb): void {
    $alice = User::create();
    $request = Request::factory()->create(['status' => Status::Expired]);

    expect(fn () => Requests::{$verb}($request, $alice))->toThrow(RequestAlreadyResolved::class)
        ->and($request->fresh()?->status)->toBe(Status::Expired);
})->with(['approve', 'reject']);

it('refuses to cancel an expired request or expire a cancelled one', function (): void {
    $expired = Request::factory()->create(['status' => Status::Expired]);
    $cancelled = Request::factory()->cancelled()->create();

    expect(fn () => Requests::cancel($expired))->toThrow(RequestAlreadyResolved::class)
        ->and(fn () => Requests::expire($cancelled))->toThrow(RequestAlreadyResolved::class)
        ->and($expired->fresh()?->status)->toBe(Status::Expired)
        ->and($cancelled->fresh()?->status)->toBe(Status::Cancelled);
});

it('refuses a closed request under enforcement too, with the more specific exception', function (): void {
    config()->set('requests.enforce_transitions', true);

    $request = Request::factory()->cancelled()->create();

    Requests::reject($request, User::create());
})->throws(RequestAlreadyResolved::class);

it('treats cancelling a cancelled request and expiring an expired one as no-ops', function (): void {
    $cancelled = Request::factory()->cancelled()->create();
    $expired = Request::factory()->create(['status' => Status::Expired]);

    Event::fake([RequestStatusChanged::class, RequestCancelled::class, RequestExpired::class]);

    Requests::cancel($cancelled);
    Requests::expire($expired);

    Event::assertNothingDispatched();
});

it('reopens an expired request with a fresh approval round', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    Requests::expire($request);
    Requests::reopen($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::New)
        ->and($request->currentApprovalStatus())->toBe(ApprovalStatus::Pending);

    Requests::approve($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('refuses a decision once the approval round is over, until the request is reopened', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();

    Requests::approve($request, $alice);
    Requests::approve($request, $bob);

    // The round is resolved: a decision would count towards nothing.
    expect(fn () => Requests::reject($request, $bob))
        ->toThrow(fn (RequestAlreadyResolved $e) => expect($e->status)->toBe(Status::Approved))
        ->and(fn () => Requests::approve($request, $alice))->toThrow(RequestAlreadyResolved::class)
        ->and($bob->hasRejected($request))->toBeFalse()
        ->and($request->fresh()?->status)->toBe(Status::Approved);

    Requests::reopen($request, $bob);
    Requests::reject($request, $bob);

    expect($request->fresh()?->status)->toBe(Status::Rejected);
});

it('never lets the engine move a closed request, even with the guard off', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();
    $round = $request->approvalRequests()->firstOrFail();

    // A round resolved concurrently with the cancel, after the request was closed.
    $request->update(['status' => Status::Cancelled]);
    $round->forceFill(['status' => ApprovalStatus::Approved])->save();

    event(new ApprovalRequestResolved($round));

    expect($request->fresh()?->status)->toBe(Status::Cancelled);
});
