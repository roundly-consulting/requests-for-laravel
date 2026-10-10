<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCreated;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Tests\User;

it('creates request', function () {
    Event::fake(RequestCreated::class);

    $action = new CreateRequest;
    $request = $action->execute(new CreateRequestDto);

    Event::assertDispatched(fn (RequestCreated $e) => $e->request->is($request));

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::New->value,
    ]);
});

it('creates request with all details', function () {
    Event::fake(RequestCreated::class);

    /** @var User $author */
    $author = User::create();

    $action = new CreateRequest;

    $request = $action->execute(new CreateRequestDto(
        status: Status::Approved,
        author: $author,
        type: 'Claim',
        title: 'Its mine!',
        description: 'This is mine and only mine.',
        meta: collect([
            'ip' => '127.0.0.1',
        ]),
        approvers: [$author],
    ));

    Event::assertDispatched(fn (RequestCreated $e) => $e->request->is($request));

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Approved->value,
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->id,
        'type' => 'Claim',
        'title' => 'Its mine!',
        'description' => 'This is mine and only mine.',
        'meta' => '{"ip":"127.0.0.1"}',
        'require_approvals_from' => json_encode([$author->id]),
    ]);
});

it('stamps expires_at from the default ttl when set', function () {
    config()->set('requests.default_ttl', 60);

    Carbon::setTestNow('2026-06-19 12:00:00');

    $request = (new CreateRequest)->execute(new CreateRequestDto);

    expect($request->expires_at?->equalTo(now()->addMinutes(60)))->toBeTrue();

    Carbon::setTestNow();
});

it('prefers an explicit expiry over the default ttl', function () {
    config()->set('requests.default_ttl', 60);

    $at = now()->addDays(5)->startOfSecond();

    $request = (new CreateRequest)->execute(new CreateRequestDto(expiresAt: $at));

    expect($request->expires_at?->equalTo($at))->toBeTrue();
});

it('leaves expires_at null without a ttl', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto);

    expect($request->expires_at)->toBeNull();
});

/**
 * C-2: the request's deadline never reached its approval round, so the engine kept
 * taking decisions on a request the package itself reported as expired.
 */
it('gives the approval round the request expiry', function (): void {
    Carbon::setTestNow('2026-10-01 12:00:00');

    $alice = User::create();
    $bob = User::create();
    $deadline = Carbon::parse('2026-10-08 12:00:00');

    $flat = Requests::make()->requireApprovalsFrom([$alice, $bob])->expiresAt($deadline)->create();
    $staged = Requests::make()->stages([
        new StageDefinition([$alice], ApprovalRule::Unanimous, name: 'first'),
        new StageDefinition([$bob], ApprovalRule::Unanimous, name: 'second'),
    ])->expiresAt($deadline)->create();

    config()->set('requests.default_ttl', 60);
    $defaulted = Requests::make()->requireApprovalsFrom([$alice])->create();

    expect($flat->approvalRequests()->firstOrFail()->expires_at?->toDateTimeString())->toBe('2026-10-08 12:00:00')
        ->and($staged->approvalRequests()->firstOrFail()->expires_at?->toDateTimeString())->toBe('2026-10-08 12:00:00')
        ->and($defaulted->approvalRequests()->firstOrFail()->expires_at?->toDateTimeString())->toBe('2026-10-01 13:00:00');

    Carbon::setTestNow();
});
