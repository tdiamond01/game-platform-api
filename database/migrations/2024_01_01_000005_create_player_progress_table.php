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
            $table->integer('level')->default(1);
            $table->integer('experience_points')->default(0);
            $table->integer('total_score')->default(0);
            $table->integer('games_played')->default(0);
            $table->integer('games_won')->default(0);
            $table->integer('best_score')->nullable();
            $table->integer('best_time_seconds')->nullable();
            $table->float('average_score')->nullable();
            $table->float('average_time_seconds')->nullable();
            $table->integer('total_hints_used')->default(0);
            $table->integer('daily_challenges_completed')->default(0);
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
