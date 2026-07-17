<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Tests\Fixtures\CustomRequest;

/**
 * A — the secret-safe `about` capture.
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous. This capture goes through
 * `Artisan::call('about')`, asserts the output is non-empty, and asserts every
 * `mustRender` string is present *before* it looks for a secret.
 *
 * The deleted local coverage (`it('contributes a requests section to about')` and friends,
 * in tests/Unit/RequestsServiceProviderTest.php) used `expectsOutputToContain`, which is
 * genuinely non-vacuous — but it was **positive only**. It never asked what the section
 * must not print.
 *
 * Requests' section carries no credentials, which is what makes it worth pinning: the risk
 * here is the host's own **internal namespace**. The provider deliberately renders
 * `class_basename(RequestModel::class())` rather than the FQCN, so a host that swaps in
 * `Acme\Billing\Internal\...` gets `CustomRequest` in `about`, not its private module
 * layout. Nothing enforced that narrowing until now: replacing `class_basename(...)` with
 * the raw class string would have shipped green.
 */
it('renders the requests section without leaking the host model namespace', function (): void {
    config()->set('requests.model', CustomRequest::class);
    config()->set('requests.enforce_transitions', true);
    config()->set('requests.default_ttl', 90);

    expect('requests')->toLeakNoSecrets(
        secrets: [
            // The host's internal namespace. `class_basename` is why this must not render,
            // and this line is the only thing keeping it that way.
            CustomRequest::class,
            'RoundlyConsulting\Requests\Tests\Fixtures',
        ],
        mustRender: [
            'Model',
            'Transition guard',
            'Default TTL',
            'Facade alias',
            // The positive halves that prove the lines report rather than sit empty — and
            // that the basename really is rendered, so the secret check above is aimed at
            // a section that genuinely printed the model.
            'CustomRequest',
            'ENFORCED',
            '90 min',
            'Requests',
        ],
    );
});

/**
 * The other branch of every reported line, so the section is pinned in both states rather
 * than only the one the defaults happen to produce.
 */
it('reports the off-state of each configurable line', function (): void {
    config()->set('requests.enforce_transitions', false);
    config()->set('requests.default_ttl', null);
    config()->set('requests.register_facade_alias', false);

    expect('requests')->toLeakNoSecrets(
        secrets: [],
        mustRender: [
            'OFF',
            'NONE',
            'DISABLED',
        ],
    );
});
