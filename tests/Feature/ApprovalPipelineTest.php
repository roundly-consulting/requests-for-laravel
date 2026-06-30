<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Tests\User;

it('clears a sequential pipeline stage by stage', function (): void {
    $manager = User::create();
    $finance = User::create();
    $director = User::create();

    $request = Requests::make()
        ->title('Payout')
        ->stages([
            new StageDefinition([$manager], ApprovalRule::Unanimous, name: 'manager'),
            new StageDefinition([$finance], ApprovalRule::Unanimous, name: 'finance'),
            new StageDefinition([$director], ApprovalRule::Unanimous, name: 'director'),
        ])
        ->create();

    expect($request->currentStage()?->name)->toBe('manager');

    Requests::approve($request, $manager);
    expect($request->fresh()?->status)->toBe(Status::New)
        ->and($request->currentStage()?->name)->toBe('finance');

    Requests::approve($request, $finance);
    expect($request->currentStage()?->name)->toBe('director');

    Requests::approve($request, $director);
    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('rejects the whole pipeline when a stage is rejected', function (): void {
    $manager = User::create();
    $finance = User::create();

    $request = Requests::make()
        ->stages([
            new StageDefinition([$manager], name: 'manager'),
            new StageDefinition([$finance], name: 'finance'),
        ])
        ->create();

    Requests::reject($request, $manager, reason: 'No budget');

    expect($request->fresh()?->status)->toBe(Status::Rejected);
});

it('continues past a rejected stage when configured not to reject the request', function (): void {
    $manager = User::create();
    $finance = User::create();

    $request = Requests::make()
        ->rejectOnStageRejection(false)
        ->stages([
            new StageDefinition([$manager], name: 'manager'),
            new StageDefinition([$finance], name: 'finance'),
        ])
        ->create();

    // The first stage rejects, but the pipeline carries on to the next stage.
    Requests::reject($request, $manager, reason: 'Skip me');

    expect($request->fresh()?->status)->toBe(Status::New)
        ->and($request->currentStage()?->name)->toBe('finance');

    Requests::approve($request, $finance);

    expect($request->fresh()?->status)->toBe(Status::Approved);
});
