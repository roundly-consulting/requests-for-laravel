<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Tests\SeniorUser;
use RoundlyConsulting\Requests\Tests\User;

it('resolves on a quorum before every approver decides', function (): void {
    $a = User::create();
    $b = User::create();
    $c = User::create();

    $request = Requests::make()
        ->requireApprovalsFrom([$a, $b, $c])
        ->rule(ApprovalRule::Quorum)
        ->quorum(2)
        ->create();

    Requests::approve($request, $a);
    expect($request->fresh()?->status)->toBe(Status::New);

    Requests::approve($request, $b);
    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('resolves on the first approval under the any rule', function (): void {
    $a = User::create();
    $b = User::create();

    $request = Requests::make()
        ->requireApprovalsFrom([$a, $b])
        ->rule(ApprovalRule::Any)
        ->create();

    Requests::approve($request, $a);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('lets a weighted approver clear the threshold alone', function (): void {
    $senior = SeniorUser::create();
    $junior = User::create();

    $request = Requests::make()
        ->requireApprovalsFrom([$senior, $junior])
        ->rule(ApprovalRule::Weighted)
        ->quorum(3)
        ->create();

    Requests::approve($request, $senior, reason: 'Signed off');

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('defaults to a unanimous rule', function (): void {
    $a = User::create();
    $b = User::create();

    $request = Requests::make()->requireApprovalsFrom([$a, $b])->create();

    expect($request->currentApprovalStatus())->toBe(ApprovalStatus::Pending);

    Requests::approve($request, $a);
    expect($request->fresh()?->status)->toBe(Status::New);

    Requests::approve($request, $b);
    expect($request->fresh()?->status)->toBe(Status::Approved);
});
