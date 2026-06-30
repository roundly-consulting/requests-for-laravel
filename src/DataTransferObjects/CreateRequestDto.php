<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Requests\Enums\Status;

final readonly class CreateRequestDto
{
    /**
     * @param  Collection<array-key, mixed>|null  $meta
     * @param  Collection<array-key, int|string>|null  $requireApprovalsFrom  declared approver ids stored on the request
     * @param  list<Model>  $approvers  approver models retained for opening a workflow-preset request
     * @param  list<StageDefinition>  $stages  an ad-hoc, sequential approval pipeline
     * @param  list<list<Model>>  $stageApprovers  approver groups, one per stage, for a staged workflow preset
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
        public ApprovalRule $rule = ApprovalRule::Unanimous,
        public ?int $quorum = null,
        public array $approvers = [],
        public array $stages = [],
        public ?string $workflow = null,
        public array $stageApprovers = [],
        public bool $rejectOnStageRejection = true,
    ) {}
}
