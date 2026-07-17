<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Exceptions\RequestException;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The seven presets replace this file's generic hand-written rules; the package's own
 * domain rules are kept below, because the presets have no equivalent for them.
 */
ArchPresets::strictTypes('RoundlyConsulting\Requests');

/**
 * The deliberate extension points are exempt: `Request` is what `requests.model` invites a
 * host to subclass (pinned by the preset below instead), and RequestException is the base
 * every requests error extends so a host can catch them uniformly.
 *
 * Note the `$ignoring` PARAMETER rather than Pest's fluent `->ignoring()`. Only the
 * parameter is rot-checked (by `exemptionsExist` below): the fluent form accepts any string
 * and never verifies it, so a typo or an exemption that outlived its code is a silent no-op
 * — the ban then has a hole nobody can see.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Requests', [
    Request::class,
    RequestException::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable
 * model is a PHP fatal the moment a host uses the seam the config documents. Requests
 * shipped `Request` non-final and had no arch rule keeping it that way — the previous
 * "keeps events final" / "keeps actions final" rules named two namespaces and said nothing
 * about Models, so the fatal could have arrived at any time unnoticed.
 *
 * It also pins the other direction: that `requests.model` really defaults to the packaged
 * Request, so the seam cannot rot into naming something else.
 */
ArchPresets::swappableModelsAreNotFinal([
    Request::class => 'requests.model',
]);

/**
 * Requests does no cryptography and has no reason to start. A standing guard.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Requests');

/**
 * Every `requests.model` read goes through Support\RequestModel (which delegates to the
 * toolkit's ModelResolver). Adopted rather than rejected as jwt rejected it: requests has
 * exactly the shape the preset targets — a real Eloquent model behind a `*.model` key,
 * resolved through a Support seam — so the stray-literal half has something to say. The
 * key is declared rather than inferred so the preset polices the one this package means.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support', ['requests.model']);

/**
 * The morph-key seam, guarded. Requests' `author` column migrated off raw
 * `$table->morphs()` onto `morphKey('author', KeyType::fromConfig(...))` so a uuid/ulid
 * host can flip its whole graph coherently — a hardcoded bigint id breaks those hosts on
 * Postgres, and SQLite type affinity hides it. This pin reds if a future migration
 * reintroduces a raw morph and bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: requests' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red the graph is
 * wrong — never widen the allow-list to quiet it (bug #6 is a true positive).
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

/**
 * Replaces the local `expect(['dd', 'dump', 'ray'])->each->not->toBeUsed()`, which named
 * three functions; the preset covers the full leftover set.
 */
ArchPresets::noDebuggingLeftovers();

/*
|--------------------------------------------------------------------------
| Package-specific rules — kept: no preset equivalent
|--------------------------------------------------------------------------
*/

it('extends the base package exception')
    ->expect('RoundlyConsulting\Requests\Exceptions')
    ->classes()
    ->toExtend(RequestException::class)
    ->ignoring(RequestException::class);

it('keeps actions to a single execute method')
    ->expect('RoundlyConsulting\Requests\Actions')
    ->toHaveMethod('execute');
