<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Events\ApprovalStatusChanged;
use RoundlyConsulting\Requests\Actions\ResolveRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCancelled;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

/*
|--------------------------------------------------------------------------
| Cancelling and expiring close the approval round
|--------------------------------------------------------------------------
|
| The round used to stay pending: a late approval then resolved it, and the sync
| listener pulled the cancelled or expired request back to Approved (the transition
| guard is off by default).
|
*/

it('closes the approval round on cancel so a late approval cannot revive the request', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    Requests::cancel($request);

    expect($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Cancelled);

    // A late decision straight through the approvals engine.
    $alice->approve($request);

    expect($request->fresh()?->status)->toBe(Status::Cancelled)
        ->and($request->currentApprovalStatus())->toBe(ApprovalStatus::Cancelled);
});

it('closes the approval round on expire so a late approval cannot revive the request', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    Requests::expire($request);

    expect($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Expired);

    $alice->approve($request);

    expect($request->fresh()?->status)->toBe(Status::Expired);
});

it('closes the approval round of every request the sweep expires', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->expiresAt(now()->subMinute())->create();

    expect(Requests::expireDue())->toBe(1)
        ->and($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Expired);

    $alice->approve($request);

    expect($request->fresh()?->status)->toBe(Status::Expired);
});

it('announces the closed round through the approvals events, once', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    Event::fake([ApprovalRequestResolved::class, ApprovalStatusChanged::class, RequestStatusChanged::class, RequestCancelled::class]);

    Requests::cancel($request);

    Event::assertDispatchedTimes(ApprovalRequestResolved::class, 1);
    Event::assertDispatched(fn (ApprovalStatusChanged $e): bool => $e->from === ApprovalStatus::Pending
        && $e->to === ApprovalStatus::Cancelled);
    Event::assertDispatchedTimes(RequestStatusChanged::class, 1);
    Event::assertDispatchedTimes(RequestCancelled::class, 1);
});

it('leaves an already-resolved round alone when the request is cancelled', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    Requests::approve($request, $alice);
    Requests::cancel($request);

    expect($request->fresh()?->status)->toBe(Status::Cancelled)
        ->and($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Approved);
});

it('routes a raw cancelled or expired resolution through the lifecycle actions', function (): void {
    $alice = User::create();

    $cancelled = Requests::make()->requireApprovalsFrom([$alice])->create();
    $expired = Requests::make()->requireApprovalsFrom([$alice])->create();

    Event::fake([RequestCancelled::class, RequestExpired::class]);

    app(ResolveRequest::class)->execute($cancelled, $alice, Status::Cancelled);
    app(ResolveRequest::class)->execute($expired, $alice, Status::Expired);

    Event::assertDispatched(fn (RequestCancelled $e): bool => $e->request->is($cancelled));
    Event::assertDispatched(fn (RequestExpired $e): bool => $e->request->is($expired));

    expect($cancelled->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Cancelled)
        ->and($expired->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Expired);
});
