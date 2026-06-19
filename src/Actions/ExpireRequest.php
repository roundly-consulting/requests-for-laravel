<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;

final class ExpireRequest
{
    public function execute(Request $request): Request
    {
        $request->update(['status' => Status::Expired]);

        event(new RequestStatusChanged($request));
        event(new RequestExpired($request));

        return $request;
    }
}
