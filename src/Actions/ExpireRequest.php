<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;

final class ExpireRequest
{
    public function __construct(
        private readonly CloseApprovalRound $closeRound = new CloseApprovalRound,
    ) {}

    /**
     * Expire the request and close its open approval round, so no later decision can
     * resolve it.
     */
    public function execute(Request $request): Request
    {
        $request->getConnection()->transaction(function () use ($request): void {
            $request->update(['status' => Status::Expired]);

            $this->closeRound->execute($request, ApprovalStatus::Expired);
        });

        event(new RequestStatusChanged($request));
        event(new RequestExpired($request));

        return $request;
    }
}
