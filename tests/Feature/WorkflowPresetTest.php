<?php

declare(strict_types=1);

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
