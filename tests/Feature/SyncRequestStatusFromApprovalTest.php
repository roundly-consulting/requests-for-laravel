<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalApproved;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCancelled;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
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

it('announces an engine-driven expiry or cancellation with the requests events', function (): void {
    Event::fake([RequestExpired::class, RequestCancelled::class]);

    $expiring = Request::factory()->pending()->create();
    $cancelling = Request::factory()->pending()->create();

    event(new ApprovalRequestResolved(approvalRequestFor($expiring, ApprovalStatus::Expired)));
    event(new ApprovalRequestResolved(approvalRequestFor($cancelling, ApprovalStatus::Cancelled)));

    Event::assertDispatched(fn (RequestExpired $e): bool => $e->request->is($expiring));
    Event::assertDispatched(fn (RequestCancelled $e): bool => $e->request->is($cancelling));
});

it('expires the request when its approval round lapses in the engine', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();
    $request->approvalRequests()->firstOrFail()->forceFill(['expires_at' => now()->subMinute()])->save();

    Event::fake([RequestExpired::class]);

    Approvals::expire();

    expect($request->fresh()?->status)->toBe(Status::Expired);
    Event::assertDispatched(fn (RequestExpired $e): bool => $e->request->is($request));
});

/**
 * C-5: the listener read the request, checked it, then wrote unconditionally outside any
 * transaction. A cancel landing between its read and its write was overwritten: the
 * request ended Approved although RequestCancelled had fired.
 */
it('never overwrites a cancel that lands between its read and its write', function (): void {
    $alice = User::create();
    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    $armed = false;
    $statuses = [];

    Event::listen(ApprovalApproved::class, function () use (&$armed): void {
        $armed = true;
    });

    // Fires as the listener loads the round's subject: cancel right behind its read.
    Request::retrieved(function (Request $retrieved) use (&$armed, $request): void {
        if (! $armed || ! $retrieved->is($request)) {
            return;
        }

        $armed = false;

        Requests::cancel(Request::query()->findOrFail($retrieved->getKey()));
    });

    Event::listen(RequestStatusChanged::class, function (RequestStatusChanged $event) use (&$statuses): void {
        $statuses[] = $event->request->status;
    });

    Requests::approve($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::Cancelled)
        ->and($statuses)->toBe([Status::Cancelled]);
});
