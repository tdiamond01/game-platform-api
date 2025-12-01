<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DailyChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'challenge_date',
        'challenge_number',
        'difficulty',
        'content',
        'solution',
        'hints',
        'metadata',
        'is_active',
        'generated_by',
    ];

    protected $hidden = [
        'solution',
    ];

    protected function casts(): array
    {
        return [
            'challenge_date' => 'date',
            'content' => 'array',
            'solution' => 'array',
            'hints' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'difficulty' => 'integer',
            'challenge_number' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
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

    public function scopeForToday($query)
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $today = now($timezone)->format('Y-m-d');
        return $query->where('challenge_date', $today);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('challenge_date', $date);
    }

    public function scopeUpcoming($query)
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $today = now($timezone)->format('Y-m-d');
        return $query->where('challenge_date', '>=', $today);
    }

    public function scopePast($query)
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $today = now($timezone)->format('Y-m-d');
        return $query->where('challenge_date', '<', $today);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isToday(): bool
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        return $this->challenge_date->format('Y-m-d') === now($timezone)->format('Y-m-d');
    }

    public function isPast(): bool
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        return $this->challenge_date->lt(now($timezone)->startOfDay());
    }

    public function isFuture(): bool
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        return $this->challenge_date->gt(now($timezone)->startOfDay());
    }

    public function getCompletionCount(): int
    {
        return $this->sessions()
            ->where('status', 'completed')
            ->count();
    }

    public function getAverageTimeSeconds(): ?float
    {
        return $this->sessions()
            ->where('status', 'completed')
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');
    }

    public function getAverageScore(): ?float
    {
        return $this->sessions()
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->avg('score');
    }

    public function getHint(int $level): ?array
    {
        $hints = $this->hints ?? [];
        return $hints[$level - 1] ?? null;
    }

    /**
     * Get content for client (without solution)
     */
    public function getClientContent(): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'challenge_number' => $this->challenge_number,
            'challenge_date' => $this->challenge_date->format('Y-m-d'),
            'difficulty' => $this->difficulty,
            'content' => $this->content,
            'hint_count' => count($this->hints ?? []),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Verify a solution attempt
     */
    public function verifySolution(mixed $attempt): bool
    {
        // Normalize and compare
        $expected = $this->normalizeSolution($this->solution);
        $submitted = $this->normalizeSolution($attempt);
        
        return $expected === $submitted;
    }

    protected function normalizeSolution(mixed $solution): mixed
    {
        if (is_string($solution)) {
            return strtolower(trim($solution));
        }
        if (is_array($solution)) {
            return array_map(fn($v) => $this->normalizeSolution($v), $solution);
        }
        return $solution;
    }
}
