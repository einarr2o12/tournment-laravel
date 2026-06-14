<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Court;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    protected $model = Court::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'tournament_id' => Tournament::factory(),
            'name' => 'Court '.$counter,
            'display_order' => $counter,
            'active' => true,
        ];
    }

    public function forTournament(Tournament|string $tournament): static
    {
        $id = $tournament instanceof Tournament ? $tournament->id : $tournament;

        return $this->state(fn (): array => [
            'tournament_id' => $id,
        ]);
    }

    public function named(string $name, int $order = 0): static
    {
        return $this->state(fn (): array => [
            'name' => $name,
            'display_order' => $order,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
