<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Support\ApprovalRequestModelResolver;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Events\RequestCreated;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\RequestModel;

final class CreateRequest
{
    public function execute(CreateRequestDto $dto): Request
    {
        $request = $this->newModelInstance([
            'status' => $dto->status,
            'type' => $dto->type,
            'title' => $dto->title,
            'description' => $dto->description,
            'meta' => $dto->meta,
            'require_approvals_from' => $dto->requireApprovalsFrom,
            'expires_at' => $dto->expiresAt ?? $this->defaultExpiry(),
        ]);

        if ($dto->author !== null) {
            $request->author()->associate($dto->author);
        }

        $request->save();

        $this->openApprovalRequest($request, $dto);

        event(new RequestCreated($request));

        return $request;
    }

    /**
     * Open an approvals-engine request for the new request, when one is called for.
     * A named workflow preset wins, then an explicit staged pipeline, then the flat
     * declared approver set. A request without approvers opens nothing.
     */
    private function openApprovalRequest(Request $request, CreateRequestDto $dto): void
    {
        if ($dto->workflow !== null) {
            $approvers = $dto->stageApprovers !== [] ? $dto->stageApprovers : $dto->approvers;

            Approvals::request($request)->workflow($dto->workflow)->open($approvers);

            return;
        }

        if ($dto->stages !== []) {
            $request->requestStagedApproval($dto->stages, $dto->rejectOnStageRejection);

            return;
        }

        $ids = $request->require_approvals_from;

        if ($ids === null || $ids->isEmpty()) {
            return;
        }

        $this->openFlatApprovalRequest($request, $dto, $ids->unique()->count());
    }

    private function openFlatApprovalRequest(Request $request, CreateRequestDto $dto, int $required): void
    {
        $model = ApprovalRequestModelResolver::class();

        $approvalRequest = new $model;
        $approvalRequest->subject_id = $request->getKey();
        $approvalRequest->subject_type = $request->getMorphClass();
        $approvalRequest->rule = $dto->rule;
        $approvalRequest->quorum = $dto->quorum;
        $approvalRequest->required_approvers = $required;
        $approvalRequest->status = ApprovalStatus::Pending;
        $approvalRequest->save();
    }

    private function defaultExpiry(): ?CarbonInterface
    {
        $ttl = config('requests.default_ttl');

        if (! is_int($ttl)) {
            return null;
        }

        return Carbon::now()->addMinutes($ttl);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newModelInstance(array $attributes): Request
    {
        $model = RequestModel::class();

        return new $model($attributes);
    }
}
