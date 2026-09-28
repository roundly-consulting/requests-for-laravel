<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidApprover;
use RoundlyConsulting\Requests\RequestBuilder;
use RoundlyConsulting\Requests\RequestManager;
use RoundlyConsulting\Requests\Tests\User;

function builder(): RequestBuilder
{
    return new RequestBuilder(app(RequestManager::class));
}

it('defaults to a new request', function (): void {
    expect(builder()->toDto()->status)->toBe(Status::New);
});

it('carries the approver models into the dto', function (): void {
    $alice = User::create();
    $bob = User::create();

    $dto = builder()->requireApprovalsFrom([$alice, $bob])->toDto();

    expect($dto->approvers)->toBe([$alice, $bob]);
});

it('accepts a single approver model', function (): void {
    $alice = User::create();

    $dto = builder()->requireApprovalsFrom($alice)->toDto();

    expect($dto->approvers)->toBe([$alice]);
});

it('accepts a collection of approver models', function (): void {
    $alice = User::create();

    $dto = builder()->requireApprovalsFrom(collect([$alice]))->toDto();

    expect($dto->approvers)->toBe([$alice]);
});

it('refuses a bare approver id, which names no model type', function (): void {
    $alice = User::create();

    builder()->requireApprovalsFrom([$alice, 2]);
})->throws(InvalidApprover::class, 'An approver must be an Eloquent model, int given');

it('wraps array meta into a collection', function (): void {
    $dto = builder()->meta(['ip' => '127.0.0.1'])->toDto();

    expect($dto->meta)->toBeInstanceOf(Collection::class)
        ->and($dto->meta?->get('ip'))->toBe('127.0.0.1');
});

it('carries every fluent value into the dto', function (): void {
    $author = User::create();
    $at = now()->addDay();

    $dto = builder()
        ->status(Status::Approved)
        ->author($author)
        ->type('Claim')
        ->title('Title')
        ->description('Description')
        ->expiresAt($at)
        ->toDto();

    expect($dto->status)->toBe(Status::Approved)
        ->and($dto->author?->is($author))->toBeTrue()
        ->and($dto->type)->toBe('Claim')
        ->and($dto->title)->toBe('Title')
        ->and($dto->description)->toBe('Description')
        ->and($dto->expiresAt?->equalTo($at))->toBeTrue();
});

it('persists a request through create', function (): void {
    $request = builder()->title('Persisted')->create();

    expect($request->exists)->toBeTrue()
        ->and($request->title)->toBe('Persisted');
});
