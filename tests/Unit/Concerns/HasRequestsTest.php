<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

it('lists requests authored by the model', function (): void {
    $author = User::create();
    $other = User::create();

    Request::factory()->authoredBy($author)->pending()->create();
    Request::factory()->authoredBy($other)->pending()->create();

    expect($author->requests()->count())->toBe(1);
});

it('filters by status through relation helpers', function (): void {
    $author = User::create();

    Request::factory()->authoredBy($author)->pending()->create();
    Request::factory()->authoredBy($author)->approved()->create();
    Request::factory()->authoredBy($author)->rejected()->create();

    expect($author->openRequests()->count())->toBe(1)
        ->and($author->approvedRequests()->count())->toBe(1)
        ->and($author->rejectedRequests()->count())->toBe(1);
});
