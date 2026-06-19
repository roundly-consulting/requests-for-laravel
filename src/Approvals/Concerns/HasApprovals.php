<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Approvals\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Requests\Approvals\Approval;

/**
 * Approvable side: a model that can be approved by actors.
 *
 * @mixin Model
 */
trait HasApprovals
{
    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function hasBeenApprovedBy(Model $actor): bool
    {
        return $this->approvals()
            ->whereMorphedTo('actor', $actor)
            ->exists();
    }
}
