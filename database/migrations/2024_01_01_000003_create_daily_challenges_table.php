<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->date('challenge_date');
            $table->integer('challenge_number');
            $table->tinyInteger('difficulty')->default(2); // 1-5
            $table->json('content'); // Puzzle content (without solution)
            $table->json('solution'); // Answer/solution
            $table->json('hints')->nullable(); // Progressive hints
            $table->json('metadata')->nullable(); // Additional data
            $table->boolean('is_active')->default(true);
            $table->string('generated_by', 50)->nullable(); // claude, manual, etc.
            $table->timestamps();

            $table->unique(['game_id', 'challenge_date']);
            $table->unique(['game_id', 'challenge_number']);
            $table->index(['game_id', 'is_active', 'challenge_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_challenges');
    }
};
