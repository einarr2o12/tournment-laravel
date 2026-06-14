<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\MatchModel;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single-match detail view for spectators. Eager-loads the full graph
 * (teams + players + sets + score events + court + category) so the page
 * renders in a single query plan.
 */
class MatchController extends Controller
{
    /**
     * GET /matches/{match}
     *
     * Inertia render of Public/Match with the fully serialized match.
     * 404s if the match belongs to a non-public tournament.
     */
    public function show(MatchModel $match): Response
    {
        $match->load([
            'tournament:id,name,status,points_to_win,sets_to_win,deuce_cap,venue',
            'category:id,tournament_id,type,name',
            'court:id,name',
            'group:id,name',
            'teamA:id,category_id,display_name,seed',
            'teamA.players' => fn ($q) => $q->orderBy('team_players.position'),
            'teamB:id,category_id,display_name,seed',
            'teamB.players' => fn ($q) => $q->orderBy('team_players.position'),
            'winner:id,display_name',
            'sets' => fn ($q) => $q->orderBy('set_number'),
            'scoreEvents' => fn ($q) => $q->where('undone', false)->orderBy('scored_at'),
        ]);

        abort_unless($this->tournamentIsPublic($match), 404);

        return Inertia::render('Public/Match', [
            'match' => $this->serializeMatch($match),
        ]);
    }

    private function tournamentIsPublic(MatchModel $match): bool
    {
        return in_array($match->tournament?->status, [
            TournamentStatus::SCHEDULED,
            TournamentStatus::IN_PROGRESS,
            TournamentStatus::COMPLETED,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMatch(MatchModel $match): array
    {
        return [
            'id' => $match->getKey(),
            'tournament' => $match->tournament ? [
                'id' => $match->tournament->getKey(),
                'name' => $match->tournament->name,
                'status' => $match->tournament->status?->value,
                'venue' => $match->tournament->venue,
                'points_to_win' => (int) $match->tournament->points_to_win,
                'sets_to_win' => (int) $match->tournament->sets_to_win,
                'deuce_cap' => (int) $match->tournament->deuce_cap,
            ] : null,
            'category_id' => $match->category_id,
            'category_type' => $match->category?->type?->value,
            'category_name' => $match->category?->name,
            'group' => $match->group ? [
                'id' => $match->group->getKey(),
                'name' => $match->group->name,
            ] : null,
            'stage' => $match->stage,
            'round_number' => $match->round_number,
            'bracket_slot' => $match->bracket_slot,
            'next_match_id' => $match->next_match_id,
            'court' => $match->court ? [
                'id' => $match->court->getKey(),
                'name' => $match->court->name,
            ] : null,
            'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'started_at' => $match->started_at?->toIso8601String(),
            'completed_at' => $match->completed_at?->toIso8601String(),
            'status' => $match->status?->value,
            'notes' => $match->notes,
            'team_a' => $this->serializeTeam($match->teamA),
            'team_b' => $this->serializeTeam($match->teamB),
            'winner_id' => $match->winner_id,
            'sets' => $match->sets->map(fn ($s): array => [
                'set_number' => (int) $s->set_number,
                'team_a_score' => (int) $s->team_a_score,
                'team_b_score' => (int) $s->team_b_score,
                'winner_id' => $s->winner_id,
            ])->values()->all(),
            'score_events' => $match->scoreEvents->map(fn ($e): array => [
                'id' => $e->getKey(),
                'set_number' => (int) $e->set_number,
                'scoring_team_id' => $e->scoring_team_id,
                'team_a_score_after' => (int) $e->team_a_score_after,
                'team_b_score_after' => (int) $e->team_b_score_after,
                'scored_at' => $e->scored_at?->toIso8601String(),
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
