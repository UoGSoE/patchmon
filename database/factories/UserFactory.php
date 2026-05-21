<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->regexify('[a-z]{2,4}[0-9]{1,3}[a-z]'),
            'surname' => fake()->lastName(),
            'forenames' => fake()->firstName(),
            'is_staff' => true,
            'is_admin' => false,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_staff' => true,
        ]);
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_staff' => false,
        ]);
    }
}
