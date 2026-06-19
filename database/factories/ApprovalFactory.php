<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Requests\Approvals\Approval;
use RoundlyConsulting\Requests\Models\Request;

/** @extends Factory<Approval> */
final class ApprovalFactory extends Factory
{
    protected $model = Approval::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $request = Request::factory()->create();

        return [
            'actor_type' => 'user',
            'actor_id' => $this->faker->randomNumber(),
            'approvable_type' => $request->getMorphClass(),
            'approvable_id' => $request->getKey(),
        ];
    }
}
