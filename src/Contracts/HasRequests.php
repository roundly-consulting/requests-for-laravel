<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Implemented by author models that raise requests.
 *
 * Use the matching RoundlyConsulting\Requests\Concerns\HasRequests trait to
 * satisfy this contract.
 */
interface HasRequests
{
    /** @return MorphMany<Request, *> */
    public function requests(): MorphMany;
}
