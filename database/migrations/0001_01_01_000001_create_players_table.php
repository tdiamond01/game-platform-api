<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('display_name', 50);
            $table->string('avatar_id', 50)->nullable();
            $table->unsignedInteger('hints_balance')->default(3);
            $table->unsignedTinyInteger('streak_freezes')->default(1);
            $table->unsignedInteger('total_games_played')->default(0);
            $table->unsignedInteger('total_time_played')->default(0); // seconds
            $table->json('preferences')->nullable();
            $table->string('push_token', 500)->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamps();

            $table->unique('user_id');
            $table->index('display_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
