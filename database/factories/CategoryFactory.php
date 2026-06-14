<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(CategoryType::cases());

        return [
            'tournament_id' => Tournament::factory(),
            'type' => $type->value,
            'name' => CategoryType::labels()[$type->value],
        ];
    }

    public function forTournament(Tournament|string $tournament): static
    {
        $id = $tournament instanceof Tournament ? $tournament->id : $tournament;

        return $this->state(fn (): array => [
            'tournament_id' => $id,
        ]);
    }

    public function ofType(CategoryType $type, ?string $name = null): static
    {
        return $this->state(fn (): array => [
            'type' => $type->value,
            'name' => $name ?? CategoryType::labels()[$type->value],
        ]);
    }

    public function mensSingles(): static
    {
        return $this->ofType(CategoryType::MENS_SINGLES);
    }

    public function womensSingles(): static
    {
        return $this->ofType(CategoryType::WOMENS_SINGLES);
    }

    public function mensDoubles(): static
    {
        return $this->ofType(CategoryType::MENS_DOUBLES);
    }

    public function womensDoubles(): static
    {
        return $this->ofType(CategoryType::WOMENS_DOUBLES);
    }

    public function mixedDoubles(): static
    {
        return $this->ofType(CategoryType::MIXED_DOUBLES);
    }
}
