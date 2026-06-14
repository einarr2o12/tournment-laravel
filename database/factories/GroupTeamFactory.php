<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupTeam;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupTeam>
 */
class GroupTeamFactory extends Factory
{
    protected $model = GroupTeam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'team_id' => Team::factory(),
        ];
    }

    public function forGroup(Group|string $group): static
    {
        $id = $group instanceof Group ? $group->id : $group;

        return $this->state(fn (): array => [
            'group_id' => $id,
        ]);
    }

    public function forTeam(Team|string $team): static
    {
        $id = $team instanceof Team ? $team->id : $team;

        return $this->state(fn (): array => [
            'team_id' => $id,
        ]);
    }
}
