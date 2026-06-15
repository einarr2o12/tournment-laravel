<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Tournament;
use App\Services\Standings\GroupStandings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only admin standings view. Surfaces the same per-group round-robin
 * tables as the public tournament page, but reuses the canonical
 * {@see GroupStandings} ranker (which adds the seed tiebreak the public
 * controller's inline sort lacks) so admin + public agree on ordering.
 */
class StandingsController extends Controller
{
    /**
     * GET /admin/standings
     *
     * Targets the single (live, else most-recent) tournament. Emits standings
     * grouped by Group plus the category list for the front-end filter. All
     * relations are eager-loaded to avoid N+1 over groups/matches/sets.
     */
    public function index(): Response
    {
        $tournament = $this->currentTournament();

        if ($tournament === null) {
            return Inertia::render('Admin/Standings/Index', [
                'standings' => [],
                'categories' => [],
            ]);
        }

        $groups = Group::query()
            ->select(['groups.id', 'groups.category_id', 'groups.name'])
            ->join('categories', 'categories.id', '=', 'groups.category_id')
            ->where('categories.tournament_id', $tournament->getKey())
            ->orderBy('categories.name')
            ->orderBy('groups.name')
            ->with([
                'teams' => fn ($q) => $q->select(['teams.id', 'teams.display_name', 'teams.seed']),
                'matches' => fn ($q) => $q
                    ->whereIn('status', [
                        MatchStatus::COMPLETED->value,
                        MatchStatus::WALKOVER->value,
                    ])
                    ->with(['sets' => fn ($q) => $q->orderBy('set_number')]),
            ])
            ->get();

        $standings = $groups->map(fn (Group $g): array => [
            'groupId' => $g->getKey(),
            'categoryId' => $g->category_id,
            'name' => $g->name,
            'rows' => $this->rows($g),
        ])->values()->all();

        $categories = $tournament->categories()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($c): array => [
                'id' => $c->getKey(),
                'name' => $c->name,
            ])->values()->all();

        return Inertia::render('Admin/Standings/Index', [
            'standings' => $standings,
            'categories' => $categories,
        ]);
    }

    /**
     * Rank a group with the canonical {@see GroupStandings} ranker and project
     * each row into the camelCase shape the Vue table consumes. rankedRows()
     * returns metrics keyed by teamId (no name), so we splice in display_name
     * from the group's already-loaded teams.
     *
     * @return list<array<string, int|string>>
     */
    private function rows(Group $group): array
    {
        $names = $group->teams->mapWithKeys(
            fn ($t): array => [$t->getKey() => $t->display_name ?? '']
        );

        return array_map(static function (array $r) use ($names): array {
            return [
                'teamId' => $r['teamId'],
                'teamName' => $names->get($r['teamId'], ''),
                'played' => $r['played'],
                'won' => $r['won'],
                'lost' => $r['lost'],
                'setsFor' => $r['setsFor'],
                'setsAgainst' => $r['setsAgainst'],
                'pointsFor' => $r['pointsFor'],
                'pointsAgainst' => $r['pointsAgainst'],
            ];
        }, GroupStandings::rankedRows($group));
    }

    /**
     * The single tournament this admin console manages: prefer the live one,
     * then the most-recently-created. Mirrors the dashboard's selection.
     */
    private function currentTournament(): ?Tournament
    {
        return Tournament::query()
            ->select(['id', 'name'])
            ->orderByRaw(
                "CASE status WHEN '" . TournamentStatus::IN_PROGRESS->value . "' THEN 0 "
                . "WHEN '" . TournamentStatus::SCHEDULED->value . "' THEN 1 ELSE 2 END"
            )
            ->orderByDesc('created_at')
            ->first();
    }
}
