<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Group-stage scoring overrides. NULLABLE so that when unset the scoring
     * engine falls back to the main (knockout) config — backward compatible.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_points_to_win')->nullable()->after('deuce_cap');
            $table->unsignedSmallInteger('group_sets_to_win')->nullable()->after('group_points_to_win');
            $table->unsignedSmallInteger('group_deuce_cap')->nullable()->after('group_sets_to_win');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['group_points_to_win', 'group_sets_to_win', 'group_deuce_cap']);
        });
    }
};
