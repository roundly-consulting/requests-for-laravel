<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\Actions\ResolveRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Tests\User;

it('approves request when no required approvals from are defined', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto);

    /** @var User $user */
    $user = User::create();

    $action = new ResolveRequest;

    Event::fake(RequestStatusChanged::class);

    $action->execute($request, $user, Status::Approved);

    Event::assertDispatched(
        fn (RequestStatusChanged $e) => $e->request->is($request) && $request->status === Status::Approved,
    );

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Approved->value,
    ]);
});

it('does not approve request when no only one user approved but two are required', function () {
    /** @var User $user */
    $user = User::create();

    /** @var User $anotherUser */
    $anotherUser = User::create();

    $request = (new CreateRequest)->execute(new CreateRequestDto(
        requireApprovalsFrom: collect([
            $user->id,
            $anotherUser->id,
        ])
    ));

    $action = new ResolveRequest;

    Event::fake(RequestStatusChanged::class);

    $action->execute($request, $user, Status::Approved);

    Event::assertNotDispatched(RequestStatusChanged::class);

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::New->value,
    ]);

    expect($request->hasBeenApprovedBy($user))->toBeTrue()
        ->and($request->hasBeenApprovedBy($anotherUser))->toBeFalse();
});

it('rejects request and removes previous approval', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto(
        status: Status::Approved,
    ));

    /** @var User $user */
    $user = User::create();

    $user->toggleApproval($request);

    $action = new ResolveRequest;

    Event::fake(RequestStatusChanged::class);

    $action->execute($request, $user, Status::Rejected);

    Event::assertDispatched(
        fn (RequestStatusChanged $e) => $e->request->is($request) && $request->status === Status::Rejected,
    );

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Rejected->value,
    ]);

    expect($request->hasBeenApprovedBy($user))->toBeFalse();
});

it('approves request once every required approver has approved', function () {
    $user = User::create();
    $anotherUser = User::create();

    $request = (new CreateRequest)->execute(new CreateRequestDto(
        requireApprovalsFrom: collect([$user->id, $anotherUser->id]),
    ));

    $action = new ResolveRequest;

    Event::fake(RequestStatusChanged::class);

    $action->execute($request, $user, Status::Approved);
    Event::assertNotDispatched(RequestStatusChanged::class);

    $action->execute($request, $anotherUser, Status::Approved);

    Event::assertDispatched(
        fn (RequestStatusChanged $e) => $e->request->is($request) && $request->status === Status::Approved,
    );

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Approved->value,
    ]);
});

it('resets request to new and revokes the approval', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto(
        status: Status::Approved,
    ));

    $user = User::create();
    $user->toggleApproval($request);

    $action = new ResolveRequest;

    $action->execute($request, $user, Status::New);

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::New->value,
    ]);

    expect($request->hasBeenApprovedBy($user))->toBeFalse();
});
