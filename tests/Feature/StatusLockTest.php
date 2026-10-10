<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/**
 * The stale-copy fixes (C-4, C-5) rest on a row lock, and SQLite compiles
 * `lockForUpdate()` to nothing — the deterministic race tests prove the re-read, not the
 * lock. The recording grammar keeps the lock visible as a marker, so each status write is
 * pinned to a locked read of the `requests` row, taken inside a transaction.
 *
 * SQLite only: the grammar refuses a real engine, which takes the real lock instead.
 */
beforeEach(function (): void {
    // The skip below is decided after this hook runs, so the hook stands down by itself.
    if (DatabaseDriver::current() !== DatabaseDriver::Sqlite) {
        return;
    }

    $connection = DB::connection();
    $connection->setQueryGrammar(new LockRecordingGrammar($connection));

    LockRecorder::flush();
    LockRecorder::listenForMarkers();
});

/**
 * @return list<int> the transaction depth of each lock taken on the requests table
 */
function requestRowLocks(): array
{
    $locks = array_filter(
        LockRecorder::recorded(),
        static fn (array $lock): bool => $lock['marker'] === 'lock-for-update'
            && preg_match('/from "requests"/', $lock['sql']) === 1,
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
])->skip(fn (): bool => DatabaseDriver::current() !== DatabaseDriver::Sqlite, 'the recording grammar is sqlite-only');

it('locks the request row when an approval round resolves it', function (): void {
    $alice = User::create();
    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    LockRecorder::flush();

    Requests::approve($request, $alice);

    expect(requestRowLocks())->toHaveCount(1)
        ->each->toBeGreaterThanOrEqual(1);
})->skip(fn (): bool => DatabaseDriver::current() !== DatabaseDriver::Sqlite, 'the recording grammar is sqlite-only');
