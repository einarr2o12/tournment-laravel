<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasUuids;
    use Notifiable;

    /**
     * Auth uses `username` instead of email.
     */
    protected $fillable = [
        'username',
        'password_hash',
        'full_name',
        'role',
        'active',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Laravel uses `password` by default; alias to our column.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Referee assignments owned by this user.
     *
     * @return HasMany<RefereeAssignment, $this>
     */
    public function refereeAssignments(): HasMany
    {
        return $this->hasMany(RefereeAssignment::class, 'user_id');
    }

    /**
     * Score events recorded by this user (nullable on the event side).
     *
     * @return HasMany<ScoreEvent, $this>
     */
    public function scoreEvents(): HasMany
    {
        return $this->hasMany(ScoreEvent::class, 'scored_by_user_id');
    }

    /**
     * Scope to active users only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to a specific role.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeRole($query, UserRole $role)
    {
        return $query->where('role', $role->value);
    }

    /**
     * Scope to referees.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeReferees($query)
    {
        return $query->where('role', UserRole::REFEREE->value);
    }

    /**
     * Scope to admins.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', UserRole::ADMIN->value);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isReferee(): bool
    {
        return $this->role === UserRole::REFEREE;
    }
}
