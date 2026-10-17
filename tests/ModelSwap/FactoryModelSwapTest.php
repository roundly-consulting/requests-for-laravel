<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\Fixtures\CustomRequest;

/*
 * The factory names the packaged model in `$model`, so without a `modelName()` override
 * returning the seam it keeps building the packaged class after a host swapped it — and the
 * host's events, observers and casts never run on factory-made requests.
 */
it('builds the configured model from the factory', function (): void {
    expect(Request::factory()->pending()->create()::class)->toBe(CustomRequest::class);
});
