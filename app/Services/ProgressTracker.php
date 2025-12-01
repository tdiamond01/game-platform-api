<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\DailyChallenge;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\LeaderboardEntry;
use App\Models\Player;
use App\Models\PlayerAchievement;
use App\Models\PlayerProgress;
use App\Models\Streak;
use Illuminate\Support\Facades\DB;

class ProgressTracker
{
    /**
     * Start a new game session
     */
    public function startSession(
        Player $player,
        Game $game,
        string $sessionType = GameSession::TYPE_DAILY,
        ?DailyChallenge $challenge = null,
        ?array $deviceInfo = null
    ): GameSession {
        return GameSession::create([
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'game_id' => $game->id,
            'daily_challenge_id' => $challenge?->id,
            'session_type' => $sessionType,
            'status' => GameSession::STATUS_ACTIVE,
            'started_at' => now(),
            'device_info' => $deviceInfo,
        ]);
    }

    /**
     * Complete a game session and process all rewards/achievements
     */
    public function completeSession(
        GameSession $session,
        int $score,
        ?array $sessionData = null
    ): array {
        $result = [
            'session' => null,
            'streak' => null,
            'achievements' => [],
            'level_up' => false,
            'new_level' => null,
            'hints_earned' => 0,
            'leaderboard_rank' => null,
        ];

        DB::transaction(function () use ($session, $score, $sessionData, &$result) {
            $player = $session->player;
            $game = $session->game;

            // Complete the session
            $session->complete($score, $sessionData);
            $result['session'] = $session->fresh();

            // Update player stats
            $player->incrementGamesPlayed();
            $player->addTimePlayed($session->duration_seconds ?? 0);

            // Get or create progress for this game
            $progress = PlayerProgress::firstOrCreate(
                ['player_id' => $player->id, 'game_id' => $game->id],
                ['level' => 1, 'experience_points' => 0]
            );

            $previousLevel = $progress->level;
            $isDaily = $session->session_type === GameSession::TYPE_DAILY;

            // Record completion
            $progress->recordCompletion(
                $score,
                $session->duration_seconds ?? 0,
                $isDaily
            );

            // Check for level up
            if ($progress->level > $previousLevel) {
                $result['level_up'] = true;
                $result['new_level'] = $progress->level;
            }

            // Handle streak for daily challenges
            if ($isDaily && $session->daily_challenge_id) {
                $streak = Streak::getOrCreate($player->id, $game->id);
                $streakResult = $streak->recordCompletion();
                $result['streak'] = [
                    'current' => $streakResult['streak'],
                    'extended' => $streakResult['extended'],
                    'milestone' => $streakResult['milestone'],
                ];

                // Award hints for milestone
                if ($streakResult['milestone']) {
                    $hints = config('gameplatform.rewards.hints_per_milestone', 2);
                    $player->addHints($hints);
                    $result['hints_earned'] += $hints;
                }
            }

            // Add completion hints if configured
            $hintsPerCompletion = config('gameplatform.rewards.hints_per_completion', 0);
            if ($hintsPerCompletion > 0) {
                $player->addHints($hintsPerCompletion);
                $result['hints_earned'] += $hintsPerCompletion;
            }

            // Submit to leaderboard
            LeaderboardEntry::submitScore(
                $player->id,
                $game->id,
                $score,
                $session->duration_seconds ?? 0,
                $session->daily_challenge_id
            );

            // Get leaderboard rank
            $result['leaderboard_rank'] = LeaderboardEntry::getPlayerRank(
                $player->id,
                $game->id,
                LeaderboardEntry::PERIOD_DAILY
            );

            // Check achievements
            $result['achievements'] = $this->checkAchievements(
                $player,
                $game,
                $progress,
                $session,
                $result['streak']['current'] ?? 0
            );
        });

        return $result;
    }

    /**
     * Record a hint being used
     */
    public function useHint(Player $player, GameSession $session, string $hintType = 'reveal_letter'): bool
    {
        $cost = config("gameplatform.rewards.hint_costs.{$hintType}", 1);

        if (!$player->useHints($cost)) {
            return false;
        }

        $session->recordHintUsed();

        // Track in progress
        $progress = PlayerProgress::where('player_id', $player->id)
            ->where('game_id', $session->game_id)
            ->first();

        if ($progress) {
            $progress->recordHintUsed();
        }

        return true;
    }

