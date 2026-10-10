<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests\Fixtures;

use RoundlyConsulting\Requests\Tests\TestCase;

/**
 * A uuid-keyed host, set BEFORE the providers boot and the migrations run — the only
 * window that matters, since the migrations read the key types to pick their columns and
 * the request model reads its own to decide whether to mint a key. Every key in the graph
 * agrees: the approvals engine's morph columns, the requests author morph and the
 * requests' own id, which the engine's `subject` / `approvable` columns point at.
 */
abstract class UuidKeyTestCase extends TestCase
{
    /** @return array<string, mixed> */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'approvals.key_type' => 'uuid',
            'requests.key_type' => 'uuid',
            'requests.primary_key_type' => 'uuid',
        ]);
    }
}
