<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Category;
use App\Models\Court;
use App\Models\Group;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchModel>
 */
class MatchFactory extends Factory
{
    protected $model = MatchModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'category_id' => Category::factory(),
            'court_id' => null,
            'group_id' => null,
            'stage' => MatchStage::GROUP->value,
            'round_number' => 1,
            'bracket_slot' => null,
            'next_match_id' => null,
            'scheduled_at' => fake()->dateTimeBetween('now', '+2 days'),
            'started_at' => null,
            'completed_at' => null,
            'status' => MatchStatus::SCHEDULED->value,
            'team_a_id' => null,
            'team_b_id' => null,
            'winner_id' => null,
            'notes' => null,
        ];
    }

    public function forTournament(Tournament|string $tournament): static
    {
        $id = $tournament instanceof Tournament ? $tournament->id : $tournament;

        return $this->state(fn (): array => [
            'tournament_id' => $id,
        ]);
    }

    public function forCategory(Category|string $category): static
    {
        $id = $category instanceof Category ? $category->id : $category;

        return $this->state(fn (): array => [
            'category_id' => $id,
        ]);
    }

    public function onCourt(Court|string|null $court): static
    {
        $id = $court instanceof Court ? $court->id : $court;

        return $this->state(fn (): array => [
            'court_id' => $id,
        ]);
    }

    public function inGroup(Group|string|null $group): static
    {
        $id = $group instanceof Group ? $group->id : $group;

        return $this->state(fn (): array => [
            'group_id' => $id,
            'stage' => MatchStage::GROUP->value,
        ]);
    }

    public function stage(MatchStage $stage): static
    {
        return $this->state(fn (): array => [
            'stage' => $stage->value,
        ]);
    }

    public function withTeams(Team|string $teamA, Team|string $teamB): static
    {
        return $this->state(fn (): array => [
            'team_a_id' => $teamA instanceof Team ? $teamA->id : $teamA,
            'team_b_id' => $teamB instanceof Team ? $teamB->id : $teamB,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => MatchStatus::SCHEDULED->value,
            'started_at' => null,
            'completed_at' => null,
            'winner_id' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => MatchStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'completed_at' => null,
            'winner_id' => null,
        ]);
    }

    public function completed(Team|string|null $winner = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MatchStatus::COMPLETED->value,
            'started_at' => $attributes['started_at'] ?? now()->subHour(),
            'completed_at' => now(),
            'winner_id' => $winner instanceof Team
                ? $winner->id
                : ($winner ?? $attributes['team_a_id'] ?? null),
        ]);
    }

    public function walkover(Team|string $winner): static
    {
        return $this->state(fn (): array => [
            'status' => MatchStatus::WALKOVER->value,
            'completed_at' => now(),
            'winner_id' => $winner instanceof Team ? $winner->id : $winner,
        ]);
    }
}