    /**
     * Record watching a rewarded ad
     */
    public function recordAdWatched(Player $player, string $rewardType, int $amount): void
    {
        switch ($rewardType) {
            case 'hints':
                $player->addHints($amount);
                break;
            case 'streak_freeze':
                $player->addStreakFreeze($amount);
                break;
        }
    }

    /**
     * Check and award achievements
     */
    protected function checkAchievements(
        Player $player,
        Game $game,
        PlayerProgress $progress,
        GameSession $session,
        int $currentStreak
    ): array {
        $unlocked = [];

        // Get streak
        $streak = Streak::where('player_id', $player->id)
            ->where('game_id', $game->id)
            ->first();

        // Check game-specific achievements
        $achievements = Achievement::where(function ($q) use ($game) {
            $q->where('game_id', $game->id)->orWhereNull('game_id');
        })->where('is_active', true)->get();

        foreach ($achievements as $achievement) {
            // Skip if already unlocked
            if (PlayerAchievement::hasUnlocked($player->id, $achievement->id)) {
                continue;
            }

            $shouldUnlock = false;

            // Check standard achievement types
            if ($achievement->checkUnlock($player, $progress, $streak)) {
                $shouldUnlock = true;
            }

            // Check session-specific achievements
            if (!$shouldUnlock) {
                $shouldUnlock = $this->checkSessionAchievement(
                    $achievement,
                    $session,
                    $progress
                );
            }

            if ($shouldUnlock) {
                $playerAchievement = PlayerAchievement::unlock(
                    $player->id,
                    $achievement->id,
                    $game->id,
                    null,
                    $session->id
                );

                if ($playerAchievement) {
                    $unlocked[] = [
                        'id' => $achievement->id,
                        'slug' => $achievement->slug,
                        'name' => $achievement->name,
                        'description' => $achievement->description,
                        'icon' => $achievement->icon,
                        'points' => $achievement->points,
                    ];
                }
            }
        }

        return $unlocked;
    }

    /**
     * Check session-specific achievement conditions
     */
    protected function checkSessionAchievement(
        Achievement $achievement,
        GameSession $session,
        PlayerProgress $progress
    ): bool {
        switch ($achievement->requirement_type) {
            case Achievement::TYPE_PERFECT_GAME:
                // No mistakes and no hints
                return $session->mistakes_count === 0 && $session->hints_used === 0;

            case Achievement::TYPE_SPEED:
                // Completed faster than requirement (in seconds)
                return $session->duration_seconds <= $achievement->requirement_value;

            case Achievement::TYPE_NO_HINTS:
                // Completed without hints
                return $session->hints_used === 0;

            case Achievement::TYPE_SCORE:
                // Score threshold
                return $session->score >= $achievement->requirement_value;

            default:
                return false;
        }
    }

    /**
     * Get comprehensive stats for a player
     */
    public function getPlayerStats(Player $player, ?int $gameId = null): array
    {
        $stats = [
            'overall' => [
                'total_games' => $player->total_games_played,
                'total_time' => $player->total_time_played,
                'hints_balance' => $player->hints_balance,
                'streak_freezes' => $player->streak_freezes,
                'achievement_points' => PlayerAchievement::getPlayerPoints($player->id),
                'achievements_count' => PlayerAchievement::getPlayerCount($player->id),
            ],
            'games' => [],
        ];

        $query = PlayerProgress::where('player_id', $player->id);
        if ($gameId) {
            $query->where('game_id', $gameId);
        }

        $progressRecords = $query->with('game')->get();

        foreach ($progressRecords as $progress) {
            $streak = Streak::where('player_id', $player->id)
                ->where('game_id', $progress->game_id)
                ->first();

            $stats['games'][$progress->game->slug] = [
                'game_id' => $progress->game_id,
                'game_name' => $progress->game->name,
                'level' => $progress->level,
                'experience' => $progress->experience_points,
                'xp_to_next' => $progress->getXPToNextLevel(),
                'xp_progress' => $progress->getXPProgress(),
                'games_played' => $progress->games_played,
                'games_won' => $progress->games_won,
                'win_rate' => $progress->getWinRate(),
                'best_score' => $progress->best_score,
                'best_time' => $progress->best_time_seconds,
                'average_score' => round($progress->average_score ?? 0),
                'daily_completed' => $progress->daily_challenges_completed,
                'current_streak' => $streak?->current_streak ?? 0,
                'longest_streak' => $streak?->longest_streak ?? 0,
                'last_played' => $progress->last_played_at?->toIso8601String(),
            ];
        }

        return $stats;
    }
}
