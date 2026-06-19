<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\RequestBuilder;
use RoundlyConsulting\Requests\Tests\User;

function builder(): RequestBuilder
{
    return new RequestBuilder(new CreateRequest);
}

it('defaults to a new request', function (): void {
    expect(builder()->toDto()->status)->toBe(Status::New);
});

it('normalises approver models to ids', function (): void {
    $alice = User::create();
    $bob = User::create();

    $dto = builder()->requireApprovalsFrom([$alice, $bob])->toDto();

    expect($dto->requireApprovalsFrom?->all())->toBe([$alice->id, $bob->id]);
});

it('accepts a single approver model', function (): void {
    $alice = User::create();

    $dto = builder()->requireApprovalsFrom($alice)->toDto();

    expect($dto->requireApprovalsFrom?->all())->toBe([$alice->id]);
});

it('accepts raw approver ids', function (): void {
    $dto = builder()->requireApprovalsFrom([1, 2])->toDto();

    expect($dto->requireApprovalsFrom?->all())->toBe([1, 2]);
});

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
