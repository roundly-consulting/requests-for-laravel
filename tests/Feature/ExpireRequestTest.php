<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Actions\ExpireRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

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

/**
 * C-4 (a): the status was checked and written on the caller's copy. A copy loaded before
 * an approval still read New, so under enforcement it expired an Approved request.
 */
it('checks the stored status, not a stale copy, before expiring', function (): void {
    config()->set('requests.enforce_transitions', true);

    $alice = User::create();
    $request = Requests::make()->requireApprovalsFrom([$alice])->create();
    $stale = Request::query()->findOrFail($request->getKey());

    Requests::approve($request, $alice);

    Event::fake([RequestExpired::class]);

    expect(fn () => Requests::expire($stale))->toThrow(InvalidStatusTransition::class, 'from Approved to Expired')
        ->and($request->fresh()?->status)->toBe(Status::Approved)
        ->and($request->approvalRequests()->firstOrFail()->status)->toBe(ApprovalStatus::Approved);

    Event::assertNotDispatched(RequestExpired::class);
});

it('refuses to expire a request whose row is gone', function (): void {
    $request = Request::factory()->expired()->create();

    Request::query()->whereKey($request->getKey())->forceDelete();

    expect(fn () => Requests::expire($request))->toThrow(ModelNotFoundException::class);
});
