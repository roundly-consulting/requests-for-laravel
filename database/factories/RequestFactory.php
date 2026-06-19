<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
}
