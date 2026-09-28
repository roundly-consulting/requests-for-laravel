<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCancelled;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;

final class CancelRequest
{
    public function __construct(
        private readonly StatusGuard $guard = new StatusGuard,
        private readonly CloseApprovalRound $closeRound = new CloseApprovalRound,
    ) {}

    /**
     * Cancel the request and close its open approval round, so no later decision can
     * resolve it.
     */
    public function execute(Request $request): Request
    {
        // Already cancelled: nothing to do, and nothing to announce again.
        if ($request->status === Status::Cancelled) {
            return $request;
        }

        $this->guard->assertNotClosed($request->status, Status::Cancelled);

        if ($this->enforcing()) {
            $this->guard->assert($request->status, Status::Cancelled);
        }

        $request->getConnection()->transaction(function () use ($request): void {
            $request->update(['status' => Status::Cancelled]);

            $this->closeRound->execute($request, ApprovalStatus::Cancelled);
        });

        event(new RequestStatusChanged($request));
        event(new RequestCancelled($request));

        return $request;
    }

    private function enforcing(): bool
    {
        return (bool) config('requests.enforce_transitions', false);
    }
}
