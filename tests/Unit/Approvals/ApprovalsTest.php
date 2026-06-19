<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Approvals\Approval;
use RoundlyConsulting\Requests\Database\Factories\ApprovalFactory;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('returns the approval factory', function (): void {
    expect(Approval::factory())->toBeInstanceOf(ApprovalFactory::class);
});

it('builds an approval through its factory', function (): void {
    $approval = Approval::factory()->create();

    expect($approval)->toBeInstanceOf(Approval::class)
        ->and($approval->actor_type)->toBe('user');
});

it('records an approval and toggles it off again', function (): void {
    $request = Request::factory()->create();
    $user = User::create();

    expect($user->hasApproved($request))->toBeFalse()
        ->and($request->hasBeenApprovedBy($user))->toBeFalse();

    expect($user->toggleApproval($request))->toBeTrue()
        ->and($user->hasApproved($request))->toBeTrue()
        ->and($request->hasBeenApprovedBy($user))->toBeTrue();

    expect($user->toggleApproval($request))->toBeFalse()
        ->and($user->hasApproved($request))->toBeFalse();
});

it('exposes the actor and approvable morph relations', function (): void {
    $request = Request::factory()->create();
    $user = User::create();

    $user->toggleApproval($request);

    $approval = $user->approvals()->firstOrFail();

    expect($approval->actor->is($user))->toBeTrue()
        ->and($approval->approvable->is($request))->toBeTrue();
});
