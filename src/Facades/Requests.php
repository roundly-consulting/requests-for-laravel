<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\RequestBuilder;
use RoundlyConsulting\Requests\RequestManager;
use RoundlyConsulting\Requests\Testing\RequestsFake;

/**
 * @method static RequestBuilder make()
 * @method static Request create(CreateRequestDto $dto)
 * @method static Request approve(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null)
 * @method static Request reject(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null)
 * @method static Request reopen(Request $request, Model&GivesApprovalsInterface $actor, ?string $reason = null)
 * @method static Request cancel(Request $request)
 * @method static Request expire(Request $request)
 * @method static int expireDue(bool $dryRun = false, int $chunk = 500)
 * @method static bool canTransition(Request $request, Status $to)
 * @method static RequestsFake fake()
 * @method static void assertCreated(?int $times = null)
 * @method static void assertNothingCreated()
 * @method static void assertApproved(Request $request, ?Model $actor = null, ?string $reason = null)
 * @method static void assertNothingApproved()
 * @method static void assertRejected(Request $request, ?Model $actor = null, ?string $reason = null)
 * @method static void assertNothingRejected()
 * @method static void assertReopened(Request $request, ?Model $actor = null, ?string $reason = null)
 * @method static void assertNothingReopened()
 * @method static void assertCancelled(Request $request)
 * @method static void assertNothingCancelled()
 * @method static void assertExpired(Request $request)
 * @method static void assertNothingExpired()
 * @method static void assertExpiredDue(?bool $dryRun = null)
 * @method static void assertNothingExpiredDue()
 *
 * @see RequestManager
 * @see RequestsFake
 */
final class Requests extends Facade
{
    /**
     * Swap in a recording fake behind the facade and the container. Mutating calls
     * are recorded instead of run; assert on them with the fake's `assert*()`.
     */
    public static function fake(): RequestsFake
    {
        $fake = app(RequestsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return RequestManager::class;
    }
}
