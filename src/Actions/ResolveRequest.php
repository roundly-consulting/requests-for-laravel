<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Builders\PendingApproval;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\ApprovalRecorded;
use RoundlyConsulting\Requests\Events\ApprovalRevoked;
use RoundlyConsulting\Requests\Events\RequestRejected;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;

/**
 * Records an actor's decision through the approvals engine. When the request has an
 * open approval request the engine's rule decides when the bar is met and the status
 * is synced by SyncRequestStatusFromApproval; without one (no declared approvers) the
 * decision resolves the request immediately.
 */
final class ResolveRequest
{
    public function __construct(
        private readonly StatusGuard $guard = new StatusGuard,
        private readonly RestartApprovalRound $restart = new RestartApprovalRound,
        private readonly CancelRequest $cancel = new CancelRequest,
        private readonly ExpireRequest $expire = new ExpireRequest,
    ) {}

    public function execute(
        Request $request,
        Model&GivesApprovalsInterface $actor,
        Status $status,
        ?string $reason = null,
    ): Request {
        // Cancelling and expiring are lifecycle moves, not decisions: they run the
        // actions that also close the approval round.
        if ($status === Status::Cancelled) {
            return $this->cancel->execute($request);
        }

        if ($status === Status::Expired) {
            return $this->expire->execute($request);
        }

        if ($this->enforcing()) {
            $this->guard->assert($request->status, $status);
        }

        return match ($status) {
            Status::Approved => $this->approve($request, $actor, $reason),
            Status::Rejected => $this->reject($request, $actor, $reason),
            Status::New => $this->reopen($request, $actor, $reason),
        };
    }

    private function approve(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason): Request
    {
        $hasApprovalRequest = $request->approvalRequests()->exists();

        $this->decide($request, $actor, $reason)->approve();

        event(new ApprovalRecorded($request, $actor));

        if (! $hasApprovalRequest) {
            return $this->updateRequestStatus($request, Status::Approved);
        }

        // The engine resolved (or held) the request; the sync listener owns the status.
        return $request->refresh();
    }

    private function reject(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason): Request
    {
        $hasApprovalRequest = $request->approvalRequests()->exists();

        $this->decide($request, $actor, $reason)->reject();

        if (! $hasApprovalRequest) {
            event(new RequestRejected($request, $actor));

            return $this->updateRequestStatus($request, Status::Rejected);
        }

        return $request->refresh();
    }

    /**
     * Withdraw the actor's decision and move the request back to New. When its approval
     * round is already over, a fresh round opens so the request can be decided again —
     * all as one unit, so a round that cannot reopen leaves the request as it was.
     */
    private function reopen(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason): Request
    {
        $request->getConnection()->transaction(function () use ($request, $actor, $reason): void {
            $actor->cancelApproval($request, $reason);

            $request->update(['status' => Status::New]);

            $this->restart->execute($request);
        });

        event(new ApprovalRevoked($request, $actor));
        event(new RequestStatusChanged($request));

        return $request;
    }

    private function decide(Request $request, Model $actor, ?string $reason): PendingApproval
    {
        $pending = Approvals::for($request)->as($actor);

        if ($reason !== null) {
            $pending->because($reason);
        }

        return $pending;
    }

    private function enforcing(): bool
    {
        return (bool) config('requests.enforce_transitions', false);
    }

    private function updateRequestStatus(Request $request, Status $status): Request
    {
        $request->update([
            'status' => $status,
        ]);

        event(new RequestStatusChanged($request));

        return $request;
    }
}
