<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Approvals\Builders\PendingApprovalRequest;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Events\RequestCreated;
use RoundlyConsulting\Requests\Exceptions\InvalidApprover;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\RequestDraft;

final class CreateRequest
{
    /**
     * @throws InvalidApprover when an approver is not a model (a bare id)
     */
    public function execute(CreateRequestDto $dto): Request
    {
        $request = RequestDraft::from($dto);

        // One unit: a request whose approval round failed to open (an unsaved approver,
        // an unknown preset) must not survive as a request anyone could resolve alone.
        $request->getConnection()->transaction(function () use ($request, $dto): void {
            $request->save();

            $this->openApprovalRequest($request, $dto);
        });

        event(new RequestCreated($request));

        return $request;
    }

    /**
     * Open an approvals-engine round for the new request, when one is called for, through
     * the approvals builder — so the approvers it names are stored and only they (or their
     * delegates) may decide. A named workflow preset wins, then an explicit staged
     * pipeline, then the flat declared approver set. A request without approvers opens
     * nothing and resolves on the first decision.
     *
     * A staged or flat round expires with the request, so the engine stops taking
     * decisions once its deadline passes. A workflow preset's round keeps the preset's own
     * expiry (the preset builder takes none); the request's deadline still refuses a late
     * decision there, because `approve()` / `reject()` expire an overdue request first.
     */
    private function openApprovalRequest(Request $request, CreateRequestDto $dto): void
    {
        if ($dto->workflow !== null) {
            $approvers = $dto->stageApprovers !== [] ? $dto->stageApprovers : $dto->approvers;

            Approvals::request($request)->workflow($dto->workflow)->open($approvers);

            return;
        }

        if ($dto->stages !== []) {
            // Flat approvers passed alongside stages are handed on too, so the engine
            // refuses the mix instead of silently dropping them.
            $this->expiringWith($request, Approvals::request($request))
                ->from($dto->approvers)
                ->stages($dto->stages)
                ->continueOnRejection(! $dto->rejectOnStageRejection)
                ->open();

            return;
        }

        if ($dto->approvers === []) {
            return;
        }

        $this->expiringWith($request, Approvals::request($request))
            ->from($dto->approvers)
            ->rule($dto->rule, $dto->quorum)
            ->open();
    }

    private function expiringWith(Request $request, PendingApprovalRequest $round): PendingApprovalRequest
    {
        return $request->expires_at === null ? $round : $round->expiringAt($request->expires_at);
    }
}
