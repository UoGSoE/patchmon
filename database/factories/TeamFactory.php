<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'notification_email' => fake()->unique()->safeEmail(),
            'sender_email' => null,
            'silenced_until' => null,
            'silence_reason' => null,
        ];
    }

    public function silenced()
    {
        return $this->state(fn (array $attributes) => [
            'silenced_until' => now()->addDay(),
            'silence_reason' => fake()->sentence(),
        ]);
    }
}
