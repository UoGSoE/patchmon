<?php

namespace Database\Factories;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    public function definition(): array
    {
        $creator = User::factory();

        return [
            'team_id' => Team::factory(),
            'created_by_user_id' => $creator,
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'os_type' => OsType::Linux,
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
            'notification_email' => null,
            'sender_email' => null,
        ];
    }

    public function forTeam(Team $team, ?User $creator = null): static
    {
        $creator ??= User::factory()->create();

        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
            'created_by_user_id' => $creator->id,
        ]);
    }

    public function withInterval(int $months): static
    {
        return $this->state(fn (array $attributes) => [
            'interval_months' => $months,
        ]);
    }

    public function withGrace(int $value, GraceUnit $units): static
    {
        return $this->state(fn (array $attributes) => [
            'grace_value' => $value,
            'grace_units' => $units,
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
            'alerting_since' => now()->subDays(3),
            'last_alerted_at' => now()->subDays(3),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
            'last_patched_at' => now()->subMonths(2),
        ]);
    }
}
