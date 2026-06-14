<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\MatchModel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Match is a reserved PHP keyword, so the model lives at App\Models\MatchModel.
        // Bind the {match} route parameter to it explicitly.
        Route::model('match', MatchModel::class);
    }
}
