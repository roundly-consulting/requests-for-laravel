<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Requests\Actions\ExpireDueRequests;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestExpired;
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
