<?php

declare(strict_types=1);

it('resolves exception messages from the package namespace', function (): void {
    $message = trans('requests::messages.invalid_status_transition', [
        'from' => 'Rejected',
        'to' => 'Approved',
    ]);

    expect($message)->toBe('Cannot transition a request from Rejected to Approved.');
});
