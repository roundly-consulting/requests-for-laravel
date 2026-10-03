<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Support;

use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Resolves the Eloquent model backing requests from `requests.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class RequestModel
{
    /**
     * @return class-string<Request>
     */
    public static function class(): string
    {
        return ModelResolver::for('requests.model', Request::class);
    }
}
