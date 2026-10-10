<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;
use RoundlyConsulting\Requests\Concerns\HasRequests;
use RoundlyConsulting\Requests\Contracts\HasRequests as HasRequestsContract;

/**
 * A host model keyed by uuids — an approver and author for the uuid key-type leg.
 */
final class UuidUser extends Model implements GivesApprovalsInterface, HasRequestsContract
{
    use GivesApprovals;
    use HasRequests;
    use HasUuids;

    protected $table = 'uuid_users';

    protected $guarded = [];

    public $timestamps = false;
}
