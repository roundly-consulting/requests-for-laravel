<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
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

    /** @var list<Model> */
    private array $approvers = [];

    private ?CarbonInterface $expiresAt = null;

    private ApprovalRule $rule = ApprovalRule::Unanimous;

    private ?int $quorum = null;

    /** @var list<StageDefinition> */
    private array $stages = [];

    private ?string $workflow = null;

    /** @var list<list<Model>> */
    private array $stageApprovers = [];

    private bool $rejectOnStageRejection = true;

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
     * Accepts approver models or ids. Ids are stored on the request as the declared
     * approver set; model instances are also retained so a workflow preset can be
     * opened against them.
     *
     * @param  Model|iterable<array-key, Model|int|string>  $approvers
     */
    public function requireApprovalsFrom(Model|iterable $approvers): self
    {
        $approvers = $approvers instanceof Model ? [$approvers] : $approvers;

        $collection = Collection::make($approvers);

        $models = [];

        foreach ($collection as $approver) {
            if ($approver instanceof Model) {
                $models[] = $approver;
            }
        }

        $this->approvers = $models;

        $this->requireApprovalsFrom = $collection
            ->map(fn (Model|int|string $approver): int|string => $approver instanceof Model
                ? $approver->getKey()
                : $approver)
            ->values();

        return $this;
    }

    /**
     * The rule that resolves the approval request (unanimous / quorum / any / weighted).
     */
    public function rule(ApprovalRule $rule): self
    {
        $this->rule = $rule;

        return $this;
    }

    /**
     * The quorum / weight threshold for the quorum and weighted rules.
     */
    public function quorum(?int $quorum): self
    {
        $this->quorum = $quorum;

        return $this;
    }

    /**
     * Define an ad-hoc, sequential approval pipeline. Each stage only opens once the
     * previous one clears.
     *
     * @param  list<StageDefinition>  $stages
     */
    public function stages(array $stages): self
    {
        $this->stages = $stages;

        return $this;
    }

    /**
     * Whether a rejection in any stage rejects the whole staged request.
     */
    public function rejectOnStageRejection(bool $reject = true): self
    {
        $this->rejectOnStageRejection = $reject;

        return $this;
    }

    /**
     * Open the request from a named workflow preset (config('approvals.workflows')).
     * Provide flat approvers via requireApprovalsFrom(), or one group per stage via
     * stageApprovers() for a staged preset.
     */
    public function workflow(?string $name): self
    {
        $this->workflow = $name;

        return $this;
    }

    /**
     * Approver groups, one per stage, for a staged workflow preset.
     *
     * @param  list<list<Model>>  $groups
     */
    public function stageApprovers(array $groups): self
    {
        $this->stageApprovers = $groups;

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
            rule: $this->rule,
            quorum: $this->quorum,
            approvers: $this->approvers,
            stages: $this->stages,
            workflow: $this->workflow,
            stageApprovers: $this->stageApprovers,
            rejectOnStageRejection: $this->rejectOnStageRejection,
        );
    }

    public function create(): Request
    {
        return $this->create->execute($this->toDto());
    }
}
