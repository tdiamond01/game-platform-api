<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('experience_points')->default(0);
            $table->unsignedBigInteger('total_score')->default(0);
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('games_won')->default(0);
            $table->unsignedInteger('best_score')->nullable();
            $table->unsignedInteger('best_time_seconds')->nullable();
            $table->decimal('average_score', 10, 2)->nullable();
            $table->decimal('average_time_seconds', 10, 2)->nullable();
            $table->unsignedInteger('total_hints_used')->default(0);
            $table->unsignedInteger('daily_challenges_completed')->default(0);
            $table->timestamp('last_played_at')->nullable();
            $table->json('stats')->nullable(); // Game-specific stats
            $table->timestamps();

            $table->unique(['player_id', 'game_id']);
            $table->index(['game_id', 'level']);
            $table->index(['game_id', 'total_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_progress');
    }
};
