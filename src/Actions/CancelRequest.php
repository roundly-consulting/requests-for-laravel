<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;
use RoundlyConsulting\Requests\Support\StatusWriter;

final class CancelRequest
{
    public function __construct(
        private readonly StatusGuard $guard = new StatusGuard,
        private readonly CloseApprovalRound $closeRound = new CloseApprovalRound,
    ) {}

    /**
     * Cancel the request and close its open approval round, so no later decision can
     * resolve it. The checks run on the stored status, under a lock: a cancelled request is
     * left as it is (nothing announced again), an expired one stays closed, and with
     * `requests.enforce_transitions` on the lifecycle graph decides.
     *
     * @throws RequestAlreadyResolved
     * @throws InvalidStatusTransition
     */
    public function execute(Request $request): Request
    {
        $writer = new StatusWriter($this->guard);

        $cancelled = $writer->move(
            $request,
            Status::Cancelled,
            alongside: fn (): int => $this->closeRound->execute($request, ApprovalStatus::Cancelled),
        );

        if ($cancelled) {
            $writer->announce($request);
        }

        return $request;
    }
}
