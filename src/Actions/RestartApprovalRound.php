<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Approvals\Builders\PendingApprovalRequest;
use RoundlyConsulting\Approvals\DataTransferObjects\NamedApprover;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Approvals\Models\ApprovalRequestStage;
use RoundlyConsulting\Requests\Exceptions\InvalidApprover;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Opens a fresh approval round for a reopened request whose latest round is over, so it
 * can be decided again. The round replays the latest one's shape — the same workflow
 * preset, the same stages, or the same named approvers under the same rule — through the
 * approvals builder, so its approvers stay enforced, and it expires with the request
 * (a preset round keeps the preset's own expiry only when the request has no deadline).
 * Decisions from the finished round don't carry over. A request that
 * never had a round, or whose round is still open, is left alone.
 *
 * @internal
 */
final class RestartApprovalRound
{
    /**
     * @throws InvalidApprover when a named approver no longer exists
     */
    public function execute(Request $request): ?ApprovalRequest
    {
        $latest = $request->approvalRequests()->latest('id')->first();

        if (! $latest instanceof ApprovalRequest || $latest->status === ApprovalStatus::Pending) {
            return null;
        }

        $stages = $latest->staged ? $latest->stages()->get()->all() : [];

        if ($latest->workflow !== null) {
            $approvers = $latest->staged
                ? array_map(fn (ApprovalRequestStage $stage): array => $this->models($stage->namedApprovers()), $stages)
                : $this->models($latest->namedApprovers());

            return $this->expiringWith($request, Approvals::request($request))
                ->workflow($latest->workflow)
                ->open($approvers);
        }

        if ($latest->staged) {
            return $this->expiringWith($request, Approvals::request($request))
                ->stages(array_values(array_map(fn (ApprovalRequestStage $stage): StageDefinition => new StageDefinition(
                    approvers: $this->models($stage->namedApprovers()),
                    rule: $stage->rule,
                    quorum: $stage->quorum,
                    name: $stage->name,
                    requiredApprovers: $stage->required_approvers,
                ), $stages)))
                ->continueOnRejection(! $latest->reject_on_stage_rejection)
                ->open();
        }

        return $this->expiringWith($request, Approvals::request($request))
            ->from($this->models($latest->namedApprovers()))
            ->rule($latest->rule, $latest->quorum)
            ->open();
    }

    private function expiringWith(Request $request, PendingApprovalRequest $round): PendingApprovalRequest
    {
        return $request->expires_at === null ? $round : $round->expiringAt($request->expires_at);
    }

    /**
     * @param  list<NamedApprover>  $approvers
     * @return list<Model>
     */
    private function models(array $approvers): array
    {
        return array_map($this->model(...), $approvers);
    }

    /**
     * Load a named approver back. One that no longer exists refuses the reopen rather
     * than silently shrinking — or, with every approver gone, opening — the round.
     */
    private function model(NamedApprover $approver): Model
    {
        $class = Relation::getMorphedModel($approver->type) ?? $approver->type;

        $model = is_a($class, Model::class, true)
            ? $class::query()->find($approver->id)
            : null;

        if (! $model instanceof Model) {
            throw InvalidApprover::missing($approver->type, $approver->id);
        }

        return $model;
    }
}
