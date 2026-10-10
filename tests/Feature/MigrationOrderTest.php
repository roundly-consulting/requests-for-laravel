<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\RequestsServiceProvider;
use RoundlyConsulting\Requests\Tests\User;
use RoundlyConsulting\Testing\Database\DriverMatrix;

$migrations = __DIR__.'/../../database/migrations';

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages).
 *
 * This replaces two hand-rolled cases in tests/Unit/RequestsServiceProviderTest.php that
 * had the right ideas — they even pinned the publish count and matched the timestamp
 * pattern — and are exactly why they should be the shared implementation rather than this
 * package's copy of it. `count: 1` pins the file count so neither check can pass over an
 * empty or relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(RequestsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes every migration timestamp-injected into the host', function (): void {
    expect(RequestsServiceProvider::class)->toPublishMigrationsTimestamped('requests-migrations', 1);
});

/**
 * M — `toHaveRunnableMigrationOrder` — is deliberately NOT adopted, and this note is the
 * cause rather than an omission.
 *
 * `MigrationGraph::assertRunnable()` checks two independent things and only one is about
 * foreign keys: it also pins that a `Schema::table()` ALTER sorts at or after the CREATE of
 * the table it alters (approvals #2). Requests ships **one CREATE, zero FK edges and zero
 * ALTERs** — verified against the migration source, not the row spec — so *both* halves are
 * inert. There is no edge to order and no ALTER to place.
 *
 * The contrast with its own provider is the clean illustration: `approvals` also has 0 FKs
 * but ships 2 ALTERs, so it adopts M with a `foreignKeys: 0` live pin; this row has 0 FKs
 * and 0 ALTERs and rejects it, as `connections` did.
 *
 * The `author` columns are a plain `morphs()`, deliberately unconstrained because an author
 * can live in any table. If a real FK or an ALTER is ever added, this row must adopt M
 * rather than inherit this note.
 */

/**
 * R — the real-engine proof, on both engines the package supports. `migrations: 1` pins
 * the count, and the expectation additionally fails a set that "applies cleanly" while
 * creating no tables — an empty `up()` otherwise passes and proves nothing. Each case runs
 * on its own engine's leg and skips visibly on every other.
 *
 * The negative control (`toRejectBrokenOrderOnConnection`) is deliberately NOT adopted: it
 * asserts the engine *refuses* a reordered set, and with a single migration the reversed
 * list is the same list — and with zero foreign keys neither engine has anything to refuse
 * regardless, so it would fail loudly by design. That is the assertion working correctly
 * against a shape it does not fit, not a red to chase (credits, tested not assumed).
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 1);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

it('applies its migrations on mysql', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('mysql', migrations: 1);
})->skip(fn (): bool => ! test()->connectionAvailable('mysql'), 'no mysql connection available');

/**
 * The driver-truth pin. It compares the driver the leg *declares* (TESTING_DB_DRIVER)
 * against what the connection itself *answers*, so a "pgsql" job that quietly ran on SQLite
 * — the exact failure the leg exists to prevent — is impossible rather than merely
 * detectable by reading a skip count. It caught the 3a decapitation.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The `status` enum column and the two jsonb columns (`meta`, `require_approvals_from`) are
 * what the drivers render differently — `json` has no equality operator on Postgres at all.
 * Pinning a round-trip on whatever engine the leg configured proves the columns are usable
 * rather than merely creatable.
 */
it('round-trips a request on the configured engine', function (): void {
    $author = User::query()->create();

    $request = Requests::make()
        ->title('Budget')
        ->description('Q3 hardware')
        ->author($author)
        ->meta(collect(['tier' => 2, 'region' => 'eu']))
        ->create();

    $fresh = $request->fresh();

    expect($fresh->status)->toBe(Status::New)
        ->and($fresh->title)->toBe('Budget')
        ->and($fresh->description)->toBe('Q3 hardware')
        ->and($fresh->author_type)->toBe($author->getMorphClass())
        ->and($fresh->author_id)->toBe($author->getKey())
        // Key-by-key rather than `toBe` on the whole map: jsonb sorts object keys (by
        // length, then bytewise), so `['tier' => 2, 'region' => 'eu']` comes back
        // reordered and a whole-map `toBe` (`===`, order-sensitive) would red on Postgres
        // while passing on SQLite. `toEqual` would hide the opposite bug — it is `==`, so
        // it would accept the string "2" for the int 2, which is precisely what a round-trip
        // pin exists to catch.
        ->and($fresh->meta?->get('tier'))->toBe(2)
        ->and($fresh->meta?->get('region'))->toBe('eu');
});
