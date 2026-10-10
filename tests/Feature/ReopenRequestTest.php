<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\ApprovalRevoked;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Exceptions\InvalidApprover;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Tests\User;

/**
 * Reopening used to move the request back to New while its approval round stayed
 * resolved: every later decision landed outside any round, so a reopened request could
 * never be approved again. Reopening a finished round now opens a fresh one.
 */
it('re-approves an approved request after it is reopened', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();

    Requests::approve($request, $alice);
    Requests::approve($request, $bob);

    expect($request->fresh()?->status)->toBe(Status::Approved);

    Requests::reopen($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::New)
        ->and($request->currentApprovalStatus())->toBe(ApprovalStatus::Pending)
        ->and($request->approvalRequests()->count())->toBe(2);

    // A fresh round: the earlier decisions don't carry over, so both decide again.
    Requests::approve($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::New);

    Requests::approve($request, $bob);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('keeps the declared approvers and rule on the reopened round', function (): void {
    $a = User::create();
    $b = User::create();
    $c = User::create();
    $mallory = User::create();

    $request = Requests::make()->requireApprovalsFrom([$a, $b, $c])->rule(ApprovalRule::Quorum)->quorum(2)->create();

    Requests::reject($request, $a);
    Requests::reject($request, $b);

    expect($request->fresh()?->status)->toBe(Status::Rejected);

    Requests::reopen($request, $a);

    $round = $request->approvalRequests()->latest('id')->firstOrFail();

    expect($round->rule)->toBe(ApprovalRule::Quorum)
        ->and($round->quorum)->toBe(2)
        ->and($round->required_approvers)->toBe(3)
        ->and(fn () => Requests::approve($request, $mallory))->toThrow(UnauthorizedApprovalException::class);

    Requests::approve($request, $b);
    Requests::approve($request, $c);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('replays a staged pipeline on reopen', function (): void {
    $manager = User::create();
    $finance = User::create();

    $request = Requests::make()->stages([
        new StageDefinition([$manager], ApprovalRule::Unanimous, name: 'manager'),
        new StageDefinition([$finance], ApprovalRule::Unanimous, name: 'finance'),
    ])->rejectOnStageRejection(false)->create();

    Requests::approve($request, $manager);
    Requests::approve($request, $finance);

    expect($request->fresh()?->status)->toBe(Status::Approved);

    Requests::reopen($request, $manager);

    $round = $request->approvalRequests()->latest('id')->firstOrFail();

    expect($request->currentStage()?->name)->toBe('manager')
        ->and($round->staged)->toBeTrue()
        ->and($round->reject_on_stage_rejection)->toBeFalse()
        ->and(fn () => Requests::approve($request, $finance))->toThrow(UnauthorizedApprovalException::class);

    Requests::approve($request, $manager);
    Requests::approve($request, $finance);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('replays a workflow preset on reopen', function (): void {
    config()->set('approvals.workflows', [
        'payout' => ['rule' => 'any', 'required_approvers' => 1],
        'release' => ['stages' => [
            ['rule' => 'unanimous', 'required_approvers' => 1, 'name' => 'engineering'],
            ['rule' => 'any', 'required_approvers' => 1, 'name' => 'product'],
        ]],
    ]);

    $a = User::create();
    $b = User::create();

    $flat = Requests::make()->workflow('payout')->requireApprovalsFrom([$a])->create();
    Requests::approve($flat, $a);
    Requests::reopen($flat, $a);

    expect($flat->approvalRequests()->latest('id')->firstOrFail()->workflow)->toBe('payout')
        ->and(fn () => Requests::approve($flat, $b))->toThrow(UnauthorizedApprovalException::class);

    Requests::approve($flat, $a);

    expect($flat->fresh()?->status)->toBe(Status::Approved);

    $staged = Requests::make()->workflow('release')->stageApprovers([[$a], [$b]])->create();
    Requests::approve($staged, $a);
    Requests::approve($staged, $b);
    Requests::reopen($staged, $b);

    expect($staged->currentStage()?->name)->toBe('engineering');

    Requests::approve($staged, $a);
    Requests::approve($staged, $b);

    expect($staged->fresh()?->status)->toBe(Status::Approved);
});

it('keeps the open round when a still-open request is reopened', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();

    Requests::approve($request, $alice);
    Requests::approve($request, $bob);
    Requests::reopen($request, $alice);

    Requests::approve($request, $alice);
    // Alice withdraws her decision on the open round; nothing new opens.
    Requests::reopen($request, $alice);

    expect($request->approvalRequests()->count())->toBe(2)
        ->and($request->approvalProgress()?->approved)->toBe(0);

    Requests::approve($request, $bob);

    expect($request->fresh()?->status)->toBe(Status::New);
});

it('opens no round when reopening a request without approvers', function (): void {
    $alice = User::create();

    $request = Requests::make()->create();

    Requests::approve($request, $alice);
    Requests::reopen($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::New)
        ->and($request->approvalRequests()->count())->toBe(0);
});

it('refuses to reopen when a declared approver no longer exists', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();

    Requests::approve($request, $alice);
    Requests::approve($request, $bob);

    $bob->delete();

    expect(fn () => Requests::reopen($request, $alice))
        ->toThrow(InvalidApprover::class, 'approver '.$bob->getMorphClass().' #'.$bob->id.' no longer exists');

    // Nothing moved: the reopen ran as one unit.
    expect($request->fresh()?->status)->toBe(Status::Approved)
        ->and($request->approvalRequests()->count())->toBe(1)
        ->and($alice->hasApproved($request))->toBeTrue();
});

/**
 * C-11: reopen fired ApprovalRevoked and RequestStatusChanged whatever happened. Reopening
 * a New request the actor never decided changes nothing, so it announces nothing — as
 * cancelling a cancelled request and expiring an expired one already do.
 */
it('announces nothing when reopening a new request the actor never decided', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();

    Event::fake([ApprovalRevoked::class, RequestStatusChanged::class]);

    Requests::reopen($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::New);
    Event::assertNotDispatched(ApprovalRevoked::class);
    Event::assertNotDispatched(RequestStatusChanged::class);
});

it('announces a withdrawal on an open round without a status change', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();
    Requests::approve($request, $alice);

    Event::fake([ApprovalRevoked::class, RequestStatusChanged::class]);

    Requests::reopen($request, $alice);

    Event::assertDispatchedTimes(ApprovalRevoked::class, 1);
    Event::assertNotDispatched(RequestStatusChanged::class);
});

/**
 * The closed-round half (owner, 2026-10-10): ApprovalRevoked stays — it is the reopened
 * signal, though the finished round's decisions are not withdrawn — and the status change
 * is announced once.
 */
it('announces a reopen after a closed round once', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();
    Requests::approve($request, $alice);
    Requests::approve($request, $bob);

    Event::fake([ApprovalRevoked::class, RequestStatusChanged::class]);

    Requests::reopen($request, $alice);

    Event::assertDispatchedTimes(ApprovalRevoked::class, 1);
    Event::assertDispatchedTimes(RequestStatusChanged::class, 1);
    Event::assertDispatched(fn (RequestStatusChanged $e): bool => $e->request->status === Status::New);
});

