<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Exceptions\InvalidApprovalRequestException;
use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\Approvals\Exceptions\UnknownWorkflowException;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Tests\User;

/**
 * The declared approvers are the only ones who may decide. `require_approvals_from` used to
 * be a head-count: the approval round was built by hand with `required_approvers` only, so
 * any two outsiders could clear a unanimous [alice, bob] request.
 */
it('refuses an approval from an actor who is not a declared approver', function (): void {
    $alice = User::create();
    $bob = User::create();
    $mallory = User::create();
    $trudy = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();

    expect(fn () => Requests::approve($request, $mallory))->toThrow(UnauthorizedApprovalException::class)
        ->and(fn () => Requests::approve($request, $trudy))->toThrow(UnauthorizedApprovalException::class)
        ->and(fn () => Requests::reject($request, $mallory))->toThrow(UnauthorizedApprovalException::class)
        ->and($request->fresh()?->status)->toBe(Status::New)
        ->and($mallory->hasApproved($request))->toBeFalse()
        ->and($request->approvalProgress()?->approved)->toBe(0);
});

it('refuses an outsider deciding straight through the approvals engine', function (): void {
    $alice = User::create();
    $mallory = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice])->rule(ApprovalRule::Any)->create();

    expect(fn () => $mallory->approve($request))->toThrow(UnauthorizedApprovalException::class)
        ->and($request->fresh()?->status)->toBe(Status::New);
});

it('still resolves once every declared approver has approved', function (): void {
    $alice = User::create();
    $bob = User::create();

    $request = Requests::make()->requireApprovalsFrom([$alice, $bob])->create();

    Requests::approve($request, $alice);
    Requests::approve($request, $bob);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('names the approvers passed to the raw action', function (): void {
    $alice = User::create();
    $mallory = User::create();

    $request = app(CreateRequest::class)->execute(new CreateRequestDto(approvers: [$alice]));

    expect(fn () => Requests::approve($request, $mallory))->toThrow(UnauthorizedApprovalException::class);

    Requests::approve($request, $alice);

    expect($request->fresh()?->status)->toBe(Status::Approved)
        ->and($request->require_approvals_from?->all())->toBe([$alice->id]);
});

it('lets the delegate of a declared approver decide for them', function (): void {
    $alice = User::create();
    $bob = User::create();

    Approvals::delegations($alice)->to($bob)->until(now()->addWeek())->grant();

    $request = Requests::make()->requireApprovalsFrom([$alice])->create();

    Requests::approve($request, $bob);

    expect($request->fresh()?->status)->toBe(Status::Approved)
        ->and($alice->hasApproved($request))->toBeTrue();
});

it('refuses an outsider on a staged pipeline', function (): void {
    $manager = User::create();
    $finance = User::create();
    $mallory = User::create();

    $request = Requests::make()->stages([
        new StageDefinition([$manager], ApprovalRule::Unanimous, name: 'manager'),
        new StageDefinition([$finance], ApprovalRule::Unanimous, name: 'finance'),
    ])->create();

    expect(fn () => Requests::approve($request, $mallory))->toThrow(UnauthorizedApprovalException::class)
        ->and($request->currentStage()?->name)->toBe('manager');
});

it('refuses an outsider on a workflow preset', function (): void {
    config()->set('approvals.workflows', [
        'payout' => ['rule' => 'quorum', 'quorum' => 2, 'required_approvers' => 3],
    ]);

    $a = User::create();
    $b = User::create();
    $c = User::create();
    $mallory = User::create();

    $request = Requests::make()->workflow('payout')->requireApprovalsFrom([$a, $b, $c])->create();

    expect(fn () => Requests::approve($request, $mallory))->toThrow(UnauthorizedApprovalException::class);

    Requests::approve($request, $a);
    Requests::approve($request, $b);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('leaves no request behind when its approval round cannot open', function (): void {
    expect(fn () => Requests::make()->title('Unsaved approver')->requireApprovalsFrom([new User])->create())
        ->toThrow(InvalidApprovalRequestException::class)
        ->and(fn () => Requests::make()->title('Unknown preset')->workflow('missing')->create())
        ->toThrow(UnknownWorkflowException::class);

    // A surviving row would have no approval round, so anyone could resolve it alone.
    expect(Request::query()->count())->toBe(0)
        ->and(ApprovalRequest::query()->count())->toBe(0);
});

it('refuses flat approvers mixed with stages instead of dropping them', function (): void {
    $alice = User::create();
    $manager = User::create();

    expect(fn () => Requests::make()
        ->requireApprovalsFrom([$alice])
        ->stages([new StageDefinition([$manager], ApprovalRule::Unanimous, name: 'manager')])
        ->create())->toThrow(InvalidApprovalRequestException::class);

    expect(Request::query()->count())->toBe(0);
});
