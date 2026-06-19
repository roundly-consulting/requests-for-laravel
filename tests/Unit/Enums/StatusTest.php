<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;

it('exposes the expected cases', function (): void {
    expect(Status::New->value)->toBe('New')
        ->and(Status::Approved->value)->toBe('Approved')
        ->and(Status::Rejected->value)->toBe('Rejected')
        ->and(Status::cases())->toHaveCount(3);
});
