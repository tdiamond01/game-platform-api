<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'avatar_id',
        'hints_balance',
        'streak_freezes',
        'total_games_played',
        'total_time_played',
        'preferences',
        'push_token',
        'notifications_enabled',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'notifications_enabled' => 'boolean',
            'hints_balance' => 'integer',
            'streak_freezes' => 'integer',
            'total_games_played' => 'integer',
            'total_time_played' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(PlayerProgress::class);
    }

    public function streaks(): HasMany
    {
        return $this->hasMany(Streak::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(PlayerAchievement::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(PlayerReward::class);
    }

    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Hint Management
    |--------------------------------------------------------------------------
    */

    public function hasHints(int $amount = 1): bool
    {
        return $this->hints_balance >= $amount;
    }

    public function useHints(int $amount = 1): bool
    {
        if (!$this->hasHints($amount)) {
            return false;
        }
        $this->decrement('hints_balance', $amount);
        return true;
    }

    public function addHints(int $amount): void
    {
        $this->increment('hints_balance', $amount);
    }

    /*
    |--------------------------------------------------------------------------
    | Streak Freeze Management
    |--------------------------------------------------------------------------
    */

    public function hasStreakFreeze(): bool
    {
        return $this->streak_freezes > 0;
    }

    public function useStreakFreeze(): bool
    {
        if (!$this->hasStreakFreeze()) {
            return false;
        }
        $this->decrement('streak_freezes');
        return true;
    }

    public function addStreakFreeze(int $amount = 1): void
    {
        $max = config('gameplatform.streaks.max_freezes', 5);
        $new = min($this->streak_freezes + $amount, $max);
        $this->update(['streak_freezes' => $new]);
    }

    /*
    |--------------------------------------------------------------------------
    | Progress Helpers
    |--------------------------------------------------------------------------
    */

    public function getProgressForGame(int $gameId): ?PlayerProgress
    {
        return $this->progress()->where('game_id', $gameId)->first();
    }

    public function getStreakForGame(int $gameId): ?Streak
    {
        return $this->streaks()->where('game_id', $gameId)->first();
    }

    public function incrementGamesPlayed(): void
    {
        $this->increment('total_games_played');
    }

    public function addTimePlayed(int $seconds): void
    {
        $this->increment('total_time_played', $seconds);
    }

    /*
    |--------------------------------------------------------------------------
    | Preferences
    |--------------------------------------------------------------------------
    */

    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    public function setPreference(string $key, mixed $value): void
    {
        $prefs = $this->preferences ?? [];
        data_set($prefs, $key, $value);
        $this->update(['preferences' => $prefs]);
    }

    public function getSoundEnabled(): bool
    {
        return $this->getPreference('sound_enabled', true);
    }

    public function getHapticsEnabled(): bool
    {
        return $this->getPreference('haptics_enabled', true);
    }

    public function getDarkMode(): bool
    {
        return $this->getPreference('dark_mode', false);
    }
}
