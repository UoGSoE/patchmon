<?php

namespace Database\Factories;

use App\Models\PatchEvent;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatchEvent>
 */
class PatchEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'patched_at' => now(),
            'source_ip' => fake()->ipv4(),
        ];
    }
}
