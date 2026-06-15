<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MatchStage;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\MatchModel;
use App\Models\Tournament;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only admin bracket view. Surfaces the knockout matches
 * (SEMIFINAL / FINAL / THIRD_PLACE) for the managed tournament, serialized in
 * the same camelCase shape the public bracket tree consumes so the two views
 * render identically.
 */
class BracketController extends Controller
{
    /**
     * GET /admin/bracket
     *
     * Targets the single (live, else most-recent) tournament. Returns the
     * knockout matches plus the category list for the front-end filter.
     * Teams + category are eager-loaded to avoid N+1.
     */
    public function index(): Response
    {
        $tournament = $this->currentTournament();

        if ($tournament === null) {
            return Inertia::render('Admin/Bracket/Index', [
                'matches' => [],
                'categories' => [],
            ]);
        }

        $matches = MatchModel::query()
            ->where('tournament_id', $tournament->getKey())
            ->whereIn('stage', [
                MatchStage::SEMIFINAL->value,
                MatchStage::FINAL->value,
                MatchStage::THIRD_PLACE->value,
            ])
            ->with([
                'teamA:id,display_name',
                'teamB:id,display_name',
            ])
            ->orderByRaw('bracket_slot IS NULL, bracket_slot ASC')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MatchModel $m): array => $this->serializeMatch($m))
            ->values()
            ->all();

        $categories = $tournament->categories()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($c): array => [
                'id' => $c->getKey(),
                'name' => $c->name,
            ])->values()->all();

        return Inertia::render('Admin/Bracket/Index', [
            'matches' => $matches,
            'categories' => $categories,
        ]);
    }

    /**
     * Serialize a knockout match in the camelCase shape the bracket tree
     * consumes (mirrors the public page).
     *
     * @return array<string, mixed>
     */
    private function serializeMatch(MatchModel $match): array
    {
        return [
            'id' => $match->getKey(),
            'stage' => $match->stage?->value,
            'bracketSlot' => $match->bracket_slot,
            'categoryId' => $match->category_id,
            'teamA' => $this->serializeTeam($match->teamA),
            'teamB' => $this->serializeTeam($match->teamB),
            'teamASource' => $match->team_a_source,
            'teamBSource' => $match->team_b_source,
            'winnerId' => $match->winner_id,
            'scheduledAt' => $match->scheduled_at?->toIso8601String(),
            'status' => $match->status?->value,
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
