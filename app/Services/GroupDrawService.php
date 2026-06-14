<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStage;
use App\Models\Category;
use App\Models\Group;
use App\Models\MatchModel;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Snake-draws teams into N groups and persists Group + GroupTeam rows.
 * Ports apps/api/src/groups/groups.service.ts (`draw`, `clear`, `moveTeam`).
 */
final class GroupDrawService
{
    /** @var list<string> */
    private const GROUP_NAMES = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
        'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
    ];

    /**
     * Snake-draw all teams of a category into `$numGroups` groups.
     *
     * Seed 1 → A, 2 → B, …, N → nth group, then back N+1 → nth, etc. so the
     * top seeds spread evenly across groups. Existing group-stage matches in
     * the category are deleted first because they reference the old draw.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,Group>
     */
    public function draw(Category $category, int $numGroups): \Illuminate\Database\Eloquent\Collection
    {
        if ($numGroups > count(self::GROUP_NAMES)) {
            throw ValidationException::withMessages([
                'numGroups' => 'Max '.count(self::GROUP_NAMES).' groups supported.',
            ]);
        }
        if ($numGroups < 1) {
            throw ValidationException::withMessages([
                'numGroups' => 'numGroups must be at least 1.',
            ]);
        }

        $teams = Team::query()
            ->where('category_id', $category->getKey())
            ->orderByRaw('seed IS NULL, seed ASC')
            ->orderBy('created_at')
            ->get(['id', 'seed', 'created_at']);

        if ($teams->count() < $numGroups) {
            throw ValidationException::withMessages([
                'numGroups' => "Need at least {$numGroups} teams to draw {$numGroups} groups (have {$teams->count()}).",
            ]);
        }

        // Snake assignment.
        $assignments = [];
        $direction = 1;
        $groupIndex = 0;
        foreach ($teams as $team) {
            $assignments[] = ['teamId' => (string) $team->getKey(), 'groupIndex' => $groupIndex];
            $groupIndex += $direction;
            if ($groupIndex >= $numGroups) {
                $groupIndex = $numGroups - 1;
                $direction = -1;
            } elseif ($groupIndex < 0) {
                $groupIndex = 0;
                $direction = 1;
            }
        }

        DB::transaction(function () use ($category, $numGroups, $assignments): void {
            // Stale group-stage matches reference the old draw.
            MatchModel::query()
                ->where('category_id', $category->getKey())
                ->where('stage', MatchStage::GROUP->value)
                ->delete();

            Group::query()
                ->where('category_id', $category->getKey())
                ->delete();

            for ($i = 0; $i < $numGroups; $i++) {
                $group = Group::query()->create([
                    'category_id' => $category->getKey(),
                    'name' => self::GROUP_NAMES[$i],
                ]);

                $teamIdsInGroup = array_values(array_map(
                    static fn (array $a): string => $a['teamId'],
                    array_filter($assignments, static fn (array $a): bool => $a['groupIndex'] === $i)
                ));

                if ($teamIdsInGroup !== []) {
                    $group->teams()->attach($teamIdsInGroup);
                }
            }
        });

        return Group::query()
            ->where('category_id', $category->getKey())
            ->orderBy('name')
            ->with(['teams.players'])
            ->get();
    }

    /**
     * Remove every group + group-stage match in the category.
     */
    public function clear(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            MatchModel::query()
                ->where('category_id', $category->getKey())
                ->where('stage', MatchStage::GROUP->value)
                ->delete();

            Group::query()
                ->where('category_id', $category->getKey())
                ->delete();
        });
    }

    /**
     * Move a team into a different group in the same category. Group matches
     * become stale and are wiped so they can be regenerated.
     */
    public function moveTeam(Category $category, Group $targetGroup, Team $team): void
    {
        if ((string) $targetGroup->category_id !== (string) $category->getKey()) {
            throw ValidationException::withMessages([
                'group' => 'Target group not in this category.',
            ]);
        }
        if ((string) $team->category_id !== (string) $category->getKey()) {
            throw ValidationException::withMessages([
                'team' => 'Team not in this category.',
            ]);
        }

        DB::transaction(function () use ($category, $targetGroup, $team): void {
            // Detach team from every group in this category.
            $groupIdsInCategory = Group::query()
                ->where('category_id', $category->getKey())
                ->pluck('id')
                ->all();

            DB::table('group_teams')
                ->where('team_id', $team->getKey())
                ->whereIn('group_id', $groupIdsInCategory)
                ->delete();

            $targetGroup->teams()->attach($team->getKey());

            MatchModel::query()
                ->where('category_id', $category->getKey())
                ->where('stage', MatchStage::GROUP->value)
                ->delete();
        });
    }
}
