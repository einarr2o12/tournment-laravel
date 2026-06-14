<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_sets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->unsignedSmallInteger('set_number');
            $table->unsignedSmallInteger('team_a_score')->default(0);
            $table->unsignedSmallInteger('team_b_score')->default(0);
            $table->uuid('winner_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('winner_id')->references('id')->on('teams')->nullOnDelete();

            $table->unique(['match_id', 'set_number']);
            $table->index('match_id');
            $table->index('winner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_sets');
    }
};
