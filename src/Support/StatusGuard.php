<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Support;

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;

/**
 * Native, library-free state guard. Enforces the lifecycle graph defined on the
 * Status enum without any third-party state-machine package, and — whether or not
 * that graph is enforced — keeps a closed request closed.
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

    /**
     * Whether a request in `$from` is closed to a move to `$to`, whatever
     * `requests.enforce_transitions` says: a Cancelled request is final, and an Expired
     * one can only be reopened (moved back to New). Staying on the same status is not
     * a move.
     */
    public function closes(Status $from, Status $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return match ($from) {
            Status::Cancelled => true,
            Status::Expired => $to !== Status::New,
            default => false,
        };
    }

    /**
     * @throws RequestAlreadyResolved
     */
    public function assertNotClosed(Status $from, Status $to): void
    {
        if ($this->closes($from, $to)) {
            throw RequestAlreadyResolved::inStatus($from);
        }
    }
}
