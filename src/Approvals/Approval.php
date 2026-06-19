<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Approvals;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Requests\Database\Factories\ApprovalFactory;

/**
 * @property int $id
 * @property string $actor_type
 * @property int $actor_id
 * @property string $approvable_type
 * @property int $approvable_id
 */
final class Approval extends Model
{
    /** @use HasFactory<ApprovalFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): ApprovalFactory
    {
        return ApprovalFactory::new();
    }
}
