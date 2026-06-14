<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tournament_id');
            $table->string('full_name', 160);
            $table->enum('gender', ['MALE', 'FEMALE']);
            $table->string('club', 160)->nullable();
            $table->string('contact', 160)->nullable();
            $table->timestamps();

            $table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();

            $table->index('tournament_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
