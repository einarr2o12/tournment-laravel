<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_teams', function (Blueprint $table) {
            $table->uuid('group_id');
            $table->uuid('team_id');
            $table->timestamps();

            $table->primary(['group_id', 'team_id']);

            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();

            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_teams');
    }
};
