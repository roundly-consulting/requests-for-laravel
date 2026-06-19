<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Database\Factories\RequestFactory;
use RoundlyConsulting\Requests\Enums\Status;
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

it('treats an empty required-approvers list as approved by all', function (): void {
    $request = Request::factory()->create();

    expect($request->hasBeenApprovedByAll('user', []))->toBeTrue();
});

it('reports approval status across multiple required actors', function (): void {
    $request = Request::factory()->create();

    $first = User::create();
    $second = User::create();

    $first->toggleApproval($request);

    expect($request->hasBeenApprovedByAll($first->getMorphClass(), [$first->id, $second->id]))->toBeFalse();

    $second->toggleApproval($request);

    expect($request->hasBeenApprovedByAll($first->getMorphClass(), [$first->id, $second->id]))->toBeTrue();
});
