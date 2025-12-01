<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add gaming fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('password');
            $table->string('provider_id')->nullable()->after('provider');
            $table->string('avatar_url')->nullable()->after('provider_id');
            $table->string('timezone')->nullable()->after('avatar_url');
            $table->timestamp('last_active_at')->nullable()->after('timezone');

            $table->index(['provider', 'provider_id']);
        });

        // Create players table (gaming profile)
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('display_name', 50);
            $table->string('avatar_id', 50)->nullable();
            $table->integer('hints_balance')->default(3);
            $table->integer('streak_freezes')->default(1);
            $table->integer('total_games_played')->default(0);
            $table->integer('total_time_played')->default(0); // seconds
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

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider', 'provider_id']);
            $table->dropColumn(['provider', 'provider_id', 'avatar_url', 'timezone', 'last_active_at']);
        });
    }
};
