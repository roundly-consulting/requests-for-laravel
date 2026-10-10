<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Exceptions\InvalidApprover;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Builds the unsaved request a CreateRequestDto describes, as the configured
 * `requests.model`: status, details, meta, the declared approvers' keys, the expiry
 * (explicit, else `requests.default_ttl`) and the author. CreateRequest saves it and opens
 * its approval round; `Requests::fake()` hands it back unsaved — so the two never differ.
 *
 * @internal
 */
final class RequestDraft
{
    /**
     * @throws InvalidApprover when an approver is not a model (a bare id)
     */
    public static function from(CreateRequestDto $dto): Request
    {
        self::assertModels($dto->approvers);

        foreach ($dto->stageApprovers as $group) {
            self::assertModels($group);
        }

        $model = RequestModel::class();

        $request = new $model([
            'status' => $dto->status,
            'type' => $dto->type,
            'title' => $dto->title,
            'description' => $dto->description,
            'meta' => $dto->meta,
            'require_approvals_from' => self::approverKeys($dto->approvers),
            'expires_at' => $dto->expiresAt ?? self::defaultExpiry(),
        ]);

        if ($dto->author !== null) {
            $request->author()->associate($dto->author);
        }

        return $request;
    }

    /**
     * Refuse anything but a model among the approvers before anything is written: a bare
     * id names no model type, so the round could never enforce it.
     *
     * @param  array<array-key, mixed>  $approvers
     *
     * @throws InvalidApprover
     */
    private static function assertModels(array $approvers): void
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
    private static function approverKeys(array $approvers): ?array
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

    private static function defaultExpiry(): ?CarbonInterface
    {
        $ttl = DefaultTtl::minutes();

        return $ttl === null ? null : Carbon::now()->addMinutes($ttl);
    }
}
