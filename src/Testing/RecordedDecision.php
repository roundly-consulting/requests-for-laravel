<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Requests\Models\Request;

/**
 * One approve / reject / reopen call captured by {@see RequestsFake}.
 */
final readonly class RecordedDecision
{
    public function __construct(
        public Request $request,
        public Model $actor,
        public ?string $reason,
    ) {}

    public function matches(Request $request, ?Model $actor, ?string $reason): bool
    {
        return self::same($this->request, $request)
            && ($actor === null || self::same($this->actor, $actor))
            && ($reason === null || $this->reason === $reason);
    }

    /**
     * Whether two models are the same record. `Model::is()` compares keys, and two unsaved
     * models — what the fake's own create() returns — both have none, so without a key
     * only the very same instance matches.
     *
     * @internal
     */
    public static function same(Model $recorded, Model $given): bool
    {
        if ($recorded->getKey() === null || $given->getKey() === null) {
            return $recorded === $given;
        }

        return $recorded->is($given);
    }
}
