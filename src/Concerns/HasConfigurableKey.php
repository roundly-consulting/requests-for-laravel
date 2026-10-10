<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * Drives the request model's primary key from `requests.primary_key_type`, matching the
 * column type the migration emitted for the same config value.
 *
 * This is the **inbound** key — the requests' own `id`, which the approvals engine's
 * polymorphic `subject` / `approvable` columns point *at*, so it must match
 * `approvals.key_type`. It is distinct from the **outbound** `requests.key_type`, the key
 * type of the authors a request points at.
 *
 * Laravel's {@see HasUniqueStringIds} keys `getKeyType()`, `getIncrementing()`,
 * `uniqueIds()` and route-binding validation off the single `$usesUniqueIds` flag, so
 * flipping that flag in the trait initializer is the whole seam: on `bigint` the model
 * behaves exactly as if it had never used the trait.
 */
trait HasConfigurableKey
{
    use HasUniqueStringIds;

    /**
     * Toggle Laravel's unique-string-id machinery off entirely on the `bigint` default, so
     * `getKeyType()` / `getIncrementing()` fall through to the auto-incrementing parent.
     */
    public function initializeHasUniqueStringIds(): void
    {
        $this->usesUniqueIds = $this->configuredKeyType() !== KeyType::BigInt;
    }

    /**
     * Generate a key of the configured type, minted on `creating`. Never called on
     * `bigint` — the database mints those. Both types are time-ordered (`uuid7`, `ulid`),
     * so `latest('id')` and the sweep's id-ordered chunks keep creation order.
     */
    public function newUniqueId(): string
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? (string) Str::ulid()
            : (string) Str::uuid7();
    }

    /** The configured inbound primary-key strategy of the requests table. */
    public function configuredKeyType(): KeyType
    {
        return KeyType::fromConfig('requests.primary_key_type');
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? Str::isUlid($value)
            : Str::isUuid($value);
    }
}
