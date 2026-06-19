<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Enums\Status;

final readonly class CreateRequestDto
{
    /**
     * @param  Collection<array-key, mixed>|null  $meta
     * @param  Collection<array-key, int|string>|null  $requireApprovalsFrom
     */
    public function __construct(
        public Status $status = Status::New,
        public ?Model $author = null,
        public ?string $type = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?Collection $meta = null,
        public ?Collection $requireApprovalsFrom = null,
        public ?CarbonInterface $expiresAt = null,
    ) {}
}
