<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Approvals\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by actor models that can give approvals to approvable models.
 *
 * Use the matching RoundlyConsulting\Requests\Approvals\Concerns\GivesApprovals
 * trait to satisfy this contract.
 */
interface GivesApprovals
{
    public function hasApproved(Model $model): bool;

    public function toggleApproval(Model $model): bool;
}
