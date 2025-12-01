<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'achievement_id',
        'game_id',
        'unlocked_at',
        'unlocked_value',
        'session_id',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
            'unlocked_value' => 'integer',
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

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'session_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForPlayer($query, int $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeForGame($query, int $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('unlocked_at', '>=', now()->subDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function hasUnlocked(int $playerId, int $achievementId): bool
    {
        return self::where('player_id', $playerId)
            ->where('achievement_id', $achievementId)
            ->exists();
    }

    public static function unlock(
        int $playerId,
        int $achievementId,
        ?int $gameId = null,
        ?int $unlockedValue = null,
        ?int $sessionId = null
    ): ?self {
        // Don't unlock twice
        if (self::hasUnlocked($playerId, $achievementId)) {
            return null;
        }

        return self::create([
            'player_id' => $playerId,
            'achievement_id' => $achievementId,
            'game_id' => $gameId,
            'unlocked_at' => now(),
            'unlocked_value' => $unlockedValue,
            'session_id' => $sessionId,
        ]);
    }

    public static function getPlayerPoints(int $playerId): int
    {
        return self::where('player_id', $playerId)
            ->join('achievements', 'achievements.id', '=', 'player_achievements.achievement_id')
            ->sum('achievements.points');
    }

    public static function getPlayerCount(int $playerId, ?int $gameId = null): int
    {
        $query = self::where('player_id', $playerId);
        if ($gameId) {
            $query->where('game_id', $gameId);
        }
        return $query->count();
    }
}
