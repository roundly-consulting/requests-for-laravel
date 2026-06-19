<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Requests\Approvals\Contracts\GivesApprovals;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\ApprovalRecorded;
use RoundlyConsulting\Requests\Events\ApprovalRevoked;
use RoundlyConsulting\Requests\Events\RequestRejected;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;

final class ResolveRequest
{
    public function __construct(private readonly StatusGuard $guard = new StatusGuard) {}

    public function execute(Request $request, Model&GivesApprovals $actor, Status $status): Request
    {
        if ($this->enforcing()) {
            $this->guard->assert($request->status, $status);
        }

        $actorHasApprovedRequest = $request->hasBeenApprovedBy($actor);

        if (($status === Status::Rejected || $status === Status::New) && $actorHasApprovedRequest) {
            // Revoke a previously given approval.
            $actor->toggleApproval($request);
            event(new ApprovalRevoked($request, $actor));
        } elseif ($status === Status::Approved && ! $actorHasApprovedRequest) {
            // Record a new approval.
            $actor->toggleApproval($request);
            event(new ApprovalRecorded($request, $actor));
        }

        // When approving, hold the status until every required approver has approved.
        if ($status === Status::Approved && $request->require_approvals_from?->isNotEmpty()) {
            /** @var array<int, int|string> $ids */
            $ids = $request->require_approvals_from->values()->all();

            if (! $request->hasBeenApprovedByAll($actor->getMorphClass(), $ids)) {
                return $request;
            }
        }

        if ($status === Status::Rejected) {
            event(new RequestRejected($request, $actor));
        }

        return $this->updateRequestStatus($request, $status);
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
