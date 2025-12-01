<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'avatar_url',
        'timezone',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'provider_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getOrCreatePlayer(): Player
    {
        return $this->player()->firstOrCreate(
            ['user_id' => $this->id],
            [
                'display_name' => $this->name ?? 'Player',
                'hints_balance' => config('gameplatform.rewards.initial_hints', 3),
                'streak_freezes' => config('gameplatform.streaks.initial_freezes', 1),
            ]
        );
    }

    public function touchLastActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }

    public function getTimezone(): string
    {
        return $this->timezone ?? config('gameplatform.daily.timezone', 'America/Denver');
    }
}
