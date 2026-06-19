<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Support;

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;

/**
 * Native, library-free state guard. Enforces the lifecycle graph defined on the
 * Status enum without any third-party state-machine package.
 */
final class StatusGuard
{
    public function allows(Status $from, Status $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return $from->canTransitionTo($to);
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function assert(Status $from, Status $to): void
    {
        if (! $this->allows($from, $to)) {
            throw InvalidStatusTransition::between($from, $to);
        }
    }
}
