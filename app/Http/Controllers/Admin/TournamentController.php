<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TournamentUpdateRequest;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage tournament settings. The product is single-tournament focused, so
 * there is no create/destroy here — operators edit the settings of existing
 * tournaments (a list is still provided so multiple tournaments can coexist).
 */
class TournamentController extends Controller
{
    /**
     * GET /manage/tournaments
     */
    public function index(): Response
    {
        $tournaments = Tournament::query()
            ->withCount(['courts', 'categories', 'players', 'matches'])
            ->select([
                'id', 'name', 'venue', 'format', 'status',
                'points_to_win', 'sets_to_win', 'deuce_cap',
                'start_date', 'end_date',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Tournament $t): array => $this->serialize($t))
            ->all();

        return Inertia::render('Admin/Tournaments/Index', [
            'tournaments' => $tournaments,
        ]);
    }

    /**
     * GET /manage/tournaments/{tournament}/edit
     */
    public function edit(Tournament $tournament): Response
    {
        return Inertia::render('Admin/Tournaments/Edit', [
            'tournament' => $this->serialize($tournament),
            'statuses' => TournamentStatus::labels(),
            'formats' => TournamentFormat::labels(),
        ]);
    }

    /**
     * PUT /manage/tournaments/{tournament}
     */
    public function update(TournamentUpdateRequest $request, Tournament $tournament): RedirectResponse
    {
        $tournament->update($request->validated());

        return redirect()
            ->route('admin.tournaments.edit', ['tournament' => $tournament->getKey()])
            ->with('success', 'Tournament settings saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Tournament $t): array
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
            'group_points_to_win' => $t->group_points_to_win,
            'group_sets_to_win' => $t->group_sets_to_win,
            'group_deuce_cap' => $t->group_deuce_cap,
            // <input type="date"> wants YYYY-MM-DD; full ISO also kept for display.
            'startDate' => $t->start_date?->toDateString(),
            'endDate' => $t->end_date?->toDateString(),
            'courtsCount' => (int) ($t->courts_count ?? 0),
            'categoriesCount' => (int) ($t->categories_count ?? 0),
            'playersCount' => (int) ($t->players_count ?? 0),
            'matchesCount' => (int) ($t->matches_count ?? 0),
        ];
    }
}
