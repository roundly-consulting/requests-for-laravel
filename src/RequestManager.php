<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Requests\Actions\CancelRequest;
use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\Actions\ExpireRequest;
use RoundlyConsulting\Requests\Actions\ResolveRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Container-bound entry point. Thin sugar over the package actions, exposed via
 * the Requests facade.
 */
class RequestManager
{
    public function __construct(
        private readonly CreateRequest $create,
        private readonly ResolveRequest $resolve,
        private readonly CancelRequest $cancel,
        private readonly ExpireRequest $expire,
    ) {}

    public function make(): RequestBuilder
    {
        return new RequestBuilder($this->create);
    }

    public function create(CreateRequestDto $dto): Request
    {
        return $this->create->execute($dto);
    }

    public function approve(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        return $this->resolve->execute($request, $actor, Status::Approved, $reason);
    }

    public function reject(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        return $this->resolve->execute($request, $actor, Status::Rejected, $reason);
    }

    public function reopen(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        return $this->resolve->execute($request, $actor, Status::New, $reason);
    }

    public function cancel(Request $request): Request
    {
        return $this->cancel->execute($request);
    }

    public function expire(Request $request): Request
    {
        return $this->expire->execute($request);
    }
}
