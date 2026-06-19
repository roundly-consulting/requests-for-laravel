<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Support\StatusGuard;

it('allows staying in the same status', function (): void {
    expect((new StatusGuard)->allows(Status::Approved, Status::Approved))->toBeTrue();
});

it('allows legal transitions', function (): void {
    expect((new StatusGuard)->allows(Status::New, Status::Approved))->toBeTrue();
});

it('rejects illegal transitions', function (): void {
    expect((new StatusGuard)->allows(Status::Rejected, Status::Approved))->toBeFalse();
});

it('asserts a legal transition without throwing', function (): void {
    (new StatusGuard)->assert(Status::New, Status::Rejected);
})->throwsNoExceptions();

it('throws on an illegal transition', function (): void {
    (new StatusGuard)->assert(Status::Cancelled, Status::New);
})->throws(InvalidStatusTransition::class);
