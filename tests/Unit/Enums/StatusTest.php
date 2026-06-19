<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;

it('exposes the expected cases', function (): void {
    expect(Status::New->value)->toBe('New')
        ->and(Status::Approved->value)->toBe('Approved')
        ->and(Status::Rejected->value)->toBe('Rejected')
        ->and(Status::Cancelled->value)->toBe('Cancelled')
        ->and(Status::Expired->value)->toBe('Expired')
        ->and(Status::cases())->toHaveCount(5);
});

it('reports terminal statuses', function (Status $status, bool $terminal): void {
    expect($status->isTerminal())->toBe($terminal)
        ->and($status->isOpen())->toBe(! $terminal);
})->with([
    'new' => [Status::New, false],
    'approved' => [Status::Approved, true],
    'rejected' => [Status::Rejected, true],
    'cancelled' => [Status::Cancelled, true],
    'expired' => [Status::Expired, true],
]);

it('returns a translatable label for every case', function (Status $status): void {
    expect($status->label())->toBe($status->value);
})->with(Status::cases());

it('enforces the transition graph', function (Status $from, Status $to, bool $allowed): void {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [Status::New, Status::Approved, true],
    [Status::New, Status::Rejected, true],
    [Status::New, Status::Cancelled, true],
    [Status::New, Status::Expired, true],
    [Status::Approved, Status::Rejected, true],
    [Status::Approved, Status::New, true],
    [Status::Approved, Status::Cancelled, true],
    [Status::Approved, Status::Expired, false],
    [Status::Rejected, Status::New, true],
    [Status::Rejected, Status::Approved, false],
    [Status::Rejected, Status::Cancelled, false],
    [Status::Expired, Status::New, true],
    [Status::Expired, Status::Approved, false],
    [Status::Cancelled, Status::New, false],
    [Status::Cancelled, Status::Approved, false],
]);
