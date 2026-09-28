<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Requests\Actions\ExpireRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;

it('expires a request and fires the event', function (): void {
    Event::fake(RequestExpired::class);

    $request = Request::factory()->expired()->create();

    (new ExpireRequest)->execute($request);

    Event::assertDispatched(fn (RequestExpired $e) => $e->request->is($request));

    expect($request->fresh()?->status)->toBe(Status::Expired);
});

it('refuses to expire an approved request under enforcement', function (): void {
    config()->set('requests.enforce_transitions', true);

    $request = Request::factory()->approved()->create();

    expect(fn () => Requests::expire($request))->toThrow(InvalidStatusTransition::class)
        ->and($request->fresh()?->status)->toBe(Status::Approved);
});

it('expires an open request under enforcement', function (): void {
    config()->set('requests.enforce_transitions', true);

    $request = Request::factory()->pending()->create();

    Requests::expire($request);

    expect($request->fresh()?->status)->toBe(Status::Expired);
});
