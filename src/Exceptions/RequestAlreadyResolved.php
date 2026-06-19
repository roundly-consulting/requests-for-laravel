<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Exceptions;

use RoundlyConsulting\Requests\Enums\Status;

final class RequestAlreadyResolved extends RequestException
{
    public function __construct(public readonly Status $status)
    {
        parent::__construct((string) trans('requests::messages.request_already_resolved', [
            'status' => $status->value,
        ]));
    }

    public static function inStatus(Status $status): self
    {
        return new self($status);
    }
}
