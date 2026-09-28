<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Exceptions;

/**
 * An approver the package cannot name on an approval round: anything but an Eloquent
 * model (a bare id carries no model type, so it could never be enforced), or — when a
 * reopened request replays its approval round — an approver that no longer exists.
 */
final class InvalidApprover extends RequestException
{
    public static function notAModel(mixed $approver): self
    {
        return new self((string) trans('requests::messages.approver_not_a_model', [
            'type' => get_debug_type($approver),
        ]));
    }

    public static function missing(string $type, int|string $id): self
    {
        return new self((string) trans('requests::messages.approver_missing', [
            'type' => $type,
            'id' => (string) $id,
        ]));
    }
}
