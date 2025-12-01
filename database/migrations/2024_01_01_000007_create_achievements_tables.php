<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Achievement definitions
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->nullable()->constrained()->onDelete('cascade'); // null = global
            $table->string('slug', 50);
            $table->string('name', 100);
            $table->text('description');
            $table->string('icon', 100)->nullable();
            $table->string('category', 50)->default('progress'); // progress, mastery, streak, speed, special
            $table->integer('points')->default(10);
            $table->string('requirement_type', 50); // streak, games_played, score, perfect_game, etc.
            $table->integer('requirement_value')->default(1);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'slug']);
            $table->index(['game_id', 'is_active', 'sort_order']);
            $table->index('category');
        });

        // Player unlocked achievements
        Schema::create('player_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('unlocked_at');
            $table->integer('unlocked_value')->nullable(); // The value when unlocked (e.g., streak count)
            $table->unsignedBigInteger('session_id')->nullable(); // Session where it was unlocked
            $table->timestamps();

            $table->unique(['player_id', 'achievement_id']);
            $table->index(['player_id', 'unlocked_at']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_achievements');
        Schema::dropIfExists('achievements');
    }
};
