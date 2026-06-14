<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Category;
use App\Models\Group;
use App\Models\MatchModel;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates round-robin (group stage) and single-elimination (knockout)
 * matches. Ports apps/api/src/matches/matches.service.ts generation methods
 * to Laravel idioms.
 *
 * Knockout slots that are empty become walkovers; the bye winner is then
 * cascaded forward into the next bracket round until the chain is stable.
 */
final class MatchGeneratorService
{
    public const SOURCE_ALL_TEAMS = 'ALL_TEAMS';
    public const SOURCE_GROUP_TOP_N = 'GROUP_TOP_N';

    /**
     * Generate every round-robin pairing for every group in a category.
     *
     * @return array{created:int}
     */
    public function generateRoundRobin(Category $category, bool $replace): array
    {
        $groups = Group::query()
            ->where('category_id', $category->getKey())
            ->with('teams:id')
            ->get();

        if ($groups->isEmpty()) {
            throw ValidationException::withMessages([
                'groups' => 'No groups defined. Draw groups before generating round-robin matches.',
            ]);
        }

        $existingQuery = MatchModel::query()
            ->where('category_id', $category->getKey())
            ->where('stage', MatchStage::GROUP->value);

        if ($replace) {
            (clone $existingQuery)->delete();
        } elseif ((clone $existingQuery)->exists()) {
            throw ValidationException::withMessages([
                'matches' => 'Round-robin matches already exist. Pass replace=true to regenerate.',
            ]);
        }

        $totalCreated = 0;

        DB::transaction(function () use ($groups, $category, &$totalCreated): void {
            foreach ($groups as $group) {
                /** @var list<string> $teamIds */
                $teamIds = $group->teams->pluck('id')->map(static fn ($id): string => (string) $id)->all();
                if (count($teamIds) < 2) {
                    continue;
                }

                $rounds = BracketBuilder::roundRobinPairings($teamIds);
                foreach ($rounds as $roundIndex => $pairs) {
                    foreach ($pairs as [$aId, $bId]) {
                        MatchModel::query()->create([
                            'tournament_id' => $category->tournament_id,
                            'category_id' => $category->getKey(),
                            'group_id' => $group->getKey(),
                            'stage' => MatchStage::GROUP->value,
                            'round_number' => $roundIndex + 1,
                            'team_a_id' => $aId,
                            'team_b_id' => $bId,
                            'status' => MatchStatus::SCHEDULED->value,
                        ]);
                        $totalCreated += 1;
                    }
                }
            }
        });

        return ['created' => $totalCreated];
    }

    /**
     * Generate a single-elimination bracket for the category.
     *
     * @param  self::SOURCE_*  $source
     * @return array{created:int}
     */
    public function generateKnockout(
        Category $category,
        string $source,
        ?int $advanceCount,
        bool $replace,
        bool $thirdPlace = false,
    ): array {
        $teamsForBracket = $this->resolveBracketTeams($category, $source, $advanceCount);

        if (count($teamsForBracket) < 2) {
            throw ValidationException::withMessages([
                'teams' => 'Need at least 2 teams to generate a bracket.',
            ]);
        }

        $existingQuery = MatchModel::query()
            ->where('category_id', $category->getKey())
            ->where('stage', '!=', MatchStage::GROUP->value);

        if ($replace) {
            (clone $existingQuery)->delete();
        } elseif ((clone $existingQuery)->exists()) {
            throw ValidationException::withMessages([
                'matches' => 'Knockout matches already exist. Pass replace=true to regenerate.',
            ]);
        }

        $slots = BracketBuilder::placeTeamsInBracket($teamsForBracket);
        $bracketSize = count($slots);
        $stages = BracketBuilder::bracketStagesByRound($bracketSize);

        $createdCount = 0;

        DB::transaction(function () use (
            $category,
            $slots,
            $bracketSize,
            $stages,
            $thirdPlace,
            &$createdCount,
        ): void {
            /** @var list<string> $prevRoundIds */
            $prevRoundIds = [];

            foreach ($stages as $round => $stage) {
                $matchesThisRound = [];
                $matchesInRound = (int) ($bracketSize / (2 ** ($round + 1)));

                for ($m = 0; $m < $matchesInRound; $m++) {
                    $teamAId = null;
                    $teamBId = null;

                    if ($round === 0) {
                        $slotA = $slots[$m * 2] ?? null;
                        $slotB = $slots[$m * 2 + 1] ?? null;
                        $teamAId = $slotA['teamId'] ?? null;
                        $teamBId = $slotB['teamId'] ?? null;
                    }

                    $match = MatchModel::query()->create([
                        'tournament_id' => $category->tournament_id,
                        'category_id' => $category->getKey(),
                        'stage' => $stage->value,
                        'round_number' => $round + 1,
                        'bracket_slot' => $m + 1,
                        'team_a_id' => $teamAId,
                        'team_b_id' => $teamBId,
                        'status' => MatchStatus::SCHEDULED->value,
                    ]);

                    $matchesThisRound[] = (string) $match->getKey();

                    // Auto-advance byes in round 0 (exactly one team is null).
                    if ($round === 0 && (($teamAId === null) !== ($teamBId === null))) {
                        $winnerId = $teamAId ?? $teamBId;
                        $match->forceFill([
                            'status' => MatchStatus::WALKOVER->value,
                            'winner_id' => $winnerId,
                            'completed_at' => Carbon::now(),
                        ])->save();
                    }
                }

                // Link previous round's matches to this round's matches.
                if ($round > 0) {
                    foreach ($prevRoundIds as $i => $prevId) {
                        $targetIndex = intdiv($i, 2);
                        MatchModel::query()
                            ->whereKey($prevId)
                            ->update(['next_match_id' => $matchesThisRound[$targetIndex] ?? null]);
                    }
                }

                $prevRoundIds = $matchesThisRound;
            }

            $createdCount = $bracketSize - 1;

            if ($thirdPlace) {
                $semis = MatchModel::query()
                    ->where('category_id', $category->getKey())
                    ->where('stage', MatchStage::SEMIFINAL->value)
                    ->count();

                if ($semis >= 2) {
                    MatchModel::query()->create([
                        'tournament_id' => $category->tournament_id,
                        'category_id' => $category->getKey(),
                        'stage' => MatchStage::THIRD_PLACE->value,
                        'round_number' => count($stages),
                        'bracket_slot' => 1,
                        'status' => MatchStatus::SCHEDULED->value,
                    ]);
                    $createdCount += 1;
                }
            }

            $this->cascadeByes($category);
        });

        return ['created' => $createdCount];
    }

