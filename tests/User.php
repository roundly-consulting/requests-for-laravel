<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Requests\Approvals\Concerns\GivesApprovals;
use RoundlyConsulting\Requests\Approvals\Contracts\GivesApprovals as GivesApprovalsContract;

final class User extends Model implements GivesApprovalsContract
{
    use GivesApprovals;

    protected $guarded = [];

    public $timestamps = false;
}
