<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MatchModel;
use App\Models\ScoreEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoreEvent>
 */
class ScoreEventFactory extends Factory
{
    protected $model = ScoreEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => MatchModel::factory(),
            'set_number' => 1,
            'scoring_team_id' => Team::factory(),
            'scored_by_user_id' => null,
            'team_a_score_after' => 1,
            'team_b_score_after' => 0,
            'undone' => false,
            'scored_at' => now(),
        ];
    }

    public function forMatch(MatchModel|string $match): static
    {
        $id = $match instanceof MatchModel ? $match->id : $match;

        return $this->state(fn (): array => [
            'match_id' => $id,
        ]);
    }

    public function inSet(int $setNumber): static
    {
        return $this->state(fn (): array => [
            'set_number' => $setNumber,
        ]);
    }

    public function scoredBy(User|string|null $user): static
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->state(fn (): array => [
            'scored_by_user_id' => $id,
        ]);
    }

    public function for(Team|string $scoringTeam): static
    {
        return $this->state(fn (): array => [
            'scoring_team_id' => $scoringTeam instanceof Team ? $scoringTeam->id : $scoringTeam,
        ]);
    }

    public function scores(int $a, int $b): static
    {
        return $this->state(fn (): array => [
            'team_a_score_after' => $a,
            'team_b_score_after' => $b,
        ]);
    }

    public function undone(): static
    {
        return $this->state(fn (): array => [
            'undone' => true,
        ]);
    }
}
