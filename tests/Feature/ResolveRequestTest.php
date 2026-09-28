<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\Actions\ResolveRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\ApprovalRecorded;
use RoundlyConsulting\Requests\Events\ApprovalRevoked;
use RoundlyConsulting\Requests\Events\RequestRejected;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Tests\User;

it('approves immediately when no approvers are required', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto);
    $user = User::create();

    Event::fake(RequestStatusChanged::class);

    (new ResolveRequest)->execute($request, $user, Status::Approved);

    Event::assertDispatched(
        fn (RequestStatusChanged $e) => $e->request->is($request) && $request->status === Status::Approved,
    );

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Approved->value,
    ]);
});

it('holds at new until every required approver has approved', function () {
    $user = User::create();
    $anotherUser = User::create();

    $request = (new CreateRequest)->execute(new CreateRequestDto(
        approvers: [$user, $anotherUser],
    ));

    Event::fake(RequestStatusChanged::class);

    (new ResolveRequest)->execute($request, $user, Status::Approved);

    Event::assertNotDispatched(RequestStatusChanged::class);

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::New->value,
    ]);

    expect($user->hasApproved($request))->toBeTrue()
        ->and($anotherUser->hasApproved($request))->toBeFalse();
});

it('approves once every required approver has approved', function () {
    $user = User::create();
    $anotherUser = User::create();

    $request = (new CreateRequest)->execute(new CreateRequestDto(
        approvers: [$user, $anotherUser],
    ));

    Event::fake(RequestStatusChanged::class);

    (new ResolveRequest)->execute($request, $user, Status::Approved);
    Event::assertNotDispatched(RequestStatusChanged::class);

    (new ResolveRequest)->execute($request, $anotherUser, Status::Approved);

    Event::assertDispatched(
        fn (RequestStatusChanged $e) => $e->request->is($request) && $e->request->status === Status::Approved,
    );

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => Status::Approved->value,
    ]);
});

it('rejects a unanimous request on the first rejection', function () {
    $user = User::create();
    $anotherUser = User::create();

    $request = (new CreateRequest)->execute(new CreateRequestDto(
        approvers: [$user, $anotherUser],
    ));

    (new ResolveRequest)->execute($request, $user, Status::Rejected, reason: 'Out of policy');

    expect($request->fresh()?->status)->toBe(Status::Rejected)
        ->and($user->hasRejected($request))->toBeTrue();

    $this->assertDatabaseHas('approvals', [
        'status' => ApprovalStatus::Rejected->value,
        'reason' => 'Out of policy',
    ]);
});

it('records a per-decision reason on approval', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto);
    $user = User::create();

    (new ResolveRequest)->execute($request, $user, Status::Approved, reason: 'Looks good');

    $this->assertDatabaseHas('approvals', [
        'status' => ApprovalStatus::Approved->value,
        'reason' => 'Looks good',
    ]);
});

it('reopens a request and revokes the approval', function () {
    $request = (new CreateRequest)->execute(new CreateRequestDto(status: Status::Approved));
    $user = User::create();
    $user->approve($request);

    (new ResolveRequest)->execute($request, $user, Status::New);

    expect($request->fresh()?->status)->toBe(Status::New)
        ->and($user->hasApproved($request))->toBeFalse();
});

it('fires granular approval, revoke and rejection events with the acting actor', function () {
    Event::fake([ApprovalRecorded::class, ApprovalRevoked::class, RequestRejected::class]);

    $request = (new CreateRequest)->execute(new CreateRequestDto);
    $user = User::create();

    $action = new ResolveRequest;

    $action->execute($request, $user, Status::Approved);
    Event::assertDispatched(fn (ApprovalRecorded $e) => $e->request->is($request) && $e->actor->is($user));

    $action->execute($request, $user, Status::Rejected);
    Event::assertDispatched(fn (RequestRejected $e) => $e->request->is($request) && $e->actor->is($user));

    $action->execute($request, $user, Status::New);
    Event::assertDispatched(fn (ApprovalRevoked $e) => $e->request->is($request) && $e->actor->is($user));
});
