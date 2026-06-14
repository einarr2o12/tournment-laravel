<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Group;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;

/**
 * Group-stage standings. Aggregates completed (and walkover) group matches
 * into per-team rows sorted by:
 *   1. wins (desc)
 *   2. set diff = sets_for - sets_against (desc)
 *   3. point diff = points_for - points_against (desc)
 *
 * The entire computation runs off three queries (group + group_teams +
 * matches with sets) per category to keep the standings page under the
 * 100ms SLA for typical tournaments.
 */
class StandingsController extends Controller
{
    /**
     * GET /api/public/tournaments/{tournament}/categories/{category}/standings
     *
     * Returns standings rows grouped by Group. 404s if the tournament is
     * non-public or the category doesn't belong to it.
     */
    public function index(Tournament $tournament, Category $category): JsonResponse
    {
        abort_unless($this->tournamentIsPublic($tournament), 404);
        abort_unless($category->tournament_id === $tournament->getKey(), 404);

        $groups = Group::query()
            ->select(['id', 'category_id', 'name'])
            ->where('category_id', $category->getKey())
            ->orderBy('name')
            ->with([
                'teams' => fn ($q) => $q->select(['teams.id', 'teams.display_name']),
                'matches' => fn ($q) => $q
                    ->select([
                        'id', 'tournament_id', 'category_id', 'group_id',
                        'team_a_id', 'team_b_id', 'winner_id', 'status',
                    ])
                    ->whereIn('status', [
                        MatchStatus::COMPLETED->value,
                        MatchStatus::WALKOVER->value,
                    ])
                    ->with(['sets' => fn ($q) => $q->select([
                        'id', 'match_id', 'set_number',
                        'team_a_score', 'team_b_score', 'winner_id',
                    ])->orderBy('set_number')]),
            ])
            ->get();

        $payload = $groups->map(fn (Group $g): array => [
            'group_id' => $g->getKey(),
            'name' => "Group {$g->name}",
            'rows' => $this->computeStandingsRows($g),
        ])->all();

        return response()->json($payload);
    }

    /**
     * Compute the sorted standings rows for one group.
     *
     * @return list<array<string, int|string>>
     */
    private function computeStandingsRows(Group $group): array
    {
        $stats = [];
        foreach ($group->teams as $team) {
            $stats[$team->getKey()] = [
                'team_id' => $team->getKey(),
                'team_name' => $team->display_name ?? '',
                'played' => 0,
                'won' => 0,
                'lost' => 0,
                'sets_for' => 0,
                'sets_against' => 0,
                'points_for' => 0,
                'points_against' => 0,
            ];
        }

        foreach ($group->matches as $match) {
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

                $stats[$match->team_a_id]['points_for'] += $a;
                $stats[$match->team_a_id]['points_against'] += $b;
                $stats[$match->team_b_id]['points_for'] += $b;
                $stats[$match->team_b_id]['points_against'] += $a;

                if ($set->winner_id === $match->team_a_id) {
                    $stats[$match->team_a_id]['sets_for']++;
                    $stats[$match->team_b_id]['sets_against']++;
                } elseif ($set->winner_id === $match->team_b_id) {
                    $stats[$match->team_b_id]['sets_for']++;
                    $stats[$match->team_a_id]['sets_against']++;
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
        usort($rows, function (array $x, array $y): int {
            if ($y['won'] !== $x['won']) {
                return $y['won'] <=> $x['won'];
            }
            $xSetDiff = $x['sets_for'] - $x['sets_against'];
            $ySetDiff = $y['sets_for'] - $y['sets_against'];
            if ($ySetDiff !== $xSetDiff) {
                return $ySetDiff <=> $xSetDiff;
            }
            $xPointDiff = $x['points_for'] - $x['points_against'];
            $yPointDiff = $y['points_for'] - $y['points_against'];

            return $yPointDiff <=> $xPointDiff;
        });

        return $rows;
    }

    private function tournamentIsPublic(Tournament $t): bool
    {
        return in_array($t->status, [
            TournamentStatus::SCHEDULED,
            TournamentStatus::IN_PROGRESS,
            TournamentStatus::COMPLETED,
        ], true);
    }
}
