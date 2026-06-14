<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Encodes where each knockout slot is fed from before teams are
            // resolved. Conventions:
            //   "G:A:1" group A standings pos 1, "G:B:2" group B pos 2,
            //   "G::1"  single-group pos 1 (empty group letter),
            //   "W:<bracket_slot>" winner of that slot's match (same category),
            //   "L:<bracket_slot>" loser of that slot's match (same category).
            $table->string('team_a_source')->nullable()->after('team_a_id');
            $table->string('team_b_source')->nullable()->after('team_b_id');

            // Forward pointer for the LOSER of this match (mirrors next_match_id
            // which carries the winner). Used to feed the bronze/third-place match.
            $table->uuid('loser_next_match_id')->nullable()->after('next_match_id');

            $table->foreign('loser_next_match_id')->references('id')->on('matches')->nullOnDelete();
            $table->index('loser_next_match_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['loser_next_match_id']);
            $table->dropIndex(['loser_next_match_id']);
            $table->dropColumn(['team_a_source', 'team_b_source', 'loser_next_match_id']);
        });
    }
};
