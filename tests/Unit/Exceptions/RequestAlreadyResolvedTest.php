<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;
use RoundlyConsulting\Requests\Exceptions\RequestException;

it('carries the resolved status', function (): void {
    $exception = RequestAlreadyResolved::inStatus(Status::Approved);

    expect($exception)->toBeInstanceOf(RequestException::class)
        ->and($exception->status)->toBe(Status::Approved)
        ->and($exception->getMessage())->toContain('Approved');
});
