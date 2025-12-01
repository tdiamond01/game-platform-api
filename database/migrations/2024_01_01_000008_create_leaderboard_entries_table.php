<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('daily_challenge_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('period', 20); // daily, weekly, monthly, alltime, challenge
            $table->string('period_key', 50); // 2024-01-15, 2024-W03, 2024-01, all, challenge_123
            $table->integer('score')->default(0);
            $table->integer('time_seconds')->nullable();
            $table->integer('games_count')->default(1);
            $table->integer('rank')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'game_id', 'period', 'period_key'], 'leaderboard_unique');
            $table->index(['game_id', 'period', 'period_key', 'score'], 'leaderboard_ranking');
            $table->index(['daily_challenge_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};
