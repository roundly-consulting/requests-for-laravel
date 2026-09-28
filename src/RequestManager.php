<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Requests\Actions\CancelRequest;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\Actions\ExpireDueRequests;
use RoundlyConsulting\Requests\Actions\ExpireRequest;
use RoundlyConsulting\Requests\Actions\ResolveRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\StatusGuard;

/**
 * The requests API: the root behind the {@see Facades\Requests} facade, and the
 * class to inject when you prefer dependency injection. Every method resolves its
 * action from the container, so host overrides apply.
 *
 * Not final on purpose: {@see Testing\RequestsFake} extends it so a constructor-
 * injected manager receives the fake under `Requests::fake()`.
 */
class RequestManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Start a fluent request; `create()` on the builder goes through {@see create()}.
     */
    public function make(): RequestBuilder
    {
        return new RequestBuilder($this);
    }

    public function create(CreateRequestDto $dto): Request
    {
        return $this->container->make(CreateRequest::class)->execute($dto);
    }

    public function approve(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        return $this->container->make(ResolveRequest::class)->execute($request, $actor, Status::Approved, $reason);
    }

    public function reject(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        return $this->container->make(ResolveRequest::class)->execute($request, $actor, Status::Rejected, $reason);
    }

    public function reopen(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        return $this->container->make(ResolveRequest::class)->execute($request, $actor, Status::New, $reason);
    }

    public function cancel(Request $request): Request
    {
        return $this->container->make(CancelRequest::class)->execute($request);
    }

    public function expire(Request $request): Request
    {
        return $this->container->make(ExpireRequest::class)->execute($request);
    }

    /**
     * Expire every open request whose deadline has passed, `$chunk` rows at a time.
     * A dry run only counts them. Pending approval decisions lapse separately,
     * through `Approvals::expire()`.
     *
     * @return int the number of requests expired (or, on a dry run, due)
     */
    public function expireDue(bool $dryRun = false, int $chunk = 500): int
    {
        return $this->container->make(ExpireDueRequests::class)->execute($dryRun, $chunk);
    }

    /**
     * Whether the lifecycle graph allows the request to move to `$to` — the rule
     * `requests.enforce_transitions` enforces. Staying on the same status is allowed.
     */
    public function canTransition(Request $request, Status $to): bool
    {
        return $this->container->make(StatusGuard::class)->allows($request->status, $to);
    }
}
