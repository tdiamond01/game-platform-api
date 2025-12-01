<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('daily_challenge_id')->nullable()->constrained()->onDelete('set null');
            $table->string('session_type', 20)->default('practice'); // daily, practice, endless, timed
            $table->string('status', 20)->default('active'); // active, paused, completed, abandoned, failed
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('hints_used')->default(0);
            $table->unsignedInteger('moves_count')->default(0);
            $table->unsignedSmallInteger('mistakes_count')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('session_data')->nullable(); // Game-specific progress data
            $table->json('device_info')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'game_id', 'status']);
            $table->index(['player_id', 'daily_challenge_id']);
            $table->index(['game_id', 'status', 'started_at']);
            $table->index(['daily_challenge_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
