<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->date('last_completed_date')->nullable();
            $table->date('streak_frozen_date')->nullable();
            $table->integer('freezes_used_total')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'game_id']);
            $table->index(['game_id', 'current_streak']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaks');
    }
};
