<?php

declare(strict_types=1);

namespace App\Services\Standings;

use App\Enums\MatchStatus;
use App\Models\Group;
use App\Models\MatchModel;
use App\Models\Team;

/**
 * Single source of truth for round-robin group ranking.
 *
 * Reuses the metric logic that lives (duplicated) in the public
 * TournamentController / StandingsController, but exposes it as a reusable,
 * framework-light helper so the knockout seeder can derive 1st..Nth finishers.
 *
 * Ordering rule (per the confirmed spec, 1 win = 1 point, loss = 0):
 *   (a) match wins desc          (1 point per win)
 *   (b) total points scored desc (pointsFor — head-to-head: more points ranks higher)
 *   (c) point difference desc    (pointsFor - pointsAgainst)
 *   (d) seed asc                 (final deterministic tiebreak — lower seed wins)
 */
final class GroupStandings
{
    /**
     * Return this group's team IDs ordered 1st..Nth.
     *
     * Index 0 = group winner, index 1 = runner-up, etc. Only
     * COMPLETED/WALKOVER matches contribute to the metrics. Teams with no
     * recorded results still appear (seeded at zero), ordered by their seed.
     *
     * @return list<string>
     */
    public static function rankGroup(Group $group): array
    {
        return array_map(
            static fn (array $row): string => $row['teamId'],
            self::rankedRows($group),
        );
    }

    /**
     * Full ranked metric rows (1st..Nth) for a group. Useful when the caller
     * wants the metrics, not just the ordered IDs.
     *
     * @return list<array{teamId:string,seed:int,played:int,won:int,lost:int,setsFor:int,setsAgainst:int,pointsFor:int,pointsAgainst:int}>
     */
    public static function rankedRows(Group $group): array
    {
        $group->loadMissing([
            'teams',
            'matches' => fn ($q) => $q
                ->whereIn('status', [MatchStatus::COMPLETED->value, MatchStatus::WALKOVER->value])
                ->with('sets'),
        ]);

        $stats = [];
        foreach ($group->teams as $team) {
            /** @var Team $team */
            $stats[$team->getKey()] = [
                'teamId' => $team->getKey(),
                'seed' => (int) ($team->seed ?? PHP_INT_MAX),
                'played' => 0,
                'won' => 0,
                'lost' => 0,
                'setsFor' => 0,
                'setsAgainst' => 0,
                'pointsFor' => 0,
                'pointsAgainst' => 0,
            ];
        }

        foreach ($group->matches as $match) {
            /** @var MatchModel $match */
            if ($match->team_a_id === null || $match->team_b_id === null) {
                continue;
            }
            if (! isset($stats[$match->team_a_id], $stats[$match->team_b_id])) {
                continue;
            }

            $stats[$match->team_a_id]['played']++;
            $stats[$match->team_b_id]['played']++;

            foreach ($match->sets as $set) {
                $a = (int) $set->team_a_score;
                $b = (int) $set->team_b_score;

                $stats[$match->team_a_id]['pointsFor'] += $a;
                $stats[$match->team_a_id]['pointsAgainst'] += $b;
                $stats[$match->team_b_id]['pointsFor'] += $b;
                $stats[$match->team_b_id]['pointsAgainst'] += $a;

                if ($set->winner_id === $match->team_a_id) {
                    $stats[$match->team_a_id]['setsFor']++;
                    $stats[$match->team_b_id]['setsAgainst']++;
                } elseif ($set->winner_id === $match->team_b_id) {
                    $stats[$match->team_b_id]['setsFor']++;
                    $stats[$match->team_a_id]['setsAgainst']++;
                }
            }

            if ($match->winner_id === $match->team_a_id) {
                $stats[$match->team_a_id]['won']++;
                $stats[$match->team_b_id]['lost']++;
            } elseif ($match->winner_id === $match->team_b_id) {
                $stats[$match->team_b_id]['won']++;
                $stats[$match->team_a_id]['lost']++;
            }
        }

        $rows = array_values($stats);
        usort($rows, static function (array $x, array $y): int {
            if ($y['won'] !== $x['won']) {
                return $y['won'] <=> $x['won'];               // (a) wins desc (1 win = 1 point)
            }
            if ($y['pointsFor'] !== $x['pointsFor']) {
                return $y['pointsFor'] <=> $x['pointsFor'];    // (b) total points scored desc
            }
            $xPointDiff = $x['pointsFor'] - $x['pointsAgainst'];
            $yPointDiff = $y['pointsFor'] - $y['pointsAgainst'];
            if ($yPointDiff !== $xPointDiff) {
                return $yPointDiff <=> $xPointDiff;            // (c) point difference desc
            }

            return $x['seed'] <=> $y['seed'];                 // (d) seed asc (final)
        });

        return $rows;
    }
}
