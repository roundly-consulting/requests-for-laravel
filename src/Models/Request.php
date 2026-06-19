<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Approvals\Concerns\HasApprovals;
use RoundlyConsulting\Requests\Approvals\Contracts\HasApprovals as HasApprovalsContract;
use RoundlyConsulting\Requests\Database\Factories\RequestFactory;
use RoundlyConsulting\Requests\Enums\Status;

/**
 * @property int $id
 * @property Status $status
 * @property string|null $type
 * @property string|null $title
 * @property string|null $description
 * @property Collection<array-key, mixed>|null $meta
 * @property Collection<array-key, mixed>|null $require_approvals_from
 */
class Request extends Model implements HasApprovalsContract
{
    use HasApprovals;

    /** @use HasFactory<RequestFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether every actor in $ids (of the given morph $type) has approved this request.
     *
     * @param  array<int, int|string>  $ids
     */
    public function hasBeenApprovedByAll(string $type, array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        $approved = $this->approvals()
            ->where('actor_type', $type)
            ->whereIn('actor_id', $ids)
            ->distinct()
            ->count('actor_id');

        return $approved === count(array_unique($ids));
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
        ];
    }
}
