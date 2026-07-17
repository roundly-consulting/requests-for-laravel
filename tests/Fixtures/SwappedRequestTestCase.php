<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests\Fixtures;

use RoundlyConsulting\Requests\Tests\TestCase;

/**
 * The base case for the model-swap proof: `requests.model` points at the host subclass
 * BEFORE the providers boot — the only window a real host has, since `config/requests.php`
 * is read at boot.
 *
 * The swap lives here rather than in the test body deliberately. The deleted
 * `CreateRequestModelOverrideTest` set `config('requests.model')` inside the test, which
 * is a window no host ever occupies; that shape is what let reviews ship a swap test
 * structurally incapable of seeing the migration-time bug it was named for. It is
 * `swapModel()` in `defineEnvironment()` or it proves nothing about a real install.
 *
 * `defineEnvironment()` is deliberately NOT overridden: PackageTestCase does its whole
 * job there (DriverMatrix::configure + configBeforeBoot + the swaps), so an override
 * without `parent::` decapitates the base case silently — no error, no red, DriverMatrix
 * simply never configured.
 */
abstract class SwappedRequestTestCase extends TestCase
{
    /** @return array<string, mixed> */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'requests.model' => CustomRequest::class,
        ]);
    }
}
