<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('filters by status', function (): void {
    Request::factory()->pending()->create();
    Request::factory()->approved()->create();
    Request::factory()->rejected()->create();
    Request::factory()->cancelled()->create();

    expect(Request::query()->withStatus(Status::New)->count())->toBe(1)
        ->and(Request::query()->pending()->count())->toBe(1)
        ->and(Request::query()->approved()->count())->toBe(1)
        ->and(Request::query()->rejected()->count())->toBe(1)
        ->and(Request::query()->cancelled()->count())->toBe(1);
});

it('finds expired requests as open and past due', function (): void {
    Request::factory()->expired()->create();
    Request::factory()->pending()->create(['expires_at' => now()->addDay()]);
    Request::factory()->approved()->create(['expires_at' => now()->subDay()]);

    expect(Request::query()->expired()->count())->toBe(1)
        ->and(Request::query()->expiringBefore(now()->addWeek())->count())->toBe(2);
});

it('filters by author', function (): void {
    $author = User::create();
    $other = User::create();

    Request::factory()->authoredBy($author)->pending()->create();
    Request::factory()->authoredBy($other)->pending()->create();

    expect(Request::query()->authoredBy($author)->count())->toBe(1);
});