/**
 * C-6: reopen reached the approvals authorization gate only through the withdrawal, which
 * it skips once the round is over — so a denied actor could reopen a decided request
 * although the same call on an open round was refused.
 */
it('refuses to reopen a decided request for an actor the authorization gate denies', function (): void {
    $alice = User::create();
    $mallory = User::create();

    config()->set('approvals.authorization.enabled', true);
    Gate::define('decide-approval', fn (Model $actor): bool => ! $actor->is($mallory));

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();
    Requests::reject($request, $alice);

    Event::fake([ApprovalRevoked::class]);

    expect(fn () => Requests::reopen($request, $mallory))->toThrow(UnauthorizedApprovalException::class)
        ->and($request->fresh()?->status)->toBe(Status::Rejected)
        ->and($request->approvalRequests()->count())->toBe(1);

    Event::assertNotDispatched(ApprovalRevoked::class);

    Requests::reopen($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::New)
        ->and($request->approvalRequests()->count())->toBe(2);
});

it('checks the configured authorization ability on reopen', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();
    Requests::approve($request, $alice);

    config()->set('approvals.authorization.enabled', true);
    config()->set('approvals.authorization.ability', 'reopen-request');
    Gate::define('decide-approval', fn (): bool => true);
    Gate::define('reopen-request', fn (): bool => false);

    expect(fn () => Requests::reopen($request, $alice))->toThrow(UnauthorizedApprovalException::class)
        ->and($request->fresh()?->status)->toBe(Status::Approved);
});
