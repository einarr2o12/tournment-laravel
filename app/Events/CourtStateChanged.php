<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\ScoreboardResource;
use App\Models\Court;
use App\Models\MatchModel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when the current/next match on a court changes — e.g. a match
 * is started, completed, reassigned, or the queue advances.
 *
 * Public channel "court.{court_id}" is consumed by per-court scoreboards
 * (TV displays, referee tablets) that don't care about the rest of the
 * tournament.
 *
 * Broadcasts synchronously (ShouldBroadcastNow) — no queue worker needed.
 */
class CourtStateChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Court $court;

    public ?MatchModel $current;

    public ?MatchModel $next;

    public function __construct(Court $court, ?MatchModel $current, ?MatchModel $next)
    {
        $this->court = $court;

        $relations = [
            'teamA.players',
            'teamB.players',
            'sets',
            'court',
            'category',
            'winner',
        ];

        $this->current = $current?->loadMissing($relations);
        $this->next = $next?->loadMissing($relations);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('court.'.$this->court->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'court.state-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'court' => [
                'id' => $this->court->id,
                'name' => $this->court->name,
            ],
            'current' => $this->current
                ? (new ScoreboardResource($this->current))->resolve()
                : null,
            'next' => $this->next
                ? (new ScoreboardResource($this->next))->resolve()
                : null,
        ];
    }
}
