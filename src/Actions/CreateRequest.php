<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Requests\Contracts\CreatesRequests;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Events\RequestCreated;
use RoundlyConsulting\Requests\Models\Request;

final class CreateRequest implements CreatesRequests
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

        event(new RequestCreated($request));

        return $request;
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
        /** @var class-string<Request> $model */
        $model = config('requests.model', Request::class);

        return new $model($attributes);
    }
}
