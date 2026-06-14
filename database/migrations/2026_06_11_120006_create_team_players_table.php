<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_players', function (Blueprint $table) {
            $table->uuid('team_id');
            $table->uuid('player_id');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();

            $table->primary(['team_id', 'player_id']);

            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();

            $table->unique(['team_id', 'position']);
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_players');
    }
};
