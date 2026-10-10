<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Requests\Actions\CancelRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCancelled;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('cancels an open request and fires the event', function (): void {
    Event::fake(RequestCancelled::class);

    $request = Request::factory()->pending()->create();

    (new CancelRequest)->execute($request);

    Event::assertDispatched(fn (RequestCancelled $e) => $e->request->is($request));

    expect($request->fresh()?->status)->toBe(Status::Cancelled);
});

it('throws when cancelling a terminal request under enforcement', function (): void {
    config()->set('requests.enforce_transitions', true);

    $request = Request::factory()->rejected()->create();

    (new CancelRequest)->execute($request);
})->throws(InvalidStatusTransition::class);

/**
 * C-4 (a): a copy loaded before a rejection still read New, so under enforcement it
 * cancelled a Rejected request.
 */
it('checks the stored status, not a stale copy, before cancelling', function (): void {
    config()->set('requests.enforce_transitions', true);

    $alice = User::create();
    $request = Requests::make()->requireApprovalsFrom([$alice])->create();
    $stale = Request::query()->findOrFail($request->getKey());

    Requests::reject($request, $alice);

    Event::fake([RequestCancelled::class]);

    expect(fn () => Requests::cancel($stale))->toThrow(InvalidStatusTransition::class, 'from Rejected to Cancelled')
        ->and($request->fresh()?->status)->toBe(Status::Rejected);

    Event::assertNotDispatched(RequestCancelled::class);
});
