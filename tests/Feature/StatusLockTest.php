<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/**
 * The stale-copy fixes (C-4, C-5) rest on a row lock: each status write is pinned to a
 * locked read of the `requests` row, taken inside a transaction. The deterministic race
 * tests prove the re-read, not the lock — these prove the lock, on every leg.
 *
 *  - SQLite compiles `lockForUpdate()` to nothing, so the recording grammar keeps the lock
 *    visible as a marker comment (local `composer test` and the Laravel 12 / 13 matrix).
 *  - MySQL and Postgres compile it themselves (`… for update`), so a plain query listener
 *    sees the native clause — the grammar would replace the engine's and drop the real lock.
 *
 * Both feed the same LockRecorder, so the assertions below are one set for every engine.
 */
beforeEach(function (): void {
    LockRecorder::flush();

    if (DatabaseDriver::current() === DatabaseDriver::Sqlite) {
        $connection = DB::connection();
        $connection->setQueryGrammar(new LockRecordingGrammar($connection));

        LockRecorder::listenForMarkers();

        return;
    }

    DB::listen(static function (QueryExecuted $query): void {
        if (preg_match('/\sfor update(\s|$)/i', $query->sql) === 1) {
            LockRecorder::record('lock-for-update', $query->connection->transactionLevel(), $query->sql);
        }
    });
});

/**
 * @return list<int> the transaction depth of each lock taken on the requests table
 */
function requestRowLocks(): array
{
    // Either quoting: "requests" on sqlite and pgsql, `requests` on mysql. The quote right
    // before the name keeps the approvals engine's own tables (`approval_requests`) out.
    $locks = array_filter(
        LockRecorder::recorded(),
        static fn (array $lock): bool => $lock['marker'] === 'lock-for-update'
            && preg_match('/\bfrom ["`]requests["`]/', $lock['sql']) === 1,
    );

    return array_values(array_map(static fn (array $lock): int => $lock['transactionDepth'], $locks));
}

// The test's parameter is typed Closure, so Pest hands each flow over as is.
it('locks the request row before writing its status', function (Closure $flow): void {
    $flow(Request::factory()->pending()->create());

    expect(requestRowLocks())->not->toBeEmpty()
        ->each->toBeGreaterThanOrEqual(1);
})->with([
    'expire' => static fn (Request $request): Request => Requests::expire($request),
    'cancel' => static fn (Request $request): Request => Requests::cancel($request),
    'approve without approvers' => static fn (Request $request): Request => Requests::approve($request, User::create()),
    'reject without approvers' => static fn (Request $request): Request => Requests::reject($request, User::create()),
    'reopen' => static function (Request $request): Request {
        $alice = User::create();
        Requests::approve($request, $alice);

        return Requests::reopen($request, $alice);
    },
    'expireDue' => static function (Request $request): int {
        $request->update(['expires_at' => now()->subMinute()]);

        return Requests::expireDue();
    },
]);

it('locks the request row when an approval round resolves it', function (): void {
    $alice = User::create();
    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    LockRecorder::flush();

    Requests::approve($request, $alice);

    expect(requestRowLocks())->toHaveCount(1)
        ->each->toBeGreaterThanOrEqual(1);
});

/**
 * The lock observed doing its job: a second session holds the row, and a status write
 * waits on it at the locked read — then goes through once it is released. Only a real
 * engine has a lock to wait on.
 *
 * *Which* statement waited is the load-bearing assertion, not *that* one did. Without the
 * lock the write still waits — its `update` takes a row lock of its own — but only after
 * an unlocked read has handed it the status the holder is about to overwrite: the lost
 * update C-4 / C-5 closed. Proven red with `lockForUpdate()` removed.
 *
 * Both sessions wait one second at most (the engine defaults are 50s on MySQL and forever
 * on Postgres), and the holder is rolled back and disconnected in `finally`, so a failure
 * here never leaves a lock behind for the teardown's table drop to queue on.
 */
it('makes a status write wait while another session holds the row', function (): void {
    $request = Request::factory()->pending()->create();
    $driver = DatabaseDriver::current();

    config(['database.connections.requests_lock_holder' => config('database.connections.'.DB::getDefaultConnection())]);
    $holder = DB::connection('requests_lock_holder');

    $shortWait = $driver === DatabaseDriver::Mysql
        ? 'set session innodb_lock_wait_timeout = 1'
        : "set lock_timeout = '1s'";

    DB::statement($shortWait);
    $holder->statement($shortWait);

    $blocked = null;

    try {
        $holder->beginTransaction();
        $holder->table($request->getTable())->where($request->getKeyName(), $request->getKey())->lockForUpdate()->first();

        try {
            Requests::expire($request);
        } catch (QueryException $exception) {
            $blocked = $exception;
        }
    } finally {
        try {
            $holder->rollBack();
        } finally {
            DB::purge('requests_lock_holder');
        }
    }

    // MySQL: 1205 "Lock wait timeout exceeded". Postgres: 55P03 lock_not_available.
    expect($blocked)->toBeInstanceOf(QueryException::class)
        ->and($driver === DatabaseDriver::Mysql ? $blocked?->errorInfo[1] : $blocked?->errorInfo[0])
        ->toBe($driver === DatabaseDriver::Mysql ? 1205 : '55P03')
        ->and($blocked?->getSql())->toMatch('/\bfrom ["`]requests["`].*\sfor update$/is')
        ->and($request->fresh()?->status)->toBe(Status::New);

    expect(Requests::expire($request)->status)->toBe(Status::Expired)
        ->and($request->fresh()?->status)->toBe(Status::Expired);
})->skip(
    fn (): bool => ! in_array(DatabaseDriver::current(), [DatabaseDriver::Mysql, DatabaseDriver::Pgsql], true),
    'a row lock only blocks on a real engine (mysql / pgsql)',
);
