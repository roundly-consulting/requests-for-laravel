<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('opens no approval request without declared approvers', function (): void {
    $request = Requests::make()->title('Open')->create();

    expect($request->approvalRequests()->count())->toBe(0)
        ->and($request->approvalProgress())->toBeNull()
        ->and($request->currentApprovalStatus())->toBe(ApprovalStatus::Pending);
});

it('opens exactly one pending approval request for declared approvers', function (): void {
    $a = User::create();
    $b = User::create();

    $request = Requests::make()->requireApprovalsFrom([$a, $b])->create();

    expect($request->approvalRequests()->count())->toBe(1)
        ->and($request->isPendingApproval())->toBeTrue()
        ->and($request->approvalProgress()?->required)->toBe(2);
});

it('reports an approval progress snapshot', function (): void {
    $a = User::create();
    $b = User::create();

    $request = Requests::make()->requireApprovalsFrom([$a, $b])->create();

    Requests::approve($request, $a);

    $progress = $request->approvalProgress();

    expect($progress?->approved)->toBe(1)
        ->and($progress?->required)->toBe(2)
        ->and($request->isApproved())->toBeFalse();

    Requests::approve($request, $b);

    expect($request->isApproved())->toBeTrue();
});

it('dedupes the required approver count', function (): void {
    $a = User::create();

    $request = Requests::make()->requireApprovalsFrom([$a->id, $a->id])->create();

    expect($request->approvalProgress()?->required)->toBe(1);
});

it('proxies the subject relation back to the request', function (): void {
    $a = User::create();

    $request = Requests::make()->requireApprovalsFrom([$a])->create();

    $approvalRequest = $request->approvalRequests()->firstOrFail();

    expect($approvalRequest->subject)->toBeInstanceOf(Request::class)
        ->and($approvalRequest->subject->is($request))->toBeTrue();
});
