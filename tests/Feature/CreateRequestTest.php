<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCreated;
use RoundlyConsulting\Requests\Tests\User;

it('creates request', function () {
    Event::fake(RequestCreated::class);

    $action = new CreateRequest;
    $request = $action->execute(new CreateRequestDto);

    Event::assertDispatched(fn (RequestCreated $e) => $e->request->is($request));

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::New->value,
    ]);
});

it('creates request with all details', function () {
    Event::fake(RequestCreated::class);

    /** @var User $author */
    $author = User::create();

    $action = new CreateRequest;

    $request = $action->execute(new CreateRequestDto(
        status: Status::Approved,
        author: $author,
        type: 'Claim',
        title: 'Its mine!',
        description: 'This is mine and only mine.',
        meta: collect([
            'ip' => '127.0.0.1',
        ]),
        requireApprovalsFrom: collect([1]),
    ));

    Event::assertDispatched(fn (RequestCreated $e) => $e->request->is($request));

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Approved->value,
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->id,
        'type' => 'Claim',
        'title' => 'Its mine!',
        'description' => 'This is mine and only mine.',
        'meta' => '{"ip":"127.0.0.1"}',
        'require_approvals_from' => '[1]',
    ]);
});
