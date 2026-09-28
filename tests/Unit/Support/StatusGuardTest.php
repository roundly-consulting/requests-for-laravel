<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;
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

it('closes a cancelled request to every move', function (Status $to): void {
    expect((new StatusGuard)->closes(Status::Cancelled, $to))->toBeTrue();
})->with([Status::New, Status::Approved, Status::Rejected, Status::Expired]);

it('closes an expired request to everything but a reopen', function (): void {
    $guard = new StatusGuard;

    expect($guard->closes(Status::Expired, Status::New))->toBeFalse()
        ->and($guard->closes(Status::Expired, Status::Approved))->toBeTrue()
        ->and($guard->closes(Status::Expired, Status::Rejected))->toBeTrue()
        ->and($guard->closes(Status::Expired, Status::Cancelled))->toBeTrue();
});

it('never closes an open or decided request, nor a stay on the same status', function (): void {
    $guard = new StatusGuard;

    expect($guard->closes(Status::New, Status::Cancelled))->toBeFalse()
        ->and($guard->closes(Status::Approved, Status::Rejected))->toBeFalse()
        ->and($guard->closes(Status::Rejected, Status::Approved))->toBeFalse()
        ->and($guard->closes(Status::Cancelled, Status::Cancelled))->toBeFalse()
        ->and($guard->closes(Status::Expired, Status::Expired))->toBeFalse();
});

it('throws RequestAlreadyResolved for a move out of a closed status', function (): void {
    (new StatusGuard)->assertNotClosed(Status::Cancelled, Status::Rejected);
})->throws(RequestAlreadyResolved::class, 'already resolved (Cancelled)');

it('lets a move from an open status through', function (): void {
    (new StatusGuard)->assertNotClosed(Status::New, Status::Approved);
})->throwsNoExceptions();
