<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BracketController as AdminBracketController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CourtController as AdminCourtController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\Admin\MatchController as AdminMatchController;
use App\Http\Controllers\Admin\PlayerController as AdminPlayerController;
use App\Http\Controllers\Admin\StandingsController as AdminStandingsController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\TournamentController as AdminTournamentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Public\LiveController;
use App\Http\Controllers\Public\TournamentController as PublicTournamentController;
use App\Http\Controllers\Referee\CourtController as RefereeCourtController;
use App\Http\Controllers\Referee\DashboardController as RefereeDashboardController;
use App\Http\Controllers\Referee\ScoringController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Inertia + Vue)
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicTournamentController::class, 'index'])->name('public.index');
Route::get('/tournament/{tournament}', [PublicTournamentController::class, 'show'])
    ->name('public.tournament.show');
Route::get('/tournament/{tournament}/live', [LiveController::class, 'index'])
    ->name('public.tournament.live');
Route::get('/tournament/{tournament}/draw/{category}', [PublicTournamentController::class, 'bracket'])
    ->name('public.tournament.bracket');

/*
|--------------------------------------------------------------------------
| Auth Routes (Referee + admin login — admin console lives at /admin)
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', LogoutController::class)->name('logout');

/*
|--------------------------------------------------------------------------
| Referee Routes (Inertia + Vue — big tap-target scoring)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:REFEREE,ADMIN'])
    ->prefix('referee')
    ->name('referee.')
    ->group(function (): void {
        Route::get('/', [RefereeDashboardController::class, 'index'])->name('dashboard');

        Route::get('courts/{court}', [RefereeCourtController::class, 'show'])->name('courts.show');

        Route::post('matches/{match}/start', [ScoringController::class, 'start'])
            ->name('matches.start');
        Route::post('matches/{match}/point', [ScoringController::class, 'point'])
            ->name('matches.point');
        Route::post('matches/{match}/undo', [ScoringController::class, 'undo'])
            ->name('matches.undo');
        Route::post('matches/{match}/walkover', [ScoringController::class, 'walkover'])
            ->name('matches.walkover');
    });

/*
|--------------------------------------------------------------------------
| Admin Console (Inertia + Vue)
|--------------------------------------------------------------------------
| Served under /admin. Filament has been removed; this group owns /admin.
*/

Route::middleware(['auth', 'role:ADMIN'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Tournaments — settings only (single-tournament focus); list + edit.
        Route::get('tournaments', [AdminTournamentController::class, 'index'])
            ->name('tournaments.index');
        Route::get('tournaments/{tournament}/edit', [AdminTournamentController::class, 'edit'])
            ->name('tournaments.edit');
        Route::put('tournaments/{tournament}', [AdminTournamentController::class, 'update'])
            ->name('tournaments.update');

        // Courts — full CRUD (canonical reference resource).
        Route::get('courts', [AdminCourtController::class, 'index'])->name('courts.index');
        Route::get('courts/create', [AdminCourtController::class, 'create'])->name('courts.create');
        Route::post('courts', [AdminCourtController::class, 'store'])->name('courts.store');
        Route::get('courts/{court}/edit', [AdminCourtController::class, 'edit'])->name('courts.edit');
        Route::put('courts/{court}', [AdminCourtController::class, 'update'])->name('courts.update');
        Route::delete('courts/{court}', [AdminCourtController::class, 'destroy'])->name('courts.destroy');

        // Categories — full CRUD.
        Route::get('categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        // Players — full CRUD.
        Route::get('players', [AdminPlayerController::class, 'index'])->name('players.index');
        Route::get('players/create', [AdminPlayerController::class, 'create'])->name('players.create');
        Route::post('players', [AdminPlayerController::class, 'store'])->name('players.store');
        Route::get('players/{player}/edit', [AdminPlayerController::class, 'edit'])->name('players.edit');
        Route::put('players/{player}', [AdminPlayerController::class, 'update'])->name('players.update');
        Route::delete('players/{player}', [AdminPlayerController::class, 'destroy'])->name('players.destroy');

        // Teams — full CRUD (attaches players via team_players pivot).
        Route::get('teams', [AdminTeamController::class, 'index'])->name('teams.index');
        Route::get('teams/create', [AdminTeamController::class, 'create'])->name('teams.create');
        Route::post('teams', [AdminTeamController::class, 'store'])->name('teams.store');
        Route::get('teams/{team}/edit', [AdminTeamController::class, 'edit'])->name('teams.edit');
        Route::put('teams/{team}', [AdminTeamController::class, 'update'])->name('teams.update');
        Route::delete('teams/{team}', [AdminTeamController::class, 'destroy'])->name('teams.destroy');

        // Matches — schedule edits (court/time/status) + admin result entry.
        Route::get('matches', [AdminMatchController::class, 'index'])->name('matches.index');
        Route::get('matches/{match}/edit', [AdminMatchController::class, 'edit'])->name('matches.edit');
        Route::put('matches/{match}', [AdminMatchController::class, 'update'])->name('matches.update');
        Route::put('matches/{match}/result', [AdminMatchController::class, 'result'])->name('matches.result');
        Route::put('matches/{match}/walkover', [AdminMatchController::class, 'walkover'])->name('matches.walkover');
        Route::put('matches/{match}/reset', [AdminMatchController::class, 'reset'])->name('matches.reset');

        // Groups — read-only view.
        Route::get('groups', [AdminGroupController::class, 'index'])->name('groups.index');

        // Standings — read-only round-robin tables (per group, per category).
        Route::get('standings', [AdminStandingsController::class, 'index'])->name('standings.index');

        // Bracket — read-only knockout tree (semifinals / final / third place).
        Route::get('bracket', [AdminBracketController::class, 'index'])->name('bracket.index');

        // Users — full CRUD (manage referees/admins).
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    });
