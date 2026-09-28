<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Tests\User;

it('defaults to a new request without an author', function (): void {
    $dto = new CreateRequestDto;

    expect($dto->status)->toBe(Status::New)
        ->and($dto->author)->toBeNull()
        ->and($dto->type)->toBeNull()
        ->and($dto->meta)->toBeNull()
        ->and($dto->approvers)->toBe([]);
});

it('carries all provided values', function (): void {
    $dto = new CreateRequestDto(
        status: Status::Approved,
        type: 'Claim',
        title: 'Title',
        description: 'Description',
        meta: collect(['ip' => '127.0.0.1']),
        approvers: [$alice = User::create()],
    );

    expect($dto->status)->toBe(Status::Approved)
        ->and($dto->type)->toBe('Claim')
        ->and($dto->title)->toBe('Title')
        ->and($dto->description)->toBe('Description')
        ->and($dto->meta?->all())->toBe(['ip' => '127.0.0.1'])
        ->and($dto->approvers)->toBe([$alice]);
});

it('carries an expiry deadline', function (): void {
    $at = now()->addDay();

    $dto = new CreateRequestDto(expiresAt: $at);

    expect($dto->expiresAt?->equalTo($at))->toBeTrue();
});
