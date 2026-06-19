<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Events\RequestCreated;
use RoundlyConsulting\Requests\Models\Request;

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
        ]);

        if ($dto->author !== null) {
            $request->author()->associate($dto->author);
        }

        $request->save();

        event(new RequestCreated($request));

        return $request;
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
