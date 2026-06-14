<?php

declare(strict_types=1);

namespace App\Http\Controllers\Referee;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\MatchModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single-court scoring view. The referee drills into this from the
 * dashboard to score the current match (and see what comes next on the
 * same court).
 */
class CourtController extends Controller
{
    /**
     * GET /referee/courts/{court}
     *
     * Renders Referee/Court with the current match + queue for the given
     * court. Any authenticated REFEREE or ADMIN can score any court — the
     * role middleware on the route is the only gate (small club setting).
     */
    public function show(Request $request, Court $court): Response
    {
        $matchEagerLoads = $this->matchEagerLoads();

        $current = MatchModel::query()
            ->where('court_id', $court->getKey())
            ->where('status', MatchStatus::IN_PROGRESS->value)
            ->with($matchEagerLoads)
            ->first();

        $queue = MatchModel::query()
            ->where('court_id', $court->getKey())
            ->where('status', MatchStatus::SCHEDULED->value)
            ->orderByRaw('scheduled_at IS NULL, scheduled_at ASC')
            ->orderBy('created_at')
            ->with($matchEagerLoads)
            ->limit(10)
            ->get();

        $next = $current ? null : $queue->first();
        $queueTail = $current ? $queue : $queue->slice(1)->values();

        $court->loadMissing('tournament:id,name,status,points_to_win,sets_to_win,deuce_cap');

        return Inertia::render('Referee/Court', [
            'card' => [
                'court' => [
                    'id' => $court->getKey(),
                    'name' => $court->name,
                    'tournament' => $court->tournament ? [
                        'id' => $court->tournament->getKey(),
                        'name' => $court->tournament->name,
                        'status' => $court->tournament->status?->value,
                        'points_to_win' => (int) $court->tournament->points_to_win,
                        'sets_to_win' => (int) $court->tournament->sets_to_win,
                        'deuce_cap' => (int) $court->tournament->deuce_cap,
                    ] : null,
                ],
                'current' => $current ? $this->serializeMatch($current) : null,
                'next' => $next ? $this->serializeMatch($next) : null,
                'queue' => $queueTail->map(fn (MatchModel $m): array => $this->serializeMatch($m))->values()->all(),
            ],
        ]);
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
            'categoryName' => $match->category?->name,
            'stage' => $match->stage?->value,
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
            'notes' => $match->notes,
            'teamA' => $this->serializeTeam($match->teamA),
            'teamB' => $this->serializeTeam($match->teamB),
            'winnerId' => $match->winner_id,
            'sets' => $match->sets->map(fn ($s): array => [
                'setNumber' => (int) $s->set_number,
                'teamAScore' => (int) $s->team_a_score,
                'teamBScore' => (int) $s->team_b_score,
                'winnerId' => $s->winner_id,
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
            'seed' => $team->seed,
            'players' => $team->players->map(fn ($p): array => [
                'id' => $p->getKey(),
                'full_name' => $p->full_name,
                'position' => (int) $p->pivot->position,
            ])->values()->all(),
        ];
    }
}
