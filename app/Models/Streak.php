<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Streak extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'game_id',
        'current_streak',
        'longest_streak',
        'last_completed_date',
        'streak_frozen_date',
        'freezes_used_total',
    ];

    protected function casts(): array
    {
        return [
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'freezes_used_total' => 'integer',
            'last_completed_date' => 'date',
            'streak_frozen_date' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Streak Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Check and update streak status for today.
     * Call this when user opens the game.
     */
    public function checkStatus(): array
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $today = now($timezone)->startOfDay();
        $yesterday = $today->copy()->subDay();
        $graceHours = config('gameplatform.streaks.grace_period_hours', 12);

        // Already completed today
        if ($this->last_completed_date && $this->last_completed_date->eq($today)) {
            return [
                'status' => 'completed_today',
                'streak' => $this->current_streak,
                'needs_action' => false,
            ];
        }

        // Completed yesterday - streak is safe
        if ($this->last_completed_date && $this->last_completed_date->eq($yesterday)) {
            return [
                'status' => 'active',
                'streak' => $this->current_streak,
                'needs_action' => true,
            ];
        }

        // Freeze was used for today
        if ($this->streak_frozen_date && $this->streak_frozen_date->eq($today)) {
            return [
                'status' => 'frozen_today',
                'streak' => $this->current_streak,
                'needs_action' => true,
            ];
        }

        // Freeze was used yesterday (treated like a completion)
        if ($this->streak_frozen_date && $this->streak_frozen_date->eq($yesterday)) {
            return [
                'status' => 'active',
                'streak' => $this->current_streak,
                'needs_action' => true,
            ];
        }

        // Check if within grace period
        $lastActive = $this->last_completed_date ?? $this->streak_frozen_date;
        if ($lastActive) {
            $hoursSince = $lastActive->endOfDay()->diffInHours(now($timezone));
            if ($hoursSince <= $graceHours) {
                return [
                    'status' => 'grace_period',
                    'streak' => $this->current_streak,
                    'hours_remaining' => $graceHours - $hoursSince,
                    'needs_action' => true,
                ];
            }
        }

        // Streak is broken
        if ($this->current_streak > 0) {
            $lostStreak = $this->current_streak;
            $this->breakStreak();
            return [
                'status' => 'broken',
                'streak' => 0,
                'lost_streak' => $lostStreak,
                'needs_action' => true,
            ];
        }

        return [
            'status' => 'none',
            'streak' => 0,
            'needs_action' => true,
        ];
    }

    /**
     * Record a daily challenge completion
     */
    public function recordCompletion(): array
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $today = now($timezone)->startOfDay();

        // Already completed today
        if ($this->last_completed_date && $this->last_completed_date->eq($today)) {
            return [
                'extended' => false,
                'streak' => $this->current_streak,
                'milestone' => null,
            ];
        }

        // Extend streak
        $this->current_streak++;
        $this->last_completed_date = $today;

        // Update longest streak
        if ($this->current_streak > $this->longest_streak) {
            $this->longest_streak = $this->current_streak;
        }

        $this->save();

        // Check for milestone
        $milestone = $this->checkMilestone();

        return [
            'extended' => true,
            'streak' => $this->current_streak,
            'milestone' => $milestone,
        ];
    }

    /**
     * Use a streak freeze for today
     */
    public function useFreeze(): bool
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $today = now($timezone)->startOfDay();

        // Can't freeze if already completed today
        if ($this->last_completed_date && $this->last_completed_date->eq($today)) {
            return false;
        }

        // Can't freeze if already frozen today
        if ($this->streak_frozen_date && $this->streak_frozen_date->eq($today)) {
            return false;
        }

        // Try to use player's freeze
        if (!$this->player->useStreakFreeze()) {
            return false;
        }

        $this->streak_frozen_date = $today;
        $this->freezes_used_total++;
        $this->save();

        return true;
    }

    /**
     * Break the streak (reset to 0)
     */
    public function breakStreak(): void
    {
        $this->current_streak = 0;
        $this->save();
    }

    /**
     * Check if current streak hits a milestone
     */
    protected function checkMilestone(): ?int
    {
        $milestones = config('gameplatform.streaks.milestones', [7, 30, 100, 365]);
        
        if (in_array($this->current_streak, $milestones)) {
            return $this->current_streak;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Helpers
    |--------------------------------------------------------------------------
    */

    public static function getOrCreate(int $playerId, int $gameId): self
    {
        return self::firstOrCreate(
            ['player_id' => $playerId, 'game_id' => $gameId],
            ['current_streak' => 0, 'longest_streak' => 0]
        );
    }

    public function isActive(): bool
    {
        $status = $this->checkStatus();
        return in_array($status['status'], ['completed_today', 'active', 'frozen_today', 'grace_period']);
    }
}
