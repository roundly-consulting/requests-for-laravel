<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\Requests\Enums\Status;
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
