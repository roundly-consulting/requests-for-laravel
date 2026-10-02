<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Events\RequestCreated;
use RoundlyConsulting\Requests\Exceptions\InvalidApprover;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\DefaultTtl;
use RoundlyConsulting\Requests\Support\RequestModel;

final class CreateRequest
{
    /**
     * @throws InvalidApprover when an approver is not a model (a bare id)
     */
    public function execute(CreateRequestDto $dto): Request
    {
        $this->assertModels($dto->approvers);

        foreach ($dto->stageApprovers as $group) {
            $this->assertModels($group);
        }

        $request = $this->newModelInstance([
            'status' => $dto->status,
            'type' => $dto->type,
            'title' => $dto->title,
            'description' => $dto->description,
            'meta' => $dto->meta,
            'require_approvals_from' => $this->approverKeys($dto->approvers),
            'expires_at' => $dto->expiresAt ?? $this->defaultExpiry(),
        ]);

        if ($dto->author !== null) {
            $request->author()->associate($dto->author);
        }

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
            Approvals::request($request)
                ->from($dto->approvers)
                ->stages($dto->stages)
                ->continueOnRejection(! $dto->rejectOnStageRejection)
                ->open();

            return;
        }

        if ($dto->approvers === []) {
            return;
        }

        Approvals::request($request)
            ->from($dto->approvers)
            ->rule($dto->rule, $dto->quorum)
            ->open();
    }

    /**
     * Refuse anything but a model among the approvers before anything is written: a bare
     * id names no model type, so the round could never enforce it.
     *
     * @param  array<array-key, mixed>  $approvers
     *
     * @throws InvalidApprover
     */
    private function assertModels(array $approvers): void
    {
        foreach ($approvers as $approver) {
            if (! $approver instanceof Model) {
                throw InvalidApprover::notAModel($approver);
            }
        }
    }

    /**
     * The declared approvers' keys, each approver once, stored on the request.
     *
     * @param  list<Model>  $approvers
     * @return list<mixed>|null
     */
    private function approverKeys(array $approvers): ?array
    {
        $unique = [];

        foreach ($approvers as $approver) {
            foreach ($unique as $seen) {
                if ($seen->getMorphClass() === $approver->getMorphClass()
                    && (string) $seen->getKey() === (string) $approver->getKey()) {
                    continue 2;
                }
            }

            $unique[] = $approver;
        }

        return $unique === []
            ? null
            : array_map(static fn (Model $approver): mixed => $approver->getKey(), $unique);
    }

    private function defaultExpiry(): ?CarbonInterface
    {
        $ttl = DefaultTtl::minutes();

        return $ttl === null ? null : Carbon::now()->addMinutes($ttl);
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
