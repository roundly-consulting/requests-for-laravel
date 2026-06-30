<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Enums;

use RoundlyConsulting\Enums\Helpers;

enum Status: string
{
    use Helpers;

    case New = 'New';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Cancelled = 'Cancelled';
    case Expired = 'Expired';

    /**
     * Whether the request has reached an end state and should no longer move on
     * its own.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected, self::Cancelled, self::Expired => true,
            self::New => false,
        };
    }

    /**
     * Whether the request is still open and awaiting a decision.
     */
    public function isOpen(): bool
    {
        return $this === self::New;
    }

    /**
     * Whether a move from this status to $to is allowed by the lifecycle graph.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * The statuses this status may transition to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Approved, self::Rejected, self::Cancelled, self::Expired],
            self::Approved => [self::Rejected, self::New, self::Cancelled],
            self::Rejected => [self::New],
            self::Expired => [self::New],
            self::Cancelled => [],
        };
    }
}
