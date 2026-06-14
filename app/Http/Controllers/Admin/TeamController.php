<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamRequest;
use App\Models\Category;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage teams (the competing units within a category — 1 player for singles,
 * 2 for doubles). Players are attached via the `team_players` pivot with a
 * `position` value (1-based ordering within the team).
 */
class TeamController extends Controller
{
    /**
     * GET /manage/teams
     */
    public function index(): Response
    {
        $teams = Team::query()
            ->with([
                'category:id,tournament_id,name,type',
                'category.tournament:id,name',
                'players' => fn ($q) => $q
                    ->select(['players.id', 'players.full_name'])
                    ->orderBy('team_players.position'),
            ])
            ->select(['id', 'category_id', 'display_name', 'seed'])
            ->orderBy('display_name')
            ->get()
            ->map(fn (Team $t): array => $this->serialize($t))
            ->all();

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
        ]);
    }

    /**
     * GET /manage/teams/create
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Teams/Form', [
            'team' => null,
            'categories' => $this->categoryOptions(),
            'players' => $this->playerOptions(),
        ]);
    }

    /**
     * POST /manage/teams
     */
    public function store(TeamRequest $request): RedirectResponse
    {
        $team = Team::query()->create($request->teamAttributes());
        $this->syncPlayers($team, $request->playerIds());

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team created.');
    }

    /**
     * GET /manage/teams/{team}/edit
     */
    public function edit(Team $team): Response
    {
        $team->load([
            'players' => fn ($q) => $q
                ->select(['players.id', 'players.full_name'])
                ->orderBy('team_players.position'),
        ]);

        return Inertia::render('Admin/Teams/Form', [
            'team' => $this->serialize($team),
            'categories' => $this->categoryOptions(),
            'players' => $this->playerOptions(),
        ]);
    }

    /**
     * PUT /manage/teams/{team}
     */
    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->teamAttributes());
        $this->syncPlayers($team, $request->playerIds());

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team updated.');
    }

    /**
     * DELETE /manage/teams/{team}
     */
    public function destroy(Team $team): RedirectResponse
    {
        $team->players()->detach();
        $team->delete();

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team deleted.');
    }

    /**
     * Attach the given players in order, writing the pivot `position` (1-based).
     *
     * @param  list<string>  $playerIds
     */
    private function syncPlayers(Team $team, array $playerIds): void
    {
        $pivot = [];
        $position = 1;
        foreach ($playerIds as $playerId) {
            $pivot[$playerId] = ['position' => $position++];
        }

        $team->players()->sync($pivot);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Team $t): array
    {
        return [
            'id' => $t->getKey(),
            'category_id' => $t->category_id,
            'categoryName' => $t->category?->name,
            'tournamentName' => $t->category?->tournament?->name,
            'display_name' => $t->display_name,
            'seed' => $t->seed,
            'players' => $t->relationLoaded('players')
                ? $t->players->map(fn (Player $p): array => [
                    'id' => $p->getKey(),
                    'full_name' => $p->full_name,
                    'position' => (int) ($p->pivot->position ?? 0),
                ])->values()->all()
                : [],
            // Convenience: ordered list of attached player ids for the form.
            'playerIds' => $t->relationLoaded('players')
                ? $t->players->pluck('id')->values()->all()
                : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->with('tournament:id,name')
            ->select(['id', 'tournament_id', 'name', 'type'])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c): array => [
                'id' => $c->getKey(),
                'name' => $c->name,
                'type' => $c->type?->value,
                'tournamentName' => $c->tournament?->name,
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function playerOptions(): array
    {
        return Player::query()
            ->select(['id', 'full_name'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (Player $p): array => [
                'id' => $p->getKey(),
                'full_name' => $p->full_name,
            ])
            ->all();
    }
}
