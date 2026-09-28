<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\RequestManager;

/**
 * Recording test double for the requests manager. `Requests::fake()` swaps it in
 * behind the facade and the container, so the facade, the fluent builder and any
 * constructor-injected RequestManager all land here. Mutating calls are recorded
 * and never touch the database; `canTransition()` still answers for real.
 */
final class RequestsFake extends RequestManager
{
    /** @var list<CreateRequestDto> */
    private array $created = [];

    /** @var list<RecordedDecision> */
    private array $approved = [];

    /** @var list<RecordedDecision> */
    private array $rejected = [];

    /** @var list<RecordedDecision> */
    private array $reopened = [];

    /** @var list<Request> */
    private array $cancelled = [];

    /** @var list<Request> */
    private array $expired = [];

    /** @var list<bool> the dry-run flag of each expireDue() call */
    private array $expiredDue = [];

    /**
     * Records the request and returns an unsaved model built from the DTO.
     */
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

    public function approve(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        $this->approved[] = new RecordedDecision($request, $actor, $reason);

        return $request;
    }

    public function reject(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        $this->rejected[] = new RecordedDecision($request, $actor, $reason);

        return $request;
    }

    public function reopen(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null): Request
    {
        $this->reopened[] = new RecordedDecision($request, $actor, $reason);

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

    /**
     * Records the sweep and returns how many requests are due, without expiring any.
     */
    public function expireDue(bool $dryRun = false, int $chunk = 500): int
    {
        $this->expiredDue[] = $dryRun;

        return parent::expireDue(true, $chunk);
    }

    /**
     * @param  int|null  $times  the exact number of requests created, when given
     */
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

    /**
     * @param  Model|null  $actor  when given, the actor must match
     * @param  string|null  $reason  when given, the recorded reason must match
     */
    public function assertApproved(Request $request, ?Model $actor = null, ?string $reason = null): void
    {
        Assert::assertTrue(
            $this->decided($this->approved, $request, $actor, $reason),
            'Expected the request to be approved'.$this->describe($actor, $reason).'.',
        );
    }

    public function assertNothingApproved(): void
    {
        Assert::assertSame([], $this->approved, 'Expected no request to be approved.');
    }

    public function assertRejected(Request $request, ?Model $actor = null, ?string $reason = null): void
    {
        Assert::assertTrue(
            $this->decided($this->rejected, $request, $actor, $reason),
            'Expected the request to be rejected'.$this->describe($actor, $reason).'.',
        );
    }

    public function assertNothingRejected(): void
    {
        Assert::assertSame([], $this->rejected, 'Expected no request to be rejected.');
    }

    public function assertReopened(Request $request, ?Model $actor = null, ?string $reason = null): void
    {
        Assert::assertTrue(
            $this->decided($this->reopened, $request, $actor, $reason),
            'Expected the request to be reopened'.$this->describe($actor, $reason).'.',
        );
    }

    public function assertNothingReopened(): void
    {
        Assert::assertSame([], $this->reopened, 'Expected no request to be reopened.');
    }

    public function assertCancelled(Request $request): void
    {
        Assert::assertTrue(
            $this->contains($this->cancelled, $request),
            'Expected the request to be cancelled.',
        );
    }

    public function assertNothingCancelled(): void
    {
        Assert::assertSame([], $this->cancelled, 'Expected no request to be cancelled.');
    }

    public function assertExpired(Request $request): void
    {
        Assert::assertTrue(
            $this->contains($this->expired, $request),
            'Expected the request to be expired.',
        );
    }

    public function assertNothingExpired(): void
    {
        Assert::assertSame([], $this->expired, 'Expected no request to be expired.');
    }

    /**
     * @param  bool|null  $dryRun  when given, a sweep with that dry-run flag must have run
     */
    public function assertExpiredDue(?bool $dryRun = null): void
    {
        $matching = $dryRun === null
            ? $this->expiredDue
            : array_filter($this->expiredDue, static fn (bool $recorded): bool => $recorded === $dryRun);

        Assert::assertNotEmpty($matching, match ($dryRun) {
            null => 'Expected due requests to be expired, but expireDue() was not called.',
            true => 'Expected a dry-run expireDue() call, but none was made.',
            false => 'Expected a live expireDue() call, but none was made.',
        });
    }

    public function assertNothingExpiredDue(): void
    {
        Assert::assertSame([], $this->expiredDue, 'Expected expireDue() not to be called.');
    }

    /**
     * @param  list<RecordedDecision>  $records
     */
    private function decided(array $records, Request $request, ?Model $actor, ?string $reason): bool
    {
        foreach ($records as $record) {
            if ($record->matches($request, $actor, $reason)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Request>  $records
     */
    private function contains(array $records, Request $request): bool
    {
        foreach ($records as $record) {
            if ($record->is($request)) {
                return true;
            }
        }

        return false;
    }

    private function describe(?Model $actor, ?string $reason): string
    {
        return ($actor === null ? '' : ' by the given actor')
            .($reason === null ? '' : " with reason [{$reason}]");
    }
}
