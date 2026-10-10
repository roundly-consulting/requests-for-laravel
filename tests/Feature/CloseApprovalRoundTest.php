<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalCancelled;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Events\ApprovalStatusChanged;
use RoundlyConsulting\Approvals\Exceptions\ClosedApprovalRequestException;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\Approval;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
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

    // A late decision straight through the approvals engine is refused.
    expect(fn () => $alice->approve($request))->toThrow(ClosedApprovalRequestException::class);

    expect($request->fresh()?->status)->toBe(Status::Cancelled)
        ->and($request->currentApprovalStatus())->toBe(ApprovalStatus::Cancelled);
});

it('closes the approval round on expire so a late approval cannot revive the request', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    Requests::expire($request);

    expect($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Expired);

    expect(fn () => $alice->approve($request))->toThrow(ClosedApprovalRequestException::class);

    expect($request->fresh()?->status)->toBe(Status::Expired);
});

it('closes the approval round of every request the sweep expires', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->expiresAt(now()->subMinute())->create();

    expect(Requests::expireDue())->toBe(1)
        ->and($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Expired);

    expect(fn () => $alice->approve($request))->toThrow(ClosedApprovalRequestException::class);

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

it('keeps the outcome of a decision that resolved the round between the read and the close', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    // Simulate the interleaving: the round is read as pending, then a concurrent decision
    // resolves it in the database before the close's conditional update runs.
    $raced = false;
    ApprovalRequest::retrieved(function (ApprovalRequest $round) use (&$raced): void {
        if ($raced) {
            return;
        }

        $raced = true;
        ApprovalRequest::query()->whereKey($round->getKey())->update(['status' => ApprovalStatus::Approved->value]);
    });

    Event::fake([ApprovalRequestResolved::class]);

    Requests::cancel($request);

    Event::assertNotDispatched(ApprovalRequestResolved::class);

    expect($raced)->toBeTrue()
        ->and($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Approved)
        ->and($request->fresh()?->status)->toBe(Status::Cancelled);
});

/**
 * C-9: closing the round on cancel or expire copied the engine's finalize, and missed the
 * engine retiring the round's outstanding asks — the asked approver's pending decision
 * stayed live, and could be neither answered nor withdrawn.
 */
it('retires the outstanding asks of the round it closes', function (string $verb, ApprovalStatus $outcome): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();
    $ask = Approvals::for($request)->as($alice)->ask();

    $events = [];

    Event::listen(ApprovalCancelled::class, function (ApprovalCancelled $e) use (&$events): void {
        $events[] = 'ask cancelled';
    });
    Event::listen(ApprovalStatusChanged::class, function (ApprovalStatusChanged $e) use (&$events): void {
        $events[] = ($e->subject instanceof Approval ? 'ask ' : 'round ').$e->from->value.' -> '.$e->to->value;
    });
    Event::listen(ApprovalRequestResolved::class, function () use (&$events): void {
        $events[] = 'round resolved';
    });

    Requests::{$verb}($request);

    $ask->refresh();

    expect($ask->status)->toBe(ApprovalStatus::Cancelled)
        ->and($ask->live)->toBeNull()
        ->and(Approvals::for($request)->as($alice)->hasPending())->toBeFalse()
        ->and($request->approvalRequests()->firstOrFail()->status)->toBe($outcome)
        ->and($events)->toBe([
            'ask cancelled',
            'ask pending -> cancelled',
            'round resolved',
            'round pending -> '.$outcome->value,
        ]);
})->with([
    'cancel' => ['cancel', ApprovalStatus::Cancelled],
    'expire' => ['expire', ApprovalStatus::Expired],
]);

/**
 * The round carries the request's deadline (C-2), and the engine closes a round already past
 * its expiry as expired whatever outcome is asked for (approvals 1.1). Cancelling an overdue
 * request the sweep has not reached yet still cancels the request: only its round reads
 * expired, and the late resolution does not move the cancelled request.
 */
it('cancels an overdue request whose round the engine closes as expired', function (): void {
    Carbon::setTestNow('2026-10-01 12:00:00');

    $alice = User::create();
    $request = Requests::make()->requireApprovalsFrom([$alice])->expiresAt(now()->addDay())->create();

    Carbon::setTestNow('2026-10-03 12:00:00');

    Event::fake([RequestCancelled::class, RequestExpired::class]);

    Requests::cancel($request);

    expect($request->fresh()?->status)->toBe(Status::Cancelled)
        ->and($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Expired);

    Event::assertDispatchedTimes(RequestCancelled::class, 1);
    Event::assertNotDispatched(RequestExpired::class);

    Carbon::setTestNow();
});
