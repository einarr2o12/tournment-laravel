<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('score_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->unsignedSmallInteger('set_number');
            $table->uuid('scoring_team_id');
            $table->uuid('scored_by_user_id')->nullable();
            $table->unsignedSmallInteger('team_a_score_after');
            $table->unsignedSmallInteger('team_b_score_after');
            $table->boolean('undone')->default(false);
            $table->timestamp('scored_at')->useCurrent();
            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('scoring_team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('scored_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['match_id', 'scored_at']);
            $table->index('scoring_team_id');
            $table->index('scored_by_user_id');
            $table->index(['match_id', 'undone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_events');
    }
};
