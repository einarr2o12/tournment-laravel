<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Only private/presence channels need an auth callback. The public
| channels — "tournament.{tournament_id}" and "court.{court_id}" — are
| open to anyone with the Reverb credentials, so we register them here
| as documentation-only stubs that always allow access.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channel: real-time updates for a tournament (matches, standings, etc.)
Broadcast::channel('tournament.{tournament}', function ($user, $tournament) {
    return true;
});

// Public channel: real-time scoring updates for a specific court.
Broadcast::channel('court.{court}', function ($user, $court) {
    return true;
});
