<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Builders\PendingApproval;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\ApprovalRecorded;
use RoundlyConsulting\Requests\Events\ApprovalRevoked;
use RoundlyConsulting\Requests\Events\RequestRejected;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;
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
        $hasApprovalRequest = $this->hasOpenRound($request);

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
        $hasApprovalRequest = $this->hasOpenRound($request);

        $this->decide($request, $actor, $reason)->reject();

        if (! $hasApprovalRequest) {
            // Written first, as the sync listener does, so RequestRejected's listeners
            // see the rejection they are told about.
            $this->updateRequestStatus($request, Status::Rejected);

            event(new RequestRejected($request, $actor));

            return $request;
        }

        return $request->refresh();
    }

    /**
     * Move the request back to New. While its decisions still count (no approval round,
     * or a round still open) the actor's decision is withdrawn; once the round is over the
     * engine refuses that withdrawal, and a fresh round opens instead so the request can
     * be decided again — all as one unit, so a round that cannot reopen leaves the
     * request as it was.
     */
    private function reopen(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason): Request
    {
        $from = $request->status;
        $withdrawn = null;
        $round = null;

        $request->getConnection()->transaction(function () use ($request, $actor, $reason, &$withdrawn, &$round): void {
            if ($this->acceptsDecisions($request)) {
                $withdrawn = $actor->cancelApproval($request, $reason);
            }

            $request->update(['status' => Status::New]);

            $round = $this->restart->execute($request);
        });

        $statusChanged = $from !== Status::New;

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

    private function updateRequestStatus(Request $request, Status $status): Request
    {
        $request->update([
            'status' => $status,
        ]);

        event(new RequestStatusChanged($request));

        return $request;
    }
}
