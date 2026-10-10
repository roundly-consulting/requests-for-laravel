<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Closes a request's open approval round when the request is cancelled or expires, so a
 * late decision can no longer resolve the round and pull the request back.
 *
 * It is the approvals engine's own close (`Approvals::for()->close()`, approvals 1.1): the
 * round only moves while it is still pending — a decision that resolved it first keeps its
 * outcome — its outstanding asks are retired (ApprovalCancelled and ApprovalStatusChanged
 * pending → cancelled for each), then ApprovalRequestResolved and ApprovalStatusChanged fire
 * once for the round. A round already past its own expiry closes as expired, whatever the
 * outcome.
 *
 * @internal
 */
final class CloseApprovalRound
{
    /**
     * @return int the number of rounds closed
     */
    public function execute(Request $request, ApprovalStatus $outcome): int
    {
        return Approvals::for($request)->close($outcome);
    }
}
