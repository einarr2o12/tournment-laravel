<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Group;
use App\Models\MatchModel;
use App\Models\Tournament;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public-facing tournament browser. No auth. Only tournaments in the
 * SCHEDULED / IN_PROGRESS / COMPLETED phase are listed — drafts and
 * archived records stay hidden.
 */
class TournamentController extends Controller
{
    /**
     * GET /tournaments
     *
     * Index of public-visible tournaments, sorted IN_PROGRESS first then
     * by most-recent start date. One query, no relations needed for the
     * list view.
     */
    public function index(): Response
    {
        $tournaments = Tournament::query()
            ->public()
            ->select([
                'id', 'name', 'description', 'venue', 'format', 'status',
                'points_to_win', 'sets_to_win', 'deuce_cap',
                'start_date', 'end_date', 'created_at',
            ])
            ->orderByRaw("CASE status "
                . "WHEN '" . TournamentStatus::IN_PROGRESS->value . "' THEN 0 "
                . "WHEN '" . TournamentStatus::SCHEDULED->value . "' THEN 1 "
                . "WHEN '" . TournamentStatus::COMPLETED->value . "' THEN 2 "
                . "ELSE 3 END")
            ->orderByRaw('start_date IS NULL, start_date DESC')
            ->get()
            ->map(fn (Tournament $t): array => $this->serializeBase($t))
            ->all();

        return Inertia::render('Public/Index', [
            'tournaments' => $tournaments,
        ]);
    }

    /**
     * GET /tournaments/{tournament}
     *
     * Single tournament detail page including its active courts and
     * categories — both eager loaded in the same call to avoid N+1.
     */
    public function show(Tournament $tournament): Response
    {
        abort_unless($this->isPublic($tournament), 404);

        $tournament->load([
            'courts' => fn ($q) => $q
                ->select(['id', 'tournament_id', 'name', 'display_order', 'active'])
                ->where('active', true)
                ->orderBy('display_order'),
            'categories' => fn ($q) => $q
                ->select(['id', 'tournament_id', 'type', 'name'])
                ->orderBy('name'),
        ]);

        $courts = $tournament->courts->map(fn (Court $c): array => [
            'id' => $c->getKey(),
            'name' => $c->name,
        ])->values()->all();

        $categories = $tournament->categories->map(fn ($c): array => [
            'id' => $c->getKey(),
            'type' => $c->type?->value,
            'name' => $c->name,
        ])->values()->all();

        return Inertia::render('Public/Tournament', [
            'tournament' => array_merge($this->serializeBase($tournament), [
                'categories' => $categories,
            ]),
            'courts' => $courts,
            'live' => $this->buildLive($tournament),
            'matches' => $this->buildMatches($tournament),
            'standings' => $this->buildStandings($tournament),
        ]);
    }

    /**
     * Build the live per-court { court, current, next } payload for this
     * tournament. Mirrors Public/LiveController but in camelCase shape
     * expected by Pages/Public/Tournament.vue.
     *
     * @return list<array<string, mixed>>
     */
    private function buildLive(Tournament $tournament): array
    {
        $courts = $tournament->courts;
        if ($courts->isEmpty()) {
            return [];
        }

        $courtIds = $courts->pluck('id')->all();
        $eagerLoads = $this->matchEagerLoads();

        $currentByCourt = MatchModel::query()
            ->where('tournament_id', $tournament->getKey())
            ->whereIn('court_id', $courtIds)
            ->where('status', MatchStatus::IN_PROGRESS->value)
            ->with($eagerLoads)
            ->get()
            ->keyBy('court_id');

        $scheduledByCourt = MatchModel::query()
            ->where('tournament_id', $tournament->getKey())
            ->whereIn('court_id', $courtIds)
            ->where('status', MatchStatus::SCHEDULED->value)
            ->orderByRaw('scheduled_at IS NULL, scheduled_at ASC')
            ->orderBy('created_at')
            ->with($eagerLoads)
            ->get()
            ->groupBy('court_id');

        return $courts->map(function (Court $court) use ($currentByCourt, $scheduledByCourt): array {
            $current = $currentByCourt->get($court->getKey());
            $next = $current ? null : $scheduledByCourt->get($court->getKey(), collect())->first();

            return [
                'court' => [
                    'id' => $court->getKey(),
                    'name' => $court->name,
                ],
                'current' => $current ? $this->serializeMatch($current) : null,
                'next' => $next ? $this->serializeMatch($next) : null,
            ];
        })->values()->all();
    }

