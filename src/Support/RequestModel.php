<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Support;

use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Resolves the Eloquent model backing requests from `requests.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that isn't a Request (so it can't answer the
 * package's scopes, status lifecycle, or approval flow) falls back to the
 * packaged model.
 */
final class RequestModel
{
    /**
     * @return class-string<Request>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('requests.model', Request::class);

        return is_a($model, Request::class, true) ? $model : Request::class;
    }
}
