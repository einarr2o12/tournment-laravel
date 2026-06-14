<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;

/**
 * Live scoreboard endpoint. JSON-only (called from the public-facing Vue
 * scoreboard via WebSocket + polling fallback). Returns every active court
 * in the tournament with its IN_PROGRESS match (or the next SCHEDULED
 * one if the court is idle).
 */
class LiveController extends Controller
{
    /**
     * GET /api/public/tournaments/{tournament}/live
     *
     * Returns a list of { court, current, next } payloads. Reads are
     * batched so the entire scoreboard fits in three queries regardless
     * of the number of courts.
     */
    public function index(Tournament $tournament): JsonResponse
    {
        abort_unless($this->isPublic($tournament), 404);

        $courts = $tournament->courts()
            ->select(['id', 'tournament_id', 'name', 'display_order', 'active'])
            ->where('active', true)
            ->orderBy('display_order')
            ->get();

        if ($courts->isEmpty()) {
            return response()->json([]);
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

        $payload = $courts->map(function (Court $court) use ($currentByCourt, $scheduledByCourt): array {
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

        return response()->json($payload);
    }

    private function isPublic(Tournament $t): bool
    {
        return in_array($t->status, [
            TournamentStatus::SCHEDULED,
            TournamentStatus::IN_PROGRESS,
            TournamentStatus::COMPLETED,
        ], true);
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
            'teamA.players' => fn ($q) => $q->orderBy('team_players.position'),
            'teamB:id,category_id,display_name,seed',
            'teamB.players' => fn ($q) => $q->orderBy('team_players.position'),
            'winner:id,display_name',
            'sets' => fn ($q) => $q->orderBy('set_number'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMatch(MatchModel $match): array
    {
        return [
            'id' => $match->getKey(),
            'tournament_id' => $match->tournament_id,
            'category_id' => $match->category_id,
            'category_type' => $match->category?->type?->value,
            'category_name' => $match->category?->name,
            'stage' => $match->stage,
            'round_number' => $match->round_number,
            'bracket_slot' => $match->bracket_slot,
            'next_match_id' => $match->next_match_id,
            'group_id' => $match->group_id,
            'court' => $match->court ? [
                'id' => $match->court->getKey(),
                'name' => $match->court->name,
            ] : null,
            'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'started_at' => $match->started_at?->toIso8601String(),
            'completed_at' => $match->completed_at?->toIso8601String(),
            'status' => $match->status?->value,
            'team_a' => $this->serializeTeam($match->teamA),
            'team_b' => $this->serializeTeam($match->teamB),
            'winner_id' => $match->winner_id,
            'sets' => $match->sets->map(fn ($s): array => [
                'set_number' => (int) $s->set_number,
                'team_a_score' => (int) $s->team_a_score,
                'team_b_score' => (int) $s->team_b_score,
                'winner_id' => $s->winner_id,
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
            'display_name' => $team->display_name ?? '',
            'seed' => $team->seed,
            'players' => $team->players->map(fn ($p): array => [
                'id' => $p->getKey(),
                'full_name' => $p->full_name,
                'position' => (int) $p->pivot->position,
            ])->values()->all(),
        ];
    }
}
