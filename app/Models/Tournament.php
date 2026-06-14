<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tournament extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'venue',
        'format',
        'status',
        'points_to_win',
        'sets_to_win',
        'deuce_cap',
        'group_points_to_win',
        'group_sets_to_win',
        'group_deuce_cap',
        'start_date',
        'end_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => TournamentFormat::class,
            'status' => TournamentStatus::class,
            'points_to_win' => 'integer',
            'sets_to_win' => 'integer',
            'deuce_cap' => 'integer',
            'group_points_to_win' => 'integer',
            'group_sets_to_win' => 'integer',
            'group_deuce_cap' => 'integer',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    /**
     * Courts owned by this tournament.
     *
     * @return HasMany<Court, $this>
     */
    public function courts(): HasMany
    {
        return $this->hasMany(Court::class, 'tournament_id');
    }

    /**
     * Categories within this tournament.
     *
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'tournament_id');
    }

    /**
     * Players registered in this tournament.
     *
     * @return HasMany<Player, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'tournament_id');
    }

    /**
     * Matches scheduled for this tournament.
     *
     * @return HasMany<Match, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'tournament_id');
    }

    /**
     * Tournaments visible on the public site: SCHEDULED, IN_PROGRESS, COMPLETED.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Tournament>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Tournament>
     */
    public function scopePublic($query)
    {
        return $query->whereIn('status', [
            TournamentStatus::SCHEDULED->value,
            TournamentStatus::IN_PROGRESS->value,
            TournamentStatus::COMPLETED->value,
        ]);
    }

    /**
     * Currently live tournaments (status IN_PROGRESS).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Tournament>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Tournament>
     */
    public function scopeLive($query)
    {
        return $query->where('status', TournamentStatus::IN_PROGRESS->value);
    }

    /**
     * Tournaments still being prepared (DRAFT).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Tournament>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Tournament>
     */
    public function scopeDrafts($query)
    {
        return $query->where('status', TournamentStatus::DRAFT->value);
    }

    /**
     * Filter by an explicit status enum.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Tournament>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Tournament>
     */
    public function scopeStatus($query, TournamentStatus $status)
    {
        return $query->where('status', $status->value);
    }
}
