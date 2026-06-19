<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Author side: a model that can author requests.
 *
 * @mixin Model
 */
trait HasRequests
{
    /** @return MorphMany<Request, $this> */
    public function requests(): MorphMany
    {
        /** @var class-string<Request> $model */
        $model = config('requests.model', Request::class);

        return $this->morphMany($model, 'author');
    }

    /** @return MorphMany<Request, $this> */
    public function openRequests(): MorphMany
    {
        return $this->requests()->where('status', Status::New);
    }

    /** @return MorphMany<Request, $this> */
    public function approvedRequests(): MorphMany
    {
        return $this->requests()->where('status', Status::Approved);
    }

    /** @return MorphMany<Request, $this> */
    public function rejectedRequests(): MorphMany
    {
        return $this->requests()->where('status', Status::Rejected);
    }
}
