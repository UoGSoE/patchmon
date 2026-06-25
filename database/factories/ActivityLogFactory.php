<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'user_name' => null,
            'server_id' => null,
            'server_name' => null,
            'description' => fake()->sentence(),
            'source_ip' => fake()->ipv4(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'user_name' => $user->full_name,
        ]);
    }

    public function forServer(Server $server): static
    {
        return $this->state(fn () => [
            'server_id' => $server->id,
            'server_name' => $server->name,
        ]);
    }
}
