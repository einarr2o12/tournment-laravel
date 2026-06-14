<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('venue', 200)->nullable();
            $table->enum('format', ['SINGLE_ELIMINATION', 'ROUND_ROBIN', 'GROUP_KNOCKOUT', 'SWISS'])->default('GROUP_KNOCKOUT');
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'ARCHIVED'])->default('DRAFT');
            $table->unsignedSmallInteger('points_to_win')->default(21);
            $table->unsignedSmallInteger('sets_to_win')->default(2);
            $table->unsignedSmallInteger('deuce_cap')->default(30);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
