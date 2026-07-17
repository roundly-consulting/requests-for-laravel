<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests\Fixtures;

use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * A host's subclass of the packaged Request — what `requests.model` invites.
 *
 * `CountsCreations` is not decoration. Without it `toHonourModelSwap` silently drops its
 * strongest half: asserting the concrete class of a *returned* object cannot tell a row
 * really created as this class from one created as the packaged class and re-hydrated
 * (permissions #31). Counting `created` events on this exact class is the independent
 * oracle.
 */
final class CustomRequest extends Request
{
    use CountsCreations;

    protected $table = 'requests';
}
