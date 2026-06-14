<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use InvalidArgumentException;
use RuntimeException;

/**
 * Pure BWF scoring rules. No DB, no framework. Logic only.
 *
 * State is represented as plain associative arrays so it can be cheaply
 * cloned, serialized, and compared. All methods are pure and static.
 *
 * Side constants:
 *   - self::SIDE_A => 'A'
 *   - self::SIDE_B => 'B'
 *
 * MatchScoreState shape:
 *   [
 *     'sets' => [ ['teamAScore' => int, 'teamBScore' => int], ... ],
 *     'currentSetIndex' => int,
 *     'setsWonA' => int,
 *     'setsWonB' => int,
 *     'matchWinner' => 'A'|'B'|null,
 *   ]
 *
 * ScoringConfig shape:
 *   [
 *     'pointsToWin' => int, // typical: 21
 *     'setsToWin'   => int, // typical: 2  (best of 3)
 *     'deuceCap'    => int, // typical: 30
 *   ]
 */
final class ScoringRules
{
    public const SIDE_A = 'A';
    public const SIDE_B = 'B';

    /**
     * Build an empty match score state with a single empty set.
     *
     * @return array{
     *     sets: list<array{teamAScore:int,teamBScore:int}>,
     *     currentSetIndex: int,
     *     setsWonA: int,
     *     setsWonB: int,
     *     matchWinner: 'A'|'B'|null
     * }
     */
    public static function emptyMatchState(): array
    {
        return [
            'sets' => [
                ['teamAScore' => 0, 'teamBScore' => 0],
            ],
            'currentSetIndex' => 0,
            'setsWonA' => 0,
            'setsWonB' => 0,
            'matchWinner' => null,
        ];
    }

    /**
     * Determine winner of a set given its scores and the scoring config.
     * Returns 'A' / 'B' if the set is won; null if still in progress.
     *
     * BWF rules:
     *   - Win at pointsToWin (21) with a 2-point margin.
     *   - At 20-20, play continues until a 2-point lead OR one team reaches deuceCap (30).
     *   - At deuceCap, win by 1 (i.e. first to reach deuceCap wins).
     *
     * @param  array{teamAScore:int,teamBScore:int}        $set
     * @param  array{pointsToWin:int,setsToWin:int,deuceCap:int} $config
     * @return 'A'|'B'|null
     */
    public static function setWinner(array $set, array $config): ?string
    {
        $a = $set['teamAScore'];
        $b = $set['teamBScore'];
        $pointsToWin = $config['pointsToWin'];
        $deuceCap = $config['deuceCap'];

        if ($a >= $deuceCap && $a > $b) {
            return self::SIDE_A;
        }
        if ($b >= $deuceCap && $b > $a) {
            return self::SIDE_B;
        }
        if ($a >= $pointsToWin && ($a - $b) >= 2) {
            return self::SIDE_A;
        }
        if ($b >= $pointsToWin && ($b - $a) >= 2) {
            return self::SIDE_B;
        }

        return null;
    }

    /**
     * Apply a single point to a match state. Returns a new state (does not mutate).
     *
     * Behaviour:
     *  - If the match is already won, refuse (throw).
     *  - If the current set is already won, refuse (throw).
     *  - Award point. If the set is now decided, close the set, increment that
     *    team's setsWon.
     *  - If a team has reached setsToWin, mark matchWinner.
     *  - Otherwise, start a new set.
     *
     * @param  array{
     *     sets: list<array{teamAScore:int,teamBScore:int}>,
     *     currentSetIndex: int,
     *     setsWonA: int,
     *     setsWonB: int,
     *     matchWinner: 'A'|'B'|null
     * } $state
     * @param  'A'|'B' $side
     * @param  array{pointsToWin:int,setsToWin:int,deuceCap:int} $config
     * @return array{
     *     sets: list<array{teamAScore:int,teamBScore:int}>,
     *     currentSetIndex: int,
     *     setsWonA: int,
     *     setsWonB: int,
     *     matchWinner: 'A'|'B'|null
     * }
     */
    public static function scorePoint(array $state, string $side, array $config): array
    {
        if ($side !== self::SIDE_A && $side !== self::SIDE_B) {
            throw new InvalidArgumentException('Side must be "A" or "B"');
        }
        if ($state['matchWinner'] !== null) {
            throw new RuntimeException('Match is already complete');
        }

        $currentSetIndex = $state['currentSetIndex'];
        if (! isset($state['sets'][$currentSetIndex])) {
            throw new RuntimeException('No current set');
        }
        $currentSet = $state['sets'][$currentSetIndex];

        if (self::setWinner($currentSet, $config) !== null) {
            throw new RuntimeException('Current set already complete — start next set first');
        }

        $newSets = $state['sets'];
        $updatedSet = [
            'teamAScore' => $currentSet['teamAScore'] + ($side === self::SIDE_A ? 1 : 0),
            'teamBScore' => $currentSet['teamBScore'] + ($side === self::SIDE_B ? 1 : 0),
        ];
        $newSets[$currentSetIndex] = $updatedSet;

        $setsWonA = $state['setsWonA'];
        $setsWonB = $state['setsWonB'];
        $matchWinner = null;

        $closedWinner = self::setWinner($updatedSet, $config);
        if ($closedWinner === self::SIDE_A) {
            $setsWonA += 1;
        }
        if ($closedWinner === self::SIDE_B) {
            $setsWonB += 1;
        }

        if ($setsWonA >= $config['setsToWin']) {
            $matchWinner = self::SIDE_A;
        } elseif ($setsWonB >= $config['setsToWin']) {
            $matchWinner = self::SIDE_B;
        } elseif ($closedWinner !== null) {
            // Start a new set
            $newSets[] = ['teamAScore' => 0, 'teamBScore' => 0];
            $currentSetIndex += 1;
        }

        return [
            'sets' => $newSets,
            'currentSetIndex' => $currentSetIndex,
            'setsWonA' => $setsWonA,
            'setsWonB' => $setsWonB,
            'matchWinner' => $matchWinner,
        ];
    }

    /**
     * Replay scoring from an ordered list of point events. Useful for "undo"
     * by rebuilding state from the kept (non-undone) events.
     *
     * Each event is shaped: ['side' => 'A'|'B'].
     *
     * @param  list<array{side:'A'|'B'}> $events
     * @param  array{pointsToWin:int,setsToWin:int,deuceCap:int} $config
     * @return array{
     *     sets: list<array{teamAScore:int,teamBScore:int}>,
     *     currentSetIndex: int,
     *     setsWonA: int,
     *     setsWonB: int,
     *     matchWinner: 'A'|'B'|null
     * }
     */
    public static function replayPoints(array $events, array $config): array
    {
        $state = self::emptyMatchState();
        foreach ($events as $event) {
            $state = self::scorePoint($state, $event['side'], $config);
        }

        return $state;
    }

    /**
     * Maximum possible sets in best-of-N (setsToWin * 2 - 1).
     *
     * @param  array{pointsToWin:int,setsToWin:int,deuceCap:int} $config
     */
    public static function maxSets(array $config): int
    {
        return $config['setsToWin'] * 2 - 1;
    }
}