    /**
     * Build the full match list for the tournament (all statuses), in the
     * camelCase shape Pages/Public/Tournament.vue expects.
     *
     * @return list<array<string, mixed>>
     */
    private function buildMatches(Tournament $tournament): array
    {
        return MatchModel::query()
            ->where('tournament_id', $tournament->getKey())
            ->with($this->matchEagerLoads())
            ->orderByRaw('scheduled_at IS NULL, scheduled_at ASC')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MatchModel $m): array => $this->serializeMatch($m))
            ->values()
            ->all();
    }

    /**
     * Build standings grouped by Group across every category in the
     * tournament. Mirrors Public/StandingsController but emits camelCase
     * keys for the Vue page.
     *
     * @return list<array<string, mixed>>
     */
    private function buildStandings(Tournament $tournament): array
    {
        $groups = Group::query()
            ->select(['groups.id', 'groups.category_id', 'groups.name'])
            ->join('categories', 'categories.id', '=', 'groups.category_id')
            ->where('categories.tournament_id', $tournament->getKey())
            ->orderBy('categories.name')
            ->orderBy('groups.name')
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

        return $groups->map(fn (Group $g): array => [
            'groupId' => $g->getKey(),
            'categoryId' => $g->category_id,
            'name' => $g->name,
            'rows' => $this->computeStandingsRows($g),
        ])->values()->all();
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function computeStandingsRows(Group $group): array
    {
        $stats = [];
        foreach ($group->teams as $team) {
            $stats[$team->getKey()] = [
                'teamId' => $team->getKey(),
                'teamName' => $team->display_name ?? '',
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
        usort($rows, function (array $x, array $y): int {
            if ($y['won'] !== $x['won']) {
                return $y['won'] <=> $x['won'];
            }
            $xSetDiff = $x['setsFor'] - $x['setsAgainst'];
            $ySetDiff = $y['setsFor'] - $y['setsAgainst'];
            if ($ySetDiff !== $xSetDiff) {
                return $ySetDiff <=> $xSetDiff;
            }
            $xPointDiff = $x['pointsFor'] - $x['pointsAgainst'];
            $yPointDiff = $y['pointsFor'] - $y['pointsAgainst'];

            return $yPointDiff <=> $xPointDiff;
        });

        return $rows;
    }

    /**
     * @return array<string, \Closure|string>
     */
    private function matchEagerLoads(): array
    {
        return [
            'category:id,tournament_id,type,name',
            'court:id,name',
            'teamA:id,category_id,display_name,seed',
            'teamB:id,category_id,display_name,seed',
            'sets' => fn ($q) => $q->orderBy('set_number'),
        ];
    }

    /**
     * Serialize a match in the camelCase shape Pages/Public/Tournament.vue
     * expects: teamA, teamB, displayName, categoryName, scheduledAt,
     * winnerId, sets[].teamAScore/teamBScore.
     *
     * @return array<string, mixed>
     */
    private function serializeMatch(MatchModel $match): array
    {
        return [
            'id' => $match->getKey(),
            'status' => $match->status?->value,
            'stage' => $match->stage?->value,
            'roundNumber' => $match->round_number,
            'bracketSlot' => $match->bracket_slot,
            'nextMatchId' => $match->next_match_id,
            'loserNextMatchId' => $match->loser_next_match_id,
            'scheduledAt' => $match->scheduled_at?->toIso8601String(),
            'categoryId' => $match->category_id,
            'categoryName' => $match->category?->name,
            'categoryType' => $match->category?->type?->value,
            'teamA' => $this->serializeTeam($match->teamA),
            'teamB' => $this->serializeTeam($match->teamB),
            'teamASource' => $match->team_a_source,
            'teamBSource' => $match->team_b_source,
            'court' => $match->court ? [
                'id' => $match->court->getKey(),
                'name' => $match->court->name,
            ] : null,
            'winnerId' => $match->winner_id,
            'sets' => $match->sets->map(fn ($s): array => [
                'teamAScore' => (int) $s->team_a_score,
                'teamBScore' => (int) $s->team_b_score,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeTeam(mixed $team): ?array
    {
        if ($team === null) {
            return null;
        }

        return [
            'id' => $team->getKey(),
            'displayName' => $team->display_name ?? '',
        ];
    }

    /**
     * Stable JSON shape for a Tournament summary; mirrors NestJS
     * TournamentsService.serializeBase.
     *
     * @return array<string, mixed>
     */
    private function serializeBase(Tournament $t): array
    {
        return [
            'id' => $t->getKey(),
            'name' => $t->name,
            'description' => $t->description,
            'venue' => $t->venue,
            'format' => $t->format?->value,
            'status' => $t->status?->value,
            'points_to_win' => (int) $t->points_to_win,
            'sets_to_win' => (int) $t->sets_to_win,
            'deuce_cap' => (int) $t->deuce_cap,
            'startDate' => $t->start_date?->toIso8601String(),
            'endDate' => $t->end_date?->toIso8601String(),
        ];
    }

    private function isPublic(Tournament $t): bool
    {
        return in_array($t->status, [
            TournamentStatus::SCHEDULED,
            TournamentStatus::IN_PROGRESS,
            TournamentStatus::COMPLETED,
        ], true);
    }
}
