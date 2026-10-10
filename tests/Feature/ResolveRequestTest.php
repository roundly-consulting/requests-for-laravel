<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\Actions\ResolveRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\ApprovalRecorded;
use RoundlyConsulting\Requests\Events\ApprovalRevoked;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Events\RequestRejected;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('approves immediately when no approvers are required', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto);
    $user = User::create();

    Event::fake(RequestStatusChanged::class);

    (new ResolveRequest)->execute($request, $user, Status::Approved);

    Event::assertDispatched(
        fn (RequestStatusChanged $e) => $e->request->is($request) && $request->status === Status::Approved,
    );

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Approved->value,
    ]);
});

it('holds at new until every required approver has approved', function () {
    $user = User::create();
    $anotherUser = User::create();

    $request = (new CreateRequest)->execute(new CreateRequestDto(
        approvers: [$user, $anotherUser],
    ));

    Event::fake(RequestStatusChanged::class);

    (new ResolveRequest)->execute($request, $user, Status::Approved);

    Event::assertNotDispatched(RequestStatusChanged::class);

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::New->value,
    ]);

    expect($user->hasApproved($request))->toBeTrue()
        ->and($anotherUser->hasApproved($request))->toBeFalse();
});

it('approves once every required approver has approved', function () {
    $user = User::create();
    $anotherUser = User::create();

    $request = (new CreateRequest)->execute(new CreateRequestDto(
        approvers: [$user, $anotherUser],
    ));

    Event::fake(RequestStatusChanged::class);

    (new ResolveRequest)->execute($request, $user, Status::Approved);
    Event::assertNotDispatched(RequestStatusChanged::class);

    (new ResolveRequest)->execute($request, $anotherUser, Status::Approved);

    Event::assertDispatched(
        fn (RequestStatusChanged $e) => $e->request->is($request) && $e->request->status === Status::Approved,
    );

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Approved->value,
    ]);
});

it('rejects a unanimous request on the first rejection', function () {
    $user = User::create();
    $anotherUser = User::create();

    $request = (new CreateRequest)->execute(new CreateRequestDto(
        approvers: [$user, $anotherUser],
    ));

    (new ResolveRequest)->execute($request, $user, Status::Rejected, reason: 'Out of policy');

    expect($request->fresh()?->status)->toBe(Status::Rejected)
        ->and($user->hasRejected($request))->toBeTrue();

    $this->assertDatabaseHas('approvals', [
        'status' => ApprovalStatus::Rejected->value,
        'reason' => 'Out of policy',
    ]);
});

it('records a per-decision reason on approval', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto);
    $user = User::create();

    (new ResolveRequest)->execute($request, $user, Status::Approved, reason: 'Looks good');

    $this->assertDatabaseHas('approvals', [
        'status' => ApprovalStatus::Approved->value,
        'reason' => 'Looks good',
    ]);
});

it('reopens a request and revokes the approval', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto(status: Status::Approved));
    $user = User::create();
    $user->approve($request);

    (new ResolveRequest)->execute($request, $user, Status::New);

    expect($request->fresh()?->status)->toBe(Status::New)
        ->and($user->hasApproved($request))->toBeFalse();
});

it('fires granular approval, revoke and rejection events with the acting actor', function () {
    Event::fake([ApprovalRecorded::class, ApprovalRevoked::class, RequestRejected::class]);

    $request = (new CreateRequest)->execute(new CreateRequestDto);
    $user = User::create();

    $action = new ResolveRequest;

    $action->execute($request, $user, Status::Approved);
    Event::assertDispatched(fn (ApprovalRecorded $e) => $e->request->is($request) && $e->actor->is($user));

    $action->execute($request, $user, Status::Rejected);
    Event::assertDispatched(fn (RequestRejected $e) => $e->request->is($request) && $e->actor->is($user));

    $action->execute($request, $user, Status::New);
    Event::assertDispatched(fn (ApprovalRevoked $e) => $e->request->is($request) && $e->actor->is($user));
});

/**
 * C-10: on the no-round path RequestRejected fired before the status was written, so its
 * listeners still saw New — the round path (the sync listener) writes first.
 */
it('writes the rejection before announcing it on a request without approvers', function (): void {
    $request = (new CreateRequest)->execute(new CreateRequestDto);
    $seen = [];

    Event::listen(RequestRejected::class, function (RequestRejected $event) use (&$seen): void {
        $seen = [$event->request->status, $event->request->fresh()?->status];
    });

    (new ResolveRequest)->execute($request, User::create(), Status::Rejected);

    expect($seen)->toBe([Status::Rejected, Status::Rejected]);
});

/**
 * C-4 (d): a request without approvers resolved on a decision checked against the caller's
 * copy, so a copy loaded before a cancel turned the Cancelled request Approved.
 */
it('refuses a decision on a copy loaded before the request was cancelled', function (): void {
    $alice = User::create();
    $request = Requests::make()->create();
    $stale = Request::query()->findOrFail($request->getKey());

    Requests::cancel($request);

    expect(fn () => Requests::approve($stale, $alice))
        ->toThrow(fn (RequestAlreadyResolved $e) => expect($e->status)->toBe(Status::Cancelled))
        ->and($request->fresh()?->status)->toBe(Status::Cancelled)
        ->and($alice->hasApproved($request))->toBeFalse();
});

/**
 * The same stale-copy write on reopen: a copy loaded before a cancel reopened the
 * Cancelled request with a fresh approval round.
 */
it('refuses to reopen a copy loaded before the request was cancelled', function (): void {
    $alice = User::create();
    $request = Requests::make()->requireApprovalsFrom([$alice])->create();
    Requests::approve($request, $alice);

    $stale = Request::query()->findOrFail($request->getKey());

    Requests::cancel($request);

    expect(fn () => Requests::reopen($stale, $alice))->toThrow(RequestAlreadyResolved::class)
        ->and($request->fresh()?->status)->toBe(Status::Cancelled)
        ->and($request->approvalRequests()->count())->toBe(1);
});

/**
 * C-2: approve and reject checked the status only, so a request past its deadline — one
 * `isExpired()` and the `expired()` scope already report — was still decided until the
 * sweep caught up. It is expired on the spot instead, and the decision refused.
 */
it('expires an overdue request instead of deciding it', function (bool $withApprover): void {
    Carbon::setTestNow('2026-10-01 12:00:00');

    $alice = User::create();
    $builder = Requests::make()->expiresAt(now()->addDays(7));

    if ($withApprover) {
        $builder->requireApprovalsFrom([$alice]);
    }

    $request = $builder->create();

    Carbon::setTestNow('2026-10-09 12:00:00');

    Event::fake([RequestExpired::class]);

    expect(fn () => Requests::approve($request, $alice))
        ->toThrow(fn (RequestAlreadyResolved $e) => expect($e->status)->toBe(Status::Expired))
        ->and(fn () => Requests::reject($request, $alice))
        ->toThrow(fn (RequestAlreadyResolved $e) => expect($e->status)->toBe(Status::Expired))
        ->and($request->fresh()?->status)->toBe(Status::Expired)
        ->and($alice->hasApproved($request))->toBeFalse()
        ->and($alice->hasRejected($request))->toBeFalse();

    Event::assertDispatchedTimes(RequestExpired::class, 1);

    Carbon::setTestNow();
})->with(['with an approver' => true, 'without approvers' => false]);
