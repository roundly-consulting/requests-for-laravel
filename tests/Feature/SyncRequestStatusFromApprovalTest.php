<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

function approvalRequestFor(Request $request, ApprovalStatus $status): ApprovalRequest
{
    $approvalRequest = new ApprovalRequest;
    $approvalRequest->subject_id = $request->getKey();
    $approvalRequest->subject_type = $request->getMorphClass();
    $approvalRequest->rule = ApprovalRule::Unanimous;
    $approvalRequest->required_approvers = 1;
    $approvalRequest->status = $status;
    $approvalRequest->save();

    return $approvalRequest;
}

it('syncs the request when the engine resolves it directly', function (): void {
    $a = User::create();
    $b = User::create();

    $request = Requests::make()
        ->requireApprovalsFrom([$a, $b])
        ->rule(ApprovalRule::Any)
        ->create();

    // Decide straight through the approvals engine, bypassing the Requests facade.
    Approvals::for($request)->as($a)->approve();

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('maps a cancelled approval request to a cancelled request', function (): void {
    $request = Request::factory()->pending()->create();

    event(new ApprovalRequestResolved(approvalRequestFor($request, ApprovalStatus::Cancelled)));

    expect($request->fresh()?->status)->toBe(Status::Cancelled);
});

it('maps an expired approval request to an expired request', function (): void {
    $request = Request::factory()->pending()->create();

    event(new ApprovalRequestResolved(approvalRequestFor($request, ApprovalStatus::Expired)));

    expect($request->fresh()?->status)->toBe(Status::Expired);
});

it('ignores a still-pending approval request', function (): void {
    $request = Request::factory()->pending()->create();

    event(new ApprovalRequestResolved(approvalRequestFor($request, ApprovalStatus::Pending)));

    expect($request->fresh()?->status)->toBe(Status::New);
});

it('ignores approval requests whose subject is not a request', function (): void {
    $user = User::create();

    $approvalRequest = new ApprovalRequest;
    $approvalRequest->subject_id = $user->getKey();
    $approvalRequest->subject_type = $user->getMorphClass();
    $approvalRequest->rule = ApprovalRule::Unanimous;
    $approvalRequest->required_approvers = 1;
    $approvalRequest->status = ApprovalStatus::Approved;
    $approvalRequest->save();

    event(new ApprovalRequestResolved($approvalRequest));
})->throwsNoExceptions();

it('respects enforced transitions when syncing', function (): void {
    config()->set('requests.enforce_transitions', true);

    $request = Request::factory()->cancelled()->create();

    event(new ApprovalRequestResolved(approvalRequestFor($request, ApprovalStatus::Approved)));

    // Cancelled is terminal: the illegal sync is skipped.
    expect($request->fresh()?->status)->toBe(Status::Cancelled);
});
