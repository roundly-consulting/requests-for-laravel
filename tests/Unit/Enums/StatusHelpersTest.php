<?php

declare(strict_types=1);

use RoundlyConsulting\Enums\DataTransferObjects\EnumOption;
use RoundlyConsulting\Requests\Enums\Status;

it('exposes backed values through the enums trait', function (): void {
    expect(Status::values()->all())->toBe(['New', 'Approved', 'Rejected', 'Cancelled', 'Expired']);
});

it('exposes case names through the enums trait', function (): void {
    expect(Status::names()->all())->toBe(['New', 'Approved', 'Rejected', 'Cancelled', 'Expired']);
});

it('builds readable labels through the enums trait', function (): void {
    expect(Status::labels()->all())->toBe(['New', 'Approved', 'Rejected', 'Cancelled', 'Expired'])
        ->and(Status::New->label())->toBe('New')
        ->and(Status::Approved->readable())->toBe('Approved');
});

it('builds a value => label option map', function (): void {
    expect(Status::toOptions()->all())->toBe([
        'New' => 'New',
        'Approved' => 'Approved',
        'Rejected' => 'Rejected',
        'Cancelled' => 'Cancelled',
        'Expired' => 'Expired',
    ])->and(Status::toArray())->toBe(Status::toOptions()->all());
});

it('builds option DTOs for selects', function (): void {
    $options = Status::options();

    expect($options->first())->toBeInstanceOf(EnumOption::class)
        ->and($options->first()->value)->toBe('New')
        ->and($options->first()->name)->toBe('New');
});

it('produces a laravel validation rule', function (): void {
    expect(Status::validationRule())->toBe('in:New,Approved,Rejected,Cancelled,Expired');
});

it('resolves cases by name and compares cases', function (): void {
    expect(Status::fromName('Approved'))->toBe(Status::Approved)
        ->and(Status::tryFromName('Nope'))->toBeNull()
        ->and(Status::New->is(Status::New))->toBeTrue()
        ->and(Status::New->isIn([Status::Approved, Status::New]))->toBeTrue();
});
