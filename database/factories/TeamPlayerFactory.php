<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamPlayer>
 */
class TeamPlayerFactory extends Factory
{
    protected $model = TeamPlayer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'position' => 1,
        ];
    }

    public function forTeam(Team|string $team): static
    {
        $id = $team instanceof Team ? $team->id : $team;

        return $this->state(fn (): array => [
            'team_id' => $id,
        ]);
    }

    public function forPlayer(Player|string $player): static
    {
        $id = $player instanceof Player ? $player->id : $player;

        return $this->state(fn (): array => [
            'player_id' => $id,
        ]);
    }

    public function position(int $position): static
    {
        return $this->state(fn (): array => [
            'position' => $position,
        ]);
    }
}
