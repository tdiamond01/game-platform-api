<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('type', 50); // cryptogram, sort_puzzle, math_block
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('store_url_ios')->nullable();
            $table->string('store_url_android')->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->string('min_app_version', 20)->nullable();
            $table->boolean('daily_enabled')->default(true);
            $table->boolean('has_leaderboard')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
