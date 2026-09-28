<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\RequestManager;
use RoundlyConsulting\Requests\Tests\User;

it('documents its root, is fakeable and reaches every action', function (): void {
    expect(Requests::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

it('creates a request fluently through the facade', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()
        ->author($alice)
        ->type('Claim')
        ->title('Expense reimbursement')
        ->meta(['ip' => '127.0.0.1'])
        ->requireApprovalsFrom([$alice, $bob])
        ->create();

    expect($request)->toBeInstanceOf(Request::class)
        ->and($request->status)->toBe(Status::New)
        ->and($request->require_approvals_from?->all())->toBe([$alice->id, $bob->id]);
});

it('resolves through intent-named facade methods', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();

    Requests::approve($request, $alice);
    expect($request->fresh()?->status)->toBe(Status::New);

    Requests::approve($request, $bob);
    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('cancels and expires through the facade', function (): void {
    $request = Request::factory()->pending()->create();

    Requests::cancel($request);
    expect($request->fresh()?->status)->toBe(Status::Cancelled);

    $expiring = Request::factory()->expired()->create();
    Requests::expire($expiring);
    expect($expiring->fresh()?->status)->toBe(Status::Expired);
});

it('expires the due requests through the facade', function (): void {
    $due = Request::factory()->expired()->create();
    $future = Request::factory()->pending()->create(['expires_at' => now()->addDay()]);

    expect(Requests::expireDue(dryRun: true))->toBe(1)
        ->and($due->fresh()?->status)->toBe(Status::New)
        ->and(Requests::expireDue())->toBe(1)
        ->and($due->fresh()?->status)->toBe(Status::Expired)
        ->and($future->fresh()?->status)->toBe(Status::New)
        ->and(Requests::expireDue())->toBe(0);
});

it('answers whether a request may move to a status through the facade', function (): void {
    $open = Request::factory()->pending()->create();
    $cancelled = Request::factory()->create(['status' => Status::Cancelled]);

    expect(Requests::canTransition($open, Status::Approved))->toBeTrue()
        ->and(Requests::canTransition($open, Status::New))->toBeTrue()
        ->and(Requests::canTransition($cancelled, Status::New))->toBeFalse()
        ->and(Requests::canTransition($cancelled, Status::Cancelled))->toBeTrue();
});

it('passes the reason through to the approvals engine', function (): void {
    $alice = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    Requests::approve($request, $alice, 'Within budget');

    expect($alice->approvalFor($request)?->reason)->toBe('Within budget');
});

it('resolves the manager as a singleton from the container', function (): void {
    expect(app(RequestManager::class))->toBe(app(RequestManager::class));
});

it('registers a collision-safe Requests alias', function (): void {
    expect(class_exists('Requests'))->toBeTrue()
        ->and(is_a('Requests', Requests::class, true))->toBeTrue();
});
