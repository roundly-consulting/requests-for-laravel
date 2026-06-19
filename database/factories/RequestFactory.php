<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Models\Request;

/** @extends Factory<Request> */
final class RequestFactory extends Factory
{
    protected $model = Request::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'status' => $this->faker->randomElement(Status::cases()),
            'type' => $this->faker->word(),
            'title' => $this->faker->words(asText: true),
            'description' => $this->faker->sentence(),
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => Status::New]);
    }

    public function approved(): self
    {
        return $this->state(['status' => Status::Approved]);
    }

    public function rejected(): self
    {
        return $this->state(['status' => Status::Rejected]);
    }

    public function cancelled(): self
    {
        return $this->state(['status' => Status::Cancelled]);
    }

    /**
     * An open request whose expiry deadline has already passed, ready to be
     * expired by the prune command.
     */
    public function expired(): self
    {
        return $this->state([
            'status' => Status::New,
            'expires_at' => now()->subDay(),
        ]);
    }

    /** @param  array<int, int|string>  $ids */
    public function requiring(array $ids): self
    {
        return $this->state(['require_approvals_from' => collect($ids)]);
    }

    public function authoredBy(Model $author): self
    {
        return $this->state([
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
        ]);
    }
}
