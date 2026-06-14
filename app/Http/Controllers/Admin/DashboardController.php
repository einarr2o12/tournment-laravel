<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin dashboard overview. Surfaces the (typically single) active tournament
 * plus headline counts across the managed resources so the operator gets an
 * at-a-glance health check before drilling into a resource.
 */
class DashboardController extends Controller
{
    /**
     * GET /manage
     */
    public function index(): Response
    {
        // Prefer the live tournament, then the most-recently-created one.
        $tournament = Tournament::query()
            ->select([
                'id', 'name', 'venue', 'format', 'status',
                'points_to_win', 'sets_to_win', 'deuce_cap',
                'start_date', 'end_date',
            ])
            ->orderByRaw(
                "CASE status WHEN '" . TournamentStatus::IN_PROGRESS->value . "' THEN 0 "
                . "WHEN '" . TournamentStatus::SCHEDULED->value . "' THEN 1 ELSE 2 END"
            )
            ->orderByDesc('created_at')
            ->first();

        $matchCountsByStatus = MatchModel::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('Admin/Dashboard', [
            'tournament' => $tournament ? $this->serializeTournament($tournament) : null,
            'counts' => [
                'tournaments' => Tournament::query()->count(),
                'courts' => Court::query()->count(),
                'categories' => Category::query()->count(),
                'teams' => Team::query()->count(),
                'players' => Player::query()->count(),
                'matches' => [
                    'total' => (int) $matchCountsByStatus->sum(),
                    'scheduled' => (int) $matchCountsByStatus->get(MatchStatus::SCHEDULED->value, 0),
                    'inProgress' => (int) $matchCountsByStatus->get(MatchStatus::IN_PROGRESS->value, 0),
                    'completed' => (int) $matchCountsByStatus->get(MatchStatus::COMPLETED->value, 0),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTournament(Tournament $t): array
    {
        return [
            'id' => $t->getKey(),
            'name' => $t->name,
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
}
