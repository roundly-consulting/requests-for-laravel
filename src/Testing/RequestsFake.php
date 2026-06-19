<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Requests\Contracts\CreatesRequests;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\RequestBuilder;

/**
 * Recording test double for the Requests manager. Swap it in with
 * Requests::fake() to capture calls and assert on them without touching the
 * database.
 */
final class RequestsFake implements CreatesRequests
{
    /** @var list<CreateRequestDto> */
    private array $created = [];

    /** @var list<array{request: Request, actor: Model}> */
    private array $approved = [];

    /** @var list<array{request: Request, actor: Model}> */
    private array $rejected = [];

    /** @var list<array{request: Request, actor: Model}> */
    private array $reopened = [];

    /** @var list<Request> */
    private array $cancelled = [];

    /** @var list<Request> */
    private array $expired = [];

    public function make(): RequestBuilder
    {
        return new RequestBuilder($this);
    }

    public function execute(CreateRequestDto $dto): Request
    {
        return $this->create($dto);
    }

    public function create(CreateRequestDto $dto): Request
    {
        $this->created[] = $dto;

        $model = new Request;
        $model->forceFill([
            'status' => $dto->status,
            'type' => $dto->type,
            'title' => $dto->title,
            'description' => $dto->description,
            'expires_at' => $dto->expiresAt,
        ]);

        return $model;
    }

    public function approve(Request $request, Model $actor): Request
    {
        $this->approved[] = ['request' => $request, 'actor' => $actor];

        return $request;
    }

    public function reject(Request $request, Model $actor): Request
    {
        $this->rejected[] = ['request' => $request, 'actor' => $actor];

        return $request;
    }

    public function reopen(Request $request, Model $actor): Request
    {
        $this->reopened[] = ['request' => $request, 'actor' => $actor];

        return $request;
    }

    public function cancel(Request $request): Request
    {
        $this->cancelled[] = $request;

        return $request;
    }

    public function expire(Request $request): Request
    {
        $this->expired[] = $request;

        return $request;
    }

    public function assertCreated(?int $times = null): void
    {
        if ($times === null) {
            Assert::assertNotEmpty($this->created, 'Expected a request to be created, but none were.');

            return;
        }

        Assert::assertCount($times, $this->created, "Expected {$times} created request(s).");
    }

    public function assertNothingCreated(): void
    {
        Assert::assertSame([], $this->created, 'Expected no requests to be created.');
    }

    public function assertApproved(Request $request, Model $actor): void
    {
        Assert::assertTrue(
            $this->wasResolved($this->approved, $request, $actor),
            'Expected the request to be approved by the given actor.',
        );
    }

    public function assertRejected(Request $request, Model $actor): void
    {
        Assert::assertTrue(
            $this->wasResolved($this->rejected, $request, $actor),
            'Expected the request to be rejected by the given actor.',
        );
    }

    public function assertReopened(Request $request, Model $actor): void
    {
        Assert::assertTrue(
            $this->wasResolved($this->reopened, $request, $actor),
            'Expected the request to be reopened by the given actor.',
        );
    }

    public function assertCancelled(Request $request): void
    {
        Assert::assertTrue(
            $this->wasActioned($this->cancelled, $request),
            'Expected the request to be cancelled.',
        );
    }

    public function assertExpired(Request $request): void
    {
        Assert::assertTrue(
            $this->wasActioned($this->expired, $request),
            'Expected the request to be expired.',
        );
    }

    /**
     * @param  list<array{request: Request, actor: Model}>  $records
     */
    private function wasResolved(array $records, Request $request, Model $actor): bool
    {
        foreach ($records as $record) {
            if ($record['request']->is($request) && $record['actor']->is($actor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Request>  $records
     */
    private function wasActioned(array $records, Request $request): bool
    {
        foreach ($records as $record) {
            if ($record->is($request)) {
                return true;
            }
        }

        return false;
    }
}
