<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'player_id',
        'game_id',
        'daily_challenge_id',
        'session_type',
        'status',
        'score',
        'duration_seconds',
        'hints_used',
        'moves_count',
        'mistakes_count',
        'completion_percentage',
        'started_at',
        'completed_at',
        'session_data',
        'device_info',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'duration_seconds' => 'integer',
            'hints_used' => 'integer',
            'moves_count' => 'integer',
            'mistakes_count' => 'integer',
            'completion_percentage' => 'float',
            'session_data' => 'array',
            'device_info' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Session types
    const TYPE_DAILY = 'daily';
    const TYPE_PRACTICE = 'practice';
    const TYPE_ENDLESS = 'endless';
    const TYPE_TIMED = 'timed';

    // Session statuses
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ABANDONED = 'abandoned';
    const STATUS_FAILED = 'failed';

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(DailyChallenge::class, 'daily_challenge_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeDaily($query)
    {
        return $query->where('session_type', self::TYPE_DAILY);
    }

    public function scopeForGame($query, int $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    public function scopeForPlayer($query, int $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeToday($query)
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $today = now($timezone)->startOfDay();
        return $query->where('started_at', '>=', $today);
    }

    /*
    |--------------------------------------------------------------------------
    | Session Management
    |--------------------------------------------------------------------------
    */

    public function start(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
    }

    public function pause(): void
    {
        $this->update(['status' => self::STATUS_PAUSED]);
    }

    public function resume(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    public function complete(int $score, ?array $data = null): void
    {
        $now = now();
        $duration = $this->started_at 
            ? $now->diffInSeconds($this->started_at) 
            : null;

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => $now,
            'score' => $score,
            'duration_seconds' => $duration,
            'completion_percentage' => 100,
            'session_data' => array_merge($this->session_data ?? [], $data ?? []),
        ]);
    }

    public function fail(): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
        ]);
    }

    public function abandon(): void
    {
        $this->update([
            'status' => self::STATUS_ABANDONED,
            'completed_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Progress Tracking
    |--------------------------------------------------------------------------
    */

    public function recordHintUsed(): void
    {
        $this->increment('hints_used');
    }

    public function recordMove(): void
    {
        $this->increment('moves_count');
    }

    public function recordMistake(): void
    {
        $this->increment('mistakes_count');
    }

    public function updateProgress(float $percentage, ?array $data = null): void
    {
        $updates = ['completion_percentage' => $percentage];
        if ($data) {
            $updates['session_data'] = array_merge($this->session_data ?? [], $data);
        }
        $this->update($updates);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isDaily(): bool
    {
        return $this->session_type === self::TYPE_DAILY;
    }

    public function getDurationFormatted(): string
    {
        if (!$this->duration_seconds) {
            return '0:00';
        }
        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getScorePercentile(): ?float
    {
        if (!$this->score || !$this->daily_challenge_id) {
            return null;
        }

        $betterScores = GameSession::where('daily_challenge_id', $this->daily_challenge_id)
            ->where('status', self::STATUS_COMPLETED)
            ->where('score', '>', $this->score)
            ->count();

        $totalScores = GameSession::where('daily_challenge_id', $this->daily_challenge_id)
            ->where('status', self::STATUS_COMPLETED)
            ->count();

        if ($totalScores === 0) return 100;

        return round((1 - ($betterScores / $totalScores)) * 100, 1);
    }
}
