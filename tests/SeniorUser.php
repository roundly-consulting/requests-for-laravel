<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Interfaces\ProvidesApprovalWeight;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;

/**
 * An actor whose decision carries extra weight, for the weighted rule.
 */
final class SeniorUser extends Model implements GivesApprovalsInterface, ProvidesApprovalWeight
{
    use GivesApprovals;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function approvalWeight(?Model $approvable = null): int
    {
        return 3;
    }
}
