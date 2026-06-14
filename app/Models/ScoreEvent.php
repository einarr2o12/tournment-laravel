<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreEvent extends Model
{
    use HasUuids;

    protected $table = 'score_events';

    protected $fillable = [
        'match_id',
        'set_number',
        'scoring_team_id',
        'scored_by_user_id',
        'team_a_score_after',
        'team_b_score_after',
        'undone',
        'scored_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'team_a_score_after' => 'integer',
            'team_b_score_after' => 'integer',
            'undone' => 'boolean',
            'scored_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    public function scoringTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'scoring_team_id');
    }

    public function scoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by_user_id');
    }

    /**
     * Only events that have not been undone.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('undone', false);
    }
}
