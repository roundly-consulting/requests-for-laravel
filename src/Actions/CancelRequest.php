<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCancelled;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;

final class CancelRequest
{
    public function __construct(private readonly StatusGuard $guard = new StatusGuard) {}

    public function execute(Request $request): Request
    {
        if ($this->enforcing()) {
            $this->guard->assert($request->status, Status::Cancelled);
        }

        $request->update(['status' => Status::Cancelled]);

        event(new RequestStatusChanged($request));
        event(new RequestCancelled($request));

        return $request;
    }

    private function enforcing(): bool
    {
        return (bool) config('requests.enforce_transitions', false);
    }
}
