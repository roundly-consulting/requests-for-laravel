<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Tests\Fixtures\CustomRequest;
use RoundlyConsulting\Requests\Tests\SeniorUser;
use RoundlyConsulting\Requests\Tests\User;

/**
 * S — the model-swap proof for `requests.model`, driven through the flows a host calls.
 *
 * This replaces `tests/Unit/Actions/CreateRequestModelOverrideTest.php`, which set
 * `config('requests.model')` **in the test body** — a window no host occupies, since
 * `config/requests.php` is read at boot. That shape is how reviews shipped a swap test
 * structurally incapable of catching the bug it was named for. Here the key is set before
 * the providers boot, via SwappedRequestTestCase, which this directory is bound to (Pest
 * binds a test case per directory, not per file).
 *
 * It also adds what the deleted test structurally could not: `CountsCreations` proves each
 * row was created **as** the host class. `instanceof` passes for a row created as the
 * packaged Request and re-hydrated — which would fire none of the host's model events
 * (permissions #31).
 */
it('honours a host request model through the creation flows', function (): void {
    expect('requests.model')->toHonourModelSwap(CustomRequest::class, function (): array {
        $author = User::query()->create();

        // The two write paths a host reaches: the fluent builder and the action it
        // delegates to. Both must mint the host's class, not merely return something
        // that passes `instanceof`.
        $viaBuilder = Requests::make()->title('Budget')->author($author)->create();
        $viaAction = Requests::create(new CreateRequestDto(title: 'Laptop', author: $author));

        return [
            $viaBuilder,
            $viaAction,
            // Reads hydrate through the seam too, not just the writes: `requests()` is a
            // morphMany onto RequestModel::class(), so a bypass here would return the
            // packaged class for rows the host's class wrote.
            ...$author->requests()->get()->all(),
        ];
    });
});

/**
 * The resolution flow is the seam most worth proving separately: it is the package writing
 * on the host's behalf, and it lands through the boot-time `ApprovalRequestResolved`
 * listener rather than a direct call. A swap honoured on create but bypassed on resolve
 * would write status through a different class than the one that opened the request.
 */
it('honours the host model when an approval resolves the request', function (): void {
    $author = User::query()->create();
    $approver = SeniorUser::query()->create();

    $request = Requests::make()
        ->title('Budget')
        ->author($author)
        ->requireApprovalsFrom(collect([$approver->getKey()]))
        ->create();

    Requests::approve($request, $approver);

    expect($request->refresh())->toBeInstanceOf(CustomRequest::class)
        ->and($request->refresh()->status)->toBe(Status::Approved)
        ->and($author->requests()->first())->toBeInstanceOf(CustomRequest::class);
});

// The structural half of the seam — `Request` is non-final, and `requests.model` really
// defaults to the packaged model — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.
