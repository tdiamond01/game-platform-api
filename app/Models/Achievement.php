<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'slug',
        'name',
        'description',
        'icon',
        'category',
        'points',
        'requirement_type',
        'requirement_value',
        'is_hidden',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'requirement_value' => 'integer',
            'is_hidden' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Requirement types
    const TYPE_STREAK = 'streak';
    const TYPE_GAMES_PLAYED = 'games_played';
    const TYPE_GAMES_WON = 'games_won';
    const TYPE_SCORE = 'score';
    const TYPE_PERFECT_GAME = 'perfect_game';
    const TYPE_SPEED = 'speed';
    const TYPE_NO_HINTS = 'no_hints';
    const TYPE_DAILY_COMPLETED = 'daily_completed';
    const TYPE_LEVEL = 'level';
    const TYPE_CUSTOM = 'custom';

    // Categories
    const CATEGORY_PROGRESS = 'progress';
    const CATEGORY_MASTERY = 'mastery';
    const CATEGORY_STREAK = 'streak';
    const CATEGORY_SPEED = 'speed';
    const CATEGORY_SPECIAL = 'special';

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function playerAchievements(): HasMany
    {
        return $this->hasMany(PlayerAchievement::class);
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

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeForGame($query, int $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('game_id');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('points');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isGlobal(): bool
    {
        return is_null($this->game_id);
    }

    public function getUnlockCount(): int
    {
        return $this->playerAchievements()->count();
    }

    public function getUnlockPercentage(): float
    {
        $totalPlayers = Player::count();
        if ($totalPlayers === 0) return 0;
        return round(($this->getUnlockCount() / $totalPlayers) * 100, 1);
    }

    /**
     * Check if a player qualifies for this achievement
     */
    public function checkUnlock(Player $player, ?PlayerProgress $progress = null, ?Streak $streak = null): bool
    {
        switch ($this->requirement_type) {
            case self::TYPE_STREAK:
                return $streak && $streak->current_streak >= $this->requirement_value;

            case self::TYPE_GAMES_PLAYED:
                return $progress && $progress->games_played >= $this->requirement_value;

            case self::TYPE_GAMES_WON:
                return $progress && $progress->games_won >= $this->requirement_value;

            case self::TYPE_SCORE:
                return $progress && $progress->best_score >= $this->requirement_value;

            case self::TYPE_DAILY_COMPLETED:
                return $progress && $progress->daily_challenges_completed >= $this->requirement_value;

            case self::TYPE_LEVEL:
                return $progress && $progress->level >= $this->requirement_value;

            case self::TYPE_PERFECT_GAME:
            case self::TYPE_SPEED:
            case self::TYPE_NO_HINTS:
            case self::TYPE_CUSTOM:
                // These are checked at completion time with session data
                return false;

            default:
                return false;
        }
    }
}
