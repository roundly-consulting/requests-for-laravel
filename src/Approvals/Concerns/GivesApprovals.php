<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Approvals\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Requests\Approvals\Approval;

/**
 * Actor side: a model that can give approvals to approvable models.
 *
 * @mixin Model
 */
trait GivesApprovals
{
    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'actor');
    }

    public function hasApproved(Model $model): bool
    {
        return $this->approvals()
            ->whereMorphedTo('approvable', $model)
            ->exists();
    }

    public function toggleApproval(Model $model): bool
    {
        $approval = $this->approvals()
            ->whereMorphedTo('approvable', $model)
            ->firstOrCreate([
                'approvable_id' => $model->getKey(),
                'approvable_type' => $model->getMorphClass(),
            ]);

        if (! $approval->wasRecentlyCreated) {
            $approval->delete();

            return false;
        }

        return true;
    }
}
