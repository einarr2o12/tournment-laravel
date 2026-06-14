<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\ScoreboardResource;
use App\Models\MatchModel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a match's score, status, sets, or court changes.
 *
 * Listeners on the public channel "tournament.{tournament_id}" receive the
 * full serialized match payload (teamA, teamB, sets, court) so the public
 * scoreboard can update without re-fetching over HTTP.
 *
 * Broadcasts synchronously (ShouldBroadcastNow) — no queue worker needed.
 */
class MatchUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public MatchModel $match;

    public function __construct(MatchModel $match)
    {
        // Ensure the relations the resource expects are loaded — avoids N+1
        // when this event is queued and the model is restored from the DB.
        $this->match = $match->loadMissing([
            'teamA.players',
            'teamB.players',
            'sets',
            'court',
            'category',
            'winner',
        ]);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tournament.'.$this->match->tournament_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'match.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'match' => (new ScoreboardResource($this->match))->resolve(),
        ];
    }
}
