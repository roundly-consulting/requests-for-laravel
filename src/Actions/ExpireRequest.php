<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;

final class ExpireRequest
{
    public function __construct(
        private readonly StatusGuard $guard = new StatusGuard,
        private readonly CloseApprovalRound $closeRound = new CloseApprovalRound,
    ) {}

    /**
     * Expire the request and close its open approval round, so no later decision can
     * resolve it.
     */
    public function execute(Request $request): Request
    {
        // Already expired: nothing to do, and nothing to announce again.
        if ($request->status === Status::Expired) {
            return $request;
        }

        $this->guard->assertNotClosed($request->status, Status::Expired);

        if ($this->enforcing()) {
            $this->guard->assert($request->status, Status::Expired);
        }

        $request->getConnection()->transaction(function () use ($request): void {
            $request->update(['status' => Status::Expired]);

            $this->closeRound->execute($request, ApprovalStatus::Expired);
        });

        event(new RequestStatusChanged($request));
        event(new RequestExpired($request));

        return $request;
    }

    private function enforcing(): bool
    {
        return Config::boolean('requests.enforce_transitions');
    }
}
