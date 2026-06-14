<?php

declare(strict_types=1);

namespace App\Http\Controllers\Referee;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\MatchModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The referee landing page. Renders every active court of every public
 * tournament that is SCHEDULED or IN_PROGRESS — any referee can pick any
 * court (small club setting; role middleware guards the route). Each card
 * carries its current in-progress match, next scheduled match, and the
 * remaining queue. All heavy reads are eager-loaded in one query per
 * relation to stay well below 100ms.
 */
class DashboardController extends Controller
{
    /**
     * GET /referee
     *
     * Inertia render of Referee/Dashboard with the courts payload.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Referee/Dashboard', [
            'courts' => $this->buildCourtPayload(),
        ]);
    }

    /**
     * Build the per-court current/next/queue payload. Sourced from every
     * active court of SCHEDULED / IN_PROGRESS tournaments. Aggressively
     * eager loads teams, players, sets, court and category so the
     * front-end can render without follow-up requests.
     *
     * @return list<array<string, mixed>>
     */
    private function buildCourtPayload(): array
    {
        $courts = Court::query()
            ->select(['id', 'tournament_id', 'name', 'display_order', 'active'])
            ->active()
            ->whereHas('tournament', fn ($q) => $q->whereIn('status', [
                TournamentStatus::SCHEDULED->value,
                TournamentStatus::IN_PROGRESS->value,
            ]))
            ->with(['tournament:id,name,status'])
            ->ordered()
            ->get();

        if ($courts->isEmpty()) {
            return [];
        }

        $courtIds = $courts->pluck('id')->all();

        // Pull every match relevant to these courts in two queries
        // (one for IN_PROGRESS, one for SCHEDULED with a cap per court).
        $matchEagerLoads = $this->matchEagerLoads();

        $currentMatches = MatchModel::query()
            ->whereIn('court_id', $courtIds)
            ->where('status', MatchStatus::IN_PROGRESS->value)
            ->with($matchEagerLoads)
            ->get()
            ->keyBy('court_id');

        $upcoming = MatchModel::query()
            ->whereIn('court_id', $courtIds)
            ->where('status', MatchStatus::SCHEDULED->value)
            ->orderByRaw('scheduled_at IS NULL, scheduled_at ASC')
            ->orderBy('created_at')
            ->with($matchEagerLoads)
            ->get()
            ->groupBy('court_id');

        return $courts->map(function (Court $court) use ($currentMatches, $upcoming): array {
            $current = $currentMatches->get($court->getKey());
            $queue = $upcoming->get($court->getKey(), collect());

            // If a match is already running, the "next" slot stays empty and
            // the full queue is up-for-grabs. Otherwise the first scheduled
            // match becomes "next" and the rest is the queue.
            $next = $current ? null : $queue->first();
            $queueTail = $current ? $queue : $queue->slice(1)->values();

            return [
                'court' => [
                    'id' => $court->getKey(),
                    'name' => $court->name,
                    'tournament_id' => $court->tournament_id,
                    'tournamentName' => $court->tournament?->name,
                ],
                'current' => $current ? $this->serializeMatch($current) : null,
                'next' => $next ? $this->serializeMatch($next) : null,
                'queue' => $queueTail->map(fn (MatchModel $m): array => $this->serializeMatch($m))->values()->all(),
            ];
        })->all();
    }

    /**
     * Eager-load spec mirrored from {@see App\Services\Scoring\ScoringService}
     * so dashboard and scoring views share the same shape.
     *
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
     * Flatten a Match model into the same JSON shape the NestJS
     * MatchesService.serialize() emitted, so the front-end is portable.
     *
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
