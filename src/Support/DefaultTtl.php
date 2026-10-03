<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Reads `requests.default_ttl`: how many minutes a request created without an explicit
 * expiry lives. Null or empty means requests don't expire by default. An int or a
 * numeric string (an `env()` value) is accepted; anything else — a word, a fraction, a
 * zero or negative figure — fails loudly instead of silently never expiring.
 *
 * @internal
 */
final class DefaultTtl
{
    /** A hundred years, in minutes: past it the figure is a typo, not a policy. */
    private const int MAX_MINUTES = 52_560_000;

    /**
     * @throws InvalidConfigurationException
     */
    public static function minutes(): ?int
    {
        $ttl = config('requests.default_ttl');

        if ($ttl === null || $ttl === '') {
            return null;
        }

        return Config::integer('requests.default_ttl', 1, min: 1, max: self::MAX_MINUTES);
    }
}
