<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nip' => null,
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'role' => 'anggota',
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the user is an internal member.
     */
    public function anggota(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'anggota',
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the user is a librarian.
     */
    public function pustakawan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'pustakawan',
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the user is an external member awaiting approval.
     */
    public function eksternal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'eksternal',
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the user is awaiting verification or approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }
}
