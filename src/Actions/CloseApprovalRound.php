<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Events\ApprovalStatusChanged;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Closes a request's open approval round when the request is cancelled or expires, so a
 * late decision can no longer resolve the round and pull the request back.
 *
 * The approvals engine has no call to close a round from outside, so the round is
 * finalized the way the engine finalizes one: a conditional update that only moves it
 * while it is still pending — a decision that resolved it first keeps its outcome — then
 * the engine's own resolution events, fired once, for the round this call closed.
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
        $closed = 0;

        $rounds = $request->approvalRequests()->where('status', ApprovalStatus::Pending)->get();

        foreach ($rounds as $round) {
            $updated = $round->newQuery()
                ->whereKey($round->getKey())
                ->where('status', ApprovalStatus::Pending->value)
                ->update(['status' => $outcome->value, 'resolved_at' => CarbonImmutable::now()]);

            if ($updated !== 1) {
                continue;
            }

            $round->refresh();

            ApprovalRequestResolved::dispatch($round);
            ApprovalStatusChanged::dispatch($round, ApprovalStatus::Pending, $outcome);

            $closed++;
        }

        return $closed;
    }
}
