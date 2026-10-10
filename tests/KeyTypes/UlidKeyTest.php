<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\UlidUser;

/**
 * C-1, the ulid leg. The requests id was always a bigint while the approvals engine keys
 * every morph column — the round's `subject`, a decision's `approvable` — on the one
 * `approvals.key_type`. With ulid approvers that key type has to be ulid, and on
 * Postgres the bigint request id could then never be stored in those columns:
 * `invalid input syntax`, on create with approvers and on any decision.
 * `requests.primary_key_type` now keys the requests themselves.
 */
it('keys requests with ulids and decides them through the approval round', function (): void {
    $author = UlidUser::query()->create();
    $alice = UlidUser::query()->create();
    $bob = UlidUser::query()->create();

    $request = Requests::make()->author($author)->requireApprovalsFrom([$alice, $bob])->create();

    expect(Str::isUlid($request->getKey()))->toBeTrue()
        ->and($request->getKeyType())->toBe('string')
        ->and($request->getIncrementing())->toBeFalse()
        ->and($request->approvalRequests()->firstOrFail()->subject_id)->toBe($request->getKey());

    Requests::approve($request, $alice);
    Requests::approve($request, $bob);

    expect($request->fresh()?->status)->toBe(Status::Approved)
        ->and($author->requests()->sole()->is($request))->toBeTrue();
});

it('decides a ulid-keyed request without approvers', function (): void {
    $alice = UlidUser::query()->create();

    $request = Requests::make()->create();

    Requests::approve($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::Approved)
        ->and($alice->hasApproved($request))->toBeTrue();
});

it('reopens, cancels and sweeps ulid-keyed requests', function (): void {
    $alice = UlidUser::query()->create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();
    Requests::reject($request, $alice);
    Requests::reopen($request, $alice);
    Requests::cancel($request);

    $due = Request::factory()->count(3)->expired()->create();

    expect($request->fresh()?->status)->toBe(Status::Cancelled)
        ->and($request->approvalRequests()->latest('id')->firstOrFail()->status)->toBe(ApprovalStatus::Cancelled)
        ->and(Requests::expireDue(chunk: 1))->toBe(3)
        ->and($due->every(fn (Request $r): bool => $r->fresh()?->status === Status::Expired))->toBeTrue();
});

it('emits a string requests id column', function (): void {
    expect(Schema::getColumnType('requests', 'id'))
        ->toBeIn(['varchar', 'string', 'uuid', 'char', 'bpchar'])
        ->not->toBeIn(['integer', 'bigint', 'int8']);
});

it('binds routes by ulid only', function (): void {
    $request = Request::factory()->create();

    expect((new Request)->resolveRouteBinding($request->getKey())?->is($request))->toBeTrue()
        ->and(fn () => (new Request)->resolveRouteBinding('0192e6a0-0000-7000-8000-000000000000'))->toThrow(ModelNotFoundException::class);
});
