<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Events;

use RoundlyConsulting\Requests\Models\Request;

final class RequestCreated
{
    public function __construct(public Request $request) {}
}
