<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'teams';

    protected $fillable = [
        'category_id',
        'display_name',
        'seed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return BelongsToMany<Player, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'team_players', 'team_id', 'player_id')
            ->using(TeamPlayer::class)
            ->withPivot('position')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_teams', 'team_id', 'group_id')
            ->using(GroupTeam::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<Match, $this>
     */
    public function matchesAsTeamA(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'team_a_id');
    }

    /**
     * @return HasMany<Match, $this>
     */
    public function matchesAsTeamB(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'team_b_id');
    }

    /**
     * @return HasMany<Match, $this>
     */
    public function matchesWon(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'winner_id');
    }
}
