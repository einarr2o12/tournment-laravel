<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Schema::table('matches', ...) — add the self-referential FK after the table exists
        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('next_match_id')->references('id')->on('matches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['next_match_id']);
        });
    }
};
