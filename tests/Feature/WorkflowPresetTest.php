<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Tests\User;

it('opens a flat request from a named workflow preset', function (): void {
    config()->set('approvals.workflows.payout', [
        'rule' => ApprovalRule::Quorum->value,
        'quorum' => 2,
        'required_approvers' => 3,
    ]);

    $a = User::create();
    $b = User::create();
    $c = User::create();

    $request = Requests::make()
        ->workflow('payout')
        ->requireApprovalsFrom([$a, $b, $c])
        ->create();

    Requests::approve($request, $a);
    expect($request->fresh()?->status)->toBe(Status::New);

    Requests::approve($request, $b);
    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('opens a staged request from a named workflow preset', function (): void {
    config()->set('approvals.workflows.release', [
        'stages' => [
            ['rule' => ApprovalRule::Unanimous->value, 'required_approvers' => 2, 'name' => 'engineering'],
            ['rule' => ApprovalRule::Any->value, 'required_approvers' => 1, 'name' => 'product'],
        ],
    ]);

    $eng1 = User::create();
    $eng2 = User::create();
    $product = User::create();

    $request = Requests::make()
        ->workflow('release')
        ->stageApprovers([[$eng1, $eng2], [$product]])
        ->create();

    expect($request->currentStage()?->name)->toBe('engineering');

    Requests::approve($request, $eng1);
    Requests::approve($request, $eng2);
    expect($request->currentStage()?->name)->toBe('product');

    Requests::approve($request, $product);
    expect($request->fresh()?->status)->toBe(Status::Approved);
});

/**
 * #112: a preset round dropped the request's deadline, so the engine took decisions
 * past it, or stopped at the preset's own expiry first, unlike a staged or flat round.
 */
it('gives a preset round the request deadline', function (): void {
    Carbon::setTestNow('2026-10-01 12:00:00');
    config()->set('approvals.workflows', [
        'payout' => ['rule' => 'any', 'required_approvers' => 1, 'expiry' => 3600],
        'release' => ['expiry' => 3600, 'stages' => [
            ['rule' => 'unanimous', 'required_approvers' => 1, 'name' => 'engineering'],
            ['rule' => 'any', 'required_approvers' => 1, 'name' => 'product'],
        ]],
    ]);

    $a = User::create();
    $b = User::create();

    $flat = Requests::make()->workflow('payout')->requireApprovalsFrom([$a])->expiresAt(now()->addDays(7))->create();
    $staged = Requests::make()->workflow('release')->stageApprovers([[$a], [$b]])->expiresAt(now()->addDays(7))->create();

    config()->set('requests.default_ttl', 120);
    $defaulted = Requests::make()->workflow('payout')->requireApprovalsFrom([$a])->create();

    expect($flat->approvalRequests()->firstOrFail()->expires_at?->toDateTimeString())->toBe('2026-10-08 12:00:00')
        ->and($staged->approvalRequests()->firstOrFail()->expires_at?->toDateTimeString())->toBe('2026-10-08 12:00:00')
        ->and($defaulted->approvalRequests()->firstOrFail()->expires_at?->toDateTimeString())->toBe('2026-10-01 14:00:00');

    Carbon::setTestNow();
});

it('keeps the preset expiry when the request has no deadline', function (): void {
    Carbon::setTestNow('2026-10-01 12:00:00');
    config()->set('approvals.workflows', [
        'payout' => ['rule' => 'any', 'required_approvers' => 1, 'expiry' => 3600],
        'release' => ['expiry' => 3600, 'stages' => [
            ['rule' => 'any', 'required_approvers' => 1, 'name' => 'engineering'],
        ]],
        'open-ended' => ['rule' => 'any', 'required_approvers' => 1],
    ]);

    $a = User::create();

    $flat = Requests::make()->workflow('payout')->requireApprovalsFrom([$a])->create();
    $staged = Requests::make()->workflow('release')->stageApprovers([[$a]])->create();
    $openEnded = Requests::make()->workflow('open-ended')->requireApprovalsFrom([$a])->create();

    expect($flat->expires_at)->toBeNull()
        ->and($flat->approvalRequests()->firstOrFail()->expires_at?->toDateTimeString())->toBe('2026-10-01 13:00:00')
        ->and($staged->approvalRequests()->firstOrFail()->expires_at?->toDateTimeString())->toBe('2026-10-01 13:00:00')
        ->and($openEnded->approvalRequests()->firstOrFail()->expires_at)->toBeNull();

    Carbon::setTestNow();
});
