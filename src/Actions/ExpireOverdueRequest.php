<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;
use RoundlyConsulting\Requests\Support\StatusWriter;

/**
 * Expires a request because its deadline passed — only if, on its locked row, it is still
 * open and past due. A request decided, cancelled, expired or given a new deadline since
 * the caller loaded it is left alone. The sweep and an overdue decision use it.
 *
 * @internal
 */
final class ExpireOverdueRequest
{
    public function __construct(
        private readonly StatusGuard $guard = new StatusGuard,
        private readonly CloseApprovalRound $closeRound = new CloseApprovalRound,
    ) {}

    /**
     * @return bool whether this call expired the request
     */
    public function execute(Request $request): bool
    {
        $writer = new StatusWriter($this->guard);

        $expired = $writer->move(
            $request,
            Status::Expired,
            strict: false,
            when: static fn (Request $locked): bool => $locked->isExpired(),
            alongside: fn (): int => $this->closeRound->execute($request, ApprovalStatus::Expired),
        );

        if ($expired) {
            $writer->announce($request);
        }

        return $expired;
    }
}
