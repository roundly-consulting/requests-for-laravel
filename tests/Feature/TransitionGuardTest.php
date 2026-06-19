<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\Actions\ResolveRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Tests\User;

it('throws on an illegal transition when enforcement is on', function (): void {
    config()->set('requests.enforce_transitions', true);

    $request = (new CreateRequest)->execute(new CreateRequestDto(status: Status::Rejected));
    $user = User::create();

    (new ResolveRequest)->execute($request, $user, Status::Approved);
})->throws(InvalidStatusTransition::class);

it('allows a legal transition when enforcement is on', function (): void {
    config()->set('requests.enforce_transitions', true);

    $request = (new CreateRequest)->execute(new CreateRequestDto);
    $user = User::create();

    (new ResolveRequest)->execute($request, $user, Status::Approved);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('preserves legacy behaviour when enforcement is off', function (): void {
    config()->set('requests.enforce_transitions', false);

    $request = (new CreateRequest)->execute(new CreateRequestDto(status: Status::Rejected));
    $user = User::create();

    // With the flag off, the legacy toggle path runs and the move is not guarded.
    (new ResolveRequest)->execute($request, $user, Status::Approved);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});
