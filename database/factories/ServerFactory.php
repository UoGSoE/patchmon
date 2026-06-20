<?php

namespace Database\Factories;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            'netbox_id' => null,
            'is_virtual' => false,
            'inactive_since' => null,
            'name' => fake()->unique()->regexify('[a-z]{6}[0-9]{4}').'.example.com',
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

    public function virtual(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_virtual' => true,
        ]);
    }

    public function fromNetbox(int $netboxId, bool $isVirtual = false): static
    {
        return $this->state(fn (array $attributes) => [
            'netbox_id' => $netboxId,
            'is_virtual' => $isVirtual,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'inactive_since' => now()->subDays(3),
        ]);
    }

    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => null,
            'created_by_user_id' => null,
        ]);
    }

    public function provisioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'patch_token_provisioned_at' => now(),
        ]);
    }

    public function silenced(): static
    {
        return $this->state(fn (array $attributes) => [
            'silenced_from' => now()->subHour(),
            'silenced_until' => now()->addDay(),
            'silence_reason' => fake()->sentence(),
        ]);
    }

    public function scheduledSilenceFrom(Carbon $from, Carbon $until): static
    {
        return $this->state(fn (array $attributes) => [
            'silenced_from' => $from,
            'silenced_until' => $until,
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
