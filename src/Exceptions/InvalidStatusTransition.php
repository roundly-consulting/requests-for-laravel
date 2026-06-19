<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Exceptions;

use RoundlyConsulting\Requests\Enums\Status;

final class InvalidStatusTransition extends RequestException
{
    public function __construct(
        public readonly Status $from,
        public readonly Status $to,
    ) {
        parent::__construct((string) trans('requests::messages.invalid_status_transition', [
            'from' => $from->value,
            'to' => $to->value,
        ]));
    }

    public static function between(Status $from, Status $to): self
    {
        return new self($from, $to);
    }
}
