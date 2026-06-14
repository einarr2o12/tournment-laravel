<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchModel extends Model
{
    use HasUuids;

    protected $table = 'matches';

    protected $fillable = [
        'tournament_id',
        'category_id',
        'court_id',
        'group_id',
        'stage',
        'round_number',
        'bracket_slot',
        'next_match_id',
        'loser_next_match_id',
        'scheduled_at',
        'started_at',
        'completed_at',
        'status',
        'team_a_id',
        'team_b_id',
        'team_a_source',
        'team_b_source',
        'winner_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => \App\Enums\MatchStage::class,
            'status' => \App\Enums\MatchStatus::class,
            'round_number' => 'integer',
            'bracket_slot' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'court_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function loserNextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'loser_next_match_id');
    }

    public function prevMatches(): HasMany
    {
        return $this->hasMany(self::class, 'next_match_id');
    }

    public function sets(): HasMany
    {
        return $this->hasMany(MatchSet::class, 'match_id');
    }

    public function scoreEvents(): HasMany
    {
        return $this->hasMany(ScoreEvent::class, 'match_id');
    }
}
