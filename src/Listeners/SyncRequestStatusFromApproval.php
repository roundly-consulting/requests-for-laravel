<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;
use RoundlyConsulting\Requests\Support\StatusWriter;

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

        $writer = new StatusWriter($this->guard);

        // Checked and written on the request's locked row, not the copy just loaded: a
        // cancel or expiry that landed since stays — a cancelled or expired request stays
        // closed even when a round it no longer follows resolves late, whatever the
        // transition guard says — and a move the enforced guard forbids is skipped.
        if (! $writer->move($subject, $target, strict: false)) {
            return;
        }

        $writer->announce(
            $subject,
            rejectedBy: $target === Status::Rejected ? $this->rejectingActor($approvalRequest) : null,
        );
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
}
