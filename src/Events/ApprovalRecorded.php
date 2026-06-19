<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Events;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Requests\Models\Request;

final class ApprovalRecorded
{
    public function __construct(
        public Request $request,
        public Model $actor,
    ) {}
}
