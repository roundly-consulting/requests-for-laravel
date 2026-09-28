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
        return $this->request->is($request)
            && ($actor === null || $this->actor->is($actor))
            && ($reason === null || $this->reason === $reason);
    }
}
