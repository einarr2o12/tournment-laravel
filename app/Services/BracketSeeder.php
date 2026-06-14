<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Category;
use App\Models\Group;
use App\Models\MatchModel;
use App\Services\Standings\GroupStandings;
use Illuminate\Support\Facades\DB;

/**
 * Resolves "G:<letter>:<pos>" knockout seed sources into concrete
 * team_a_id/team_b_id once a category's group stage is fully decided.
 *
 * Extracted from the `bracket:resolve` console command so the same per-category
 * logic can run both standalone (the command) and inline after an admin
 * completes the last group match of a category. "W:*"/"L:*" sources are NOT
 * touched here — those are filled live by scoring propagation.
 *
 * Idempotent: only null slots whose source is "G:*" are written, so re-running
 * after some knockout matches have already been seeded will not clobber them.
 */
final class BracketSeeder
{
    /**
     * Whether every GROUP match for the category is COMPLETED/WALKOVER and at
     * least one group match exists. This is the gate for resolving seeds.
     */
    public function isGroupStageComplete(Category $category): bool
    {
        $groupMatchCount = MatchModel::query()
            ->where('category_id', $category->id)
            ->where('stage', MatchStage::GROUP->value)
            ->count();

        if ($groupMatchCount === 0) {
            return false;
        }

        $pending = MatchModel::query()
            ->where('category_id', $category->id)
            ->where('stage', MatchStage::GROUP->value)
            ->whereNotIn('status', [
                MatchStatus::COMPLETED->value,
                MatchStatus::WALKOVER->value,
            ])
            ->count();

        return $pending === 0;
    }

    /**
     * Resolve all "G:*" knockout seed slots for one category, IF its group
     * stage is complete. Returns the number of slots filled (0 if the group
     * stage is incomplete or everything was already resolved).
     */
    public function resolveForCategory(Category $category): int
    {
        if (! $this->isGroupStageComplete($category)) {
            return 0;
        }

        // Ranked team IDs per group letter, e.g. ['A' => [id1, id2, ...]].
        $groups = Group::query()
            ->where('category_id', $category->id)
            ->orderBy('name')
            ->get();

        /** @var array<string, list<string>> $rankingByLetter */
        $rankingByLetter = [];
        foreach ($groups as $group) {
            $rankingByLetter[$this->groupLetter($group->name)] = GroupStandings::rankGroup($group);
        }

        $knockout = MatchModel::query()
            ->where('category_id', $category->id)
            ->where('stage', '!=', MatchStage::GROUP->value)
            ->get();

        $filled = 0;

        DB::transaction(function () use ($knockout, $rankingByLetter, &$filled): void {
            foreach ($knockout as $match) {
                $changes = [];

                if ($match->team_a_id === null) {
                    $teamId = $this->resolveGroupSource($match->team_a_source, $rankingByLetter);
                    if ($teamId !== null) {
                        $changes['team_a_id'] = $teamId;
                    }
                }
                if ($match->team_b_id === null) {
                    $teamId = $this->resolveGroupSource($match->team_b_source, $rankingByLetter);
                    if ($teamId !== null) {
                        $changes['team_b_id'] = $teamId;
                    }
                }

                if ($changes !== []) {
                    $match->forceFill($changes)->save();
                    $filled += count($changes);
                }
            }
        });

        return $filled;
    }

    /**
     * Resolve a single "G:<letter>:<pos>" source string to a team id, or null
     * if the source is not a group source / position is out of range.
     *
     * @param  array<string, list<string>>  $rankingByLetter
     */
    public function resolveGroupSource(?string $source, array $rankingByLetter): ?string
    {
        if ($source === null || ! str_starts_with($source, 'G:')) {
            return null;
        }

        // "G:A:1" -> ['G','A','1']; "G::1" -> ['G','','1'].
        $parts = explode(':', $source);
        if (count($parts) !== 3) {
            return null;
        }

        $letter = $parts[1] === '' ? array_key_first($rankingByLetter) : strtoupper($parts[1]);
        $position = (int) $parts[2];

        if ($letter === null || ! isset($rankingByLetter[$letter])) {
            return null;
        }

        $ranking = $rankingByLetter[$letter];
        $index = $position - 1;

        return $ranking[$index] ?? null;
    }

    /**
     * Extract the group letter from a group name like "Group A" -> "A".
     * Falls back to the trimmed name uppercased.
     */
    public function groupLetter(string $name): string
    {
        if (preg_match('/([A-Za-z])\s*$/', trim($name), $m) === 1) {
            return strtoupper($m[1]);
        }

        return strtoupper(trim($name));
    }
}
