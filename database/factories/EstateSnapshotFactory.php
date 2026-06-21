<?php

namespace Database\Factories;

use App\Models\EstateSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstateSnapshot>
 */
class EstateSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(50, 400);

        return [
            'snapshot_date' => today(),
            'total' => $total,
            'overdue' => fake()->numberBetween(0, (int) ($total * 0.2)),
            'silenced' => fake()->numberBetween(0, (int) ($total * 0.1)),
            'patched_30d' => fake()->numberBetween((int) ($total * 0.5), $total),
            'never_checked_in' => fake()->numberBetween(0, 20),
        ];
    }
}
