<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Requests\Actions\CancelRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCancelled;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Models\Request;

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
