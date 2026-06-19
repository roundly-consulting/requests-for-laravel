<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('records created requests without touching the database', function (): void {
    $fake = Requests::fake();

    Requests::make()->title('Faked')->create();

    $fake->assertCreated();
    $fake->assertCreated(1);

    expect(Request::query()->count())->toBe(0);
});

it('asserts approve, reject, reopen, cancel and expire calls', function (): void {
    $fake = Requests::fake();

    $request = new Request;
    $request->forceFill(['id' => 1]);
    $actor = User::create();

    Requests::approve($request, $actor);
    Requests::reject($request, $actor);
    Requests::reopen($request, $actor);
    Requests::cancel($request);
    Requests::expire($request);

    $fake->assertApproved($request, $actor);
    $fake->assertRejected($request, $actor);
    $fake->assertReopened($request, $actor);
    $fake->assertCancelled($request);
    $fake->assertExpired($request);
});

it('asserts nothing was created', function (): void {
    $fake = Requests::fake();

    $fake->assertNothingCreated();
});

it('fails assertions for calls that did not happen', function (): void {
    $fake = Requests::fake();

    $request = new Request;
    $request->forceFill(['id' => 99]);
    $actor = User::create();

    expect(fn () => $fake->assertApproved($request, $actor))
        ->toThrow(AssertionFailedError::class);

    expect(fn () => $fake->assertCancelled($request))
        ->toThrow(AssertionFailedError::class);
});
