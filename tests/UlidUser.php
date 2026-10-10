<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;
use RoundlyConsulting\Requests\Concerns\HasRequests;
use RoundlyConsulting\Requests\Contracts\HasRequests as HasRequestsContract;

/**
 * A host model keyed by ulids — an approver and author for the ulid key-type leg.
 */
final class UlidUser extends Model implements GivesApprovalsInterface, HasRequestsContract
{
    use GivesApprovals;
    use HasRequests;
    use HasUlids;

    protected $table = 'ulid_users';

    protected $guarded = [];

    public $timestamps = false;
}
