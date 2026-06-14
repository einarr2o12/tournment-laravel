<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'display_name' => null, // typically derived from player names by the service layer
            'seed' => null,
        ];
    }

    public function forCategory(Category|string $category): static
    {
        $id = $category instanceof Category ? $category->id : $category;

        return $this->state(fn (): array => [
            'category_id' => $id,
        ]);
    }

    public function seeded(int $seed): static
    {
        return $this->state(fn (): array => [
            'seed' => $seed,
        ]);
    }

    public function named(string $displayName): static
    {
        return $this->state(fn (): array => [
            'display_name' => $displayName,
        ]);
    }
}
