<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

afterEach(function (): void {
    Carbon::setTestNow();
    Relation::morphMap([], false);
});

it('expires only open, past-due requests', function (): void {
    Event::fake(RequestExpired::class);

    $due = Request::factory()->expired()->create();
    $future = Request::factory()->pending()->create(['expires_at' => now()->addDay()]);
    $approved = Request::factory()->approved()->create(['expires_at' => now()->subDay()]);

    $this->artisan('requests:expire')
        ->expectsOutputToContain('Expired 1 request(s).')
        ->assertSuccessful();

    expect($due->fresh()?->status)->toBe(Status::Expired)
        ->and($future->fresh()?->status)->toBe(Status::New)
        ->and($approved->fresh()?->status)->toBe(Status::Approved);

    Event::assertDispatchedTimes(RequestExpired::class, 1);
});

it('does not mutate anything on a dry run', function (): void {
    Event::fake(RequestExpired::class);

    $due = Request::factory()->expired()->create();

    $this->artisan('requests:expire', ['--dry-run' => true])
        ->expectsOutputToContain('Would expire 1 request(s).')
        ->assertSuccessful();

    expect($due->fresh()?->status)->toBe(Status::New);

    Event::assertNotDispatched(RequestExpired::class);
});

it('expires across multiple chunks', function (): void {
    Request::factory()->count(3)->expired()->create();

    $this->artisan('requests:expire', ['--chunk' => 1])
        ->expectsOutputToContain('Expired 3 request(s).')
        ->assertSuccessful();

    expect(Request::query()->expired()->count())->toBe(0);
});

it('reports zero when nothing is due', function (): void {
    Request::factory()->pending()->create();

    $this->artisan('requests:expire')
        ->expectsOutputToContain('Expired 0 request(s).')
        ->assertSuccessful();
});

it('lapses expired pending approval decisions', function (): void {
    $request = Request::factory()->pending()->create();
    $user = User::create();

    Approvals::for($request)->as($user)->expiringAt(now()->subDay())->ask();

    $this->artisan('requests:expire')
        ->expectsOutputToContain('Lapsed 1 expired approval decision(s).')
        ->assertSuccessful();
});

it('lapses only the approval decisions on requests, leaving the rest of the app alone', function (): void {
    $request = Request::factory()->pending()->create();
    $user = User::create();

    $onRequest = Approvals::for($request)->as($user)->expiringAt(now()->subDay())->ask();
    // A host's own approvable (any other model) whose decision has also passed its expiry.
    $elsewhere = Approvals::for(User::create())->as($user)->expiringAt(now()->subDay())->ask();

    $this->artisan('requests:expire')
        ->expectsOutputToContain('Lapsed 1 expired approval decision(s).')
        ->assertSuccessful();

    expect($onRequest->fresh()?->status)->toBe(ApprovalStatus::Expired)
        ->and($elsewhere->fresh()?->status)->toBe(ApprovalStatus::Pending);
});

it('scopes the approvals sweep to the request morph alias when the host maps one', function (): void {
    Relation::morphMap(['request' => Request::class]);

    $request = Request::factory()->pending()->create();
    $user = User::create();

    $onRequest = Approvals::for($request)->as($user)->expiringAt(now()->subDay())->ask();
    $elsewhere = Approvals::for(User::create())->as($user)->expiringAt(now()->subDay())->ask();

    expect($onRequest->approvable_type)->toBe('request');

    $this->artisan('requests:expire')
        ->expectsOutputToContain('Lapsed 1 expired approval decision(s).')
        ->assertSuccessful();

    expect($onRequest->fresh()?->status)->toBe(ApprovalStatus::Expired)
        ->and($elsewhere->fresh()?->status)->toBe(ApprovalStatus::Pending);
});
