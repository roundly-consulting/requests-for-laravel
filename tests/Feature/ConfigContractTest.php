<?php

declare(strict_types=1);

/**
 * C — the config contract, pinned in both directions.
 *
 * The bugs each direction exists for:
 *  - forward — shops #18: the whole store-credit feature read `shops.payments.*` while the
 *    file shipped `payment.*`; 330 tests stayed green because the suite set the same wrong
 *    key. Requests had no equivalent guard at all.
 *  - reverse — media #27's `max_file_size` cap that never applied. A shipped key with no
 *    reader is a documented lie: a host that sets `requests.default_ttl` believes stale
 *    requests expire.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/requests.php')->toSatisfyConfigContract(
        [__DIR__.'/../../src', __DIR__.'/../../database'],
        [
            // `requests.model` is read through the toolkit's `ModelResolver::for(…)` seam
            // (via Support\RequestModel), not as a `config(` token, so the prefix is what
            // makes that real read visible to the scraper.
            'extraReadPrefixes' => ['requests.'],

            // Deliberately NO `excludeFromReverse` for the provider. The testing README's
            // example excludes the service provider on the grounds that "a render is not a
            // read" — but this provider's `contributesToAbout()` closure calls
            // `config('requests.enforce_transitions')`, `config('requests.default_ttl')` and
            // `config('requests.register_facade_alias')` for real, and `registerFacadeAlias()`
            // branches on the last one. For several of those keys it is the only reader in
            // the package: excluding it would discard readers and weaken the reverse
            // direction for nothing.
        ],
    );
});
