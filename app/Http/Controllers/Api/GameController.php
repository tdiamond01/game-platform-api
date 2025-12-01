<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\DailyChallenge;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\LeaderboardEntry;
use App\Models\PlayerProgress;
use App\Models\Streak;
use App\Services\ContentGenerator;
use App\Services\ProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GameController extends Controller
{
    public function __construct(
        protected ProgressTracker $progressTracker,
        protected ContentGenerator $contentGenerator
    ) {}

    /**
     * List all active games
     */
    public function index(Request $request): JsonResponse
    {
        $games = Game::active()->get();

        $player = $request->user()?->getOrCreatePlayer();

        $formatted = $games->map(function ($game) use ($player) {
            $data = [
                'id' => $game->id,
                'slug' => $game->slug,
                'name' => $game->name,
                'type' => $game->type,
                'description' => $game->description,
                'icon_url' => $game->icon_url,
                'daily_enabled' => $game->daily_enabled,
                'has_leaderboard' => $game->has_leaderboard,
            ];

            // Add player-specific data if authenticated
            if ($player) {
                $progress = PlayerProgress::where('player_id', $player->id)
                    ->where('game_id', $game->id)
                    ->first();

                $streak = Streak::where('player_id', $player->id)
                    ->where('game_id', $game->id)
                    ->first();

                $data['player'] = [
                    'level' => $progress?->level ?? 1,
                    'games_played' => $progress?->games_played ?? 0,
                    'current_streak' => $streak?->current_streak ?? 0,
                    'last_played' => $progress?->last_played_at?->toIso8601String(),
                ];

                // Check if daily completed today
                if ($game->daily_enabled) {
                    $todaysChallenge = $game->getTodaysChallenge();
                    $data['daily_completed_today'] = $todaysChallenge 
                        ? GameSession::where('player_id', $player->id)
                            ->where('daily_challenge_id', $todaysChallenge->id)
                            ->where('status', 'completed')
                            ->exists()
                        : false;
                }
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'games' => $formatted,
            ],
        ]);
    }

    /**
     * Get game details
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->active()->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        }

        $player = $request->user()?->getOrCreatePlayer();

        $data = [
            'id' => $game->id,
            'slug' => $game->slug,
            'name' => $game->name,
            'type' => $game->type,
            'description' => $game->description,
            'icon_url' => $game->icon_url,
            'store_url_ios' => $game->store_url_ios,
            'store_url_android' => $game->store_url_android,
            'daily_enabled' => $game->daily_enabled,
            'has_leaderboard' => $game->has_leaderboard,
            'settings' => $game->settings,
        ];

        if ($player) {
            $progress = PlayerProgress::where('player_id', $player->id)
                ->where('game_id', $game->id)
                ->first();

            $streak = Streak::where('player_id', $player->id)
                ->where('game_id', $game->id)
                ->first();

            $data['player'] = [
                'level' => $progress?->level ?? 1,
                'experience' => $progress?->experience_points ?? 0,
                'xp_to_next' => $progress?->getXPToNextLevel() ?? 100,
                'games_played' => $progress?->games_played ?? 0,
                'games_won' => $progress?->games_won ?? 0,
                'best_score' => $progress?->best_score,
                'daily_completed' => $progress?->daily_challenges_completed ?? 0,
                'current_streak' => $streak?->current_streak ?? 0,
                'longest_streak' => $streak?->longest_streak ?? 0,
            ];

            // Streak status
            if ($streak) {
                $data['streak_status'] = $streak->checkStatus();
            }
        }

        // Get achievements for this game
        $achievements = Achievement::where('game_id', $game->id)
            ->orWhereNull('game_id')
            ->where('is_active', true)
            ->visible()
            ->ordered()
            ->get(['id', 'slug', 'name', 'description', 'icon', 'category', 'points']);

        $data['achievements'] = $achievements;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get today's daily challenge for a game
     */
    public function daily(Request $request, string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->active()->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        }

        if (!$game->daily_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Daily challenges not enabled for this game',
            ], 400);
        }

        $challenge = $game->getTodaysChallenge();

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'No challenge available today',
            ], 404);
        }

        $player = $request->user()?->getOrCreatePlayer();
        $completed = false;
        $previousScore = null;

        if ($player) {
            $existingSession = GameSession::where('player_id', $player->id)
                ->where('daily_challenge_id', $challenge->id)
                ->where('status', 'completed')
                ->first();

            $completed = (bool)$existingSession;
            $previousScore = $existingSession?->score;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'challenge' => $challenge->getClientContent(),
                'completed' => $completed,
                'previous_score' => $previousScore,
                'stats' => [
                    'completions' => $challenge->getCompletionCount(),
                    'average_time' => round($challenge->getAverageTimeSeconds() ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Get a specific challenge by number (for archives)
     */
    public function challenge(Request $request, string $slug, int $number): JsonResponse
    {
        $game = Game::where('slug', $slug)->active()->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        }

        $challenge = $game->challenges()
            ->where('challenge_number', $number)
            ->where('is_active', true)
            ->first();

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Challenge not found',
            ], 404);
        }

        // Only allow access to past challenges
        if ($challenge->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'Challenge not yet available',
            ], 403);
        }

        $player = $request->user()?->getOrCreatePlayer();
        $completed = false;
        $previousScore = null;

        if ($player) {
            $existingSession = GameSession::where('player_id', $player->id)
                ->where('daily_challenge_id', $challenge->id)
                ->where('status', 'completed')
                ->first();

            $completed = (bool)$existingSession;
            $previousScore = $existingSession?->score;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'challenge' => $challenge->getClientContent(),
                'completed' => $completed,
                'previous_score' => $previousScore,
                'is_today' => $challenge->isToday(),
            ],
        ]);
    }

    /**
     * Get leaderboard for a game
     */
    public function leaderboard(Request $request, string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->active()->first();

        if (!$game || !$game->has_leaderboard) {
            return response()->json([
                'success' => false,
                'message' => 'Leaderboard not available',
            ], 404);
        }

        $period = $request->query('period', 'daily');
        if (!in_array($period, ['daily', 'weekly', 'monthly', 'alltime'])) {
            $period = 'daily';
        }

        $limit = min((int)$request->query('limit', 100), 100);

        $leaderboard = LeaderboardEntry::getLeaderboard($game->id, $period, $limit);

        $player = $request->user()?->getOrCreatePlayer();
        $playerRank = null;

        if ($player) {
            $playerRank = LeaderboardEntry::getPlayerRank($player->id, $game->id, $period);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'leaderboard' => $leaderboard,
                'player_rank' => $playerRank,
            ],
        ]);
    }

    /**
     * Get challenge leaderboard
     */
    public function challengeLeaderboard(Request $request, string $slug, int $challengeId): JsonResponse
    {
        $game = Game::where('slug', $slug)->active()->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        }

        $challenge = DailyChallenge::where('id', $challengeId)
            ->where('game_id', $game->id)
            ->first();

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Challenge not found',
            ], 404);
        }

        $leaderboard = LeaderboardEntry::getChallengeLeaderboard($challengeId);

        return response()->json([
            'success' => true,
            'data' => [
                'challenge_number' => $challenge->challenge_number,
                'challenge_date' => $challenge->challenge_date->format('Y-m-d'),
                'leaderboard' => $leaderboard,
            ],
        ]);
    }
}
