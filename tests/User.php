<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Requests\Approvals\Concerns\GivesApprovals;
use RoundlyConsulting\Requests\Approvals\Contracts\GivesApprovals as GivesApprovalsContract;
use RoundlyConsulting\Requests\Concerns\HasRequests;
use RoundlyConsulting\Requests\Contracts\HasRequests as HasRequestsContract;

final class User extends Model implements GivesApprovalsContract, HasRequestsContract
{
    use GivesApprovals;
    use HasRequests;

    protected $guarded = [];

    public $timestamps = false;
}
