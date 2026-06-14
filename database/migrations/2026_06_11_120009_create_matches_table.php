<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tournament_id');
            $table->uuid('category_id');
            $table->uuid('court_id')->nullable();
            $table->uuid('group_id')->nullable();
            $table->enum('stage', ['GROUP', 'ROUND_OF_64', 'ROUND_OF_32', 'ROUND_OF_16', 'QUARTERFINAL', 'SEMIFINAL', 'FINAL', 'THIRD_PLACE']);
            $table->unsignedSmallInteger('round_number')->nullable();
            $table->unsignedInteger('bracket_slot')->nullable();
            $table->uuid('next_match_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'WALKOVER', 'CANCELLED'])->default('SCHEDULED');
            $table->uuid('team_a_id')->nullable();
            $table->uuid('team_b_id')->nullable();
            $table->uuid('winner_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('court_id')->references('id')->on('courts')->nullOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->foreign('team_a_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('team_b_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('winner_id')->references('id')->on('teams')->nullOnDelete();
            // next_match_id FK added in follow-up migration to avoid self-reference ordering issues

            $table->index(['tournament_id', 'status']);
            $table->index('category_id');
            $table->index(['court_id', 'status']);
            $table->index('group_id');
            $table->index('team_a_id');
            $table->index('team_b_id');
            $table->index('winner_id');
            $table->index('next_match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
