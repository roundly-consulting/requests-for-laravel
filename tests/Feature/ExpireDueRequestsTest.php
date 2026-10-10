<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Requests\Actions\ExpireDueRequests;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;

it('expires every open, past-due request and nothing else', function (): void {
    Event::fake(RequestExpired::class);

    $due = Request::factory()->count(2)->expired()->create();
    $future = Request::factory()->pending()->create(['expires_at' => now()->addDay()]);
    $approved = Request::factory()->approved()->create(['expires_at' => now()->subDay()]);

    $count = app(ExpireDueRequests::class)->execute();

    expect($count)->toBe(2)
        ->and($due->map(fn (Request $request) => $request->fresh()?->status)->all())
        ->toBe([Status::Expired, Status::Expired])
        ->and($future->fresh()?->status)->toBe(Status::New)
        ->and($approved->fresh()?->status)->toBe(Status::Approved);

    Event::assertDispatchedTimes(RequestExpired::class, 2);
});

it('only counts the due requests on a dry run', function (): void {
    Event::fake(RequestExpired::class);

    $due = Request::factory()->expired()->create();

    expect(app(ExpireDueRequests::class)->execute(dryRun: true))->toBe(1)
        ->and($due->fresh()?->status)->toBe(Status::New);

    Event::assertNotDispatched(RequestExpired::class);
});

it('walks every chunk, clamping a non-positive chunk size to one', function (): void {
    Request::factory()->count(3)->expired()->create();

    expect(app(ExpireDueRequests::class)->execute(chunk: 0))->toBe(3)
        ->and(Request::query()->expired()->count())->toBe(0);
});

/**
 * C-4 (b): the sweep expired each preloaded row from its chunk copy. A request decided
 * after the chunk was read was expired over its decision.
 */
it('leaves a request decided mid-sweep alone', function (): void {
    [$first, $second] = Request::factory()->count(2)->expired()->create()->all();
    $expired = [];

    Event::listen(RequestExpired::class, function (RequestExpired $event) use (&$expired, $second): void {
        $expired[] = $event->request->getKey();

        // The second request is decided between the chunk read and its turn.
        Request::query()->findOrFail($second->getKey())->update(['status' => Status::Approved]);
    });

    expect(app(ExpireDueRequests::class)->execute())->toBe(1)
        ->and($second->fresh()?->status)->toBe(Status::Approved)
        ->and($expired)->toBe([$first->getKey()]);
});

/**
 * C-4 (c): two overlapping sweeps each expired the rows they had read, so a request was
 * expired — and announced — twice.
 */
it('expires and announces each request once when sweeps overlap', function (): void {
    [$first, $second] = Request::factory()->count(2)->expired()->create()->all();
    $expired = [];
    $nested = false;

    Event::listen(RequestExpired::class, function (RequestExpired $event) use (&$expired, &$nested): void {
        $expired[] = $event->request->getKey();

        if (! $nested) {
            $nested = true;

            Requests::expireDue();
        }
    });

    app(ExpireDueRequests::class)->execute();

    expect($expired)->toBe([$first->getKey(), $second->getKey()]);
});
