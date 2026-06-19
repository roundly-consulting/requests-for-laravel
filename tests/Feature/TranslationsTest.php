<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;

it('resolves status labels from the package namespace', function (): void {
    expect(Status::Approved->label())->toBe('Approved')
        ->and(trans('requests::messages.status.Cancelled'))->toBe('Cancelled');
});

it('resolves exception messages from the package namespace', function (): void {
    $message = trans('requests::messages.invalid_status_transition', [
        'from' => 'Rejected',
        'to' => 'Approved',
    ]);

    expect($message)->toBe('Cannot transition a request from Rejected to Approved.');
});
