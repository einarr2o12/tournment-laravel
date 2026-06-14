<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStage;

/**
 * Pure helpers for generating matches from groups (round-robin) and brackets
 * (single elimination). Ported from apps/api/src/matches/match-draw.ts.
 *
 * No DB, no framework. All methods are static and pure so they can be
 * trivially unit-tested.
 *
 * BracketSlot shape (returned by placeTeamsInBracket):
 *   [
 *     'bracketSlot' => int,  // 1..N where N is padded size
 *     'seed'        => int|null, // null = bye
 *     'teamId'      => string|null, // null = bye
 *   ]
 */
final class BracketBuilder
{
    private const BYE_MARKER = '__BYE__';

    /**
     * Round-robin pairings for N teams using the standard "circle method"
     * so every team plays every other once.
     *
     * @param  list<string>  $teamIds
     * @return list<list<array{0:string,1:string}>> rounds, each containing pairings
     */
    public static function roundRobinPairings(array $teamIds): array
    {
        $n = count($teamIds);
        if ($n < 2) {
            return [];
        }

        $teams = $teamIds;
        // For odd numbers, add a bye placeholder so the rotation works.
        if ($n % 2 === 1) {
            $teams[] = self::BYE_MARKER;
        }

        $totalTeams = count($teams);
        $rounds = [];

        // First team stays fixed; the rest rotate.
        $rotation = array_slice($teams, 1);
        $rotationCount = count($rotation);

        for ($round = 0; $round < $totalTeams - 1; $round++) {
            $roundPairs = [];

            $a = $teams[0];
            $b = $rotation[$rotationCount - 1];
            if ($a !== self::BYE_MARKER && $b !== self::BYE_MARKER) {
                $roundPairs[] = [$a, $b];
            }

            for ($i = 0; $i < $rotationCount - 1; $i++) {
                $x = $rotation[$i];
                $y = $rotation[$rotationCount - 2 - $i];
                if ($x !== self::BYE_MARKER && $y !== self::BYE_MARKER) {
                    $roundPairs[] = [$x, $y];
                }
            }

            $rounds[] = $roundPairs;

            // Rotate: pop last, unshift to front.
            $last = array_pop($rotation);
            array_unshift($rotation, $last);
        }

        return $rounds;
    }

    /**
     * Stage mapping for a single-elimination bracket of `$numTeams` teams.
     * Returns the stage used at each round starting from the first round.
     *
     * @return list<MatchStage>
     */
    public static function bracketStagesByRound(int $numTeams): array
    {
        $rounds = [];
        $r = $numTeams;
        while ($r > 1) {
            $half = (int) ceil($r / 2);
            $rounds[] = self::matchStageForFieldSize($r);
            $r = $half;
        }
        return $rounds;
    }

    private static function matchStageForFieldSize(int $fieldSize): MatchStage
    {
        if ($fieldSize <= 2) {
            return MatchStage::FINAL;
        }
        if ($fieldSize <= 4) {
            return MatchStage::SEMIFINAL;
        }
        if ($fieldSize <= 8) {
            return MatchStage::QUARTERFINAL;
        }
        if ($fieldSize <= 16) {
            return MatchStage::ROUND_OF_16;
        }
        if ($fieldSize <= 32) {
            return MatchStage::ROUND_OF_32;
        }
        return MatchStage::ROUND_OF_64;
    }

    /**
     * Standard seeded bracket positions for N teams (N up to 64).
     * Returns an array where slot[i] is the seed (1-indexed) that goes in
     * slot i. Slots are arranged so seed 1 vs N, seed 2 vs N-1, etc.,
     * balanced to spread top seeds apart.
     *
     * @return list<int>
     */
    public static function seededSlots(int $numTeams): array
    {
        // Pad to next power of 2.
        $size = 1;
        while ($size < $numTeams) {
            $size *= 2;
        }

        // Standard bracket order generation.
        $order = [1];
        while (count($order) < $size) {
            $next = [];
            $top = count($order) * 2 + 1;
            foreach ($order as $s) {
                $next[] = $s;
                $next[] = $top - $s;
            }
            $order = $next;
        }
        return $order;
    }

    /**
     * Place teams into bracket slots by seed. Teams without a seed are placed
     * after seeded teams in registration order. Empty slots (seed > teams
     * count) get null teamId = byes.
     *
     * @param  list<array{id:string,seed:int|null}>  $teams
     * @return list<array{bracketSlot:int,seed:int|null,teamId:string|null}>
     */
    public static function placeTeamsInBracket(array $teams): array
    {
        $seededByRank = array_values(array_filter(
            $teams,
            static fn (array $t): bool => $t['seed'] !== null
        ));
        usort(
            $seededByRank,
            static fn (array $a, array $b): int => ($a['seed'] ?? 0) <=> ($b['seed'] ?? 0)
        );
        $seededByRank = array_map(
            static fn (array $t): array => ['id' => $t['id'], 'seed' => (int) $t['seed']],
            $seededByRank
        );

        $unseeded = array_values(array_filter(
            $teams,
            static fn (array $t): bool => $t['seed'] === null
        ));

        // Continue synthetic seeds after the highest real seed.
        $nextSeed = count($seededByRank) > 0
            ? $seededByRank[count($seededByRank) - 1]['seed'] + 1
            : 1;

        $allSeeded = $seededByRank;
        foreach ($unseeded as $t) {
            $allSeeded[] = ['id' => $t['id'], 'seed' => $nextSeed++];
        }

        $order = self::seededSlots(count($allSeeded));

        // Index by seed for O(1) lookup.
        $bySeed = [];
        foreach ($allSeeded as $t) {
            $bySeed[$t['seed']] = $t;
        }

        $slots = [];
        foreach ($order as $idx => $seed) {
            $team = $bySeed[$seed] ?? null;
            $slots[] = [
                'bracketSlot' => $idx + 1,
                'seed' => $team !== null ? $seed : null,
                'teamId' => $team['id'] ?? null,
            ];
        }
        return $slots;
    }
}
