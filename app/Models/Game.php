<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'type',
        'description',
        'icon_url',
        'store_url_ios',
        'store_url_android',
        'version',
        'min_app_version',
        'daily_enabled',
        'has_leaderboard',
        'is_active',
        'settings',
        'launched_at',
    ];

    protected function casts(): array
    {
        return [
            'daily_enabled' => 'boolean',
            'has_leaderboard' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'launched_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function challenges(): HasMany
    {
        return $this->hasMany(DailyChallenge::class);
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
        return $this->hasMany(Achievement::class);
    }

    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithDaily($query)
    {
        return $query->where('daily_enabled', true);
    }

    public function scopeWithLeaderboard($query)
    {
        return $query->where('has_leaderboard', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function findBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function getTodaysChallenge(): ?DailyChallenge
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $today = now($timezone)->format('Y-m-d');
        
        return $this->challenges()
            ->where('challenge_date', $today)
            ->where('is_active', true)
            ->first();
    }

    public function getChallengeForDate(string $date): ?DailyChallenge
    {
        return $this->challenges()
            ->where('challenge_date', $date)
            ->where('is_active', true)
            ->first();
    }
}
