<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LeaderboardEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'game_id',
        'daily_challenge_id',
        'period',
        'period_key',
        'score',
        'time_seconds',
        'games_count',
        'rank',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'time_seconds' => 'integer',
            'games_count' => 'integer',
            'rank' => 'integer',
        ];
    }

    // Periods
    const PERIOD_DAILY = 'daily';
    const PERIOD_WEEKLY = 'weekly';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_ALLTIME = 'alltime';
    const PERIOD_CHALLENGE = 'challenge';

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

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(DailyChallenge::class, 'daily_challenge_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForGame($query, int $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    public function scopeForPeriod($query, string $period, ?string $periodKey = null)
    {
        $query->where('period', $period);
        if ($periodKey) {
            $query->where('period_key', $periodKey);
        }
        return $query;
    }

    public function scopeForChallenge($query, int $challengeId)
    {
        return $query->where('daily_challenge_id', $challengeId)
            ->where('period', self::PERIOD_CHALLENGE);
    }

    public function scopeTopScores($query, int $limit = 100)
    {
        return $query->orderByDesc('score')
            ->orderBy('time_seconds')
            ->limit($limit);
    }

    /*
    |--------------------------------------------------------------------------
    | Static Helpers
    |--------------------------------------------------------------------------
    */

    public static function getPeriodKey(string $period): string
    {
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $now = now($timezone);

        return match ($period) {
            self::PERIOD_DAILY => $now->format('Y-m-d'),
            self::PERIOD_WEEKLY => $now->startOfWeek()->format('Y-m-d'),
            self::PERIOD_MONTHLY => $now->format('Y-m'),
            self::PERIOD_ALLTIME => 'all',
            default => $now->format('Y-m-d'),
        };
    }

    /**
     * Submit or update a score for a player
     */
    public static function submitScore(
        int $playerId,
        int $gameId,
        int $score,
        int $timeSeconds,
        ?int $challengeId = null
    ): void {
        $periods = [self::PERIOD_DAILY, self::PERIOD_WEEKLY, self::PERIOD_MONTHLY, self::PERIOD_ALLTIME];

        foreach ($periods as $period) {
            $periodKey = self::getPeriodKey($period);

            $entry = self::firstOrNew([
                'player_id' => $playerId,
                'game_id' => $gameId,
                'period' => $period,
                'period_key' => $periodKey,
            ]);

            // For aggregate periods, accumulate scores
            if ($period !== self::PERIOD_DAILY) {
                $entry->score = ($entry->score ?? 0) + $score;
                $entry->games_count = ($entry->games_count ?? 0) + 1;
                // Keep best time
                if (!$entry->time_seconds || $timeSeconds < $entry->time_seconds) {
                    $entry->time_seconds = $timeSeconds;
                }
            } else {
                // For daily, take best score
                if ($score > ($entry->score ?? 0)) {
                    $entry->score = $score;
                    $entry->time_seconds = $timeSeconds;
                }
                $entry->games_count = ($entry->games_count ?? 0) + 1;
            }

            $entry->save();
        }

        // Also submit to challenge leaderboard if applicable
        if ($challengeId) {
            $entry = self::firstOrNew([
                'player_id' => $playerId,
                'game_id' => $gameId,
                'daily_challenge_id' => $challengeId,
                'period' => self::PERIOD_CHALLENGE,
                'period_key' => "challenge_{$challengeId}",
            ]);

            // For challenge, take best score
            if ($score > ($entry->score ?? 0)) {
                $entry->score = $score;
                $entry->time_seconds = $timeSeconds;
            }
            $entry->save();
        }

        // Invalidate cache
        self::invalidateCache($gameId);
    }

    /**
     * Get leaderboard for a game and period
     */
    public static function getLeaderboard(
        int $gameId,
        string $period = self::PERIOD_DAILY,
        int $limit = 100
    ): array {
        $periodKey = self::getPeriodKey($period);
        $cacheKey = "leaderboard:{$gameId}:{$period}:{$periodKey}";
        $ttl = config('gameplatform.leaderboards.cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($gameId, $period, $periodKey, $limit) {
            return self::where('game_id', $gameId)
                ->where('period', $period)
                ->where('period_key', $periodKey)
                ->with(['player:id,display_name,avatar_id'])
                ->orderByDesc('score')
                ->orderBy('time_seconds')
                ->limit($limit)
                ->get()
                ->map(function ($entry, $index) {
                    return [
                        'rank' => $index + 1,
                        'player_id' => $entry->player_id,
                        'display_name' => $entry->player->display_name ?? 'Anonymous',
                        'avatar_id' => $entry->player->avatar_id,
                        'score' => $entry->score,
                        'time_seconds' => $entry->time_seconds,
                        'games_count' => $entry->games_count,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get challenge leaderboard
     */
    public static function getChallengeLeaderboard(int $challengeId, int $limit = 100): array
    {
        $cacheKey = "leaderboard:challenge:{$challengeId}";
        $ttl = config('gameplatform.leaderboards.cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($challengeId, $limit) {
            return self::where('daily_challenge_id', $challengeId)
                ->where('period', self::PERIOD_CHALLENGE)
                ->with(['player:id,display_name,avatar_id'])
                ->orderByDesc('score')
                ->orderBy('time_seconds')
                ->limit($limit)
                ->get()
                ->map(function ($entry, $index) {
                    return [
                        'rank' => $index + 1,
                        'player_id' => $entry->player_id,
                        'display_name' => $entry->player->display_name ?? 'Anonymous',
                        'avatar_id' => $entry->player->avatar_id,
                        'score' => $entry->score,
                        'time_seconds' => $entry->time_seconds,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get player's rank for a period
     */
    public static function getPlayerRank(int $playerId, int $gameId, string $period): ?array
    {
        $periodKey = self::getPeriodKey($period);

        $entry = self::where('player_id', $playerId)
            ->where('game_id', $gameId)
            ->where('period', $period)
            ->where('period_key', $periodKey)
            ->first();

        if (!$entry) return null;

        $rank = self::where('game_id', $gameId)
            ->where('period', $period)
            ->where('period_key', $periodKey)
            ->where(function ($q) use ($entry) {
                $q->where('score', '>', $entry->score)
                    ->orWhere(function ($q2) use ($entry) {
                        $q2->where('score', $entry->score)
                            ->where('time_seconds', '<', $entry->time_seconds);
                    });
            })
            ->count() + 1;

        return [
            'rank' => $rank,
            'score' => $entry->score,
            'time_seconds' => $entry->time_seconds,
            'games_count' => $entry->games_count,
        ];
    }

    protected static function invalidateCache(int $gameId): void
    {
        $periods = [self::PERIOD_DAILY, self::PERIOD_WEEKLY, self::PERIOD_MONTHLY, self::PERIOD_ALLTIME];
        foreach ($periods as $period) {
            $periodKey = self::getPeriodKey($period);
            Cache::forget("leaderboard:{$gameId}:{$period}:{$periodKey}");
        }
    }
}
