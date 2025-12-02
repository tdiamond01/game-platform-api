<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyChallenge;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\Streak;
use App\Services\ProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SessionController extends Controller
{
    public function __construct(
        protected ProgressTracker $progressTracker
    ) {}

    /**
     * Start a new game session
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'game_id' => 'required|integer|exists:games,id',
            'challenge_id' => 'nullable|integer|exists:daily_challenges,id',
            'session_type' => 'nullable|string|in:daily,practice,endless,timed',
            'device_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $player = $request->user()->getOrCreatePlayer();
        $game = Game::findOrFail($request->game_id);

        // Determine session type
        $sessionType = $request->session_type ?? GameSession::TYPE_PRACTICE;
        $challenge = null;

        if ($request->challenge_id) {
            $challenge = DailyChallenge::find($request->challenge_id);
            if ($challenge && $challenge->game_id === $game->id) {
                $sessionType = GameSession::TYPE_DAILY;
            }
        }

        // Check if there's an active session for this game
        $activeSession = GameSession::where('player_id', $player->id)
            ->where('game_id', $game->id)
            ->where('status', GameSession::STATUS_ACTIVE)
            ->first();

        if ($activeSession) {
            // Abandon the old session
            $activeSession->abandon();
        }

        // Start new session
        $session = $this->progressTracker->startSession(
            $player,
            $game,
            $sessionType,
            $challenge,
            $request->device_info
        );

        // Get streak status if daily
        $streakStatus = null;
        if ($sessionType === GameSession::TYPE_DAILY) {
            $streak = Streak::getOrCreate($player->id, $game->id);
            $streakStatus = $streak->checkStatus();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session' => [
                    'id' => $session->id,
                    'game_id' => $session->game_id,
                    'session_type' => $session->session_type,
                    'status' => $session->status,
                    'started_at' => $session->started_at->toIso8601String(),
                ],
                'streak' => $streakStatus,
                'hints_available' => $player->hints_balance,
            ],
        ], 201);
    }

    /**
     * Update session progress
     */
    public function update(Request $request, int $sessionId): JsonResponse
    {
        $player = $request->user()->getOrCreatePlayer();

        $session = GameSession::where('id', $sessionId)
            ->where('player_id', $player->id)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        if (!$session->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Session is not active',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'completion_percentage' => 'nullable|numeric|min:0|max:100',
            'moves_count' => 'nullable|integer|min:0',
            'mistakes_count' => 'nullable|integer|min:0',
            'session_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('completion_percentage')) {
            $session->updateProgress(
                $request->completion_percentage,
                $request->session_data
            );
        }

        if ($request->has('moves_count')) {
            $session->moves_count = $request->moves_count;
        }

        if ($request->has('mistakes_count')) {
            $session->mistakes_count = $request->mistakes_count;
        }

        $session->save();

        return response()->json([
            'success' => true,
            'data' => [
                'session' => [
                    'id' => $session->id,
                    'completion_percentage' => $session->completion_percentage,
                    'moves_count' => $session->moves_count,
                    'mistakes_count' => $session->mistakes_count,
                ],
            ],
        ]);
    }

    /**
     * Complete a game session
     */
    public function complete(Request $request, int $sessionId): JsonResponse
    {
        $player = $request->user()->getOrCreatePlayer();

        $session = GameSession::where('id', $sessionId)
            ->where('player_id', $player->id)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        if ($session->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Session already completed',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'score' => 'required|integer|min:0',
            'solution' => 'nullable|mixed',
            'session_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify solution if provided and this is a daily challenge
        if ($session->daily_challenge_id && $request->has('solution')) {
            $challenge = $session->challenge;
            if ($challenge && !$challenge->verifySolution($request->solution)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect solution',
                    'data' => [
                        'correct' => false,
                    ],
                ], 400);
            }
        }

        // Complete the session and process rewards
        $result = $this->progressTracker->completeSession(
            $session,
            $request->score,
            $request->session_data
        );

        return response()->json([
            'success' => true,
            'data' => [
                'session' => [
                    'id' => $result['session']->id,
                    'score' => $result['session']->score,
                    'duration' => $result['session']->getDurationFormatted(),
                    'duration_seconds' => $result['session']->duration_seconds,
                    'hints_used' => $result['session']->hints_used,
                    'percentile' => $result['session']->getScorePercentile(),
                ],
                'streak' => $result['streak'],
                'achievements' => $result['achievements'],
                'level_up' => $result['level_up'],
                'new_level' => $result['new_level'],
                'hints_earned' => $result['hints_earned'],
                'leaderboard_rank' => $result['leaderboard_rank'],
            ],
        ]);
    }

    /**
     * Abandon a session
     */
    public function abandon(Request $request, int $sessionId): JsonResponse
    {
        $player = $request->user()->getOrCreatePlayer();

        $session = GameSession::where('id', $sessionId)
            ->where('player_id', $player->id)
            ->where('status', GameSession::STATUS_ACTIVE)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Active session not found',
            ], 404);
        }

        $session->abandon();

        return response()->json([
            'success' => true,
            'message' => 'Session abandoned',
        ]);
    }

    /**
     * Use a hint in a session
     */
    public function useHint(Request $request, int $sessionId): JsonResponse
    {
        $player = $request->user()->getOrCreatePlayer();

        $session = GameSession::where('id', $sessionId)
            ->where('player_id', $player->id)
            ->where('status', GameSession::STATUS_ACTIVE)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Active session not found',
            ], 404);
        }

        $hintType = $request->input('hint_type', 'reveal_letter');
        $cost = config("gameplatform.rewards.hint_costs.{$hintType}", 1);

        if (!$player->hasHints($cost)) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough hints',
                'data' => [
                    'hints_available' => $player->hints_balance,
                    'cost' => $cost,
                ],
            ], 400);
        }

        // Get hint content if this is a daily challenge
        $hintContent = null;
        if ($session->daily_challenge_id) {
            $challenge = $session->challenge;
            $encodedLetter = strtoupper($request->input('encoded_letter', ''));

            // If encoded_letter is provided, look up from solution cipher
            if ($encodedLetter && $challenge) {
                $solution = $challenge->solution;
                $cipher = $solution['cipher'] ?? [];

                // Cipher is stored as original => encoded, we need to reverse lookup
                // Find the original letter that maps to this encoded letter
                $originalLetter = null;
                foreach ($cipher as $original => $encoded) {
                    if (strtoupper($encoded) === $encodedLetter) {
                        $originalLetter = $original;
                        break;
                    }
                }

                if ($originalLetter) {
                    $hintContent = [
                        'type' => 'letter',
                        'encoded' => $encodedLetter,
                        'original' => $originalLetter,
                    ];
                }
            }

            // Fallback to sequential hints if no encoded_letter or not found
            if (!$hintContent) {
                $hintLevel = $session->hints_used + 1;
                $hintContent = $challenge?->getHint($hintLevel);
            }
        }

        // Use the hint
        $this->progressTracker->useHint($player, $session, $hintType);

        return response()->json([
            'success' => true,
            'data' => [
                'hints_remaining' => $player->fresh()->hints_balance,
                'hints_used_in_session' => $session->fresh()->hints_used,
                'hint' => $hintContent,
            ],
        ]);
    }

    /**
     * Use a streak freeze
     */
    public function useStreakFreeze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'game_id' => 'required|integer|exists:games,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $player = $request->user()->getOrCreatePlayer();
        $game = Game::findOrFail($request->game_id);

        if (!$player->hasStreakFreeze()) {
            return response()->json([
                'success' => false,
                'message' => 'No streak freezes available',
            ], 400);
        }

        $streak = Streak::getOrCreate($player->id, $game->id);

        if (!$streak->useFreeze()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot use freeze right now',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'freezes_remaining' => $player->fresh()->streak_freezes,
                'streak' => [
                    'current' => $streak->current_streak,
                    'status' => 'frozen_today',
                ],
            ],
        ]);
    }

    /**
     * Record ad watched for rewards
     */
    public function recordAdWatched(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reward_type' => 'required|string|in:hints,streak_freeze',
            'amount' => 'nullable|integer|min:1|max:5',
            'ad_network' => 'nullable|string',
            'ad_unit' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $player = $request->user()->getOrCreatePlayer();
        $amount = $request->amount ?? 1;

        $this->progressTracker->recordAdWatched(
            $player,
            $request->reward_type,
            $amount
        );

        $player->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'reward_type' => $request->reward_type,
                'amount' => $amount,
                'hints_balance' => $player->hints_balance,
                'streak_freezes' => $player->streak_freezes,
            ],
        ]);
    }
}
