<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tournament_id',
        'name',
        'display_order',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * The tournament this court belongs to.
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    /**
     * Matches assigned to this court.
     *
     * @return HasMany<Match, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'court_id');
    }

    /**
     * Referee assignments for this court.
     *
     * @return HasMany<RefereeAssignment, $this>
     */
    public function refereeAssignments(): HasMany
    {
        return $this->hasMany(RefereeAssignment::class, 'court_id');
    }

    /**
     * Active courts only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Court>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Court>
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Sort by display order (a court's slot on the venue floor).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Court>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Court>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Courts belonging to a specific tournament.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Court>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Court>
     */
    public function scopeForTournament($query, string $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }
}
