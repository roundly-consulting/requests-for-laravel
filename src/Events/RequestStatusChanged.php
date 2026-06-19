<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Events;

use RoundlyConsulting\Requests\Models\Request;

final class RequestStatusChanged
{
    public function __construct(public Request $request) {}
}
