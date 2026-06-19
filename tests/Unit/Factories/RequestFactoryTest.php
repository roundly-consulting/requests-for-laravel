<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('produces status states', function (string $state, Status $status): void {
    $request = Request::factory()->{$state}()->create();

    expect($request->status)->toBe($status);
})->with([
    ['pending', Status::New],
    ['approved', Status::Approved],
    ['rejected', Status::Rejected],
    ['cancelled', Status::Cancelled],
]);

it('produces an expired-ready state', function (): void {
    $request = Request::factory()->expired()->create();

    expect($request->status)->toBe(Status::New)
        ->and($request->expires_at?->isPast())->toBeTrue()
        ->and($request->isExpired())->toBeTrue();
});

it('sets required approvers', function (): void {
    $request = Request::factory()->requiring([1, 2])->create();

    expect($request->require_approvals_from?->all())->toBe([1, 2]);
});

it('attaches an author', function (): void {
    $author = User::create();

    $request = Request::factory()->authoredBy($author)->create();

    expect($request->author?->is($author))->toBeTrue();
});
