<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Exceptions\RequestException;

it('carries the from and to statuses', function (): void {
    $exception = InvalidStatusTransition::between(Status::Rejected, Status::Approved);

    expect($exception)->toBeInstanceOf(RequestException::class)
        ->and($exception->from)->toBe(Status::Rejected)
        ->and($exception->to)->toBe(Status::Approved)
        ->and($exception->getMessage())->toContain('Rejected')
        ->and($exception->getMessage())->toContain('Approved');
});
