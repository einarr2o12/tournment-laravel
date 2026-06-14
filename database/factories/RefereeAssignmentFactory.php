<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Court;
use App\Models\RefereeAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefereeAssignment>
 */
class RefereeAssignmentFactory extends Factory
{
    protected $model = RefereeAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::REFEREE->value]),
            'court_id' => Court::factory(),
            'active' => true,
        ];
    }

    public function forUser(User|string $user): static
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->state(fn (): array => [
            'user_id' => $id,
        ]);
    }

    public function forCourt(Court|string $court): static
    {
        $id = $court instanceof Court ? $court->id : $court;

        return $this->state(fn (): array => [
            'court_id' => $id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
