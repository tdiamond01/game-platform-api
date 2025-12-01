<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerProgress extends Model
{
    use HasFactory;

    protected $table = 'player_progress';

    protected $fillable = [
        'player_id',
        'game_id',
        'level',
        'experience_points',
        'total_score',
        'games_played',
        'games_won',
        'best_score',
        'best_time_seconds',
        'average_score',
        'average_time_seconds',
        'total_hints_used',
        'daily_challenges_completed',
        'last_played_at',
        'stats',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'experience_points' => 'integer',
            'total_score' => 'integer',
            'games_played' => 'integer',
            'games_won' => 'integer',
            'best_score' => 'integer',
            'best_time_seconds' => 'integer',
            'average_score' => 'float',
            'average_time_seconds' => 'float',
            'total_hints_used' => 'integer',
            'daily_challenges_completed' => 'integer',
            'stats' => 'array',
            'last_played_at' => 'datetime',
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
    | Progress Updates
    |--------------------------------------------------------------------------
    */

    public function recordCompletion(int $score, int $timeSeconds, bool $isDaily = false): void
    {
        $this->games_played++;
        $this->games_won++;
        $this->total_score += $score;

        // Update best score
        if ($score > ($this->best_score ?? 0)) {
            $this->best_score = $score;
        }

        // Update best time (lower is better)
        if (!$this->best_time_seconds || $timeSeconds < $this->best_time_seconds) {
            $this->best_time_seconds = $timeSeconds;
        }

        // Recalculate averages
        $this->average_score = $this->total_score / $this->games_won;
        
        // Update average time (would need historical data, simplified here)
        if ($this->average_time_seconds) {
            $this->average_time_seconds = (
                ($this->average_time_seconds * ($this->games_won - 1)) + $timeSeconds
            ) / $this->games_won;
        } else {
            $this->average_time_seconds = $timeSeconds;
        }

        if ($isDaily) {
            $this->daily_challenges_completed++;
        }

        $this->last_played_at = now();
        $this->addExperience($this->calculateXP($score, $timeSeconds));
        $this->save();
    }

    public function recordLoss(): void
    {
        $this->games_played++;
        $this->last_played_at = now();
        $this->save();
    }

    public function recordHintUsed(): void
    {
        $this->increment('total_hints_used');
    }

    /*
    |--------------------------------------------------------------------------
    | Leveling System
    |--------------------------------------------------------------------------
    */

    public function addExperience(int $xp): void
    {
        $this->experience_points += $xp;
        $this->checkLevelUp();
    }

    protected function checkLevelUp(): void
    {
        $newLevel = $this->calculateLevel($this->experience_points);
        if ($newLevel > $this->level) {
            $this->level = $newLevel;
        }
    }

    protected function calculateLevel(int $xp): int
    {
        // XP curve: each level requires 10% more than previous
        // Level 1: 0 XP, Level 2: 100 XP, Level 3: 210 XP, etc.
        $level = 1;
        $threshold = 0;
        $required = 100;

        while ($xp >= $threshold + $required) {
            $threshold += $required;
            $level++;
            $required = (int)($required * 1.1);
        }

        return $level;
    }

    protected function calculateXP(int $score, int $timeSeconds): int
    {
        // Base XP from score
        $xp = (int)($score / 10);

        // Time bonus (faster = more XP, up to 50% bonus)
        $targetTime = 120; // 2 minutes
        if ($timeSeconds < $targetTime) {
            $bonus = (1 - ($timeSeconds / $targetTime)) * 0.5;
            $xp += (int)($xp * $bonus);
        }

        return max($xp, 10); // Minimum 10 XP
    }

    public function getXPToNextLevel(): int
    {
        $currentThreshold = $this->getXPThreshold($this->level);
        $nextThreshold = $this->getXPThreshold($this->level + 1);
        return $nextThreshold - $this->experience_points;
    }

    public function getXPProgress(): float
    {
        $currentThreshold = $this->getXPThreshold($this->level);
        $nextThreshold = $this->getXPThreshold($this->level + 1);
        $range = $nextThreshold - $currentThreshold;
        $progress = $this->experience_points - $currentThreshold;
        return min(1, max(0, $progress / $range));
    }

    protected function getXPThreshold(int $level): int
    {
        if ($level <= 1) return 0;
        
        $threshold = 0;
        $required = 100;
        for ($i = 2; $i <= $level; $i++) {
            $threshold += $required;
            $required = (int)($required * 1.1);
        }
        return $threshold;
    }

    /*
    |--------------------------------------------------------------------------
    | Stats Helpers
    |--------------------------------------------------------------------------
    */

    public function getWinRate(): float
    {
        if ($this->games_played === 0) return 0;
        return round(($this->games_won / $this->games_played) * 100, 1);
    }

    public function getStat(string $key, mixed $default = null): mixed
    {
        return data_get($this->stats, $key, $default);
    }

    public function setStat(string $key, mixed $value): void
    {
        $stats = $this->stats ?? [];
        data_set($stats, $key, $value);
        $this->update(['stats' => $stats]);
    }

    public function incrementStat(string $key, int $amount = 1): void
    {
        $current = $this->getStat($key, 0);
        $this->setStat($key, $current + $amount);
    }
}
