<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestRejected;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;

/**
 * Mirrors an approval request's resolution onto the requests Status of its subject,
 * so engine-driven outcomes (quorum reached, a direct Approvals decision, a staged
 * pipeline clearing) move the request and fire the requests event surface — keeping
 * that surface unchanged regardless of how the decision arrived.
 */
final class SyncRequestStatusFromApproval
{
    public function __construct(private readonly StatusGuard $guard = new StatusGuard) {}

    public function handle(ApprovalRequestResolved $event): void
    {
        $approvalRequest = $event->request;
        $subject = $approvalRequest->subject;

        if (! $subject instanceof Request) {
            return;
        }

        $target = $this->map($approvalRequest->status);

        if ($target === null || $subject->status === $target) {
            return;
        }

        // A cancelled or expired request stays closed, even when a round it no longer
        // follows resolves late (the transition guard is off by default).
        if ($this->guard->closes($subject->status, $target)) {
            return;
        }

        if ($this->enforcing() && ! $this->guard->allows($subject->status, $target)) {
            return;
        }

        $subject->update(['status' => $target]);

        event(new RequestStatusChanged($subject));

        if ($target === Status::Rejected) {
            $actor = $this->rejectingActor($approvalRequest);

            if ($actor !== null) {
                event(new RequestRejected($subject, $actor));
            }
        }
    }

    private function map(ApprovalStatus $status): ?Status
    {
        return match ($status) {
            ApprovalStatus::Approved => Status::Approved,
            ApprovalStatus::Rejected => Status::Rejected,
            ApprovalStatus::Cancelled => Status::Cancelled,
            ApprovalStatus::Expired => Status::Expired,
            ApprovalStatus::Pending => null,
        };
    }

    private function rejectingActor(ApprovalRequest $request): ?Model
    {
        $decision = $request->decisions()
            ->where('status', ApprovalStatus::Rejected)
            ->latest('id')
            ->first();

        $actor = $decision?->actor;

        return $actor instanceof Model ? $actor : null;
    }

    private function enforcing(): bool
    {
        return (bool) config('requests.enforce_transitions', false);
    }
}
