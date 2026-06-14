<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     * Cached so we hash once per test run.
     */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'full_name' => fake()->name(),
            'role' => fake()->randomElement(UserRole::cases())->value,
            'active' => true,
            'last_login_at' => null,
        ];
    }

    /**
     * Admin role, default credentials admin/admin123.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::ADMIN->value,
            'full_name' => $attributes['full_name'] ?? 'Tournament Admin',
        ]);
    }

    /**
     * Referee role, default credentials refN/referee123.
     */
    public function referee(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::REFEREE->value,
        ]);
    }

    /**
     * Set the user's plaintext password (will be bcrypted).
     */
    public function password(string $plain): static
    {
        return $this->state(fn (array $attributes): array => [
            'password_hash' => Hash::make($plain),
        ]);
    }

    /**
     * Mark the user as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
        ]);
    }
}
