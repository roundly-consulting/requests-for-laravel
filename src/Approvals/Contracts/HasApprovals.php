<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Approvals\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by models that can be approved by actors.
 *
 * Use the matching RoundlyConsulting\Requests\Approvals\Concerns\HasApprovals
 * trait to satisfy this contract.
 */
interface HasApprovals
{
    public function hasBeenApprovedBy(Model $actor): bool;
}
