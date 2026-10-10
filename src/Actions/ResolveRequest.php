<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Approvals\Builders\PendingApproval;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\ApprovalRecorded;
use RoundlyConsulting\Requests\Events\ApprovalRevoked;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\DecisionGate;
use RoundlyConsulting\Requests\Support\DefaultTtl;
use RoundlyConsulting\Requests\Support\StatusGuard;
use RoundlyConsulting\Requests\Support\StatusWriter;

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
        private readonly DecisionGate $gate = new DecisionGate,
        private readonly ExpireOverdueRequest $overdue = new ExpireOverdueRequest,
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

        // A request past its deadline is not decided: it is expired on the spot (as the
        // sweep would) and the decision refused below, as on any expired request.
        if ($status !== Status::New && $request->isExpired()) {
            $this->overdue->execute($request);
        }

        $this->guard->assertNotClosed($request->status, $status);

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
        if (! $this->hasOpenRound($request)) {
            $approved = $this->decideAlone($request, $actor, $reason, Status::Approved);

            event(new ApprovalRecorded($request, $actor));

            if ($approved) {
                $this->writer()->announce($request);
            }

            return $request;
        }

        $this->decide($request, $actor, $reason)->approve();

        event(new ApprovalRecorded($request, $actor));

        // The engine resolved (or held) the request; the sync listener owns the status.
        return $request->refresh();
    }

    private function reject(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason): Request
    {
        if (! $this->hasOpenRound($request)) {
            // Written first, as the sync listener does, so RequestRejected's listeners
            // see the rejection they are told about.
            if ($this->decideAlone($request, $actor, $reason, Status::Rejected)) {
                $this->writer()->announce($request, rejectedBy: $actor);
            }

            return $request;
        }

        $this->decide($request, $actor, $reason)->reject();

        return $request->refresh();
    }

    /**
     * Decide a request that has no approval round: the decision resolves it at once. The
     * request's row is locked first and the checks run on the status it holds, so a copy
     * loaded before a cancel (or another decision) can neither record a decision nor move
     * the status. Repeating the request's current outcome records the decision and moves
     * nothing.
     *
     * @return bool whether the status changed
     */
    private function decideAlone(Request $request, Model $actor, ?string $reason, Status $to): bool
    {
        $writer = $this->writer();

        return $writer->locked($request, function (Status $from) use ($writer, $request, $actor, $reason, $to): bool {
            $moves = $writer->permits($from, $to);

            $decision = $this->decide($request, $actor, $reason);
            $to === Status::Approved ? $decision->approve() : $decision->reject();

            if ($moves) {
                $writer->write($request, $to);
            }

            return $moves;
        });
    }

    /**
     * Move the request back to New. While its decisions still count (no approval round,
     * or a round still open) the actor's decision is withdrawn; once the round is over the
     * engine refuses that withdrawal, and a fresh round opens instead so the request can
     * be decided again — all as one unit, under a lock on the request's row, so a round
     * that cannot reopen leaves the request as it was and a stale copy cannot reopen a
     * request closed since it was loaded. A request moved back to New whose deadline
     * already passed gets a fresh one (`requests.default_ttl`) or none.
     */
    private function reopen(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason): Request
    {
        // Reopening changes the outcome as much as a decision does, and the engine's gate
        // is otherwise reached only through the withdrawal below — skipped on a closed round.
        $this->gate->authorize($actor, $request);

        $writer = $this->writer();
        $withdrawn = null;
        $round = null;

        $statusChanged = $writer->locked($request, function (Status $from) use ($writer, $request, $actor, $reason, &$withdrawn, &$round): bool {
            $moves = $writer->permits($from, Status::New);

            if ($this->acceptsDecisions($request)) {
                $withdrawn = $actor->cancelApproval($request, $reason);
            }

            if ($moves) {
                // A deadline that already passed would leave the reopened request expired
                // at once, and the next sweep would close it again: it restarts instead.
                if ($request->expires_at?->isPast() === true) {
                    $request->expires_at = $this->restartedDeadline();
                }

                $writer->write($request, Status::New);
            }

            $round = $this->restart->execute($request);

            return $moves;
        });

        // ApprovalRevoked is the reopen signal: the actor's decision was withdrawn, or the
        // request was moved back to New (a finished round's decisions stay where they
        // are). A reopen that changed nothing announces nothing.
        if ($withdrawn !== null || $statusChanged || $round !== null) {
            event(new ApprovalRevoked($request, $actor));
        }

        if ($statusChanged) {
            event(new RequestStatusChanged($request));
        }

        return $request;
    }

    /**
     * Whether the request is decided through an approval round. Once its latest round is
     * over (resolved, cancelled or expired) a decision would count towards nothing, so it
     * is refused until the request is reopened, which opens a fresh round.
     *
     * @throws RequestAlreadyResolved
     */
    private function hasOpenRound(Request $request): bool
    {
        if (! $request->approvalRequests()->exists()) {
            return false;
        }

        if (! $request->approvalRequests()->where('status', ApprovalStatus::Pending)->exists()) {
            throw RequestAlreadyResolved::inStatus($request->status);
        }

        return true;
    }

    /**
     * Whether a decision on the request still counts: it has no approval round (decisions
     * stand alone) or a round is still pending.
     */
    private function acceptsDecisions(Request $request): bool
    {
        return ! $request->approvalRequests()->exists()
            || $request->approvalRequests()->where('status', ApprovalStatus::Pending)->exists();
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
        return Config::boolean('requests.enforce_transitions');
    }

    /**
     * The deadline of a reopened request whose deadline passed: `requests.default_ttl` from
     * now when one is set, otherwise none.
     */
    private function restartedDeadline(): ?CarbonInterface
    {
        $ttl = DefaultTtl::minutes();

        return $ttl === null ? null : Carbon::now()->addMinutes($ttl);
    }

    private function writer(): StatusWriter
    {
        return new StatusWriter($this->guard);
    }
}
