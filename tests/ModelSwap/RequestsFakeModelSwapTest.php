<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Tests\Fixtures\CustomRequest;
use RoundlyConsulting\Requests\Tests\User;

/**
 * C-7: the fake's `create()` returned `new Request` — a TypeError for host code typed
 * against its `requests.model` subclass, under the fake only — and dropped the author,
 * meta, declared approvers and default-TTL expiry the real create sets.
 */
it('builds the host model under the fake, as the real create does, without saving it', function (): void {
    Carbon::setTestNow('2026-10-01 12:00:00');
    config()->set('requests.default_ttl', 60);

    $author = User::query()->create();
    $approver = User::query()->create();

    Requests::fake();

    $request = Requests::make()
        ->author($author)
        ->title('Budget')
        ->meta(['k' => 'v'])
        ->requireApprovalsFrom([$approver])
        ->create();

    expect($request)->toBeInstanceOf(CustomRequest::class)
        ->and($request->exists)->toBeFalse()
        ->and($request->status)->toBe(Status::New)
        ->and($request->title)->toBe('Budget')
        ->and($request->author_type)->toBe($author->getMorphClass())
        ->and($request->author_id)->toBe($author->getKey())
        ->and($request->meta?->all())->toBe(['k' => 'v'])
        ->and($request->require_approvals_from?->all())->toBe([$approver->getKey()])
        ->and($request->expires_at?->toDateTimeString())->toBe('2026-10-01 13:00:00')
        ->and(CustomRequest::query()->count())->toBe(0);

    Carbon::setTestNow();
});
