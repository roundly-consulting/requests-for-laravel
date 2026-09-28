<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\RequestManager;
use RoundlyConsulting\Requests\Testing\RequestsFake;
use RoundlyConsulting\Requests\Tests\User;

function fakeRequest(int $id): Request
{
    $request = new Request;
    $request->forceFill(['id' => $id, 'status' => Status::New]);

    return $request;
}

it('is a manager subtype installed behind the facade and the container', function (): void {
    $fake = Requests::fake();

    expect($fake)->toBeInstanceOf(RequestManager::class)
        ->and(app(RequestManager::class))->toBe($fake)
        ->and(Requests::getFacadeRoot())->toBe($fake);
});

it('records created requests without touching the database', function (): void {
    $fake = Requests::fake();

    $built = Requests::make()->title('Built')->create();
    Requests::create(new CreateRequestDto(title: 'Direct'));

    $fake->assertCreated();
    $fake->assertCreated(2);

    expect($built->title)->toBe('Built')
        ->and($built->exists)->toBeFalse()
        ->and(Request::query()->count())->toBe(0);
});

it('fails the create assertions when they do not hold', function (): void {
    $fake = Requests::fake();

    expect(fn () => $fake->assertCreated())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertCreated(1))->toThrow(AssertionFailedError::class);

    $fake->assertNothingCreated();

    Requests::make()->create();

    expect(fn () => $fake->assertNothingCreated())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertCreated(2))->toThrow(AssertionFailedError::class);
});

it('records approve, reject, reopen, cancel and expire calls', function (): void {
    $fake = Requests::fake();

    $request = fakeRequest(1);
    $actor = User::create();

    Requests::approve($request, $actor);
    Requests::reject($request, $actor);
    Requests::reopen($request, $actor);
    Requests::cancel($request);
    Requests::expire($request);

    $fake->assertApproved($request, $actor);
    $fake->assertApproved($request);
    $fake->assertRejected($request, $actor);
    $fake->assertReopened($request, $actor);
    $fake->assertCancelled($request);
    $fake->assertExpired($request);
});

it('records the reason of every decision', function (): void {
    $fake = Requests::fake();

    $request = fakeRequest(1);
    $actor = User::create();

    Requests::approve($request, $actor, 'Within budget');
    Requests::reject($request, $actor, 'Missing receipt');
    Requests::reopen($request, $actor, 'Receipt found');

    $fake->assertApproved($request, $actor, 'Within budget');
    $fake->assertRejected($request, reason: 'Missing receipt');
    $fake->assertReopened($request, $actor, 'Receipt found');

    expect(fn () => $fake->assertApproved($request, $actor, 'Over budget'))
        ->toThrow(AssertionFailedError::class, 'with reason [Over budget]')
        ->and(fn () => $fake->assertRejected($request, $actor, 'Within budget'))
        ->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertReopened($request, reason: 'Missing receipt'))
        ->toThrow(AssertionFailedError::class);
});

it('fails the decision assertions for another request or actor', function (): void {
    $fake = Requests::fake();

    $request = fakeRequest(1);
    $other = fakeRequest(2);
    $actor = User::create();
    $stranger = User::create();

    Requests::approve($request, $actor);
    Requests::reject($request, $actor);
    Requests::reopen($request, $actor);

    expect(fn () => $fake->assertApproved($other))
        ->toThrow(AssertionFailedError::class, 'Expected the request to be approved.')
        ->and(fn () => $fake->assertApproved($request, $stranger))
        ->toThrow(AssertionFailedError::class, 'by the given actor')
        ->and(fn () => $fake->assertRejected($other, $actor))
        ->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertReopened($request, $stranger))
        ->toThrow(AssertionFailedError::class);
});

it('fails the cancel and expire assertions for a request that was not touched', function (): void {
    $fake = Requests::fake();

    Requests::cancel(fakeRequest(1));
    Requests::expire(fakeRequest(1));

    expect(fn () => $fake->assertCancelled(fakeRequest(2)))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertExpired(fakeRequest(2)))->toThrow(AssertionFailedError::class);
});

it('asserts nothing was decided, cancelled or expired', function (): void {
    $fake = Requests::fake();

    $fake->assertNothingApproved();
    $fake->assertNothingRejected();
    $fake->assertNothingReopened();
    $fake->assertNothingCancelled();
    $fake->assertNothingExpired();
    $fake->assertNothingExpiredDue();
});

it('fails the nothing-assertions once the call was made', function (): void {
    $fake = Requests::fake();

    $request = fakeRequest(1);
    $actor = User::create();

    Requests::approve($request, $actor);
    Requests::reject($request, $actor);
    Requests::reopen($request, $actor);
    Requests::cancel($request);
    Requests::expire($request);
    Requests::expireDue();

    expect(fn () => $fake->assertNothingApproved())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingRejected())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingReopened())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingCancelled())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingExpired())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingExpiredDue())->toThrow(AssertionFailedError::class);
});

it('records expireDue sweeps and counts the due requests without expiring them', function (): void {
    $fake = Requests::fake();

    $due = Request::factory()->expired()->create();

    expect(Requests::expireDue())->toBe(1)
        ->and($due->fresh()?->status)->toBe(Status::New);

    $fake->assertExpiredDue();
    $fake->assertExpiredDue(dryRun: false);

    expect(fn () => $fake->assertExpiredDue(dryRun: true))
        ->toThrow(AssertionFailedError::class, 'dry-run');
});

it('fails assertExpiredDue when no sweep ran', function (): void {
    $fake = Requests::fake();

    expect(fn () => $fake->assertExpiredDue())
        ->toThrow(AssertionFailedError::class, 'was not called')
        ->and(fn () => $fake->assertExpiredDue(dryRun: false))
        ->toThrow(AssertionFailedError::class, 'live');

    Requests::expireDue(dryRun: true);

    $fake->assertExpiredDue(dryRun: true);
});

it('records calls made through a constructor-injected manager and the expire command', function (): void {
    $fake = Requests::fake();

    app(RequestManager::class)->cancel(fakeRequest(3));

    $this->artisan('requests:expire')->assertSuccessful();

    $fake->assertCancelled(fakeRequest(3));
    $fake->assertExpiredDue(dryRun: false);
});

it('answers canTransition for real', function (): void {
    Requests::fake();

    $cancelled = new Request;
    $cancelled->forceFill(['status' => Status::Cancelled]);

    expect(Requests::canTransition(fakeRequest(1), Status::Approved))->toBeTrue()
        ->and(Requests::canTransition($cancelled, Status::New))->toBeFalse();
});

it('builds a fresh fake from the container on every call', function (): void {
    expect(Requests::fake())->not->toBe(Requests::fake())
        ->and(Requests::fake())->toBeInstanceOf(RequestsFake::class);
});
