<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'checked_in_at' => now(),
            'source_ip' => fake()->ipv4(),
        ];
    }
}
