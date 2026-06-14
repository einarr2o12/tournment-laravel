<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MatchModel;
use App\Models\MatchSet;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchSet>
 */
class MatchSetFactory extends Factory
{
    protected $model = MatchSet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => MatchModel::factory(),
            'set_number' => 1,
            'team_a_score' => 0,
            'team_b_score' => 0,
            'winner_id' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function forMatch(MatchModel|string $match): static
    {
        $id = $match instanceof MatchModel ? $match->id : $match;

        return $this->state(fn (): array => [
            'match_id' => $id,
        ]);
    }

    public function setNumber(int $number): static
    {
        return $this->state(fn (): array => [
            'set_number' => $number,
        ]);
    }

    public function scores(int $a, int $b): static
    {
        return $this->state(fn (): array => [
            'team_a_score' => $a,
            'team_b_score' => $b,
        ]);
    }

    public function wonBy(Team|string $team): static
    {
        return $this->state(fn (): array => [
            'winner_id' => $team instanceof Team ? $team->id : $team,
            'completed_at' => now(),
        ]);
    }
}
