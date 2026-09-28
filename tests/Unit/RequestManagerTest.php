<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\RequestBuilder;
use RoundlyConsulting\Requests\RequestManager;
use RoundlyConsulting\Requests\Tests\User;

/*
 * The dependency-injection form: the same API, resolved from the container
 * instead of reached through the facade.
 */

function manager(): RequestManager
{
    return app(RequestManager::class);
}

it('starts a builder via make', function (): void {
    expect(manager()->make())->toBeInstanceOf(RequestBuilder::class);
});

it('creates from a dto', function (): void {
    $request = manager()->make()->title('Direct')->create();

    expect($request)->toBeInstanceOf(Request::class)
        ->and($request->title)->toBe('Direct');
});

it('creates directly from a dto', function (): void {
    $request = manager()->create(new CreateRequestDto(title: 'Dto'));

    expect($request)->toBeInstanceOf(Request::class)
        ->and($request->title)->toBe('Dto');
});

it('approves, rejects and reopens', function (): void {
    $user = User::create();

    $request = Request::factory()->pending()->create();
    manager()->approve($request, $user);
    expect($request->fresh()?->status)->toBe(Status::Approved);

    manager()->reject($request, $user);
    expect($request->fresh()?->status)->toBe(Status::Rejected);

    manager()->reopen($request, $user);
    expect($request->fresh()?->status)->toBe(Status::New);
});

it('cancels and expires', function (): void {
    $request = Request::factory()->pending()->create();
    manager()->cancel($request);
    expect($request->fresh()?->status)->toBe(Status::Cancelled);

    $expiring = Request::factory()->expired()->create();
    manager()->expire($expiring);
    expect($expiring->fresh()?->status)->toBe(Status::Expired);
});

it('expires the due requests and answers transition questions', function (): void {
    $due = Request::factory()->expired()->create();

    expect(manager()->expireDue())->toBe(1)
        ->and($due->fresh()?->status)->toBe(Status::Expired)
        ->and(manager()->canTransition($due->refresh(), Status::New))->toBeTrue()
        ->and(manager()->canTransition($due, Status::Approved))->toBeFalse();
});
