<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Approvals\Interfaces\RequiresApprovalInterface;
use RoundlyConsulting\Approvals\Traits\RequiresApproval;
use RoundlyConsulting\Requests\Database\Factories\RequestFactory;
use RoundlyConsulting\Requests\Enums\Status;

/**
 * @property int $id
 * @property Status $status
 * @property string|null $author_type
 * @property int|string|null $author_id
 * @property Model|null $author
 * @property string|null $type
 * @property string|null $title
 * @property string|null $description
 * @property Collection<array-key, mixed>|null $meta
 * @property Collection<array-key, mixed>|null $require_approvals_from
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * @method static Builder<static> withStatus(Status $status)
 * @method static Builder<static> pending()
 * @method static Builder<static> approved()
 * @method static Builder<static> rejected()
 * @method static Builder<static> cancelled()
 * @method static Builder<static> expired()
 * @method static Builder<static> expiringBefore(CarbonInterface $moment)
 * @method static Builder<static> authoredBy(Model $author)
 */
class Request extends Model implements RequiresApprovalInterface
{
    /** @use HasFactory<RequestFactory> */
    use HasFactory;

    /** Drives the request approval flow through the approvals-for-laravel engine. */
    use RequiresApproval;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * The column defaults to New too, but only a refresh would bring that into memory: a
     * request made with a raw `create()` must be New right away.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => Status::New->value,
    ];

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether the request is open and past its expiry deadline.
     */
    public function isExpired(): bool
    {
        return $this->status->isOpen()
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /** @param  Builder<static>  $query */
    public function scopeWithStatus(Builder $query, Status $status): void
    {
        $query->where('status', $status);
    }

    /** @param  Builder<static>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', Status::New);
    }

    /** @param  Builder<static>  $query */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', Status::Approved);
    }

    /** @param  Builder<static>  $query */
    public function scopeRejected(Builder $query): void
    {
        $query->where('status', Status::Rejected);
    }

    /** @param  Builder<static>  $query */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', Status::Cancelled);
    }

    /** @param  Builder<static>  $query */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', Status::New)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /** @param  Builder<static>  $query */
    public function scopeExpiringBefore(Builder $query, CarbonInterface $moment): void
    {
        $query->where('status', Status::New)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $moment);
    }

    /** @param  Builder<static>  $query */
    public function scopeAuthoredBy(Builder $query, Model $author): void
    {
        $query->where('author_type', $author->getMorphClass())
            ->where('author_id', $author->getKey());
    }

    protected static function newFactory(): RequestFactory
    {
        return RequestFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'meta' => 'collection',
            'require_approvals_from' => 'collection',
            'expires_at' => 'datetime',
        ];
    }
}
