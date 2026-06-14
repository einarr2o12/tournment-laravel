<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlayerRequest;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage players registered in a tournament. Mirrors the canonical
 * {@see CourtController} CRUD shape.
 */
class PlayerController extends Controller
{
    /**
     * GET /manage/players
     */
    public function index(): Response
    {
        $players = Player::query()
            ->with('tournament:id,name')
            ->select(['id', 'tournament_id', 'full_name', 'gender', 'club', 'contact'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (Player $p): array => $this->serialize($p))
            ->all();

        return Inertia::render('Admin/Players/Index', [
            'players' => $players,
        ]);
    }

    /**
     * GET /manage/players/create
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Players/Form', [
            'player' => null,
            'tournaments' => $this->tournamentOptions(),
            'genders' => Gender::labels(),
        ]);
    }

    /**
     * POST /manage/players
     */
    public function store(PlayerRequest $request): RedirectResponse
    {
        Player::query()->create($request->validated());

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Player created.');
    }

    /**
     * GET /manage/players/{player}/edit
     */
    public function edit(Player $player): Response
    {
        return Inertia::render('Admin/Players/Form', [
            'player' => $this->serialize($player),
            'tournaments' => $this->tournamentOptions(),
            'genders' => Gender::labels(),
        ]);
    }

    /**
     * PUT /manage/players/{player}
     */
    public function update(PlayerRequest $request, Player $player): RedirectResponse
    {
        $player->update($request->validated());

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Player updated.');
    }

    /**
     * DELETE /manage/players/{player}
     */
    public function destroy(Player $player): RedirectResponse
    {
        $player->delete();

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Player deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Player $p): array
    {
        return [
            'id' => $p->getKey(),
            'tournament_id' => $p->tournament_id,
            'tournamentName' => $p->tournament?->name,
            'full_name' => $p->full_name,
            'gender' => $p->gender?->value,
            'club' => $p->club,
            'contact' => $p->contact,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function tournamentOptions(): array
    {
        return Tournament::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Tournament $t): array => [
                'id' => $t->getKey(),
                'name' => $t->name,
            ])
            ->all();
    }
}
