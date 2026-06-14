<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MatchModel;
use App\Models\MatchSet;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-scoreboard shape for a single match.
 *
 * Mirrors the NestJS MatchesService::serialize() output one-for-one so the
 * Vue frontend can reuse its existing types without churn:
 *   { id, tournamentId, categoryId, categoryType, categoryName, stage,
 *     roundNumber, bracketSlot, nextMatchId, groupId, court, scheduledAt,
 *     startedAt, completedAt, status, notes, teamA, teamB, winnerId, sets }
 *
 * @mixin Match
 */
class ScoreboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Match $match */
        $match = $this->resource;

        return [
            'id' => $match->id,
            'tournamentId' => $match->tournament_id,
            'categoryId' => $match->category_id,
            'categoryType' => $match->category?->type,
            'categoryName' => $match->category?->name,
            'stage' => $match->stage,
            'roundNumber' => $match->round_number,
            'bracketSlot' => $match->bracket_slot,
            'nextMatchId' => $match->next_match_id,
            'groupId' => $match->group_id,
            'court' => $match->court
                ? ['id' => $match->court->id, 'name' => $match->court->name]
                : null,
            'scheduledAt' => $match->scheduled_at?->toIso8601String(),
            'startedAt' => $match->started_at?->toIso8601String(),
            'completedAt' => $match->completed_at?->toIso8601String(),
            'status' => $match->status,
            'notes' => $match->notes,
            'teamA' => $this->serializeTeam($match->teamA),
            'teamB' => $this->serializeTeam($match->teamB),
            'winnerId' => $match->winner_id,
            'sets' => $match->sets
                ->sortBy('set_number')
                ->values()
                ->map(fn (MatchSet $s): array => [
                    'setNumber' => $s->set_number,
                    'teamAScore' => $s->team_a_score,
                    'teamBScore' => $s->team_b_score,
                    'winnerId' => $s->winner_id,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeTeam(?Team $team): ?array
    {
        if ($team === null) {
            return null;
        }

        return [
            'id' => $team->id,
            'displayName' => $team->display_name ?? '',
            'seed' => $team->seed,
            'players' => $team->players
                ->sortBy(fn (Player $p): int => (int) ($p->pivot->position ?? 0))
                ->values()
                ->map(fn (Player $p): array => [
                    'id' => $p->id,
                    'fullName' => $p->full_name,
                    'position' => (int) ($p->pivot->position ?? 0),
                ])
                ->all(),
        ];
    }
}
