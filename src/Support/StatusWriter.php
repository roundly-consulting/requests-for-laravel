<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Events\RequestCancelled;
use RoundlyConsulting\Requests\Events\RequestExpired;
use RoundlyConsulting\Requests\Events\RequestRejected;
use RoundlyConsulting\Requests\Events\RequestStatusChanged;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Exceptions\RequestAlreadyResolved;
use RoundlyConsulting\Requests\Models\Request;

/**
 * The one way the package writes a request's status. The row is re-read with
 * `lockForUpdate()` inside a transaction, and every check — no move, closed, the enforced
 * lifecycle graph — runs on the status it holds then, never on the caller's copy, which a
 * concurrent decision, cancel or sweep may have overtaken. The caller announces the move
 * after the transaction, and only when the status really changed.
 *
 * @internal
 */
final readonly class StatusWriter
{
    public function __construct(private StatusGuard $guard = new StatusGuard) {}

    /**
     * Run `$work` in a transaction that holds the lock on the request's row. The in-memory
     * status and expiry are brought up to date from the locked row first.
     *
     * @template TResult
     *
     * @param  Closure(Status): TResult  $work  given the locked status
     * @return TResult
     *
     * @throws ModelNotFoundException when the request's row is gone
     */
    public function locked(Request $request, Closure $work): mixed
    {
        return $request->getConnection()->transaction(fn (): mixed => $work($this->lock($request)));
    }

    /**
     * Move the request to `$to` under the lock. Holding `$to` already is no move; a move the
     * request is closed to, or one the enforced graph forbids, throws (RequestAlreadyResolved,
     * InvalidStatusTransition — see {@see permits()}) when `$strict` and is skipped otherwise.
     *
     * @param  (Closure(Request): bool)|null  $when  checked on the locked request first; false skips the move
     * @param  (Closure(): mixed)|null  $alongside  more writes for the same unit, after the status
     * @return bool whether the status changed
     */
    public function move(
        Request $request,
        Status $to,
        bool $strict = true,
        ?Closure $when = null,
        ?Closure $alongside = null,
    ): bool {
        return $this->locked($request, function (Status $from) use ($request, $to, $strict, $when, $alongside): bool {
            if ($when !== null && ! $when($request)) {
                return false;
            }

            if (! $this->permits($from, $to, $strict)) {
                return false;
            }

            $this->write($request, $to);

            if ($alongside !== null) {
                $alongside();
            }

            return true;
        });
    }

    /**
     * Whether a request in `$from` is to move to `$to`: not when it holds `$to` already, nor
     * when it is closed to the move or the enforced graph forbids it — each of which throws
     * instead when `$strict`.
     *
     * @throws RequestAlreadyResolved
     * @throws InvalidStatusTransition
     */
    public function permits(Status $from, Status $to, bool $strict = true): bool
    {
        if ($from === $to) {
            return false;
        }

        if ($this->guard->closes($from, $to)) {
            return $strict ? throw RequestAlreadyResolved::inStatus($from) : false;
        }

        if (Config::boolean('requests.enforce_transitions') && ! $this->guard->allows($from, $to)) {
            return $strict ? throw InvalidStatusTransition::between($from, $to) : false;
        }

        return true;
    }

    /**
     * Write the status. Call it inside {@see locked()}, once {@see permits()} allowed it.
     */
    public function write(Request $request, Status $to): void
    {
        $request->update(['status' => $to]);
    }

    /**
     * Announce the status the request just moved to: RequestStatusChanged, then that
     * status's own event — RequestRejected (when the rejecting actor is known),
     * RequestExpired or RequestCancelled.
     */
    public function announce(Request $request, ?Model $rejectedBy = null): void
    {
        event(new RequestStatusChanged($request));

        match ($request->status) {
            Status::Rejected => $rejectedBy instanceof Model ? event(new RequestRejected($request, $rejectedBy)) : null,
            Status::Expired => event(new RequestExpired($request)),
            Status::Cancelled => event(new RequestCancelled($request)),
            Status::New, Status::Approved => null,
        };
    }

    /**
     * Lock the request's row and bring the in-memory status and expiry up to date from it.
     * Read through the base query, so the lock fires no model events of its own.
     *
     * @throws ModelNotFoundException
     */
    private function lock(Request $request): Status
    {
        $row = $request->newQueryWithoutScopes()
            ->whereKey($request->getKey())
            ->lockForUpdate()
            ->toBase()
            ->first(['status', 'expires_at']);

        if ($row === null) {
            throw (new ModelNotFoundException)->setModel($request::class, [$request->getKey()]);
        }

        $stored = $row->status ?? null;
        $status = Status::from(is_string($stored) ? $stored : '');

        $request->setRawAttributes(
            ['status' => $status->value, 'expires_at' => $row->expires_at ?? null] + $request->getAttributes(),
        );
        $request->syncOriginalAttributes(['status', 'expires_at']);

        return $status;
    }
}
