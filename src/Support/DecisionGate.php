<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * The approvals engine's authorization gate (`approvals.authorization`), applied to a move
 * the engine does not see as a decision. Reopening a decided request reaches the engine's
 * own check only through the actor's withdrawal, which is skipped once the round is over —
 * so the gate is asked here, whatever state the round is in. It reads the gate the way the
 * engine does: off unless enabled, `decide-approval` unless another ability is configured.
 *
 * @internal
 */
final class DecisionGate
{
    /**
     * @throws UnauthorizedApprovalException when authorization is on and the gate denies the actor
     * @throws InvalidConfigurationException
     */
    public function authorize(Model $actor, Model $subject): void
    {
        if (! Config::boolean('approvals.authorization.enabled')) {
            return;
        }

        $configured = config('approvals.authorization.ability');

        $ability = $configured === null || (is_string($configured) && trim($configured) === '')
            ? 'decide-approval'
            : Config::requireString('approvals.authorization.ability');

        try {
            Gate::forUser($actor)->authorize($ability, [$subject]);
        } catch (AuthorizationException) {
            throw UnauthorizedApprovalException::forActor($actor);
        }
    }
}
