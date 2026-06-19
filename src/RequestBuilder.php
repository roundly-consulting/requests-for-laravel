<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Contracts\CreatesRequests;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Fluent builder that assembles a CreateRequestDto and delegates to CreateRequest.
 */
final class RequestBuilder
{
    private Status $status = Status::New;

    private ?Model $author = null;

    private ?string $type = null;

    private ?string $title = null;

    private ?string $description = null;

    /** @var Collection<array-key, mixed>|null */
    private ?Collection $meta = null;

    /** @var Collection<array-key, int|string>|null */
    private ?Collection $requireApprovalsFrom = null;

    private ?CarbonInterface $expiresAt = null;

    public function __construct(private readonly CreatesRequests $create) {}

    public function status(Status $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function author(?Model $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function type(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /** @param  iterable<array-key, mixed>  $meta */
    public function meta(iterable $meta): self
    {
        $this->meta = Collection::make($meta);

        return $this;
    }

    /**
     * Accepts approver models or ids and normalises everything to a collection of ids.
     *
     * @param  Model|iterable<array-key, Model|int|string>  $approvers
     */
    public function requireApprovalsFrom(Model|iterable $approvers): self
    {
        $approvers = $approvers instanceof Model ? [$approvers] : $approvers;

        $this->requireApprovalsFrom = Collection::make($approvers)
            ->map(fn (Model|int|string $approver): int|string => $approver instanceof Model
                ? $approver->getKey()
                : $approver)
            ->values();

        return $this;
    }

    public function expiresAt(?CarbonInterface $at): self
    {
        $this->expiresAt = $at;

        return $this;
    }

    public function toDto(): CreateRequestDto
    {
        return new CreateRequestDto(
            status: $this->status,
            author: $this->author,
            type: $this->type,
            title: $this->title,
            description: $this->description,
            meta: $this->meta,
            requireApprovalsFrom: $this->requireApprovalsFrom,
            expiresAt: $this->expiresAt,
        );
    }

    public function create(): Request
    {
        return $this->create->execute($this->toDto());
    }
}