    /**
     * Resolve the list of teams that enter the bracket.
     *
     * @param  self::SOURCE_*  $source
     * @return list<array{id:string,seed:int|null}>
     */
    private function resolveBracketTeams(
        Category $category,
        string $source,
        ?int $advanceCount,
    ): array {
        if ($source === self::SOURCE_ALL_TEAMS) {
            return Team::query()
                ->where('category_id', $category->getKey())
                ->orderByRaw('seed IS NULL, seed ASC')
                ->orderBy('created_at')
                ->get(['id', 'seed'])
                ->map(static fn (Team $t): array => [
                    'id' => (string) $t->getKey(),
                    'seed' => $t->seed !== null ? (int) $t->seed : null,
                ])
                ->all();
        }

        if ($source !== self::SOURCE_GROUP_TOP_N) {
            throw ValidationException::withMessages([
                'source' => "Unknown source '{$source}'.",
            ]);
        }

        if ($advanceCount === null || $advanceCount < 1) {
            throw ValidationException::withMessages([
                'advanceCount' => 'advanceCount required for GROUP_TOP_N.',
            ]);
        }

        $groups = Group::query()
            ->where('category_id', $category->getKey())
            ->with(['teams' => static function ($q): void {
                $q->select('teams.id', 'teams.seed', 'teams.category_id');
            }])
            ->get();

        $teamsForBracket = [];
        foreach ($groups as $group) {
            $sorted = $group->teams
                ->sortBy(static fn (Team $t): int => $t->seed ?? PHP_INT_MAX)
                ->values()
                ->take($advanceCount);

            foreach ($sorted as $team) {
                $teamsForBracket[] = [
                    'id' => (string) $team->getKey(),
                    'seed' => $team->seed !== null ? (int) $team->seed : null,
                ];
            }
        }

        return $teamsForBracket;
    }

    /**
     * Propagate walkover winners forward into the next bracket match. Repeats
     * until stable so a chain of byes (e.g. seed 1 with two empty feeders)
     * cascades all the way through.
     */
    private function cascadeByes(Category $category): void
    {
        for ($safety = 0; $safety < 10; $safety++) {
            $walkovers = MatchModel::query()
                ->where('category_id', $category->getKey())
                ->where('status', MatchStatus::WALKOVER->value)
                ->whereNotNull('winner_id')
                ->whereNotNull('next_match_id')
                ->get();

            $changed = false;
            foreach ($walkovers as $walkover) {
                /** @var MatchModel|null $next */
                $next = MatchModel::query()->find($walkover->next_match_id);
                if ($next === null) {
                    continue;
                }

                $isFirstFeeder = (((int) $walkover->bracket_slot) - 1) % 2 === 0;
                $slotIsEmpty = $isFirstFeeder
                    ? $next->team_a_id === null
                    : $next->team_b_id === null;

                if (! $slotIsEmpty) {
                    continue;
                }

                $next->forceFill(
                    $isFirstFeeder
                        ? ['team_a_id' => $walkover->winner_id]
                        : ['team_b_id' => $walkover->winner_id]
                )->save();
                $changed = true;

                // If $next now has exactly one team and every feeder into it
                // is already resolved (walkover/completed), $next is itself a
                // walkover. Marking it here lets the next pass cascade its
                // winner further along the chain.
                $hasA = $next->team_a_id !== null;
                $hasB = $next->team_b_id !== null;
                if ($hasA !== $hasB) {
                    $unresolvedFeeders = MatchModel::query()
                        ->where('next_match_id', $next->getKey())
                        ->whereNotIn('status', [
                            MatchStatus::WALKOVER->value,
                            MatchStatus::COMPLETED->value,
                        ])
                        ->exists();

                    if (! $unresolvedFeeders) {
                        $winnerId = $next->team_a_id ?? $next->team_b_id;
                        $next->forceFill([
                            'status' => MatchStatus::WALKOVER->value,
                            'winner_id' => $winnerId,
                            'completed_at' => Carbon::now(),
                        ])->save();
                    }
                }
            }

            if (! $changed) {
                break;
            }
        }
    }
}
