<?php

namespace Database\Factories;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Models\Job;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    public function definition(): array
    {
        $owner = User::factory();

        return [
            'team_id' => null,
            'user_id' => $owner,
            'created_by_user_id' => $owner,
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'cron_expression' => null,
            'schedule_interval' => ScheduleInterval::Daily,
            'schedule_frequency' => 1,
            'grace_value' => 1,
            'grace_units' => GraceUnit::Hours,
            'notification_email' => null,
            'sender_email' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => null,
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function forTeam(Team $team, ?User $creator = null): static
    {
        $creator ??= User::factory()->create();

        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
            'user_id' => null,
            'created_by_user_id' => $creator->id,
        ]);
    }

    public function withCron(string $expression): static
    {
        return $this->state(fn (array $attributes) => [
            'cron_expression' => $expression,
            'schedule_interval' => null,
            'schedule_frequency' => 1,
        ]);
    }

    public function silenced(): static
    {
        return $this->state(fn (array $attributes) => [
            'silenced_until' => now()->addDay(),
            'silence_reason' => fake()->sentence(),
        ]);
    }

    public function alerting(): static
    {
        return $this->state(fn (array $attributes) => [
            'alerting_since' => now()->subHours(2),
        ]);
    }
}
