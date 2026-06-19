<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Requests\Approvals\Contracts\GivesApprovals;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;

final class ResolveRequest
{
    public function execute(Request $request, Model&GivesApprovals $actor, Status $status): Request
    {
        $actorHasApprovedRequest = $request->hasBeenApprovedBy($actor);

        if (
            // Revoke a previously given approval.
            (($status === Status::Rejected || $status === Status::New) && $actorHasApprovedRequest)
            // Record a new approval.
            || ($status === Status::Approved && ! $actorHasApprovedRequest)
        ) {
            $actor->toggleApproval($request);
        }

        // When approving, hold the status until every required approver has approved.
        if ($status === Status::Approved && $request->require_approvals_from?->isNotEmpty()) {
            /** @var array<int, int|string> $ids */
            $ids = $request->require_approvals_from->values()->all();

            if (! $request->hasBeenApprovedByAll($actor->getMorphClass(), $ids)) {
                return $request;
            }
        }

        return $this->updateRequestStatus($request, $status);
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
