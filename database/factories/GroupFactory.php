<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $letter = 0;
        $letter = ($letter % 26) + 1;

        return [
            'category_id' => Category::factory(),
            'name' => 'Group '.chr(64 + $letter), // A, B, C...
        ];
    }

    public function forCategory(Category|string $category): static
    {
        $id = $category instanceof Category ? $category->id : $category;

        return $this->state(fn (): array => [
            'category_id' => $id,
        ]);
    }

    public function named(string $name): static
    {
        return $this->state(fn (): array => [
            'name' => $name,
        ]);
    }
}
