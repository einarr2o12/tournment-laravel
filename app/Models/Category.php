<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tournament_id',
        'type',
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
        ];
    }

    /**
     * The tournament this category belongs to.
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    /**
     * Teams registered in this category.
     *
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'category_id');
    }

    /**
     * Groups (round-robin pools) inside this category.
     *
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'category_id');
    }

    /**
     * Matches that belong to this category (group + knockout).
     *
     * @return HasMany<Match, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'category_id');
    }

    /**
     * Filter by category type (e.g. MENS_SINGLES).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Category>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Category>
     */
    public function scopeOfType($query, CategoryType $type)
    {
        return $query->where('type', $type->value);
    }

    /**
     * Categories belonging to a specific tournament.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Category>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Category>
     */
    public function scopeForTournament($query, string $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    /**
     * Doubles categories (used for team-size validation).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Category>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Category>
     */
    public function scopeDoubles($query)
    {
        return $query->whereIn('type', [
            CategoryType::MENS_DOUBLES->value,
            CategoryType::WOMENS_DOUBLES->value,
            CategoryType::MIXED_DOUBLES->value,
        ]);
    }

    /**
     * Singles categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Category>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Category>
     */
    public function scopeSingles($query)
    {
        return $query->whereIn('type', [
            CategoryType::MENS_SINGLES->value,
            CategoryType::WOMENS_SINGLES->value,
        ]);
    }
}
