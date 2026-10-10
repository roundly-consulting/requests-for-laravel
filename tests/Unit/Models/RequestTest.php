<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Database\Factories\RequestFactory;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('returns correct factory', function (): void {
    expect(Request::factory())->toBeInstanceOf(RequestFactory::class);
});

it('casts status to the Status enum', function (): void {
    $request = Request::factory()->create(['status' => Status::Approved]);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('casts meta and require_approvals_from to collections', function (): void {
    $request = Request::factory()->create([
        'meta' => collect(['ip' => '127.0.0.1']),
        'require_approvals_from' => collect([1, 2]),
    ])->fresh();

    expect($request?->meta)->toBeInstanceOf(Collection::class)
        ->and($request?->meta?->get('ip'))->toBe('127.0.0.1')
        ->and($request?->require_approvals_from?->all())->toBe([1, 2]);
});

it('reports expiry only for open, past-due requests', function (): void {
    $expired = Request::factory()->expired()->create();
    $future = Request::factory()->pending()->create(['expires_at' => now()->addDay()]);
    $approved = Request::factory()->approved()->create(['expires_at' => now()->subDay()]);
    $noDeadline = Request::factory()->pending()->create();

    expect($expired->isExpired())->toBeTrue()
        ->and($future->isExpired())->toBeFalse()
        ->and($approved->isExpired())->toBeFalse()
        ->and($noDeadline->isExpired())->toBeFalse();
});

/**
 * C-12: the `New` default lived only in the column, so a request made with a raw
 * `create()` held a null status in memory until refreshed — `isExpired()` and
 * `Requests::approve()` then crashed on it.
 */
it('starts a raw-created request as New in memory', function (): void {
    $request = Request::create(['title' => 'x']);

    expect($request->status)->toBe(Status::New)
        ->and($request->isExpired())->toBeFalse();

    Requests::approve($request, User::create());

    expect($request->fresh()?->status)->toBe(Status::Approved);
});
